<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Query\Fixtures;

final class WarehouseNodeFixture
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public string $warehouseId = '',
        public int|null $parentId = null,
        public bool $active = true,
    ) {
    }
}
