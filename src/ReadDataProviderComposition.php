<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use InvalidArgumentException;
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryExpressionProvider;
use Kraz\ReadModel\Query\QueryExpressionProviderInterface;
use Kraz\ReadModel\Query\QueryRequest;
use Kraz\ReadModel\Specification\SpecificationInterface;
use LogicException;
use Override;
use ReflectionClassConstant;
use ReflectionObject;

use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_pop;
use function array_unique;
use function array_unshift;
use function array_values;
use function count;
use function is_array;
use function str_starts_with;

use const ARRAY_FILTER_USE_KEY;

/**
 * Provides basic behavior for composing instance of ReadDataProviderInterface.
 *
 * The methods used in this trait are free of side effects, i.e. only returns current state or modifying it
 * and returning a new instance with the changed state while keeping the previous one immutable.
 *
 * @phpstan-template-covariant T of object|array<string, mixed>
 */
trait ReadDataProviderComposition
{
    private ReadModelDescriptor|null $descriptor = null;

    /** @phpstan-var array{int<0, max>, int<0, max>}|null */
    private array|null $pagination = null;
    /** @phpstan-var array<int, array{int<0, max>, int<0, max>}|null> */
    private array $paginationHistory = [];

    /** @phpstan-var array{int<0, max>, int<0, max>|null}|null */
    private array|null $limit = null;
    /** @phpstan-var array<int, array{int<0, max>, int<0, max>|null}|null> */
    private array $limitHistory = [];

    /** @phpstan-var array{string|null, int<1, max>}|null */
    private array|null $cursor = null;
    /** @phpstan-var array<int, array{string|null, int<1, max>}|null> */
    private array $cursorHistory = [];

    /** @phpstan-var QueryExpression[] */
    private array $queryExpressions = [];
    /** @phpstan-var array<int, QueryExpression[]> */
    private array $queryExpressionsHistory = [];

    /** @phpstan-var callable[] */
    private array $queryModifiers = [];
    /** @phpstan-var array<int, callable[]> */
    private array $queryModifiersHistory = [];

    /** @phpstan-var SpecificationInterface<contravariant T>[] */
    private array $specifications = [];
    /** @phpstan-var array<int, SpecificationInterface<contravariant T>[]> */
    private array $specificationsHistory = [];

    private ReadModelDescriptor|null $readModelDescriptor = null;
    /** @phpstan-var array<int, ReadModelDescriptor|null> */
    private array $readModelDescriptorHistory = [];

    private QueryExpressionProviderInterface|null $queryExpressionProviderDefault = null;
    private QueryExpressionProviderInterface|null $queryExpressionProvider        = null;
    /** @phpstan-var array<int, QueryExpressionProviderInterface|null> */
    private array $queryExpressionProviderHistory = [];

    private ReadModelDescriptorFactoryInterface|null $descriptorFactoryDefault = null;
    private ReadModelDescriptorFactoryInterface|null $descriptorFactory        = null;
    /** @phpstan-var array<int, ReadModelDescriptorFactoryInterface|null> */
    private array $descriptorFactoryHistory = [];

    /** @phpstan-var callable|null */
    private mixed $itemNormalizer = null;
    /** @phpstan-var array<int, callable|null> */
    private array $itemNormalizerHistory = [];

    protected function createDefaultDescriptorFactory(): ReadModelDescriptorFactoryInterface
    {
        return new ReadModelDescriptorFactory();
    }

    protected function createDefaultQueryExpressionProvider(ReadModelDescriptorFactoryInterface $factory): QueryExpressionProviderInterface
    {
        return new QueryExpressionProvider($factory);
    }

    private function getOrCreateDescriptorFactory(): ReadModelDescriptorFactoryInterface
    {
        return $this->descriptorFactory ?? ($this->descriptorFactoryDefault ??= $this->createDefaultDescriptorFactory());
    }

    private function getOrCreateQueryExpressionProvider(): QueryExpressionProviderInterface
    {
        return $this->queryExpressionProvider ?? ($this->queryExpressionProviderDefault ??= $this->createDefaultQueryExpressionProvider($this->getOrCreateDescriptorFactory()));
    }

