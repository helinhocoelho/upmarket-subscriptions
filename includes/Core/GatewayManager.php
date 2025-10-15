<?php

namespace UPMarket\Subscriptions\Core;

use UPMarket\Subscriptions\Interfaces\PaymentGatewayInterface;

/**
 * Gerenciador de gateways de pagamento
 *
 * @package UPMarket\Subscriptions\Core
 */
class GatewayManager
{
    /**
     * @var GatewayManager Instância única
     */
    private static $instance = null;

    /**
     * @var array Gateways registrados
     */
    private $gateways = [];

    /**
     * @var PaymentGatewayInterface Gateway padrão
     */
    private $default_gateway = null;

    /**
     * Construtor privado (Singleton)
     */
    private function __construct()
    {
        $this->register_default_gateways();
    }

    /**
     * Retorna instância única
     *
     * @return GatewayManager
     */
    public static function instance(): GatewayManager
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registra os gateways padrão
     */
    private function register_default_gateways(): void
    {
        // Gateways serão registrados via método register_gateway()
        do_action('upms_register_gateways', $this);
    }

    /**
     * Registra um gateway
     *
     * @param PaymentGatewayInterface $gateway
     * @return bool
     */
    public function register_gateway(PaymentGatewayInterface $gateway): bool
    {
        $gateway_id = $gateway->get_id();

        if (isset($this->gateways[$gateway_id])) {
            return false;
        }

        $this->gateways[$gateway_id] = $gateway;

        // Define o primeiro gateway como padrão
        if (null === $this->default_gateway) {
            $this->default_gateway = $gateway;
        }

        return true;
    }

    /**
     * Retorna um gateway específico
     *
     * @param string $gateway_id
     * @return PaymentGatewayInterface|null
     */
    public function get_gateway(string $gateway_id): ?PaymentGatewayInterface
    {
        return $this->gateways[$gateway_id] ?? null;
    }

    /**
     * Retorna todos os gateways
     *
     * @return array
     */
    public function get_gateways(): array
    {
        return $this->gateways;
    }

    /**
     * Retorna apenas gateways ativos
     *
     * @return array
     */
    public function get_available_gateways(): array
    {
        return array_filter($this->gateways, function ($gateway) {
            return $gateway->is_configured();
        });
    }

    /**
     * Define o gateway padrão
     *
     * @param string $gateway_id
     * @return bool
     */
    public function set_default_gateway(string $gateway_id): bool
    {
        $gateway = $this->get_gateway($gateway_id);

        if ($gateway && $gateway->is_configured()) {
            $this->default_gateway = $gateway;
            return true;
        }

        return false;
    }

    /**
     * Retorna o gateway padrão
     *
     * @return PaymentGatewayInterface|null
     */
    public function get_default_gateway(): ?PaymentGatewayInterface
    {
        return $this->default_gateway;
    }

    /**
     * Processa webhook para um gateway específico
     *
     * @param string $gateway_id
     * @return void
     */
    public function process_webhook(string $gateway_id): void
    {
        $gateway = $this->get_gateway($gateway_id);

        if ($gateway) {
            $gateway->process_webhook();
        }
    }
}
