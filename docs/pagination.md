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

## Cursor-Based (Keyset) Pagination

Cursor pagination avoids the offset-drift problem of page-based pagination and scales efficiently on large, frequently-changing datasets. Instead of `page` + `itemsPerPage`, the client echoes back an opaque token that anchors the next or previous window.

### Basic usage

```php
// First page — pass null for the cursor
$result = $readModel->withCursor(cursor: null, limit: 20)->getResult();

// $result is a CursorReadResponse
$result->data;           // items on this window (up to 20)
$result->nextCursor;     // opaque token — pass to next call, or null at end
$result->previousCursor; // opaque token — pass to go back, or null at start
$result->hasNext;        // bool
$result->hasPrevious;    // bool
$result->totalItems;     // int|null — null when the adapter did not compute a total

// Next page
$result2 = $readModel->withCursor(cursor: $result->nextCursor, limit: 20)->getResult();

// Previous page
$result0 = $readModel->withCursor(cursor: $result->previousCursor, limit: 20)->getResult();
```

`getResult()` returns a `CursorReadResponse` in cursor mode (instead of the usual `ReadResponse`). Both implement `ArrayAccess` so they serialise directly to JSON.

### Checking cursor mode

```php
$readModel->isCursored(); // true when withCursor() is active
```

### The cursor paginator

`cursorPaginator()` returns a `CursorPaginatorInterface` object with all the metadata:

```php
$paginator = $readModel->withCursor(null, 20)->cursorPaginator();

$paginator->getLimit();          // 20
$paginator->getDirection();      // Direction::FORWARD or Direction::BACKWARD
$paginator->hasNext();           // bool
$paginator->hasPrevious();       // bool
$paginator->getNextCursor();     // string|null
$paginator->getPreviousCursor(); // string|null
$paginator->getTotalItems();     // int|null
$paginator->count();             // items in the current window (NOT total)

foreach ($paginator as $item) { /* ... */ }
```

### Disabling cursor pagination

```php
$readModel = $readModel->withoutCursor(); // clear completely

// Or step back one level (mirrors withoutPagination undo: true)
$readModel = $readModel->withoutCursor(undo: true);
```

### Mutual exclusivity

Cursor mode and offset/page-based pagination are mutually exclusive. Calling `withCursor()` clears any active page or limit/offset state, and vice versa. Cursor mode also cannot be combined with specifications — the same restriction that applies to page-based pagination.

### Cursor pagination in controllers

```php
// Symfony controller
public function list(Request $request, InvoicesReadModel $readModel): JsonResponse
{
    $cursor = $request->query->get('cursor') ?: null;
    $limit  = (int) $request->query->get('limit', 20);

    $result = $readModel->withCursor($cursor, $limit)->getResult();

    return new JsonResponse($result);
    // {"data":[...],"nextCursor":"...","previousCursor":null,"hasNext":true,"hasPrevious":false,"totalItems":null}
}
```

### Cursor security: signed tokens

By default the cursor token is URL-safe base64-encoded JSON (`Base64JsonCursorCodec`). It is opaque to end users but not tamper-proof. Wrap it with `SignedCursorCodec` to add HMAC integrity protection:

```php
use Kraz\ReadModel\Pagination\Cursor\Base64JsonCursorCodec;
use Kraz\ReadModel\Pagination\Cursor\SignedCursorCodec;

$codec = new SignedCursorCodec(
    inner: new Base64JsonCursorCodec(),
    secret: $appSecret,      // keep this out of version control
    algo: 'sha256',          // optional, sha256 is the default
);

$dataSource = new DataSource($data, cursorCodec: $codec);
```

Any cursor whose HMAC does not validate — whether tampered or simply issued by a different key — throws `InvalidCursorException`.

### Using QueryRequest

Cursor pagination integrates with the `QueryRequest` value object used by `withQueryRequest()`:

```php
$queryRequest = QueryRequest::create()->withCursor(cursor: $token, limit: 20);

$readModel = $readModel->withQueryRequest($queryRequest);
// equivalent to: $readModel->withCursor($token, 20)
```
