# Filtering & Sorting

All filtering and sorting is expressed through three value objects: `FilterExpression`, `SortExpression`, and `QueryExpression`. They are backend-agnostic — the same code works whether the backend is Doctrine, Elasticsearch, JSON-RPC, or in-memory.

## FilterExpression

`FilterExpression` represents one or more field-level conditions.

### Basic field filters

```php
use Kraz\ReadModel\Query\FilterExpression;

$expr = FilterExpression::create();

// Equality
$expr->equalTo('status', 'active');
$expr->notEqualTo('type', 'draft');

// Comparisons
$expr->greaterThan('price', 100);
$expr->greaterThanOrEqual('price', 100);
$expr->lowerThan('age', 65);
$expr->lowerThanOrEqual('stock', 0);

// String matching (works case-insensitively with $ignoreCase = true)
$expr->contains('name', 'acme');
$expr->doesNotContain('email', 'spam');
$expr->startsWith('code', 'INV-');
$expr->doesNotStartWith('slug', 'draft-');
$expr->endsWith('email', '@example.com');
$expr->doesNotEndWith('filename', '.tmp');

// Null / emptiness
$expr->isNull('deleted_at');
$expr->isNotNull('published_at');
$expr->isEmpty('description');     // null OR empty string
$expr->isNotEmpty('description');

// Lists
$expr->inList('status', ['active', 'pending']);
$expr->notInList('category', ['archived', 'hidden']);
```

### Case-insensitive matching

Pass `true` as the third argument to any value filter:

```php
$expr->equalTo('email', 'ALICE@EXAMPLE.COM', ignoreCase: true);
$expr->contains('name', 'acme', ignoreCase: true);
```

### Composing conditions

Use `andX()` and `orX()` to combine multiple conditions:

```php
// All conditions must match (AND)
$expr = FilterExpression::create()
    ->andX(
        FilterExpression::create()->greaterThan('price', 10),
        FilterExpression::create()->lowerThan('price', 100),
        FilterExpression::create()->equalTo('active', true),
    );

// Any condition must match (OR)
$expr = FilterExpression::create()
    ->orX(
        FilterExpression::create()->equalTo('status', 'active'),
        FilterExpression::create()->equalTo('status', 'pending'),
    );
```

You can nest arbitrarily:

```php
$expr = FilterExpression::create()
    ->andX(
        FilterExpression::create()->equalTo('tenant_id', $tenantId),
        FilterExpression::create()->orX(
            FilterExpression::create()->equalTo('status', 'active'),
            FilterExpression::create()->isNull('archived_at'),
        ),
    );
```

### Inverting a filter

```php
$isNotActive = FilterExpression::create()
    ->equalTo('status', 'active')
    ->invert();  // now matches everything EXCEPT active
```

## SortExpression

```php
use Kraz\ReadModel\Query\SortExpression;

$sort = SortExpression::create()
    ->desc('created_at')      // most recent first
    ->asc('name');            // then alphabetically

// Direction constants
SortExpression::DIR_ASC   // 'asc'
SortExpression::DIR_DESC  // 'desc'
```

## QueryExpression

`QueryExpression` is the top-level container that combines a `FilterExpression`, a `SortExpression`, and optional value constraints (for fetching by a list of IDs).

```php
use Kraz\ReadModel\Query\QueryExpression;

$qe = QueryExpression::create()
    ->andWhere(FilterExpression::create()->greaterThan('amount', 0))
    ->andWhere(FilterExpression::create()->equalTo('currency', 'EUR'))
    ->sortBy('created_at', SortExpression::DIR_DESC)
    ->sortBy('id', SortExpression::DIR_ASC);
```

### Applying to a read model

```php
$invoices = $readModel
    ->withQueryExpression($qe)
    ->data();
```

### Replacing vs. appending expressions

By default, each `withQueryExpression()` call **replaces** the previous expression:

```php
$readModel = $readModel->withQueryExpression($tenantFilter);
$readModel = $readModel->withQueryExpression($userFilter); // replaces $tenantFilter
```

To **accumulate** expressions instead, pass `true` as the second argument:

```php
$readModel = $readModel
    ->withQueryExpression($tenantFilter)              // set
    ->withQueryExpression($userFilter, append: true); // added on top
```

### Removing expressions

Clear the current expression (all history is discarded):
```php
$readModel = $readModel->withoutQueryExpression();
```

Step back to the previous expression (undo one level):
```php
$readModel = $readModel->withoutQueryExpression(undo: true);
```

### Values queries (fetch by IDs)

When you need to load a specific set of records by their identifier:

```php
$qe = QueryExpression::create()->withValues([42, 17, 99]);

$items = $readModel
    ->withQueryExpression($qe)
    ->data();
// Items are returned in the order [42, 17, 99]
// MissingValuesException is thrown if any ID is not found
```

### Serializing and sharing expressions

`QueryExpression` can be serialized to a base64-encoded string and later restored:

```php
$encoded = $qe->encode();               // base64 string, safe for URLs/headers
$restored = QueryExpression::decode($encoded);
```

## Building Filters Fluently with the Read Model

The read model itself also exposes `qry()` and `expr()` shortcuts:

```php
$readModel = $readModel
    ->withQueryExpression(
        $readModel->qry()
            ->andWhere($readModel->expr()->contains('name', 'acme'))
            ->sortBy('name', SortExpression::DIR_ASC)
    );
```

## Available Operators Reference

| Method | SQL / ES equivalent |
|---|---|
| `equalTo` | `= value` |
| `notEqualTo` | `<> value` |
| `greaterThan` | `> value` |
| `greaterThanOrEqual` | `>= value` |
| `lowerThan` | `< value` |
| `lowerThanOrEqual` | `<= value` |
| `startsWith` | `LIKE 'value%'` |
| `doesNotStartWith` | `NOT LIKE 'value%'` |
| `endsWith` | `LIKE '%value'` |
| `doesNotEndWith` | `NOT LIKE '%value'` |
| `contains` | `LIKE '%value%'` |
| `doesNotContain` | `NOT LIKE '%value%'` |
| `isNull` | `IS NULL` |
| `isNotNull` | `IS NOT NULL` |
| `isEmpty` | `IS NULL OR = ''` |
| `isNotEmpty` | `IS NOT NULL AND <> ''` |
| `inList` | `IN (...)` |
| `notInList` | `NOT IN (...)` |

## Handling User Input

A common pattern is to accept arbitrary filter/sort input from an HTTP request and apply it directly:

```php
// Symfony controller
public function list(Request $request, InvoicesReadModel $readModel): JsonResponse
{
    return new JsonResponse(
        $readModel->handleRequest($request)->getResult()
    );
}
```

This parses standard query parameters and maps them to `FilterExpression` and pagination automatically. For custom validation or field restrictions, apply specific `QueryExpression` objects before calling `handleRequest()`.
