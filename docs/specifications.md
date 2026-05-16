# Specifications

Specifications are PHP classes that encode a business rule as a predicate: "does this item satisfy the rule?" They run in-memory on items already fetched from the backend.

Use specifications when:
- The filter cannot be expressed in SQL/query-language (complex object graph traversal, service calls, etc.)
- You need to share a business rule between the read model and other parts of the application
- You are testing with in-memory data and want the same rule to work everywhere

> [!WARNING]  
> Specifications are considered an anti-pattern which introduces unnecessary overengineering and bloated code. So don't use them for simple CRUD like applications or static conditions. They are primarily a solution for highly complex, dynamic rules.

## Creating a Specification

Extend `AbstractSpecification` and implement `isSatisfiedBy()`:

```php
use Kraz\ReadModel\Specification\AbstractSpecification;

/**
 * @extends AbstractSpecification<array<string, mixed>>
 */
class ActiveAndVerifiedSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $item): bool
    {
        return $item['active'] === true
            && $item['email_verified'] === true;
    }
}
```

The generic type parameter (e.g., `array<string, mixed>`) describes the shape of the items being tested.

## Applying Specifications

```php
$readModel = $readModel
    ->withSpecification(new ActiveAndVerifiedSpecification())
    ->withLimit(100);  // required when using specifications — see below

$items = $readModel->data();
```

> **Limit required:** When specifications are used, the library fetches items from the backend in batches and filters them in PHP. You must set a limit so it knows how many items to collect.

## Composing Specifications

Specifications can be combined using `and()`, `or()`, `andNot()`, `orNot()`:

```php
$spec = (new ActiveAndVerifiedSpecification())
    ->and(new HasOrdersSpecification())
    ->andNot(new IsAdminSpecification());

$readModel = $readModel
    ->withSpecification($spec)
    ->withLimit(50);
```

To accumulate multiple specifications, pass `true` as the second argument — otherwise each call **replaces** the previous one:

```php
$readModel = $readModel
    ->withSpecification(new ActiveAndVerifiedSpecification())
    ->withSpecification(new HasOrdersSpecification(), append: true)
    ->withLimit(50);
```

## Inverting a Specification

```php
$spec = (new ActiveAndVerifiedSpecification())->invert();
// Now matches items where active OR email_verified is false
```

## Combining with Query Expressions

A specification can optionally provide a `QueryExpression` to pre-filter at the backend level, reducing the number of items PHP needs to evaluate:

```php
use Kraz\ReadModel\Specification\AbstractSpecification;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\FilterExpression;

class ActiveSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $item): bool
    {
        return $item['active'] === true;
    }

    // This is applied as a SQL/ES filter BEFORE PHP-side evaluation
    protected function buildQueryExpression(): ?QueryExpression
    {
        return QueryExpression::create()
            ->andWhere(FilterExpression::create()->equalTo('active', true));
    }
}
```

When the backend supports it, the query expression is applied server-side and the specification's `isSatisfiedBy()` then acts as a final guard. When used with an in-memory `DataSource`, the expression is also evaluated in PHP.

## Batch Iteration with Specifications

For processing large datasets through specifications, use `specificationsIterator()`:

```php
$specifications = [new ActiveAndVerifiedSpecification()];

foreach ($readModel->specificationsIterator($specifications, limit: 100) as $item) {
    // Fetches in batches, filters in PHP, yields matching items
    process($item);
}
```

## Example — Specification for Domain Rules

```php
// Shared business rule: an order is "processable"
class ProcessableOrderSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $order): bool
    {
        return $order['status'] === 'confirmed'
            && $order['payment_status'] === 'paid'
            && $order['items_count'] > 0;
    }

    protected function buildQueryExpression(): ?QueryExpression
    {
        // Pre-filter at DB level to reduce PHP work
        return QueryExpression::create()->andWhere(
            FilterExpression::create()->andX(
                FilterExpression::create()->equalTo('status', 'confirmed'),
                FilterExpression::create()->equalTo('payment_status', 'paid'),
            )
        );
    }
}

// Usage
$processableOrders = $ordersReadModel
    ->withSpecification(new ProcessableOrderSpecification())
    ->withLimit(500)
    ->data();
```

## Testing Specifications Independently

Because specifications are plain PHP classes, you can unit-test them without any infrastructure:

```php
class ProcessableOrderSpecificationTest extends TestCase
{
    public function testConfirmedPaidOrderWithItemsIsProcessable(): void
    {
        $spec = new ProcessableOrderSpecification();

        $this->assertTrue($spec->isSatisfiedBy([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'items_count' => 3,
        ]));
    }

    public function testUnpaidOrderIsNotProcessable(): void
    {
        $spec = new ProcessableOrderSpecification();

        $this->assertFalse($spec->isSatisfiedBy([
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'items_count' => 3,
        ]));
    }
}
```

## CompositeAndSpecification / CompositeOrSpecification

For cases where you assemble a specification from a dynamic list of sub-specifications:

```php
use Kraz\ReadModel\Specification\CompositeAndSpecification;

$spec = new CompositeAndSpecification();
foreach ($activeFilters as $filter) {
    $spec = $spec->with($filter);  // adds sub-specification
}

$readModel = $readModel->withSpecification($spec)->withLimit(100);
```
