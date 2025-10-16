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
     * @var bool Se os endpoints de webhook foram registrados
     */
    private $webhooks_registered = false;

    /**
     * Construtor privado (Singleton)
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * CORREÇÃO: Inicializa hooks em vez de gateways fixos
     */
    private function init_hooks(): void
    {
        // Registra endpoints de webhook no init
        add_action('init', [$this, 'register_webhook_endpoints']);

        // Processa webhooks no template_redirect
        add_action('template_redirect', [$this, 'maybe_process_webhook']);

        // Flush rewrite rules na ativação
        register_activation_hook(UPMKT_PLUGIN_FILE, [$this, 'flush_rewrite_rules']);
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
     * NOVO: Registra endpoints de webhook dinamicamente
     */
    public function register_webhook_endpoints(): void
    {
        if ($this->webhooks_registered) {
            return;
        }

        $gateways = $this->get_gateways();

        foreach ($gateways as $gateway_id => $gateway) {
            // Registra endpoint para cada gateway
            add_rewrite_rule(
                "^webhook-{$gateway_id}/?$",
                'index.php?upmkt_webhook_gateway=' . $gateway_id,
                'top'
            );
        }

        // Adiciona query var
        add_filter('query_vars', function ($vars) {
            $vars[] = 'upmkt_webhook_gateway';
            return $vars;
        });

        $this->webhooks_registered = true;

        $this->log("Webhook endpoints registered for " . count($gateways) . " gateways");
    }

    /**
     * NOVO: Processa webhooks dinamicamente
     */
    public function maybe_process_webhook(): void
    {
        $gateway_id = get_query_var('upmkt_webhook_gateway');

        if (empty($gateway_id)) {
            return;
        }

        $this->log("Processing webhook for gateway: {$gateway_id}");

        // Processa o webhook
        $this->process_webhook($gateway_id);

        // Finaliza a execução
        status_header(200);
        exit;
    }

    /**
     * NOVO: Flush rewrite rules quando necessário
     */
    public function flush_rewrite_rules(): void
    {
        $this->webhooks_registered = false;
        $this->register_webhook_endpoints();
        flush_rewrite_rules();
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
            $this->log("Gateway {$gateway_id} already registered", 'warning');
            return false;
        }

        $this->gateways[$gateway_id] = $gateway;

        // CORREÇÃO: Só define como padrão se estiver configurado
        if (null === $this->default_gateway && $gateway->is_configured()) {
            $this->default_gateway = $gateway;
            $this->log("Default gateway set to: {$gateway_id}");
        }

        $this->log("Gateway registered: {$gateway_id}");

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
            $this->log("Default gateway changed to: {$gateway_id}");
            return true;
        }

        $this->log("Failed to set default gateway: {$gateway_id} not available", 'warning');
        return false;
    }

    /**
     * Retorna o gateway padrão
     *
     * @return PaymentGatewayInterface|null
     */
    public function get_default_gateway(): ?PaymentGatewayInterface
    {
        // CORREÇÃO: Garante que retorna um gateway configurado
        if ($this->default_gateway && $this->default_gateway->is_configured()) {
            return $this->default_gateway;
        }

        // Se o padrão não estiver configurado, busca o primeiro configurado
        $available_gateways = $this->get_available_gateways();

        if (!empty($available_gateways)) {
            $new_default = reset($available_gateways);
            $this->default_gateway = $new_default;
            $this->log("Auto-set default gateway to: " . $new_default->get_id());
            return $new_default;
        }

        $this->log("No available gateways found", 'warning');
        return null;
    }

    /**
     * CORREÇÃO: Processa webhook para um gateway específico
     *
     * @param string $gateway_id
     * @return void
     */
    public function process_webhook(string $gateway_id): void
    {
        $gateway = $this->get_gateway($gateway_id);

        if (!$gateway) {
            $this->log("Webhook failed: Gateway {$gateway_id} not found", 'error');
            status_header(404);
            exit;
        }

        $this->log("Processing webhook for gateway: {$gateway_id}");

        try {
            // CORREÇÃO: Passa dados completos do webhook
            $webhook_data = [
                'headers' => $this->get_all_headers(),
                'body' => file_get_contents('php://input'),
                'get' => $_GET,
                'post' => $_POST,
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ];

            // Processa no gateway específico
            $gateway->process_webhook($webhook_data);

            $this->log("Webhook processed successfully for gateway: {$gateway_id}");

        } catch (\Exception $e) {
            $this->log("Webhook processing error for {$gateway_id}: " . $e->getMessage(), 'error');
            status_header(500);
            exit;
        }
    }

    /**
     * NOVO: Retorna URLs de webhook para todos os gateways
     *
     * @return array
     */
    public function get_webhook_urls(): array
    {
        $urls = [];
        $gateways = $this->get_gateways();

        foreach ($gateways as $gateway_id => $gateway) {
            $urls[$gateway_id] = $gateway->get_webhook_url();
        }

        return $urls;
    }

    /**
     * Obtém todos os headers HTTP
     *
     * @return array
     */
    private function get_all_headers(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    /**
     * NOVO: Sistema de logging interno
     */
    private function log(string $message, string $level = 'info'): void
    {
        $logger = \UPMarket\Subscriptions\Core\Logger::instance();
        $logger->log($level, "[GatewayManager] {$message}", 'gateways');
    }
}
