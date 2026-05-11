# JSON-RPC Backend

`kraz/read-model-json-rpc` lets you use a remote JSON-RPC 2.0 API as a data source. The read model fetches a page of data from the API and applies any additional filtering, sorting, or specifications in-memory on the result set.

```bash
composer require kraz/read-model-json-rpc
```

## Client Library

The `kraz/json-rpc-client` library provides a ready-to-use `JsonRpcClientInterface` implementation that handles the HTTP transport, request encoding, and response decoding. It integrates directly with `kraz/read-model-json-rpc` — you only need to provide a configured instance and implement `JsonRpcReadClientInterface` on top of it to handle the `read()` method for your specific API endpoint.

```bash
composer require kraz/json-rpc-client
```

## How It Works

Unlike a database where arbitrary SQL can be generated, a JSON-RPC API exposes fixed endpoints. The library:

1. Translates `QueryExpression` filter/sort into request parameters that the API understands
2. Passes pagination params (`page`, `pageSize`) or limit params (`limit`, `offset`)
3. Receives a `ReadResponse`-compatible payload `{data, page, total}` from the API
4. Applies `Specification` in-memory on the received items (for rules the API cannot express)

## Setting Up the API Client

Your API client must implement two interfaces:

- `JsonRpcClientInterface` (from `kraz/json-rpc-client`) — for sending JSON-RPC calls
- `JsonRpcReadClientInterface` — for the standard `read()` operation

```php
use Kraz\ReadModelJsonRpc\JsonRpcReadClientInterface;
use Kraz\ReadModel\ReadResponse;

class OrdersApiClient implements JsonRpcClientInterface, JsonRpcReadClientInterface
{
    public function __construct(private readonly SomeHttpClient $http) {}

    public function read(array|null $params = null): ReadResponse
    {
        $response = $this->http->post('/rpc', [
            'jsonrpc' => '2.0',
            'method'  => 'orders.list',
            'params'  => $params ?? [],
            'id'      => 1,
        ]);

        $body = $response->toArray();

        return ReadResponse::create(
            data:  $body['result']['data'],
            page:  $body['result']['page'],
            total: $body['result']['total'],
        );
    }

    // implement call(), notify(), batch() from JsonRpcClientInterface...
}
```

## Creating the Read Model

```php
use Kraz\ReadModel\ReadDataProviderInterface;
use Kraz\ReadModelJsonRpc\DataSource;
use Kraz\ReadModelJsonRpc\DataSourceBuilder;
use Kraz\ReadModelJsonRpc\JsonRpcReadDataProvider;

class OrdersReadModel implements ReadDataProviderInterface
{
    use JsonRpcReadDataProvider;

    const FIELD_ID           = 'id';
    const FIELD_STATUS       = 'status';
    const FIELD_CLIENT_NAME  = 'clientName';
    const FIELD_ORDER_VALUE  = 'orderValue';
    const FIELD_CREATED_AT   = 'createdAt';

    public function __construct(
        private readonly OrdersApiClient $api
    ) {}

    protected function createDataSource(): DataSource
    {
        return (new DataSourceBuilder())
            ->withRootIdentifier(self::FIELD_ID)
            ->create($this->api);
    }
}
```

## Querying

The read model is used exactly like any other backend:

```php
// Pagination
$result = $ordersReadModel
    ->withPagination(page: 1, itemsPerPage: 20)
    ->getResult();

// Filtering — params are forwarded to the API
$filtered = $ordersReadModel
    ->withPagination(1, 20)
    ->withQueryExpression(
        QueryExpression::create()
            ->andWhere(FilterExpression::create()->equalTo('status', 'confirmed'))
            ->sortBy('createdAt', SortExpression::DIR_DESC)
    )
    ->getResult();

// Direct access
$items = $ordersReadModel->data();
```

## Field Mapping

If the API uses different field names from what you expose in your application, declare a mapping:

```php
const MAPPING = [
    self::FIELD_CLIENT_NAME => 'client_name',  // app field → API field
    self::FIELD_ORDER_VALUE => 'order_value',
];

protected function createDataSource(): DataSource
{
    return (new DataSourceBuilder())
        ->withFieldMapping(self::MAPPING)
        ->withRootIdentifier(self::FIELD_ID)
        ->create($this->api);
}
```

