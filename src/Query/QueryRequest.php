<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Query;

use InvalidArgumentException;
use JsonSerializable;
use Kraz\ReadModel\ReadDataProviderInterface;
use Override;
use Stringable;

use function array_filter;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;

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
        // Cursor mode is mutually exclusive with the existing offset/page modes.
        $clone->page         = null;
        $clone->itemsPerPage = null;
        $clone->limit        = null;
        $clone->offset       = null;

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

    /** @phpstan-param QueryRequestComposite|string|null $request */
    public static function create(string|array|null $request = null): self
    {
        if (is_string($request)) {
            /** @phpstan-var QueryExpressionComposite|false|null $data */
            $data = json_decode($request, true);
            if ($data !== null && ! is_array($data)) {
                throw new InvalidArgumentException('Expected null or an array.');
            }

            $request = $data;
        }

        $request ??= [];

        $query = $request['query'] ?? null;
        if ($query !== null) {
            if ($query instanceof QueryExpression) {
                $query = clone $query;
            } else {
                $query = QueryExpression::create($query);
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

        return new self($query, $page, $itemsPerPage, $limit, $offset, $cursor, $cursorLimit);
    }
}
