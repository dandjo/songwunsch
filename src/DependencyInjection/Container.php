<?php

declare(strict_types=1);

namespace Songwunsch\DependencyInjection;

use Closure;
use RuntimeException;

/**
 * The service container: a name, a factory, and the object it made.
 *
 * Deliberately small. There is no autowiring and no compilation step --
 * config/services.php names every service and what it is built from, which
 * at this size is shorter to read than the reflection that would work it
 * out, and needs no cache directory on a host that may not have a writable
 * one.
 *
 * What it does buy is laziness, and that is the point rather than a side
 * effect. The front controller used to construct a database connection, ten
 * repositories and a fully parsed translation catalogue before it looked at
 * the address -- on every request, the live-update polls included, which are
 * the majority and need three of them. A service that nobody asks for is
 * never made.
 */
final class Container
{
    /** @var array<string,Closure(self):mixed> */
    private array $factories = [];

    /** @var array<string,mixed> */
    private array $services = [];

    /** @var array<string,mixed> */
    private array $parameters = [];

    /** @var array<string,true> guards against a service that asks for itself */
    private array $building = [];

    /** @param array<string,mixed> $parameters */
    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    /** @param Closure(self):mixed $factory */
    public function set(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /**
     * An object that already exists -- the request, which the kernel puts in
     * before anything can ask for it.
     */
    public function setService(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]) || isset($this->factories[$id]);
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return T|mixed
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new RuntimeException('Unknown service: ' . $id);
        }
        if (isset($this->building[$id])) {
            throw new RuntimeException('Circular service: ' . $id);
        }

        $this->building[$id] = true;
        try {
            $service = ($this->factories[$id])($this);
        } finally {
            unset($this->building[$id]);
        }

        return $this->services[$id] = $service;
    }

    /** Has this service been built already? Asked by the shell so that a
     *  value nobody needed is not fetched just to be reported. */
    public function initialised(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }

    /** @param array<string,mixed> $parameters */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters + $this->parameters;
    }
}
