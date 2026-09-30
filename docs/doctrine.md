# Doctrine Backend

`kraz/read-model-doctrine` connects the read model API to Doctrine ORM `QueryBuilder`, Doctrine `NativeQuery`, or raw DBAL SQL strings. Install it with:

```bash
composer require kraz/read-model-doctrine
```

## Two Styles of Query

| Style                | When to use                                                         |
|----------------------|---------------------------------------------------------------------|
| **ORM QueryBuilder** | Entity-mapped data, complex joins already written in DQL            |
| **Raw SQL**          | Reporting queries, complex aggregations, performance-critical paths |

## ORM QueryBuilder

### Minimal read model

```php
use Kraz\ReadModel\ReadDataProviderInterface;
use Kraz\ReadModelDoctrine\DataSource;
use Kraz\ReadModelDoctrine\DataSourceBuilder;
use Kraz\ReadModelDoctrine\DoctrineReadDataProvider;

class InvoicesReadModel implements ReadDataProviderInterface
{
    use DoctrineReadDataProvider;

    const FIELD_ID          = 'id';
    const FIELD_NUMBER      = 'number';
    const FIELD_AMOUNT      = 'amount';
    const FIELD_CLIENT_NAME = 'clientName';
    const FIELD_STATUS      = 'status';

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    protected function createDataSource(): DataSource
    {
        $qb = $this->em->createQueryBuilder()
            ->select('i.id, i.number, i.amount, c.name AS clientName, i.status')
            ->from(Invoice::class, 'i')
            ->join('i.client', 'c')
            ->where('i.deletedAt IS NULL');

        return new DataSource($qb);
    }
}
```

`DoctrineReadDataProvider` provides the standard `ReadDataProviderInterface` methods by delegating to the inner `DataSource`.

### Field mapping for DQL aliases

When your DQL aliases differ from what you want to expose to callers, declare a field mapping:

```php
protected function createDataSource(): DataSource
{
    $qb = $this->em->createQueryBuilder()
        ->select('i.id, i.invoiceNumber, i.totalAmount, c.fullName, i.currentStatus')
        ->from(Invoice::class, 'i')
        ->join('i.client', 'c');

    return (new DataSourceBuilder())
        ->withFieldMapping([
            self::FIELD_NUMBER      => 'i.invoiceNumber',
            self::FIELD_AMOUNT      => 'i.totalAmount',
            self::FIELD_CLIENT_NAME => 'c.fullName',
            self::FIELD_STATUS      => 'i.currentStatus',
        ])
        ->withData($qb)
        ->create($this->em->getConnection());
}
```

Callers filter by `InvoicesReadModel::FIELD_CLIENT_NAME` and the library translates it to `c.fullName` in the SQL.

## Raw SQL

### SQL with placeholder markers

The recommended approach for raw SQL is to use `/*#WHERE#*/` and `/*#ORDERBY#*/` placeholders in your SQL string. The library replaces them automatically with generated `WHERE` and `ORDER BY` clauses:

```php
use Kraz\ReadModelDoctrine\DataSourceBuilder;
use Kraz\ReadModelDoctrine\DataSource;
use Kraz\ReadModelDoctrine\RawQueryReadDataProvider;

class UsersReadModel implements ReadDataProviderInterface
{
    use RawQueryReadDataProvider;

    const FIELD_ID        = 'id';
    const FIELD_USERNAME  = 'username';
    const FIELD_FULL_NAME = 'full_name';
    const FIELD_ACTIVE    = 'active';

    public function __construct(
        private readonly Connection $connection
    ) {}

    protected function createDataSource(): DataSource
    {
        return (new DataSourceBuilder())
            ->withData(<<<'SQL'
                SELECT r.* FROM (
                    SELECT u.id, u.username, u.first_name || ' ' || u.last_name AS full_name, u.active
                    FROM users u
                    WHERE u.deleted_at IS NULL
                ) r
                /*#WHERE#*/
                /*#ORDERBY#*/
            SQL)
            ->create($this->connection);
    }
}
```

The `/*#WHERE#*/` placeholder is replaced with `WHERE <generated conditions>` and `/*#ORDERBY#*/` with `ORDER BY <generated sort>`. If no filter/sort is applied, the placeholders are removed cleanly.

Because the placeholders expand to complete clauses, keyword included, place them where the clause belongs and keep the fixed conditions of the query inside a subquery, as in the example above. Placing `/*#WHERE#*/` after an existing `WHERE` clause produces invalid SQL when a filter is applied.

When the SQL contains no placeholder for a part that is applied, the whole statement is wrapped in `SELECT * FROM (...)` and the clause is appended to the wrapper.

### SQL template sections

When the read model needs a default, use a section instead of a placeholder. A section wraps a default expression that the caller can override:

```sql
SELECT r.* FROM (
    SELECT u.id, u.name, u.role
    FROM users u
    WHERE u.active = true
) r
WHERE /*#WHERE_B#*/r.role = 'admin'/*#WHERE_E#*/
ORDER BY /*#ORDERBY_B#*/r.name ASC/*#ORDERBY_E#*/
```

The `_B` / `_E` suffix marks the beginning and end of a section. The content between the markers is the default: when nothing is applied the markers are removed and the default stays in place. When a filter or sort is applied, everything between the markers, the default included, is replaced by the generated expression.

Unlike a placeholder, a section does not add the keyword. Keep `WHERE` and `ORDER BY` outside the markers, otherwise they are replaced together with the default and the resulting SQL is invalid:

