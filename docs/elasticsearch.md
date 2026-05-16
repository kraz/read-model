# Elasticsearch Backend

`kraz/read-model-elastic-search` connects the read model API to Elasticsearch. It translates `FilterExpression`, `SortExpression`, and other query objects into Elasticsearch queries and returns results through the standard `ReadDataProviderInterface`.

```bash
composer require kraz/read-model-elastic-search
```

## Client Library

The Elasticsearch read model library comes with already available `kraz/elastic-search-client` library which provides a ready-to-use `ElasticSearchClientInterface` implementation that handles the HTTP transport and response parsing for Elasticsearch. It integrates directly with `kraz/read-model-elastic-search` — you only need to provide a configured instance and implement `ElasticSearchReadClientInterface` on top of it (or use `ElasticSearchClientGateway` as a base class) to wire up the `read()` method for your specific index.

## How It Works

1. `QueryExpression` filters and sorts are converted to an Elasticsearch `bool` query with `filter` clauses and a `sort` array.
2. The result's `hits.hits[*]._source` is extracted and returned as an array of items.
3. Pagination uses Elasticsearch's `from` and `size` parameters.
4. Specifications are applied in-memory on the returned items.

## Setting Up the Client

Your Elasticsearch client must implement:

- `ElasticSearchClientInterface` (from `kraz/elastic-search-client`) — for the underlying HTTP communication
- `ElasticSearchReadClientInterface` — for the standard `read()` operation

```php
use Kraz\ReadModelElasticSearch\ElasticSearchReadClientInterface;
use Kraz\ReadModel\ReadResponse;

class OrdersElasticClient implements ElasticSearchClientInterface, ElasticSearchReadClientInterface
{
    public function __construct(private readonly ElasticSearchClientInterface $client) {}

    public function read(array $query = [], string|null $index = null): ReadResponse
    {
        $response = $this->client->search($query, $index ?? 'orders');
        $hits     = $response->toArray();

        $data  = array_column($hits['hits']['hits'], '_source');
        $total = $hits['hits']['total']['value'];
        $page  = isset($query['from'], $query['size'])
            ? (int) ceil($query['from'] / $query['size']) + 1
            : 1;

        return ReadResponse::create(data: $data, page: $page, total: $total);
    }

    // delegate search(), getMapping(), getFlattenedMapping() to $this->client
}
```

### Using the Gateway Base Class

For richer use cases (denormalization, convenience methods), extend `ElasticSearchClientGateway`. It handles `_source` extraction, pagination math, and optional denormalization automatically:

```php
use Kraz\ReadModelElasticSearch\ElasticSearchClientGateway;

class OrdersElasticGateway extends ElasticSearchClientGateway implements
    ElasticSearchClientInterface,
    ElasticSearchReadClientInterface
{
    public function read(array $query = [], string|null $index = null): ReadResponse
    {
        $payload = $this->handleRead($query, null, $index ?? 'orders');
        return ReadResponse::create($payload['data'], $payload['page'], $payload['total']);
    }
}
```

## Creating the Read Model

```php
use Kraz\ReadModel\ReadDataProviderInterface;
use Kraz\ReadModelElasticSearch\DataSource;
use Kraz\ReadModelElasticSearch\DataSourceBuilder;
use Kraz\ReadModelElasticSearch\ElasticSearchReadDataProvider;
use Kraz\ReadModelElasticSearch\FullTextSearchReadModelInterface;
use Kraz\ReadModelElasticSearch\ElasticRawQuerySearchReadModelInterface;

class OrdersReadModel implements
    ReadDataProviderInterface,
    FullTextSearchReadModelInterface,
    ElasticRawQuerySearchReadModelInterface
{
    use ElasticSearchReadDataProvider;

    const FIELD_ORDER_ID    = 'orderId';
    const FIELD_STATUS      = 'status';
    const FIELD_CLIENT_NAME = 'clientName';
    const FIELD_ORDER_VALUE = 'orderValue';
    const FIELD_CREATED_AT  = 'createdAt';

    public function __construct(
        private readonly OrdersElasticGateway $api
    ) {}

    protected function createDataSource(): DataSource
    {
        return (new DataSourceBuilder())
            ->withRootIdentifier(self::FIELD_ORDER_ID)
            ->create($this->api, index: 'orders');
    }
}
```

## Querying

```php
// Pagination
$result = $ordersReadModel
    ->withPagination(page: 1, itemsPerPage: 20)
    ->getResult();

// Filtering
$filtered = $ordersReadModel
    ->withPagination(1, 20)
    ->withQueryExpression(
        QueryExpression::create()
            ->andWhere(FilterExpression::create()->equalTo('status', 'confirmed'))
            ->andWhere(FilterExpression::create()->greaterThan('orderValue', 0))
            ->sortBy('createdAt', SortExpression::DIR_DESC)
    )
    ->getResult();
```

## Full-Text Search

Add `FullTextSearchReadModelInterface` to your class (already shown above) and use `withFullTextSearch()`:

```php
$results = $ordersReadModel
    ->withFullTextSearch('ACME Corporation')
    ->withPagination(1, 20)
    ->getResult();
```

Full-text search requires a `catch_all` field in your Elasticsearch mapping (for the 9.x strategy). Set it up with `copy_to`:

```json
{
    "mappings": {
        "properties": {
            "clientName":  { "type": "text", "copy_to": "catch_all" },
            "orderNumber": { "type": "keyword", "copy_to": "catch_all" },
            "catch_all":   { "type": "text" }
        }
    }
}
```

Full-text search and field filters can be combined:

