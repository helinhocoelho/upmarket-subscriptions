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
     * CORREÇÃO: Método atualizado para receber dados dinamicamente
     * Processa webhooks do gateway
     *
     * @param array $webhook_data Dados do webhook
     * @return void
     */
    public function process_webhook(array $webhook_data = []): void
    {
        // Implementação base melhorada
        $payload = $webhook_data['body'] ?? file_get_contents('php://input');
        $headers = $webhook_data['headers'] ?? [];

        $this->log("Webhook received for gateway {$this->id}");
        $this->log("Headers: " . json_encode($headers));
        $this->log("Payload: " . $payload);

        // Dispara ação específica do gateway para extensibilidade
        do_action("upmkt_{$this->id}_webhook_received", $webhook_data);
    }

    /**
     * NOVO: Valida assinatura do webhook
     *
     * @param array $webhook_data
     * @return bool
     */
    protected function validate_webhook_signature(array $webhook_data): bool
    {
        // Implementação base - gateways específicos devem sobrescrever
        $this->log("Webhook signature validation not implemented for {$this->id}");
        return true;
    }

    /**
     * NOVO: Processa eventos de webhook de forma estruturada
     *
     * @param string $event_type
     * @param array $event_data
     * @return void
     */
    protected function handle_webhook_event(string $event_type, array $event_data): void
    {
        $action_hook = "upmkt_{$this->id}_{$event_type}";

        $this->log("Dispatching webhook event: {$action_hook}");

        // Dispara hook dinâmico para outros handlers processarem
        do_action($action_hook, $event_data, $this->id);
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

    /**
     * Retorna campos de configuração padrão
     */
    public function get_settings_fields(): array
    {
        return [
            'enabled' => [
                'title' => 'Habilitar Gateway',
                'type' => 'checkbox',
                'label' => 'Ativar este gateway',
                'default' => 'no',
                'description' => 'Habilita este gateway de pagamento'
            ],
            'title' => [
                'title' => 'Título',
                'type' => 'text',
                'default' => $this->name,
                'description' => 'Título que o cliente verá durante o checkout.',
                'class' => 'regular-text'
            ],
            'description' => [
                'title' => 'Descrição',
                'type' => 'textarea',
                'default' => '',
                'description' => 'Descrição que o cliente verá durante o checkout.',
                'rows' => 3,
                'class' => 'large-text'
            ],
            // NOVO: Campo para webhook URL (automático)
            'webhook_url' => [
                'title' => 'URL de Webhook',
                'type' => 'custom',
                'render_callback' => function ($settings) {
                    $webhook_url = $this->get_webhook_url();
                    echo "<div class='upmkt-webhook-url-container'>";
                    echo "<input type='text' class='large-text' value='{$webhook_url}' readonly>";
                    echo "<p class='description'>Configure esta URL no painel do gateway para receber notificações automáticas.</p>";
                    echo "</div>";
                }
            ]
        ];
    }

    /**
     * NOVO: Retorna URL de webhook para este gateway
     *
     * @return string
     */
    public function get_webhook_url(): string
    {
        return home_url("/webhook-{$this->id}/");
    }

    /**
     * Validação básica das configurações
     */
    public function validate_settings(array $settings): array
    {
        $errors = [];

        if (!empty($settings['enabled']) && $settings['enabled'] === 'yes') {
            // Validação básica - gateways específicos podem sobrescrever
            if (!$this->is_configured()) {
                $errors[] = 'Gateway não está completamente configurado.';
            }
        }

        return $errors;
    }

    /**
     * Teste de conexão básico
     */
    public function test_connection(): array
    {
        return [
            'success' => false,
            'message' => 'Teste de conexão não implementado para este gateway.'
        ];
    }
}
