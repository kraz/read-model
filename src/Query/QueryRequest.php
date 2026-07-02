<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Query;

use InvalidArgumentException;
use JsonSerializable;
use Kraz\ReadModel\ReadDataProviderInterface;
use Override;
use Stringable;

use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_unshift;
use function base64_decode;
use function base64_encode;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function str_starts_with;

/**
 * @phpstan-import-type QueryExpressionComposite from QueryExpression
 * @phpstan-type QueryRequestComposite = array{
 *      query?: QueryExpression|QueryExpressionComposite|string|null,
 *      page?: int|null,
 *      itemsPerPage?: int|null,
 *      limit?: int<0, max>|null,
 *      offset?: int<0, max>|null,
 *      cursor?: string|null,
 *      cursorLimit?: int<0, max>|null,
 *  }
 */
final class QueryRequest implements JsonSerializable, Stringable
{
    private function __construct(
        private QueryExpression|null $query = null,
        /** @phpstan-var int<0, max>|null */
        private int|null $page = null,
        /** @phpstan-var int<0, max>|null */
        private int|null $itemsPerPage = null,
        /** @phpstan-var int<0, max>|null */
        private int|null $limit = null,
        /** @phpstan-var int<0, max>|null */
        private int|null $offset = null,
        private string|null $cursor = null,
        /** @phpstan-var int<0, max>|null */
        private int|null $cursorLimit = null,
    ) {
        if ($page !== null && $page <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($itemsPerPage !== null && $itemsPerPage <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($limit !== null && $limit <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($offset !== null && $offset < 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($cursor !== null && $cursor === '') {
            throw new InvalidArgumentException('Cursor token cannot be an empty string.');
        }

        if ($cursorLimit !== null && $cursorLimit <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }
    }

    public function getQuery(): QueryExpression|null
    {
        return $this->query;
    }

    /** @phpstan-return int<0, max>|null */
    public function getPage(): int|null
    {
        return $this->page;
    }

    /** @phpstan-return int<0, max>|null */
    public function getItemsPerPage(): int|null
    {
        return $this->itemsPerPage;
    }

    /** @phpstan-return int<0, max>|null */
    public function getLimit(): int|null
    {
        return $this->limit;
    }

    /** @phpstan-return int<0, max>|null */
    public function getOffset(): int|null
    {
        return $this->offset;
    }

    public function getCursor(): string|null
    {
        return $this->cursor;
    }

    /** @phpstan-return int<0, max>|null */
    public function getCursorLimit(): int|null
    {
        return $this->cursorLimit;
    }

    public function isEmpty(): bool
    {
        if ($this->query !== null && ! $this->query->isEmpty()) {
            return false;
        }

        if ($this->page !== null && $this->page !== 0) {
            return false;
        }

        if ($this->itemsPerPage !== null && $this->itemsPerPage !== 0) {
            return false;
        }

        if ($this->limit !== null && $this->limit > 0) {
            return false;
        }

        if ($this->offset !== null && $this->offset >= 0) {
            return false;
        }

        if ($this->cursor !== null && $this->cursor !== '') {
            return false;
        }

        return $this->cursorLimit === null || $this->cursorLimit <= 0;
    }

    /**
     * @phpstan-param ReadDataProviderInterface<T> $dataProvider
     *
     * @return ReadDataProviderInterface<T>
     *
     * @phpstan-template T of object
     */
    public function appendTo(ReadDataProviderInterface $dataProvider): ReadDataProviderInterface
    {
        if ($this->isEmpty()) {
            return $dataProvider;
        }

        return $dataProvider->withQueryRequest($this);
    }

    /** @phpstan-return QueryRequestComposite */
    public function toArray(): array
    {
        return array_filter([
            'query' => $this->query !== null && ! $this->query->isEmpty() ? $this->query->toArray() : null,
            'page' => $this->page !== null && $this->page !== 0 ? $this->page : null,
            'itemsPerPage' => $this->itemsPerPage !== null && $this->itemsPerPage !== 0 ? $this->itemsPerPage : null,
            'limit' => $this->limit !== null && $this->limit > 0 ? $this->limit : null,
            'offset' => $this->offset !== null && $this->offset >= 0 ? $this->offset : null,
            'cursor' => $this->cursor !== null && $this->cursor !== '' ? $this->cursor : null,
            'cursorLimit' => $this->cursorLimit !== null && $this->cursorLimit > 0 ? $this->cursorLimit : null,
        ], static fn (mixed $v): bool => $v !== null && $v !== []);
    }

    public function __toString(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $items = $this->toArray();
        if (count($items) === 0) {
            return '';
        }

        $json = json_encode($items);

        return $json === false ? '' : $json;
    }

    /** @phpstan-return QueryRequestComposite|null */
    #[Override]
    public function jsonSerialize(): array|null
    {
        $items = $this->toArray();

        return count($items) > 0 ? $items : null;
    }

    public function encode(): string
    {
        $json = json_encode($this);
        if ($json === false) {
            throw new InvalidArgumentException('Can not encode the query request. The payload is invalid!');
        }

        return base64_encode($json);
    }

    public function withQueryExpression(QueryExpression $queryExpression): self
    {
        $clone        = clone $this;
        $clone->query = $queryExpression;

        return $clone;
    }

    public function withoutQueryExpression(): self
    {
        $clone        = clone $this;
        $clone->query = null;

        return $clone;
    }

    /**
     * @phpstan-param int<0, max> $page
     * @phpstan-param int<0, max> $itemsPerPage
     */
    public function withPagination(int $page, int $itemsPerPage): self
    {
        if ($page <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($itemsPerPage <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        $clone               = clone $this;
        $clone->page         = $page;
        $clone->itemsPerPage = $itemsPerPage;
        // Limit/offset mode is mutually exclusive with limit/offset mode.
        $clone->limit  = null;
        $clone->offset = null;
        // Page-based pagination is mutually exclusive with cursor mode.
        $clone->cursor      = null;
        $clone->cursorLimit = null;

        return $clone;
    }

    public function withoutPagination(): self
    {
        $clone               = clone $this;
        $clone->page         = null;
        $clone->itemsPerPage = null;

        return $clone;
    }

    /**
     * @phpstan-param int<0, max> $limit
     * @phpstan-param int<0, max>|null $offset
     */
    public function withLimit(int $limit, int|null $offset = null): self
    {
        if ($limit <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($offset !== null && $offset < 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        $clone         = clone $this;
        $clone->limit  = $limit;
        $clone->offset = $offset;
        // Limit/offset mode is mutually exclusive with pagination mode.
        $clone->page         = null;
        $clone->itemsPerPage = null;
        // Limit/offset mode is mutually exclusive with cursor mode.
        $clone->cursor      = null;
        $clone->cursorLimit = null;

        return $clone;
    }

    public function withoutLimit(): self
    {
        $clone         = clone $this;
        $clone->limit  = null;
        $clone->offset = null;

        return $clone;
    }

    /** @phpstan-param int<0, max> $limit */
    public function withCursor(string|null $cursor, int $limit): self
    {
        if ($cursor === '') {
            throw new InvalidArgumentException('Cursor token cannot be an empty string. Pass null for the first page.');
        }

        if ($limit <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        $clone              = clone $this;
        $clone->cursor      = $cursor;
        $clone->cursorLimit = $limit;
        // Cursor mode is mutually exclusive with the existing pagination modes.
        $clone->page         = null;
        $clone->itemsPerPage = null;
        // Cursor mode is mutually exclusive with the existing offset/page modes.
        $clone->limit  = null;
        $clone->offset = null;

        return $clone;
    }

    public function withoutCursor(): self
    {
        $clone              = clone $this;
        $clone->cursor      = null;
        $clone->cursorLimit = null;

        return $clone;
    }

    /** @phpstan-return QueryRequestComposite */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * @phpstan-param array{
     *     query?: QueryExpressionComposite|null,
     *     page?: int<0, max>|null,
     *     itemsPerPage?: int<0, max>|null,
     *     limit?: int<0, max>|null,
     *     offset?: int<0, max>|null,
     *     cursor?: string|null,
     *     cursorLimit?: int<0, max>|null,
     * } $data
     */
    public function __unserialize(array $data): void
    {
        $query       = $data['query'] ?? null;
        $this->query = $query !== null ? QueryExpression::create($query) : null;

        $page         = $data['page'] ?? null;
        $itemsPerPage = $data['itemsPerPage'] ?? null;

        $limit  = $data['limit'] ?? null;
        $offset = $data['offset'] ?? null;

        $cursor      = $data['cursor'] ?? null;
        $cursorLimit = $data['cursorLimit'] ?? null;

        if ($page !== null && $page <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($itemsPerPage !== null && $itemsPerPage <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($limit !== null && $limit <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($offset !== null && $offset < 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($cursor !== null && $cursor === '') {
            throw new InvalidArgumentException('Cursor token cannot be an empty string.');
        }

        if ($cursorLimit !== null && $cursorLimit <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        $this->page         = $page;
        $this->itemsPerPage = $itemsPerPage;
        $this->limit        = $limit;
        $this->offset       = $offset;
        $this->cursor       = $cursor;
        $this->cursorLimit  = $cursorLimit;
    }

    public function __clone()
    {
        if ($this->query === null) {
            return;
        }

        $this->query = clone $this->query;
    }

    public static function decode(string $request): self
    {
        if ($request === '') {
            throw new InvalidArgumentException('The query decode expression is empty!');
        }

        $exprJson = base64_decode($request, true);
        if ($exprJson === false) {
            throw new InvalidArgumentException('Invalid query decode expression!');
        }

        if ($exprJson === '') {
            throw new InvalidArgumentException('The decoded query expression is invalid!');
        }

        return self::create($exprJson);
    }

    /** @phpstan-param QueryRequestComposite|string|null $request */
    public static function create(string|array|null $request = null): self
    {
        if (is_string($request)) {
            if (! str_starts_with($request, '{')) {
                $decoded = base64_decode($request, true);
                if ($decoded !== false) {
                    $request = $decoded;
                }
            }

            /** @phpstan-var QueryExpressionComposite|false|null $data */
            $data = json_decode($request, true);
            if ($data !== null && ! is_array($data)) {
                throw new InvalidArgumentException('Invalid query request. Expected null or an array.');
            }

            $request = $data;
        }

        $request ??= [];

        $query = $request['query'] ?? null;
        if ($query !== null) {
            if ($query instanceof QueryExpression) {
                $query = clone $query;
            } else {
                $query = QueryExpression::try($query);
                if ($query === null) {
                    throw new InvalidArgumentException('Invalid query expression parameter!');
                }
            }
        }

        /** @phpstan-var int<0, max>|null $page */
        $page = $request['page'] ?? null;
        if ($page !== null && ! is_int($page)) {
            throw new InvalidArgumentException('Expected null or an integer.');
        }

        /** @phpstan-var int<0, max>|null $itemsPerPage */
        $itemsPerPage = $request['itemsPerPage'] ?? $request['pageSize'] ?? null;
        if ($itemsPerPage !== null && ! is_int($itemsPerPage)) {
            throw new InvalidArgumentException('Expected null or an integer.');
        }

        /** @phpstan-var int<0, max>|null $limit */
        $limit = $request['limit'] ?? null;
        if ($limit !== null && ! is_int($limit)) {
            throw new InvalidArgumentException('Expected null or an integer.');
        }

        /** @phpstan-var int<0, max>|null $offset */
        $offset = $request['offset'] ?? null;
        if ($offset !== null && ! is_int($offset)) {
            throw new InvalidArgumentException('Expected null or an integer.');
        }

        /** @phpstan-var string|null $cursor */
        $cursor = $request['cursor'] ?? null;
        if ($cursor !== null && ! is_string($cursor)) {
            throw new InvalidArgumentException('Expected null or a string.');
        }

        /** @phpstan-var int<0, max>|null $cursorLimit */
        $cursorLimit = $request['cursorLimit'] ?? null;
        if ($cursorLimit !== null && ! is_int($cursorLimit)) {
            throw new InvalidArgumentException('Expected null or an integer.');
        }

        if (array_key_exists('cursor', $request) || array_key_exists('cursorLimit', $request)) {
            $cursorLimit = $cursorLimit ?? $limit ?? 0;
        }

        return new self($query, $page, $itemsPerPage, $limit, $offset, $cursor, $cursorLimit);
    }

    /**
     * @phpstan-param array<string, mixed> $input
     * @phpstan-param string[] $fields
     * @phpstan-param array<string, string> $fieldsOperator
     * @phpstan-param array<string, bool> $fieldsIgnoreCase
     */
    public static function assemble(array $input, array $fields = [], array $fieldsOperator = [], array $fieldsIgnoreCase = []): self
    {
        // Load query

        $query     = null;
        $queryBase = $input['query'] ?? null;
        if ($queryBase !== null) {
            $query = QueryExpression::try($queryBase);
            if ($query === null) {
                throw new InvalidArgumentException('Invalid query expression parameter!');
            }
        }

        // Load filters

        $filters = [];
        foreach ($fields as $field) {
            $value = $input[$field] ?? null;
            if ($value === null) {
                continue;
            }

            $operator   = $fieldsOperator[$field] ?? FilterExpression::OP_EQ;
            $ignoreCase = $fieldsIgnoreCase[$field] ?? true;
            $filters[]  = FilterExpression::create()->valX($field, $operator, $value, $ignoreCase);
        }

        // Load values

        $value  = $input['value'] ?? null;
        $values = $input['values'] ?? [];
        $values = is_array($values) ? $values : [];
        if ($value !== null) {
            array_unshift($values, $value);
        }

        if (count($values) > 0) {
            $query ??= QueryExpression::create();
            /** @phpstan-ignore argument.type */
            $query = $query->withValues($values);
        }

        // Load order by

        $sort = $input['order'] ?? [];
        $sort = is_array($sort) ? $sort : [];
        $sort = array_intersect_key($sort, array_flip($fields));

        // Load pagination

        $page     = (int) ($input['page'] ?? 0);
        $pageSize = (int) ($input['pageSize'] ?? $input['itemsPerPage'] ?? 0);

        // Load limit and offset

        $limit  = $input['limit'] ?? null;
        $limit  = $limit !== null ? (int) $limit : null;
        $offset = ($input['offset'] ?? null);
        $offset = $offset !== null ? (int) $offset : null;

        // Load cursor

        $cursor      = null;
        $cursorLimit = 0;
        if (array_key_exists('cursor', $input) || array_key_exists('cursorLimit', $input)) {
            $cursor      = $input['cursor'] ?? null;
            $cursorLimit = (int) ($input['cursorLimit'] ?? $limit ?? 0);
        }

        // Apply

        if (count($filters) > 0) {
            $query ??= QueryExpression::create();
            $query   = $query->andWhere(...$filters);
        }

        foreach ($sort as $field => $dir) {
            $query ??= QueryExpression::create();
            $query   = $query->sortBy($field, $dir);
        }

        $request = self::create();

        if ($page > 0 && $pageSize > 0) {
            $request = $request->withPagination($page, $pageSize);
        } else {
            $request = $request->withoutPagination();
        }

        if ($limit !== null && $limit > 0 && ($offset === null || $offset >= 0)) {
            $request = $request->withLimit($limit, $offset);
        } else {
            $request = $request->withoutLimit();
        }

        if ($cursorLimit > 0) {
            $request = $request->withCursor($cursor, $cursorLimit);
        } else {
            $request = $request->withoutCursor();
        }

        return $query !== null ? $request->withQueryExpression($query) : $request;
    }
}
