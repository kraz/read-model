# Read Model

[![CI](https://github.com/kraz/read-model/actions/workflows/ci.yml/badge.svg)](https://github.com/kraz/read-model/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/kraz/read-model)](https://packagist.org/packages/kraz/read-model)
[![GitHub license](https://img.shields.io/github/license/kraz/read-model)](LICENSE)

A domain-first Read Model library that provides a uniform API for querying read-only data from multiple backends. Define your query logic once; plug in Doctrine ORM, raw SQL, JSON-RPC APIs, or Elasticsearch — or use the built-in in-memory implementation for fast, dependency-free tests.

## Installation

```bash
composer require kraz/read-model
```

## Additional packages

Install any of the following packages which matches your infrastructure requirements:

| Package                                                                             | Description                                                    |
|-------------------------------------------------------------------------------------|----------------------------------------------------------------|
| [kraz/read-model-doctrine](https://github.com/kraz/read-model-doctrine)             | Doctrine ORM & raw SQL backend                                 |
| [kraz/read-model-json-rpc](https://github.com/kraz/read-model-json-rpc)             | JSON-RPC 2.0 API backend _(expects read-model exposed as API)_ |
| [kraz/read-model-elastic-search](https://github.com/kraz/read-model-elastic-search) | Elasticsearch backend                                          |

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

## Example usage

### Raw SQL

```php
use Kraz\ReadModel\ReadDataProviderInterface;
use Kraz\ReadModelDoctrine\DataSource;
use Kraz\ReadModelDoctrine\DataSourceBuilder;
use Kraz\ReadModelDoctrine\RawQueryReadDataProvider;

class ProductsReadModel implements ReadDataProviderInterface
{
    use RawQueryReadDataProvider;

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
            ->select('r.id, r.name, r.price')
            ->from(Product::class, 'r');

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
        $readModel->qry()
            ->andWhere($readModel->expr()->greaterThan('price', 10))
            ->andWhere($readModel->expr()->equalTo('category', 'tools'))
            ->sortBy('name', SortExpression::DIR_ASC)
    )
    ->withPagination(page: 1, itemsPerPage: 20)
    ->getResult();

// $result->data   — items on this page
// $result->page   — current page
// $result->total  — total matching rows
```

_The query expression is serializable and can be stored and/or transferred as needed._

## Acknowledgements

The library uses source code from [Doctrine Collections](https://github.com/doctrine/collections) which was modified to meet the required behavior.

## License

This library is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
