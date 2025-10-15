<?php

namespace UPMarket\Subscriptions\Core;

/**
 * Container de Injeção de Dependências simples
 *
 * @package UPMarket\Subscriptions\Core
 */
class Container
{
    /**
     * @var array Instâncias registradas
     */
    private $instances = [];

    /**
     * @var array Callables de fabricação
     */
    private $bindings = [];

    /**
     * Registra uma binding singleton
     *
     * @param string $abstract
     * @param callable $factory
     */
    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * Resolve uma dependência
     *
     * @param string $abstract
     * @return mixed
     */
    public function make(string $abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $this->instances[$abstract] = call_user_func($this->bindings[$abstract], $this);
            return $this->instances[$abstract];
        }

        throw new \Exception("Service {$abstract} not found in container.");
    }

    /**
     * Magic getter para serviços
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->make($key);
    }
}
