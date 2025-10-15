<?php

namespace UPMarket\Subscriptions\Abstracts;

/**
 * Classe abstrata para entidades base
 *
 * @package UPMarket\Subscriptions\Abstracts
 */
abstract class BaseEntity
{
    /**
     * @var int ID da entidade
     */
    protected $id = 0;

    /**
     * @var array Dados da entidade
     */
    protected $data = [];

    /**
     * @var array Metadados da entidade
     */
    protected $meta = [];

    /**
     * Construtor
     *
     * @param int $id ID da entidade
     */
    public function __construct(int $id = 0)
    {
        if ($id > 0) {
            $this->id = $id;
            $this->load();
        }
    }

    /**
     * Carrega os dados da entidade
     *
     * @return bool
     */
    abstract protected function load(): bool;

    /**
     * Salva a entidade
     *
     * @return bool
     */
    abstract public function save(): bool;

    /**
     * Retorna o ID da entidade
     *
     * @return int
     */
    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * Define um dado
     *
     * @param string $key
     * @param mixed $value
     */
    protected function set_prop(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Retorna um dado
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function get_prop(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Define um metadado
     *
     * @param string $key
     * @param mixed $value
     */
    public function set_meta(string $key, $value): void
    {
        $this->meta[$key] = $value;
    }

    /**
     * Retorna um metadado
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get_meta(string $key, $default = null)
    {
        return $this->meta[$key] ?? $default;
    }

    /**
     * Retorna todos os dados
     *
     * @return array
     */
    public function get_data(): array
    {
        return $this->data;
    }

    /**
     * Verifica se a entidade existe
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->id > 0;
    }
}
