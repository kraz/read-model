# Core Concepts

## ReadDataProviderInterface

`ReadDataProviderInterface` is the contract every read model implements, regardless of backend. It provides:

| Method                                                 | Description                                                                                              |
|--------------------------------------------------------|----------------------------------------------------------------------------------------------------------|
| `data(): array`                                        | All items on the current "page" (or all items when not paginated)                                        |
| `getResult(): array\|ReadResponse\|CursorReadResponse` | Like `data()` but returns `ReadResponse` when page-paginated, `CursorReadResponse` when cursor-paginated |
| `getIterator(): Traversable`                           | Iterate without loading everything into memory                                                           |
| `count(): int`                                         | Number of items in the current result set                                                                |
| `totalCount(): int`                                    | Total items matching the query, ignoring pagination                                                      |
| `isEmpty(): bool`                                      | `true` when no items match                                                                               |
| `isPaginated(): bool`                                  | `true` when offset/page-based pagination is active                                                       |
| `isCursored(): bool`                                   | `true` when cursor-based pagination is active                                                            |
| `paginator(): PaginatorInterface\|null`                | Offset/page paginator object, or `null` when not active                                                  |
| `cursorPaginator(): CursorPaginatorInterface\|null`    | Cursor paginator object, or `null` when cursor mode is not active                                        |

Calling code only depends on this interface. The backend (Doctrine, JSON-RPC, Elasticsearch, in-memory) is an implementation detail of the read model class.

## DataSource — The In-Memory Backend

`Kraz\ReadModel\DataSource` is the core implementation that holds data in memory. Any `iterable` works as input:

```php
use Kraz\ReadModel\DataSource;

// From a plain array
$ds = new DataSource([
    ['id' => 1, 'name' => 'Alice'],
    ['id' => 2, 'name' => 'Bob'],
]);

// From a generator (lazy)
$ds = new DataSource((function () use ($repository) {
    foreach ($repository->getAll() as $row) {
        yield $row;
    }
})());

// From another ReadDataProviderInterface
$ds = new DataSource($anotherReadModel);
```

You can also use `DataSourceBuilder` for a fluent setup:

```php
use Kraz\ReadModel\DataSourceBuilder;

$ds = (new DataSourceBuilder())
    ->withData($myArray)
    ->withPagination(page: 1, itemsPerPage: 10)
    ->create();
```

The in-memory `DataSource` applies `FilterExpression` and `SortExpression` using PHP-side evaluation, so all query features work without a database.

## Immutability and Cloning

Every `with*()` method returns a cloned instance with the change applied. The original object is never modified. This makes it safe to share a "base" configuration and derive variants from it:

```php
$base     = $readModel->withQueryExpression($commonFilter);
$active   = $base->withQueryExpression($activeFilter);   // base unchanged
$archived = $base->withQueryExpression($archivedFilter); // base unchanged
```

## ReadResponse

When offset/page-based pagination is active, `getResult()` returns a `ReadResponse` instead of a plain array:

```php
$result = $readModel->withPagination(1, 20)->getResult();

// ReadResponse has three public readonly properties:
$result->data;  // T[] — items on the current page
$result->page;  // int — current page number
$result->total; // int — total items across all pages
```

`ReadResponse` also implements `ArrayAccess`, so you can encode it as JSON directly:
```php
return new JsonResponse($readModel->withPagination($page, 20)->getResult());
// → {"data": [...], "page": 1, "total": 57}
```

## CursorReadResponse

When cursor-based pagination is active, `getResult()` returns a `CursorReadResponse`:

```php
$result = $readModel->withCursor(cursor: null, limit: 20)->getResult();

$result->data;           // T[] — items on the current window
$result->nextCursor;     // string|null — opaque token to fetch the next window
$result->previousCursor; // string|null — opaque token to fetch the previous window
$result->hasNext;        // bool
$result->hasPrevious;    // bool
$result->totalItems;     // int|null — null when the adapter did not compute a total
```

Like `ReadResponse`, `CursorReadResponse` implements `ArrayAccess` and serialises to JSON directly:
```php
return new JsonResponse($readModel->withCursor($cursor, 20)->getResult());
// → {"data":[...],"nextCursor":"...","previousCursor":null,"hasNext":true,"hasPrevious":false,"totalItems":null}
```

Pass the opaque cursor token from `nextCursor` or `previousCursor` back to `withCursor()` for the next request. Pass `null` (or omit the parameter) to start at the first window. See [Pagination & Limits](pagination.md#cursor-based-keyset-pagination) for a full reference.

## Read Model Classes

For anything beyond ad-hoc use, create a dedicated read model class. The pattern is:

1. Implement `ReadDataProviderInterface`
2. Use one of the backend traits (`DoctrineReadDataProvider`, `JsonRpcReadDataProvider`, `ElasticSearchReadDataProvider`) *or* implement `DataSourceReadDataProvider` for custom backends
3. Implement the abstract `createDataSource()` method to provide the backend `DataSource`

```php
use Kraz\ReadModel\ReadDataProviderInterface;
use Kraz\ReadModelDoctrine\DataSource;
use Kraz\ReadModelDoctrine\DoctrineReadDataProvider;

class InvoicesReadModel implements ReadDataProviderInterface
{
    use DoctrineReadDataProvider;

    // Field name constants prevent magic strings in callers
    const FIELD_ID           = 'id';
    const FIELD_NUMBER       = 'invoice_number';
    const FIELD_AMOUNT       = 'amount';
    const FIELD_CLIENT_NAME  = 'client_name';
    const FIELD_ISSUED_AT    = 'issued_at';

    public function __construct(
        private readonly Connection $connection
    ) {}

    protected function createDataSource(): DataSource
    {
        return (new DataSourceBuilder())
            ->withData(<<<'SQL'
                SELECT i.id, i.invoice_number, i.amount, c.name AS client_name, i.issued_at
                FROM invoice i
                JOIN client c ON c.id = i.client_id
                WHERE i.deleted_at IS NULL
                /*#WHERE#*/
                /*#ORDERBY#*/
            SQL)
            ->create($this->connection);
    }
}
```

Callers interact only with the interface — they never need to know about SQL or connection objects.

## QueryExpression

`QueryExpression` is a value object that carries filter conditions, sort orders, and optional value constraints. It is backend-agnostic and can be serialized/deserialized.

```php
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\SortExpression;

$qe = QueryExpression::create()
    ->andWhere(FilterExpression::create()->greaterThan('amount', 100))
    ->andWhere(FilterExpression::create()->equalTo('status', 'paid'))
    ->sortBy('issued_at', SortExpression::DIR_DESC);

$readModel = $readModel->withQueryExpression($qe);
```

See [Filtering & Sorting](filtering.md) for a full reference.

## Applying HTTP Requests

Backends that support it (Doctrine, JSON-RPC, Elasticsearch) can parse query parameters from an HTTP request directly:

```php
// Symfony controller
public function list(Request $request, InvoicesReadModel $readModel): JsonResponse
{
    $data = $readModel->handleRequest($request)->getResult();
    return new JsonResponse($data);
}
```

The request's query string is interpreted as filter parameters. The exact format depends on the client, but it maps to `FilterExpression` and pagination internally.

## Field Mapping

If the column names in your database differ from the field names you want to expose to callers, use field mapping:

```php
// In a DataSourceBuilder or DataSource constructor options
->withFieldMapping([
    'clientName' => 'c.name',         // caller uses 'clientName', SQL uses 'c.name'
    'issuedAt'   => 'i.issued_at',
])
```

Callers then filter and sort using the public names. The backend translates them internally.
