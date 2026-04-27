<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Query;

use Kraz\ReadModel\Collections\ArrayCollection;
use Kraz\ReadModel\Collections\Criteria;
use Kraz\ReadModel\Collections\Selectable;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryExpressionProvider;
use Kraz\ReadModel\ReadModelDescriptor;
use Kraz\ReadModel\ReadModelDescriptorFactory;
use Kraz\ReadModel\ReadModelDescriptorFactoryInterface;
use Kraz\ReadModel\Tests\Query\Fixtures\FactoryDtoFixture;
use Kraz\ReadModel\Tests\Query\Fixtures\PersonFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryExpressionProvider::class)]
final class QueryExpressionProviderTest extends TestCase
{
    /** @return ArrayCollection<int, PersonFixture> */
    private function people(): ArrayCollection
    {
        return new ArrayCollection([
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
        ]);
    }

    /**
     * @phpstan-param Selectable<array-key, PersonFixture> $items
     *
     * @return list<int>
     */
    private function ids(Selectable $items): array
    {
        $ids = [];
        foreach ($items->matching(Criteria::create()) as $person) {
            $ids[] = $person->id;
        }

        return $ids;
    }

    public function testApplyDelegatesToHelperWithoutDescriptor(): void
    {
        $provider = new QueryExpressionProvider(new ReadModelDescriptorFactory());

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'Alice'));

        $result = $provider->apply($this->people(), $qry);

        self::assertSame([1], $this->ids($result));
    }

    public function testExplicitDescriptorIsForwardedAndFactoryNotInvoked(): void
    {
        $factory = $this->createMock(ReadModelDescriptorFactoryInterface::class);
        $factory->expects(self::never())->method('createReadModelDescriptorFrom');

        $provider   = new QueryExpressionProvider($factory);
        $descriptor = new ReadModelDescriptor([], [], [], ['label' => 'name']);

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('label', 'Alice'));

        $result = $provider->apply($this->people(), $qry, $descriptor);

        self::assertSame([1], $this->ids($result));
    }

    public function testStringClassDescriptorOptionResolvesViaFactory(): void
    {
        $descriptor = new ReadModelDescriptor([], [], [], ['label' => 'name']);

        $factory = $this->createMock(ReadModelDescriptorFactoryInterface::class);
        $factory
            ->expects(self::once())
            ->method('createReadModelDescriptorFrom')
            ->with(PersonFixture::class)
            ->willReturn($descriptor);

        $provider = new QueryExpressionProvider($factory);

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('label', 'Alice'));

        $result = $provider->apply(
            $this->people(),
            $qry,
            null,
            ['read_model_descriptor' => PersonFixture::class],
        );

        self::assertSame([1], $this->ids($result));
    }

    public function testReadModelDescriptorInstanceFromOptionsIsUsedDirectly(): void
    {
        $factory = $this->createMock(ReadModelDescriptorFactoryInterface::class);
        $factory->expects(self::never())->method('createReadModelDescriptorFrom');

        $provider   = new QueryExpressionProvider($factory);
        $descriptor = new ReadModelDescriptor([], [], [], ['label' => 'name']);

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('label', 'Alice'));

        $result = $provider->apply(
            $this->people(),
            $qry,
            null,
            ['read_model_descriptor' => $descriptor],
        );

        self::assertSame([1], $this->ids($result));
    }

    public function testExplicitDescriptorWinsOverOptionString(): void
    {
        $factory = $this->createMock(ReadModelDescriptorFactoryInterface::class);
        $factory->expects(self::never())->method('createReadModelDescriptorFrom');

        $provider = new QueryExpressionProvider($factory);

        $explicit = new ReadModelDescriptor([], [], [], ['label' => 'name']);

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('label', 'Alice'));

        $result = $provider->apply(
            $this->people(),
            $qry,
            $explicit,
            ['read_model_descriptor' => PersonFixture::class],
        );

        self::assertSame([1], $this->ids($result));
    }

    public function testRealFactoryProducesDescriptorFromClassString(): void
    {
        $provider = new QueryExpressionProvider(new ReadModelDescriptorFactory());

        $alice       = new FactoryDtoFixture();
        $alice->name = 'Alice';
        $alice->age  = 30;

        /** @var ArrayCollection<int, FactoryDtoFixture> $data */
        $data = new ArrayCollection([$alice]);

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'Alice'));

        $result = $provider->apply(
            $data,
            $qry,
            null,
            ['read_model_descriptor' => FactoryDtoFixture::class],
        );

        self::assertCount(1, $result->matching(Criteria::create()));
    }

    public function testEmptyQueryReturnsCollectionUnchanged(): void
    {
        $provider = new QueryExpressionProvider(new ReadModelDescriptorFactory());
        $data     = $this->people();

        $result = $provider->apply($data, QueryExpression::create());

        self::assertSame($this->ids($data), $this->ids($result));
    }
}
