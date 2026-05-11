# Read Model

A domain-first Read Model library that provides a uniform API for querying read-only data from multiple backends. Define your query logic once; plug in Doctrine ORM, raw SQL, JSON-RPC APIs, or Elasticsearch — or use the built-in in-memory implementation for fast, dependency-free tests.

> [!WARNING]  
> This library is still work in progress!

## Packages

| Package | Description |
|---|---|
| `kraz/read-model` | Core interfaces + in-memory implementation |
| `kraz/read-model-doctrine` | Doctrine ORM & raw SQL backend |
| `kraz/read-model-json-rpc` | JSON-RPC 2.0 API backend |
| `kraz/read-model-elastic-search` | Elasticsearch backend |

## Documentation

Full documentation lives in the [`docs/`](docs/) directory:

- [Getting Started](docs/getting-started.md)
- [Core Concepts](docs/core-concepts.md)
- [Filtering & Sorting](docs/filtering.md)
- [Pagination & Limits](docs/pagination.md)
- [Specifications](docs/specifications.md)
- [Testing](docs/testing.md)
- [Doctrine Backend](docs/doctrine.md)
- [JSON-RPC Backend](docs/json-rpc.md)
- [Elasticsearch Backend](docs/elasticsearch.md)

## Quick Example

### Raw SQL

```php
use Kraz\ReadModel\ReadDataProviderInterface;
use Kraz\ReadModelDoctrine\DataSource;
use Kraz\ReadModelDoctrine\DataSourceBuilder;
use Kraz\ReadModelDoctrine\RawQueryReadDataProvider;

class ProductsReadModel implements ReadDataProviderInterface
{
    use RawQueryReadDataProvider;

    const FIELD_ID       = 'id';
    const FIELD_NAME     = 'name';
    const FIELD_PRICE    = 'price';
    const FIELD_CATEGORY = 'category';

    public function __construct(private readonly Connection $connection) {}

    protected function createDataSource(): DataSource
    {
        return new DataSourceBuilder()
            ->withData(<<<'SQL'
                SELECT r.* FROM (
                    SELECT p.id, p.name, p.price, c.name AS category
                    FROM product p
                    JOIN category c ON c.id = p.category_id
                    WHERE p.deleted_at IS NULL
                ) r
                /*#WHERE#*/
                /*#ORDERBY_B#*/ORDER BY r.id ASC/*#ORDERBY_E#*/
            SQL)
            ->create($this->connection);
    }
    
    public function priceGroup1(): static
    {
        return $this->withQueryModifier(static function (QueryParts $qp, ParametersCollection $params): void {
            $qp->andWhere($qp->expr()->gt('r.price', ':p_price'));
            $params->setParameter('p_price', 25);
        });
    }
    
    public function priceGroup2(): static
    {
        return $this->withQueryExpression(
            $this->qry()
                ->andWhere($this->expr()->greaterThan('price', 25))
        );
    }    
}
```

The `/*#WHERE#*/` and `/*#ORDERBY#*/` markers are replaced automatically with generated SQL when filters and sorts are applied. When nothing is applied they are removed cleanly.

### ORM QueryBuilder

```php
use Kraz\ReadModelDoctrine\DoctrineReadDataProvider;

class ProductsReadModel implements ReadDataProviderInterface
{
    use DoctrineReadDataProvider;

    public function __construct(private EntityManagerInterface $em) {}

    protected function createDataSource(): DataSource
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p.id, p.name, p.price')
            ->from(Product::class, 'p');

        return new DataSource($qb);
    }
}
```

### Querying

Both styles share the same query API:

```php
// Filters and sorting
$products = $readModel
    ->withQueryExpression(
        QueryExpression::create()
            ->andWhere(FilterExpression::create()->greaterThan('price', 10))
            ->andWhere(FilterExpression::create()->equalTo('category', 'tools'))
            ->sortBy('name', SortExpression::DIR_ASC)
    )
    ->withPagination(page: 1, itemsPerPage: 20)
    ->getResult();

// $result->data   — items on this page
// $result->page   — current page
// $result->total  — total matching rows
```

### Testing — no database needed

```php
use Kraz\ReadModel\DataSource;

$stub = new DataSource([
    ['id' => 1, 'name' => 'Widget', 'price' => 15, 'category' => 'tools'],
    ['id' => 2, 'name' => 'Gadget', 'price' => 5,  'category' => 'electronics'],
]);

// All filter, sort, and pagination operations work identically on the in-memory stub
$cheap = $stub
    ->withQueryExpression(
        QueryExpression::create()->andWhere(
            FilterExpression::create()->lowerThan('price', 10)
        )
    )
    ->data();
// → [['id' => 2, 'name' => 'Gadget', ...]]
```

## Acknowledgements

The library uses source code from [Doctrine Collections](https://github.com/doctrine/collections) which was modified to meet the required behavior.

## License

This library is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
