# Testing

One of the main benefits of this library is that the in-memory `DataSource` is a drop-in replacement for any backend-backed read model. Your tests run without a database, HTTP client, or Elasticsearch cluster, and they are fast.

## Using In-Memory DataSource in Tests

Seed a `DataSource` with a plain array and use it anywhere a `ReadDataProviderInterface` is expected:

```php
use Kraz\ReadModel\DataSource;

$stub = new DataSource([
    ['id' => 1, 'name' => 'Alice', 'role' => 'admin', 'active' => true],
    ['id' => 2, 'name' => 'Bob',   'role' => 'user',  'active' => true],
    ['id' => 3, 'name' => 'Carol', 'role' => 'user',  'active' => false],
]);
```

All query operations (filtering, sorting, pagination, specifications) work identically to the real backend.

## Testing Filtering

```php
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;

$result = $stub
    ->withQueryExpression(
        QueryExpression::create()->andWhere(
            FilterExpression::create()->equalTo('active', true)
        )
    )
    ->data();

self::assertCount(2, $result);           // Alice and Bob
self::assertSame('Alice', $result[0]['name']);
```

## Testing Sorting

```php
use Kraz\ReadModel\Query\SortExpression;

$result = $stub
    ->withQueryExpression(
        QueryExpression::create()->sortBy('name', SortExpression::DIR_DESC)
    )
    ->data();

self::assertSame('Carol', $result[0]['name']);
self::assertSame('Bob',   $result[1]['name']);
self::assertSame('Alice', $result[2]['name']);
```

## Testing Pagination

```php
$paginated = $stub->withPagination(page: 1, itemsPerPage: 2);

self::assertTrue($paginated->isPaginated());
self::assertCount(2, $paginated->data());
self::assertSame(3, $paginated->totalCount());
self::assertSame(2, $paginated->paginator()->getLastPage());
```

## Testing Specifications

Specifications run in-memory in tests too — no need to change them:

```php
use Kraz\ReadModel\DataSource;

class ActiveUserSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $item): bool
    {
        return $item['active'] === true;
    }
}

$stub = new DataSource([/* ... */]);
$result = $stub
    ->withSpecification(new ActiveUserSpecification())
    ->withLimit(100)
    ->data();

self::assertCount(2, $result);  // Alice and Bob
```

## Replacing a Real Read Model with an In-Memory One

The cleanest approach for service or controller tests is to inject the in-memory `DataSource` wherever the read model interface is expected:

```php
// Production code depends only on the interface
class InvoiceListController
{
    public function __construct(
        private readonly ReadDataProviderInterface $invoicesReadModel
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse(
            $this->invoicesReadModel->handleRequest($request)->getResult()
        );
    }
}

// In the test — inject the stub
class InvoiceListControllerTest extends TestCase
{
    public function testReturnsInvoicesAsJson(): void
    {
        $stub = new DataSource([
            ['id' => 'INV-001', 'amount' => 150, 'status' => 'paid'],
            ['id' => 'INV-002', 'amount' => 75,  'status' => 'pending'],
        ]);

        $controller = new InvoiceListController($stub);
        $response   = $controller(new Request());

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertCount(2, $data);
    }
}
```

## Testing Item Normalizers

If your read model uses an item normalizer to transform raw rows into typed objects, test the normalizer logic by passing it directly to `DataSource`:

```php
$stub = new DataSource(
    data: [
        ['id' => 1, 'first_name' => 'Alice', 'last_name' => 'Smith'],
    ],
    itemNormalizer: fn(array $row) => new UserDTO($row['id'], $row['first_name'] . ' ' . $row['last_name'])
);

$users = $stub->data();
self::assertInstanceOf(UserDTO::class, $users[0]);
self::assertSame('Alice Smith', $users[0]->fullName);
```

## Testing Values Queries

```php
$stub = new DataSource([
    ['id' => 10, 'name' => 'First'],
    ['id' => 20, 'name' => 'Second'],
    ['id' => 30, 'name' => 'Third'],
]);

$result = $stub
    ->withQueryExpression(
        QueryExpression::create()->withValues([30, 10])  // specific IDs, in this order
    )
    ->data();

self::assertSame(30, $result[0]['id']);  // order preserved
self::assertSame(10, $result[1]['id']);
```

## Testing MissingValuesException

When querying by IDs and some are not found, the library throws `MissingValuesException`:

```php
use Kraz\ReadModel\Exception\MissingValuesException;

$this->expectException(MissingValuesException::class);

$stub->withQueryExpression(
    QueryExpression::create()->withValues([1, 999])  // 999 doesn't exist
)->data();
```

## Tips

- Use `DataSource` in unit tests; save integration tests for validating your actual SQL/ES queries.
- Keep the data set in each test small — seed only what the test cares about.
- Test specifications independently from any read model; they are plain PHP objects.
- If you need to test that a specific filter reaches the backend, check the query built by the backend's `DataSource`, not the results — or use integration tests against a real database / Elasticsearch instance.
