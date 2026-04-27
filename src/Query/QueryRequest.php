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
 *      itemsPerPage?: int|null
 *  }
 */
final class QueryRequest implements JsonSerializable, Stringable
{
    private function __construct(
        private QueryExpression|null $query = null,
        private int|null $page = null,
        private int|null $itemsPerPage = null,
    ) {
    }

    public function getQuery(): QueryExpression|null
    {
        return $this->query;
    }

    public function getPage(): int|null
    {
        return $this->page;
    }

    public function getItemsPerPage(): int|null
    {
        return $this->itemsPerPage;
    }

    public function isEmpty(): bool
    {
        if ($this->query !== null && ! $this->query->isEmpty()) {
            return false;
        }

        if ($this->page !== null && $this->page !== 0) {
            return false;
        }

        return $this->itemsPerPage === null || $this->itemsPerPage === 0;
    }

    /**
     * @phpstan-param ReadDataProviderInterface<T> $dataProvider
     *
     * @return ReadDataProviderInterface<T>
     *
     * @template T of object
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
        ]);
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

    public function withPagination(int $page, int $itemsPerPage): self
    {
        $clone               = clone $this;
        $clone->page         = $page;
        $clone->itemsPerPage = $itemsPerPage;

        return $clone;
    }

    public function withoutPagination(): self
    {
        $clone               = clone $this;
        $clone->page         = null;
        $clone->itemsPerPage = null;

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
     *     page?: int|null,
     *     itemsPerPage?: int|null
     * } $data
     */
    public function __unserialize(array $data): void
    {
        $query              = $data['query'] ?? null;
        $this->query        = $query !== null ? QueryExpression::create($query) : null;
        $this->page         = $data['page'] ?? null;
        $this->itemsPerPage = $data['itemsPerPage'] ?? null;
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

        $page = $request['page'] ?? null;
        if ($page !== null && ! is_int($page)) {
            throw new InvalidArgumentException('Expected null or an integer.');
        }

        $itemsPerPage = $request['itemsPerPage'] ?? $request['pageSize'] ?? null;
        if ($itemsPerPage !== null && ! is_int($itemsPerPage)) {
            throw new InvalidArgumentException('Expected null or an integer.');
        }

        return new self($query, $page, $itemsPerPage);
    }
}