    /** @phpstan-return list<int|string> */
    private function collectInputValues(): array
    {
        $specQEs = [];
        foreach ($this->specifications as $specification) {
            $qe = $specification->getQueryExpression();
            if ($qe === null || $qe->isEmpty()) {
                continue;
            }

            $specQEs[] = $qe;
        }

        $allQEs = [...$specQEs, ...$this->queryExpressions];
        $values = [];
        foreach ($allQEs as $queryExpression) {
            $values = [...$values, ...($queryExpression->getValues() ?? [])];
        }

        return array_values(array_unique($values));
    }

    #[Override]
    final public function qry(): QueryExpression
    {
        return QueryExpression::create();
    }

    #[Override]
    final public function expr(): FilterExpression
    {
        return FilterExpression::create();
    }

    #[Override]
    final public function queryExpressions(): array
    {
        return $this->queryExpressions;
    }

    #[Override]
    final public function isValue(): bool
    {
        return count($this->collectInputValues()) > 0;
    }

    #[Override]
    public function withPagination(int $page, int $itemsPerPage): static
    {
        if (count($this->specifications) > 0) {
            throw new LogicException('Cannot use pagination when specifications are set.');
        }

        if ($page <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($itemsPerPage <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        /** @phpstan-var static<T> $cloned */
        $cloned                      = clone $this;
        $cloned->paginationHistory[] = $cloned->pagination;
        $cloned->pagination          = [$page, $itemsPerPage];
        $cloned->limit               = null;
        $cloned->limitHistory        = [];
        $cloned->cursor              = null;
        $cloned->cursorHistory       = [];

        return $cloned;
    }

    #[Override]
    public function withoutPagination(bool $undo = false): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($undo) {
            $cloned->pagination = count($cloned->paginationHistory) > 0
                ? array_pop($cloned->paginationHistory)
                : null;
        } else {
            $cloned->pagination        = null;
            $cloned->paginationHistory = [];
        }

        return $cloned;
    }

    #[Override]
    public function withLimit(int $limit, int|null $offset = null): static
    {
        if ($limit <= 0) {
            throw new InvalidArgumentException('Expected a positive integer for limit.');
        }

        if ($offset !== null && $offset < 0) {
            throw new InvalidArgumentException('Expected a non-negative integer for offset.');
        }

        /** @phpstan-var static<T> $cloned */
        $cloned                    = clone $this;
        $cloned->limitHistory[]    = $cloned->limit;
        $cloned->limit             = [$limit, $offset];
        $cloned->pagination        = null;
        $cloned->paginationHistory = [];
        $cloned->cursor            = null;
        $cloned->cursorHistory     = [];

        return $cloned;
    }

    #[Override]
    public function withoutLimit(bool $undo = false): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($undo) {
            $cloned->limit = count($cloned->limitHistory) > 0
                ? array_pop($cloned->limitHistory)
                : null;
        } else {
            $cloned->limit        = null;
            $cloned->limitHistory = [];
        }

        return $cloned;
    }

    #[Override]
    public function withCursor(string|null $cursor, int $limit): static
    {
        if (count($this->specifications) > 0) {
            // specificationsIterator() uses limit/offset internally and would corrupt
            // a cursor's window invariant by post-filtering rows; this mirrors the
            // existing withPagination() guard.
            throw new LogicException('Cannot use cursor pagination when specifications are set.');
        }

        if ($cursor === '') {
            throw new InvalidArgumentException('Cursor token cannot be an empty string. Pass null for the first page.');
        }

        if ($limit <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        /** @phpstan-var static<T> $cloned */
        $cloned                    = clone $this;
        $cloned->cursorHistory[]   = $cloned->cursor;
        $cloned->cursor            = [$cursor, $limit];
        $cloned->pagination        = null;
        $cloned->paginationHistory = [];
        $cloned->limit             = null;
        $cloned->limitHistory      = [];

        return $cloned;
    }

    #[Override]
    public function withoutCursor(bool $undo = false): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($undo) {
            $cloned->cursor = count($cloned->cursorHistory) > 0
                ? array_pop($cloned->cursorHistory)
                : null;
        } else {
            $cloned->cursor        = null;
            $cloned->cursorHistory = [];
        }

        return $cloned;
    }

