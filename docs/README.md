# Read Model Documentation

The `kraz/read-model` library family provides a uniform API for querying read-only data from multiple backends: in-memory collections/arrays, Doctrine ORM/raw SQL, JSON-RPC APIs, and Elasticsearch. You write your query logic once against a common interface, then plug in any backend — including an in-memory one for fast, side-effect-free testing.

## Packages

| Package                                                                               | Description                                                    | Install                                           |
|---------------------------------------------------------------------------------------|----------------------------------------------------------------|---------------------------------------------------|
| [`kraz/read-model`](https://github.com/kraz/read-model)                               | Core interfaces + in-memory implementation                     | `composer require kraz/read-model`                |
| [`kraz/read-model-doctrine`](https://github.com/kraz/read-model-doctrine)             | Doctrine ORM & raw SQL backend                                 | `composer require kraz/read-model-doctrine`       |
| [`kraz/read-model-json-rpc`](https://github.com/kraz/read-model-json-rpc)             | JSON-RPC 2.0 API backend _(expects read-model exposed as API)_ | `composer require kraz/read-model-json-rpc`       |
| [`kraz/read-model-elastic-search`](https://github.com/kraz/read-model-elastic-search) | Elasticsearch backend                                          | `composer require kraz/read-model-elastic-search` |

## Contents

- [Getting Started](getting-started.md) - installation, minimal example, key ideas in one page
- [Core Concepts](core-concepts.md) - DataSource, ReadModel class, ReadResponse, immutability
- [Filtering & Sorting](filtering.md) - FilterExpression, QueryExpression, SortExpression
- [Pagination & Limits](pagination.md) - page-based pagination, limit/offset, and cursor (keyset) pagination
- [Specifications](specifications.md) - the specification pattern for in-memory business-rule filtering
- [Testing](testing.md) - using the in-memory DataSource in unit and integration tests
- [Doctrine Backend](doctrine.md) - QueryBuilder, raw SQL, SQL templates
- [JSON-RPC Backend](json-rpc.md) - connecting to a remote JSON-RPC API
- [Elasticsearch Backend](elasticsearch.md) - full-text search, nested fields, query strategies

## Quick Example

```php
// Define your read model once
class ProductsReadModel implements ReadDataProviderInterface
{
    use DoctrineReadDataProvider;

    public function __construct(private EntityManagerInterface $em) {}

    protected function createDataSource(): DataSource
    {
        $qb = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Product::class, 'r');

        return new DataSource($qb);
    }
}

// Query it
$products = $readModel
    ->withQueryExpression(
        QueryExpression::create()
            ->andWhere(FilterExpression::create()->greaterThan('price', 10))
            ->sortBy('name', SortExpression::DIR_ASC)
    )
    ->withPagination(page: 1, itemsPerPage: 20)
    ->data();

// In tests, replace the whole read model with an in-memory stub
$readModel = new DataSource([
    ['id' => 1, 'name' => 'Widget', 'price' => 15],
    ['id' => 2, 'name' => 'Gadget', 'price' => 5],
]);
```
