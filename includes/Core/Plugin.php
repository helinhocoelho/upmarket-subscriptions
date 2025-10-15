<?php

namespace UPMarket\Subscriptions\Core;

use UPMarket\Subscriptions\Providers\GatewayServiceProvider;

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
     * @var Container Instância do container de dependências
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
        // Serviços core
        $this->container->singleton('gateway_manager', function () {
            return GatewayManager::instance();
        });

        $this->container->singleton('logger', function () {
            return Logger::instance();
        });

        // Service Providers
        $this->register_service_providers();
    }

    /**
     * Registra os Service Providers
     */
    private function register_service_providers(): void
    {
        $gateway_provider = new GatewayServiceProvider();
        $gateway_provider->register($this->container->gateway_manager);
    }

    /**
     * Inicializa hooks do WordPress
     */
    private function init_hooks(): void
    {
        add_action('init', [$this, 'init_plugin']);
        add_action('wp_loaded', [$this, 'load_webhooks']);
    }

    /**
     * Inicializa o plugin
     */
    public function init_plugin(): void
    {
        $this->load_textdomain();
        do_action('upmkt_plugin_loaded');
    }

    /**
     * Carrega webhooks
     */
    public function load_webhooks(): void
    {
        if (isset($_GET['upmkt_webhook'])) {
            $gateway_id = sanitize_text_field($_GET['upmkt_webhook']);
            $this->container->gateway_manager->process_webhook($gateway_id);
            exit;
        }
    }

    /**
     * Carrega traduções
     */
    private function load_textdomain(): void
    {
        load_plugin_textdomain(
            'upmarket-subscriptions',
            false,
            dirname(plugin_basename(UPMKT_PLUGIN_FILE)) . '/languages'
        );
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