## In-Memory Specifications

When the API cannot express a complex filter, apply it in PHP using specifications. The library fetches a limited batch from the API and filters it locally:

```php
use Kraz\ReadModel\Specification\AbstractSpecification;

class HighValueOrderSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $order): bool
    {
        return $order['orderValue'] > 1000;
    }
}

// Must set a limit when using specifications
$result = $ordersReadModel
    ->withSpecification(new HighValueOrderSpecification())
    ->withLimit(100)
    ->data();
```

## Custom Parameter Names

By default the library sends `page`, `pageSize`, `limit`, and `offset` to the API. If your API uses different names:

```php
protected function createDataSource(): DataSource
{
    return (new DataSourceBuilder())
        ->create(
            data:               $this->api,
            pageParamName:      'currentPage',
            pageSizeParamName:  'perPage',
            limitParamName:     'maxItems',
            offsetParamName:    'skip',
        );
}
```

## Gateway Helper — JsonRpcClientGateway

For APIs that require multi-operation patterns (fetch by ID, fetch single by criteria), extend `JsonRpcClientGateway`:

```php
use Kraz\ReadModelJsonRpc\JsonRpcClientGateway;

class ProductsApiGateway extends JsonRpcClientGateway implements
    JsonRpcClientInterface,
    JsonRpcReadClientInterface
{
    public function read(array|null $params = null): ReadResponse
    {
        $result = $this->handleRead($params, ProductListItem::class);
        return ReadResponse::create($result['data'], $result['page'], $result['total']);
    }

    // Fetch a single product by its ID
    public function findById(int $id): ?ProductDetail
    {
        return $this->handleReadSingleValue($id, ProductDetail::class);
    }

    // Fetch by multiple IDs, in the given order
    public function findByIds(array $ids): array
    {
        return $this->handleReadMultipleValues(
            values:     $ids,
            itemClass:  ProductDetail::class,
            indexField: 'id',
        );
    }

    // Fetch by criteria fields
    public function findByCode(string $code): ?ProductDetail
    {
        return $this->handleReadSingleValueByCriteria(
            ['code' => $code],
            ProductDetail::class
        );
    }
}
```

The `handleRead*` methods take care of calling the API, denormalizing the response, and raising exceptions for unexpected results (e.g., `NonUniqueResultException` when a single-result query finds multiple).

## Denormalization

`JsonRpcClientGateway` accepts a `JsonRpcDenormalizerInterface` for converting raw arrays to typed objects. Use the provided Symfony adapter or implement your own:

```php
// Using Symfony Serializer
use Kraz\ReadModelJsonRpc\JsonRpcDenormalizerInterface;

class SymfonyJsonRpcDenormalizer implements JsonRpcDenormalizerInterface
{
    public function __construct(private readonly DenormalizerInterface $denormalizer) {}

    public function denormalize(mixed $data, string $type): mixed
    {
        return $this->denormalizer->denormalize($data, $type);
    }
}

// Wire it up
$gateway = new ProductsApiGateway($jsonRpcClient, new SymfonyJsonRpcDenormalizer($serializer));
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

## Testing with a Fake Client

The test pattern mirrors the standard in-memory approach: inject a fake client that returns controlled data.

```php
class FakeOrdersClient implements JsonRpcClientInterface, JsonRpcReadClientInterface
{
    public function __construct(private array $items) {}

    public function read(array|null $params = null): ReadResponse
    {
        $page     = (int) ($params['page']     ?? 1);
        $pageSize = (int) ($params['pageSize'] ?? count($this->items));
        $offset   = ($page - 1) * $pageSize;
        $slice    = array_slice($this->items, $offset, $pageSize);

        return ReadResponse::create(
            data:  $slice,
            page:  $page,
            total: count($this->items),
        );
    }

    // stub call(), notify(), batch()...
}

// In your test
$fakeClient = new FakeOrdersClient([
    ['id' => 1, 'status' => 'confirmed', 'orderValue' => 1500],
    ['id' => 2, 'status' => 'pending',   'orderValue' => 200],
]);

$readModel = new OrdersReadModel($fakeClient);

$result = $readModel
    ->withSpecification(new HighValueOrderSpecification())
    ->withLimit(10)
    ->data();

self::assertCount(1, $result);
self::assertSame(1, $result[0]['id']);
```
