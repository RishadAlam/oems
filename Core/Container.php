<?php

declare(strict_types=1);

namespace OEMS\Core;

use Closure;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

final class Container
{
    private array $bindings = [];

    private array $instances = [];

    public function set(string $id, object|string $concrete): void
    {
        $this->bindings[$id] = $concrete;
    }

    public function singleton(string $id, object|string $concrete): void
    {
        $this->bindings[$id] = $concrete;
        $this->instances[$id] = null;
    }

    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function get(string $id): object
    {
        if (array_key_exists($id, $this->instances) && $this->instances[$id] !== null) {
            return $this->instances[$id];
        }

        $concrete = $this->bindings[$id] ?? $id;
        $object = $this->build($concrete);

        if (array_key_exists($id, $this->instances)) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    private function build(object|string $concrete): object
    {
        if (is_object($concrete) && !$concrete instanceof Closure) {
            return $concrete;
        }

        if ($concrete instanceof Closure) {
            $object = $concrete($this);

            if (!is_object($object)) {
                throw new RuntimeException('Container factories must return an object.');
            }

            return $object;
        }

        try {
            $reflection = new ReflectionClass($concrete);
        } catch (ReflectionException $exception) {
            throw new RuntimeException("Unable to resolve {$concrete}.", 0, $exception);
        }

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class {$concrete} is not instantiable.");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type === null || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new RuntimeException("Unable to resolve parameter {$parameter->getName()} for {$concrete}.");
            }

            $arguments[] = $this->get($type->getName());
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
