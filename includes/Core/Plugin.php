<?php

namespace UPMarket\Subscriptions\Core;

/**
 * Classe principal do plugin
 *
 * @package UPMarket\Subscriptions\Core
 */
class Plugin
{
    /**
     * @var Plugin Instância única do plugin
     */
    private static $instance = null;

    /**
     * @var Container Instância do container para dependências
     */
    private $container;

    /**
     * Construtor privado (Singleton)
     */
    private function __construct()
    {
        $this->container = new Container();
        $this->register_services();
        $this->init_hooks();
    }

    /**
     * Retorna instância única do plugin
     *
     * @return Plugin
     */
    public static function instance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registra todos os serviços no container
     */
    private function register_services(): void
    {
        // Serviços core serão registrados aqui
        $this->container->singleton('subscription_manager', function () {
            return new \UPMarket\Subscriptions\Services\SubscriptionManager();
        });
    }

    /**
     * Inicializa hooks do WordPress
     */
    private function init_hooks(): void
    {
        add_action('init', [$this, 'init_plugin']);
    }

    /**
     * Inicializa o plugin
     */
    public function init_plugin(): void
    {
        do_action('upms_plugin_loaded');
    }

    /**
     * Retorna o container de dependências
     *
     * @return Container
     */
    public function container(): Container
    {
        return $this->container;
    }
}