```sql
-- Wrong: the keyword is lost when the caller sorts
/*#ORDERBY_B#*/ORDER BY r.name ASC/*#ORDERBY_E#*/

-- Right: the keyword stays, only the default expression is replaced
ORDER BY /*#ORDERBY_B#*/r.name ASC/*#ORDERBY_E#*/
```

A typical read model always has a default `ORDER BY` this way, so the result order is stable even when the caller does not sort, while the caller can still override it when required. Conditions that must always apply, like `u.active = true` above, belong outside the section, since a default `WHERE` expression is dropped as soon as the caller filters.

### Raw SQL with bound parameters

Use `ParametersCollection` for safe parameter binding when you need fixed parameters in addition to dynamic filters:

```php
use Kraz\ReadModelDoctrine\Tools\ParametersCollection;

protected function createDataSource(): DataSource
{
    $params = new ParametersCollection();
    $params->setParameter('tenant_id', $this->tenantId, Types::INTEGER);

    return $this->rawQuery(
        $this->connection,
        <<<'SQL'
            SELECT r.* FROM (
                SELECT o.id, o.number, o.total
                FROM orders o
                WHERE o.tenant_id = :tenant_id
                AND o.deleted_at IS NULL
            ) r
            /*#WHERE#*/
            ORDER BY /*#ORDERBY_B#*/r.number ASC/*#ORDERBY_E#*/
        SQL,
        $params
    );
}
```

The `rawQuery()` helper is provided by the `RawQueryReadDataProvider` trait.

### Query modifiers

When you need to add SQL clauses that depend on runtime state (not covered by the standard filter/sort), use a query modifier:

```php
protected function createDataSource(): DataSource
{
    $ds = (new DataSourceBuilder())
        ->withData('SELECT r.* FROM reports r /*#WHERE#*/ /*#ORDERBY#*/')
        ->create($this->connection);

    // Add GROUP BY at runtime
    return $ds->withQueryModifier(function (AbstractRawQuery $query) {
        $query->sql()->addGroupBy('r.report_date', 'r.category');
    });
}
```

## Accessing the Underlying Query

When you need the raw Doctrine query for debugging or for custom extensions:

```php
// For ORM QueryBuilder-backed read models
$query = $readModel->getQuery();     // returns Doctrine ORM Query or AbstractRawQuery

// For raw SQL read models
$rawQuery = $readModel->getRawQuery(); // returns AbstractRawQuery
$rawQuery->getSql();                   // the SQL string
$rawQuery->getParameters();            // bound parameters
```

## Pagination with Doctrine

Doctrine pagination is handled automatically. The library picks:
- `DoctrinePaginator` for ORM `QueryBuilder` queries
- `RawSqlPaginator` for raw SQL queries

```php
$page    = $readModel->withPagination(page: 1, itemsPerPage: 25);
$paginator = $page->paginator();

$paginator->getCurrentPage();  // 1
$paginator->getTotalItems();   // total from COUNT query
$paginator->getLastPage();     // last page number
```

For raw SQL, an efficient count query is generated automatically from your SQL. You can provide an optimized one:

```php
$rawQuery = $readModel->getRawQuery();
$rawQuery->setCountSql('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
```

## Handling HTTP Requests

`handleRequest()` parses a Symfony or PSR-7 HTTP request and applies filters and pagination:

```php
// Symfony controller
public function index(Request $request, UsersReadModel $readModel): JsonResponse
{
    return new JsonResponse(
        $readModel->handleRequest($request)->getResult()
    );
}
```

The parsed expression replaces any query expression already applied. Pass `append: true` to add it on top
of your own filters instead — see [Handling User Input](filtering.md#handling-user-input):

```php
$readModel
    ->withQueryExpression($tenantFilter)
    ->handleRequest($request, append: true)
    ->getResult();
```

## Read Model Descriptor (Field Discovery)

The library can introspect your Doctrine entities to discover available fields automatically. This is used to validate incoming filter field names and to build auto-complete helpers:

```php
// Automatic — uses reflection on the entity class
return new DataSourceBuilder()
    ->withReadModel(UserEntity::class)
    ->withData($qb)
    ->create($connection);
```

For DTOs that are not Doctrine entities, the descriptor is built from public properties of the DTO class.

## Full Example — CRUD Backend with Pagination and Search

```php
class ProductsReadModel implements ReadDataProviderInterface
{
    use DoctrineReadDataProvider;

    const FIELD_ID       = 'id';
    const FIELD_NAME     = 'name';
    const FIELD_PRICE    = 'price';
    const FIELD_CATEGORY = 'category';
    const FIELD_ACTIVE   = 'active';

    public function __construct(private readonly EntityManagerInterface $em) {}

    protected function createDataSource(): DataSource
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p.id, p.name, p.price, cat.name AS category, p.active')
            ->from(Product::class, 'p')
            ->join('p.category', 'cat')
            ->where('p.deletedAt IS NULL');

        return(new DataSourceBuilder()
            ->withRootIdentifier(self::FIELD_ID)
            ->withFieldMapping([
                self::FIELD_CATEGORY => 'cat.name',
            ])
            ->withData($qb)
            ->create($this->em->getConnection());
    }
}

// Controller
class ProductController
{
    public function list(Request $request, ProductsReadModel $readModel): JsonResponse
    {
        // Accepts ?page=1&pageSize=20&query[name][contains]=widget&query[price][gte]=10
        return new JsonResponse($readModel->handleRequest($request)->getResult());
    }

    public function byIds(Request $request, ProductsReadModel $readModel): JsonResponse
    {
        $ids = $request->query->all('ids');
        $result = $readModel
            ->withQueryExpression(QueryExpression::create()->withValues($ids))
            ->data();
        return new JsonResponse($result);
    }
}
```
