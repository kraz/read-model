<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Query\Fixtures;

final class PersonFixture
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public int $age = 0,
        public string $first_name = '',
        public string $last_name = '',
        public string|null $tag = null,
    ) {
    }
}
