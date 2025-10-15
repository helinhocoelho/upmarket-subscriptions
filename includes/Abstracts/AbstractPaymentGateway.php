<?php

namespace UPMarket\Subscriptions\Abstracts;

use UPMarket\Subscriptions\Interfaces\PaymentGatewayInterface;
use UPMarket\Subscriptions\Interfaces\SubscriptionInterface;

/**
 * Classe abstrata para gateways de pagamento
 *
 * @package UPMarket\Subscriptions\Abstracts
 */
abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @var string ID do gateway
     */
    protected $id;

    /**
     * @var string Nome do gateway
     */
    protected $name;

    /**
     * @var array Configurações do gateway
     */
    protected $settings = [];

    /**
     * @var bool Se o gateway está ativo
     */
    protected $enabled = false;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->init_settings();
        $this->enabled = $this->get_setting('enabled') === 'yes';
    }

    /**
     * Inicializa as configurações
     */
    protected function init_settings(): void
    {
        $this->settings = get_option("upmkt_gateway_{$this->id}_settings", []);
        $this->enabled = ($this->get_setting('enabled') === 'yes') && $this->is_configured();
    }

    /**
     * Retorna uma configuração
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function get_setting(string $key, $default = '')
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Processa webhooks do gateway
     *
     * @return void
     */
    public function process_webhook(): void
    {
        // Implementação base para webhooks
        $payload = file_get_contents('php://input');
        $this->log('Webhook received: ' . $payload);
    }

    /**
     * Registra logs
     *
     * @param string $message
     * @param string $level
     */
    protected function log(string $message, string $level = 'info'): void
    {
        $logger = function ($message) use ($level) {
            error_log("[UPMKT Gateway {$this->id} - {$level}] {$message}");
        };

        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message, ['source' => "upmkt-gateway-{$this->id}"]);
        } else {
            $logger($message);
        }
    }

    /**
     * Valida dados de pagamento
     *
     * @param array $payment_data
     * @return array
     */
    protected function validate_payment_data(array $payment_data): array
    {
        $errors = [];

        if (empty($payment_data['amount'])) {
            $errors[] = 'Amount is required';
        }

        if (empty($payment_data['currency'])) {
            $errors[] = 'Currency is required';
        }

        return $errors;
    }

    /**
     * Retorna o ID do gateway
     *
     * @return string
     */
    public function get_id(): string
    {
        return $this->id;
    }

    /**
     * Retorna o nome do gateway
     *
     * @return string
     */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * Retorna as configurações do gateway
     *
     * @return array
     */
    public function get_settings(): array
    {
        return $this->settings;
    }

    /**
     * Verifica se o gateway está configurado
     *
     * @return bool
     */
    public function is_configured(): bool
    {
        return $this->enabled && !empty($this->settings);
    }
}
