# Getting Started

## Requirements

- PHP 8.4 or higher
- Composer

## Installation

Install the core library (or it will be fetched as dependency anyway) and whichever backend adapter you need:

```bash
# Core (always required)
composer require kraz/read-model

# Pick one or more backends:
composer require kraz/read-model-doctrine        # Doctrine ORM / raw SQL
composer require kraz/read-model-json-rpc        # JSON-RPC 2.0 API
composer require kraz/read-model-elastic-search  # Elasticsearch
```

## Your First Read Model

A read model is a class that answers a specific read-only query. The simplest way to build one is to implement `ReadDataProviderInterface` and delegate to an appropriate `DataSource`.

### Step 1 — Define a read model class

```php
use Kraz\ReadModel\ReadDataProviderInterface;
use Kraz\ReadModelDoctrine\DataSource;
use Kraz\ReadModelDoctrine\DoctrineReadDataProvider;

class ProductsReadModel implements ReadDataProviderInterface
{
    use DoctrineReadDataProvider;

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    protected function createDataSource(): DataSource
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p.id, p.name, p.price, p.category')
            ->from(Product::class, 'p')
            ->where('p.active = true');

        return new DataSource($qb);
    }
}
```

### Step 2 — Query it

```php
$readModel = new ProductsReadModel($entityManager);

// Get all matching rows as plain arrays
$products = $readModel->data();

// Apply filters and sorting
$products = $readModel
    ->withQueryExpression(
        QueryExpression::create()
            ->andWhere(FilterExpression::create()->equalTo('category', 'electronics'))
            ->sortBy('price', SortExpression::DIR_ASC)
    )
    ->data();

// Paginate
$readModel = $readModel->withPagination(page: 1, itemsPerPage: 20);
$paginator  = $readModel->paginator(); // PaginatorInterface
$products   = $readModel->data();
```

### Step 3 — Test it with in-memory data

No database needed in tests:

```php
use Kraz\ReadModel\DataSource;

$stub = new DataSource([
    ['id' => 1, 'name' => 'Widget', 'price' => 15, 'category' => 'tools'],
    ['id' => 2, 'name' => 'Gadget', 'price' => 50, 'category' => 'electronics'],
    ['id' => 3, 'name' => 'Doohickey', 'price' => 5, 'category' => 'tools'],
]);

// The same query API works on the in-memory stub
$cheap = $stub
    ->withQueryExpression(
        QueryExpression::create()->andWhere(
            FilterExpression::create()->lowerThan('price', 20)
        )
    )
    ->data();
// → [['id' => 1, ...], ['id' => 3, ...]]
```

## Key Ideas

**One interface, many backends.** `ReadDataProviderInterface` is the common contract. Swap a Doctrine-backed read model for an in-memory one in tests without changing the calling code.

**Immutable, fluent API.** Every `with*()` call returns a *new* instance — the original is unchanged. Chain calls freely; store intermediate configurations.

```php
$base     = $readModel->withQueryExpression($commonFilters);
$page1    = $base->withPagination(1, 20);
$page2    = $base->withPagination(2, 20);  // $base is still without pagination
```

**Specifications for in-memory business rules.** When you need PHP-side filtering (e.g., complex rules that cannot be expressed in SQL), use `SpecificationInterface`. See [Specifications](specifications.md).

**Field constants.** Declare field names as class constants on your read model to avoid magic strings across the codebase:

```php
class ProductsReadModel implements ReadDataProviderInterface
{
    const FIELD_ID       = 'id';
    const FIELD_NAME     = 'name';
    const FIELD_PRICE    = 'price';
    const FIELD_CATEGORY = 'category';
    // ...
}

// Callers
->andWhere(FilterExpression::create()->equalTo(ProductsReadModel::FIELD_CATEGORY, 'tools'))
```

## Next Steps

- Learn how to build filters → [Filtering & Sorting](filtering.md)
- Add pagination → [Pagination & Limits](pagination.md)
- Test without a database → [Testing](testing.md)
- Use Doctrine ORM → [Doctrine Backend](doctrine.md)
- Use a JSON-RPC API → [JSON-RPC Backend](json-rpc.md)
- Use Elasticsearch → [Elasticsearch Backend](elasticsearch.md)
