<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use Override;

use function is_string;

class ReadModelDescriptorFactory implements ReadModelDescriptorFactoryInterface
{
    use BasicReadModelDescriptorFactory;

    #[Override]
    public function createReadModelDescriptorFrom(object|string $model): ReadModelDescriptor
    {
        $modelClass = is_string($model) ? $model : $model::class;

        return $this->createReadModelDescriptorFromDto($modelClass);
    }
}