    #[Override]
    public function withQueryExpression(QueryExpression $queryExpression, bool $append = false): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned                            = clone $this;
        $cloned->queryExpressionsHistory[] = $cloned->queryExpressions;
        $cloned->queryExpressions          = $append
            ? [...$cloned->queryExpressions, $queryExpression]
            : [$queryExpression];

        return $cloned;
    }

    #[Override]
    public function withoutQueryExpression(bool $undo = false): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($undo) {
            $cloned->queryExpressions = count($cloned->queryExpressionsHistory) > 0
                ? array_pop($cloned->queryExpressionsHistory)
                : [];
        } else {
            $cloned->queryExpressions        = [];
            $cloned->queryExpressionsHistory = [];
        }

        return $cloned;
    }

    #[Override]
    public function withQueryModifier(callable $modifier, bool $append = false): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned                          = clone $this;
        $cloned->queryModifiersHistory[] = $cloned->queryModifiers;
        $cloned->queryModifiers          = $append
            ? [...$cloned->queryModifiers, $modifier]
            : [$modifier];

        return $cloned;
    }

    #[Override]
    public function withoutQueryModifier(bool $undo = false): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($undo) {
            $cloned->queryModifiers = count($cloned->queryModifiersHistory) > 0
                ? array_pop($cloned->queryModifiersHistory)
                : [];
        } else {
            $cloned->queryModifiers        = [];
            $cloned->queryModifiersHistory = [];
        }

        return $cloned;
    }

    #[Override]
    final public function specifications(): array
    {
        /** @phpstan-var SpecificationInterface<covariant T>[] $specs */
        $specs = $this->specifications;

        return $specs;
    }

    #[Override]
    public function withSpecification(SpecificationInterface $specification, bool $append = false): static
    {
        if ($this->pagination !== null) {
            throw new LogicException('Cannot use specifications when pagination is set.');
        }

        if ($this->cursor !== null) {
            throw new LogicException('Cannot use specifications when cursor pagination is set.');
        }

        /** @phpstan-var static<T> $cloned */
        $cloned                          = clone $this;
        $cloned->specificationsHistory[] = $cloned->specifications;
        $cloned->specifications          = $append
            ? [...$cloned->specifications, $specification]
            : [$specification];

        return $cloned;
    }

    #[Override]
    public function withoutSpecification(bool $undo = false): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($undo) {
            $cloned->specifications = count($cloned->specificationsHistory) > 0
                ? array_pop($cloned->specificationsHistory)
                : [];
        } else {
            $cloned->specifications        = [];
            $cloned->specificationsHistory = [];
        }

        return $cloned;
    }

    #[Override]
    public function withReadModelDescriptor(ReadModelDescriptor $readModelDescriptor): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned                               = clone $this;
        $cloned->readModelDescriptorHistory[] = $cloned->readModelDescriptor;
        $cloned->readModelDescriptor          = $readModelDescriptor;

        return $cloned;
    }

    #[Override]
    public function withoutReadModelDescriptor(bool $undo = false): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($undo) {
            $cloned->readModelDescriptor = count($cloned->readModelDescriptorHistory) > 0
                ? array_pop($cloned->readModelDescriptorHistory)
                : null;
        } else {
            $cloned->readModelDescriptor        = null;
            $cloned->readModelDescriptorHistory = [];
        }

        return $cloned;
    }

    /**
     * @phpstan-param object|class-string $model
     *
     * @phpstan-return static<T>
     */
    #[Override]
    public function withReadModel(object|string $model): static
    {
        return $this->withReadModelDescriptor($this->getOrCreateDescriptorFactory()->createReadModelDescriptorFrom($model));
    }

    #[Override]
    public function withQueryExpressionProvider(QueryExpressionProviderInterface $queryExpressionProvider): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned                                   = clone $this;
        $cloned->queryExpressionProviderHistory[] = $cloned->queryExpressionProvider;
        $cloned->queryExpressionProvider          = $queryExpressionProvider;

        return $cloned;
    }

    #[Override]
    public function withoutQueryExpressionProvider(bool $undo = false): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($undo) {
            $cloned->queryExpressionProvider = count($cloned->queryExpressionProviderHistory) > 0
                ? array_pop($cloned->queryExpressionProviderHistory)
                : null;
        } else {
            $cloned->queryExpressionProvider        = null;
            $cloned->queryExpressionProviderHistory = [];
        }

        return $cloned;
    }

    #[Override]
    public function withDescriptorFactory(ReadModelDescriptorFactoryInterface $descriptorFactory): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned                             = clone $this;
        $cloned->descriptorFactoryHistory[] = $cloned->descriptorFactory;
        $cloned->descriptorFactory          = $descriptorFactory;

        return $cloned;
    }

    #[Override]
    public function withoutDescriptorFactory(bool $undo = false): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($undo) {
            $cloned->descriptorFactory = count($cloned->descriptorFactoryHistory) > 0
                ? array_pop($cloned->descriptorFactoryHistory)
                : null;
        } else {
            $cloned->descriptorFactory        = null;
            $cloned->descriptorFactoryHistory = [];
        }

        return $cloned;
    }

    #[Override]
    public function withItemNormalizer(callable $itemNormalizer): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned                          = clone $this;
        $cloned->itemNormalizerHistory[] = $cloned->itemNormalizer;
        $cloned->itemNormalizer          = $itemNormalizer;

        return $cloned;
    }

    #[Override]
    public function withoutItemNormalizer(bool $undo = false): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($undo) {
            $cloned->itemNormalizer = count($cloned->itemNormalizerHistory) > 0
                ? array_pop($cloned->itemNormalizerHistory)
                : null;
        } else {
            $cloned->itemNormalizer        = null;
            $cloned->itemNormalizerHistory = [];
        }

        return $cloned;
    }

    #[Override]
    public function withQueryRequest(QueryRequest $queryRequest): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;
        if ($queryRequest->getQuery() !== null) {
            $cloned = $cloned->withQueryExpression($queryRequest->getQuery());
        }

        if ($queryRequest->getPage() !== null && $queryRequest->getItemsPerPage() !== null) {
            $cloned = $cloned->withPagination($queryRequest->getPage(), $queryRequest->getItemsPerPage());
        }

        if ($queryRequest->getLimit() !== null) {
            $cloned = $cloned->withLimit($queryRequest->getLimit(), $queryRequest->getOffset());
        }

        $cursorLimit = $queryRequest->getCursorLimit();
        if ($cursorLimit !== null) {
            $cloned = $cloned->withCursor($queryRequest->getCursor(), $cursorLimit);
        }

        return $cloned;
    }

    #[Override]
    public function handleInput(array $input, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static
    {
        /** @phpstan-var static<T> $dataSource */
        $dataSource = static::applyInputTo($this, $input, $fieldsOperator, $fieldsIgnoreCase);

        return $dataSource;
    }

    /**
     * @phpstan-param J $target
     * @phpstan-param array<string, mixed>  $input
     * @phpstan-param array<string, string> $fieldsOperator
     * @phpstan-param array<string, bool> $fieldsIgnoreCase
     *
     * @phpstan-return J
     *
     * @phpstan-template J of ReadDataProviderCompositionInterface<object|array<string, mixed>>
     */
    public static function applyInputTo(ReadDataProviderCompositionInterface $target, array $input, array $fieldsOperator = [], array $fieldsIgnoreCase = []): ReadDataProviderCompositionInterface
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
        $ref     = new ReflectionObject($target);
        $fields  = $ref->getConstants(ReflectionClassConstant::IS_PUBLIC);
        $fields  = array_filter($fields, static fn ($c) => str_starts_with($c, 'FIELD_'), ARRAY_FILTER_USE_KEY);
        $fields  = array_values($fields);
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
            $cursorLimit = $input['cursorLimit'] ?? $limit;
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

        /** @phpstan-var J $clone */
        $clone = clone $target;

        if ($page > 0 && $pageSize > 0) {
            $clone = $clone->withPagination($page, $pageSize);
        } else {
            $clone = $clone->withoutPagination();
        }

        if ($limit !== null && $limit > 0 && ($offset === null || $offset >= 0)) {
            $clone = $clone->withLimit($limit, $offset);
        } else {
            $clone = $clone->withoutLimit();
        }

        if ($cursorLimit > 0) {
            $clone = $clone->withCursor($cursor, $cursorLimit);
        } else {
            $clone = $clone->withoutCursor();
        }

        /** @phpstan-var J $result */
        $result = $query !== null ? $clone->withQueryExpression($query) : $clone;

        return $result;
    }
}
