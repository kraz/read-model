<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use ReflectionClass;

trait ReadModelDescriptorFactoryDefaults
{
    /** @phpstan-var array<string, ReadModelDescriptor> */
    private array $readModelDescriptors = [];

    private function loadReadModelDescriptor(string $key): ReadModelDescriptor|null
    {
        return $this->readModelDescriptors[$key] ?? null;
    }

    private function assignReadModelDescriptor(string $key, ReadModelDescriptor $descriptor): void
    {
        $this->readModelDescriptors[$key] = $descriptor;
    }

    /** @phpstan-param class-string $modelClass */
    private function createReadModelDescriptorFromDto(string $modelClass): ReadModelDescriptor
    {
        $key = $modelClass;

        $descriptor = $this->loadReadModelDescriptor($key);
        if ($descriptor !== null) {
            return $descriptor;
        }

        $properties = [];
        $ref        = new ReflectionClass($modelClass);
        foreach ($ref->getProperties() as $property) {
            $properties[] = $property->getName();
        }

        $descriptor = new ReadModelDescriptor($properties, [], [], []);
        $this->assignReadModelDescriptor($key, $descriptor);

        return $descriptor;
    }
}
