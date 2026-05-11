# Pagination & Limits

## Page-Based Pagination

Enable pagination by calling `withPagination($page, $itemsPerPage)`:

```php
$readModel = $readModel->withPagination(page: 1, itemsPerPage: 20);

// Check if pagination is active
$readModel->isPaginated(); // true

// Get the current page's items
$items = $readModel->data();

// Get a paginator object with metadata
$paginator = $readModel->paginator();
$paginator->getCurrentPage();   // 1
$paginator->getItemsPerPage();  // 20
$paginator->getTotalItems();    // e.g. 57
$paginator->getLastPage();      // e.g. 3 (ceil(57 / 20))
$paginator->count();            // items on this page (up to 20)
```

The paginator also implements `IteratorAggregate`, so you can iterate over it directly:

```php
foreach ($readModel->paginator() as $item) {
    // ...
}
```

## ReadResponse

When paginated, `getResult()` returns a `ReadResponse` instead of a plain array. `ReadResponse` bundles data and pagination metadata together:

```php
$result = $readModel->withPagination(1, 20)->getResult();

$result->data;   // array of items on this page
$result->page;   // current page number
$result->total;  // total item count

// Encode to JSON directly
return new JsonResponse($result);
// {"data": [...], "page": 1, "total": 57}
```

## Limit / Offset

For non-page-based slicing (e.g., "give me 10 items starting from item 30"):

```php
$readModel = $readModel->withLimit(limit: 10, offset: 30);

$readModel->data();        // items 31–40
$readModel->count();       // up to 10
$readModel->totalCount();  // total matching rows (ignores limit)
```

Remove the limit:
```php
$readModel = $readModel->withoutLimit();
```

## Disabling Pagination

```php
$readModel = $readModel->withPagination(1, 20);
// Later...
$readModel = $readModel->withoutPagination();
$readModel->isPaginated(); // false
```

By default `withoutPagination()` clears pagination and discards all history. Pass `undo: true` to step back one level instead:

```php
$readModel = $readModel
    ->withPagination(1, 20)         // state A
    ->withPagination(2, 20)         // state B
    ->withoutPagination(undo: true) // back to state A (page 1)
    ->withoutPagination(undo: true) // back to no pagination
    ->withoutPagination(undo: true) // already empty — stays empty
```

The same default-clear / `undo: true` pattern applies to `withoutLimit()`, `withoutQueryExpression()`, and `withoutSpecification()`.

## Pagination in Controllers

```php
// Symfony controller
public function list(Request $request, InvoicesReadModel $readModel): JsonResponse
{
    $page         = (int) $request->query->get('page', 1);
    $itemsPerPage = (int) $request->query->get('per_page', 20);

    $result = $readModel
        ->withPagination($page, $itemsPerPage)
        ->getResult();

    return new JsonResponse($result);
}
```

Or let `handleRequest()` do it automatically (it parses `page` and `pageSize` query parameters):

```php
public function list(Request $request, InvoicesReadModel $readModel): JsonResponse
{
    return new JsonResponse(
        $readModel->handleRequest($request)->getResult()
    );
}
```

## Checking the Total Count Separately

```php
$readModel = $readModel->withPagination(1, 20);

$items = $readModel->data();           // items on page 1
$total = $readModel->totalCount();     // total regardless of page
$thisPage = $readModel->count();       // items on this page (≤ 20)
```

## Iterating Without Loading Everything

For large datasets, use `getIterator()` instead of `data()`:

```php
foreach ($readModel as $item) {
    // Processed one at a time, no full array in memory
    process($item);
}
```

When a limit is set, iteration stops automatically after that many items.