```php
$results = $ordersReadModel
    ->withFullTextSearch('ACME')
    ->withQueryExpression(
        QueryExpression::create()->andWhere(
            FilterExpression::create()->greaterThan('orderValue', 0)
        )
    )
    ->withPagination(1, 20)
    ->getResult();
```

## Raw JSON Query

When you need to pass an Elasticsearch query that cannot be expressed through `FilterExpression`, use `withRawQuerySearch()`:

```php
$rawQuery = json_encode([
    'bool' => [
        'must' => [
            ['match' => ['clientName' => 'ACME']],
        ],
        'filter' => [
            ['range' => ['orderValue' => ['gte' => 100]]],
        ],
    ],
]);

$results = $ordersReadModel
    ->withRawQuerySearch($rawQuery)
    ->withPagination(1, 20)
    ->getResult();
```

> **Note:** `withRawQuerySearch()` clears any `FilterExpression`, `QueryExpression`, pagination, and specifications that were set before. It passes the JSON string verbatim as the `query` key.

Remove it with:
```php
$readModel = $readModel->withoutRawQuerySearch();
```

## Field Mapping

When Elasticsearch field names differ from your application's field names:

```php
const MAPPING = [
    self::FIELD_CLIENT_NAME => 'JSDATA.clientName',  // nested path
    self::FIELD_ORDER_VALUE => 'JSDATA.orderValue',
];

protected function createDataSource(): DataSource
{
    return (new DataSourceBuilder())
        ->withFieldMapping(self::MAPPING)
        ->withRootIdentifier(self::FIELD_ORDER_ID)
        ->create($this->api, 'orders');
}
```

## Nested Fields

For Elasticsearch `nested` field types, the library generates nested queries automatically when it knows the mapping:

```php
// The gateway can retrieve the index mapping so the provider knows which fields are nested
$provider = new QueryExpressionProvider(
    descriptorFactory: new ReadModelDescriptorFactory(),
    queryStrategy:     new QueryStrategy9x(),
);

// Pass getIndexMappingFn as an option when applying expressions — it's called lazily
$options = [
    'getIndexMappingFn' => fn() => $this->api->getFlattenedMapping('orders'),
];
```

The library checks whether a filtered field lives inside a `nested` type and wraps the clause in a `nested` query block automatically.

## Specifications

Elasticsearch specifications work the same as all other backends — the library fetches items and filters them in PHP:

```php
class HighValueOrderSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $order): bool
    {
        return $order['orderValue'] > 1000;
    }

    protected function buildQueryExpression(): ?QueryExpression
    {
        // Pre-filter in ES to reduce the in-memory work
        return QueryExpression::create()->andWhere(
            FilterExpression::create()->greaterThan('orderValue', 1000)
        );
    }
}

$result = $ordersReadModel
    ->withSpecification(new HighValueOrderSpecification())
    ->withLimit(100)
    ->data();
```

## Query Strategies

The library ships with two strategies for Elasticsearch version compatibility:

| Class | Elasticsearch version | Notes |
|---|---|---|
| `QueryStrategy9x` (default) | 9.x and modern | Uses `bool/filter`, `catch_all`, `.keyword` suffix for sort |
| `QueryStrategy1x` | 1.x legacy | Uses `filtered`, `_all`, no `.keyword` suffix |

Specify a strategy when building the DataSource:

```php
use Kraz\ReadModelElasticSearch\QueryStrategy\QueryStrategy1x;

protected function createDataSource(): DataSource
{
    return (new DataSourceBuilder())
        ->withQueryStrategy(new QueryStrategy1x())
        ->create($this->api, 'orders');
}
```

## Handling HTTP Requests

```php
// Symfony controller
public function list(Request $request, OrdersReadModel $readModel): JsonResponse
{
    return new JsonResponse(
        $readModel->handleRequest($request)->getResult()
    );
}
```

## Supported Filter Operators

All standard `FilterExpression` operators are supported and translated to Elasticsearch queries:

| Operator | Elasticsearch clause |
|---|---|
| `equalTo` | `term` / `match` |
| `notEqualTo` | `must_not term` |
| `greaterThan` | `range gt` |
| `greaterThanOrEqual` | `range gte` |
| `lowerThan` | `range lt` |
| `lowerThanOrEqual` | `range lte` |
| `contains` | `wildcard *value*` |
| `startsWith` | `wildcard value*` |
| `endsWith` | `wildcard *value` |
| `isNull` | `must_not exists` |
| `isNotNull` | `exists` |
| `isEmpty` | `must_not exists` or empty value |
| `isNotEmpty` | `exists` and non-empty |
| `inList` | `terms` |
| `notInList` | `must_not terms` |

## Testing with a Fake Client

```php
class FakeOrdersElasticClient implements ElasticSearchClientInterface, ElasticSearchReadClientInterface
{
    public function __construct(private array $items) {}

    public function read(array $query = [], string|null $index = null): ReadResponse
    {
        $from  = (int) ($query['from'] ?? 0);
        $size  = (int) ($query['size'] ?? count($this->items));
        $page  = $size > 0 ? (int) ceil($from / $size) + 1 : 1;
        $slice = array_slice($this->items, $from, $size);

        return ReadResponse::create(data: $slice, page: $page, total: count($this->items));
    }

    // stub search(), getMapping(), getFlattenedMapping()...
}

// In tests
$fakeClient = new FakeOrdersElasticClient([
    ['orderId' => 1, 'status' => 'confirmed', 'orderValue' => 500],
    ['orderId' => 2, 'status' => 'pending',   'orderValue' => 1500],
]);

$readModel = new OrdersReadModel($fakeClient);

$result = $readModel->withPagination(1, 10)->getResult();
self::assertSame(2, $result->total);
```
