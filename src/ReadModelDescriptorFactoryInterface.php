<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

interface ReadModelDescriptorFactoryInterface
{
    /** @phpstan-param object|class-string $model */
    public function createReadModelDescriptorFrom(object|string $model): ReadModelDescriptor;
}
