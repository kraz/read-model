<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use Kraz\ReadModel\Collections\ReadableCollection;
use LogicException;
use Override;

use function is_object;
use function is_string;

class ReadModelDescriptorFactory implements ReadModelDescriptorFactoryInterface
{
    use BasicReadModelDescriptorFactory;

    #[Override]
    public function createReadModelDescriptorFrom(object|string $model): ReadModelDescriptor
    {
        if ($model instanceof ReadableCollection) {
            $model = $model->first();
            if (! is_object($model)) {
                throw new LogicException('Can not create model descriptor. Can not determine the model item type!');
            }
        }

        $modelClass = is_string($model) ? $model : $model::class;

        return $this->createReadModelDescriptorFromDto($modelClass);
    }
}
