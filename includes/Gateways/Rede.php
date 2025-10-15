<?php

namespace UPMarket\Subscriptions\Gateways;

use UPMarket\Subscriptions\Abstracts\AbstractPaymentGateway;
use UPMarket\Subscriptions\Interfaces\SubscriptionInterface;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Gateway de pagamento da Rede
 *
 * @package UPMarket\Subscriptions\Gateways
 */
class Rede extends AbstractPaymentGateway
{
    /**
     * @var string URL da API da Rede
     */
    private $api_url;

    /**
     * @var string PV (Affiliation) da Rede
     */
    private $pv;

    /**
     * @var string Token da Rede
     */
    private $token;

    /**
     * @var bool Modo sandbox
     */
    private $sandbox;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->id = 'rede';
        $this->name = 'Rede';
        $this->init_settings();

        $this->sandbox = $this->get_setting('environment') === 'sandbox';
        $this->api_url = $this->sandbox
            ? 'https://api.userede.com.br/desenvolvedores'
            : 'https://api.userede.com.br';

        $this->pv = $this->get_setting('pv');
        $this->token = $this->get_setting('token');

        parent::__construct();

        // Registrar webhook
        add_action('init', [$this, 'register_webhook_endpoint']);
    }

    /**
     * Registra endpoint para webhooks
     */
    public function register_webhook_endpoint(): void
    {
        add_rewrite_rule(
            '^upmkt-webhook/rede/?$',
            'index.php?upmkt_webhook=rede',
            'top'
        );

        add_filter('query_vars', function ($vars) {
            $vars[] = 'upmkt_webhook';
            return $vars;
        });

        add_action('template_redirect', [$this, 'handle_webhook']);
    }

    /**
     * Processa um pagamento inicial
     */
    public function process_initial_payment(array $payment_data, SubscriptionInterface $subscription): array
    {
        Logger::instance()->info(
            "Processing initial payment for subscription {$subscription->get_id()}",
            'gateways'
        );

        $validation_errors = $this->validate_payment_data($payment_data);
        if (!empty($validation_errors)) {
            return [
                'success' => false,
                'errors' => $validation_errors
            ];
        }

        try {
            // Preparar dados para a Rede
            $transaction_data = $this->prepare_transaction_data($payment_data, $subscription);

            // Criar transação na Rede
            $response = $this->create_transaction($transaction_data);

            if ($response['success']) {
                // Salvar token do cartão para cobranças futuras
                if (!empty($response['card_token'])) {
                    $subscription->set_meta('rede_card_token', $response['card_token']);
                    $subscription->save();
                }

                Logger::instance()->info(
                    "Initial payment successful for subscription {$subscription->get_id()}",
                    'gateways'
                );

                return [
                    'success' => true,
                    'transaction_id' => $response['transaction_id'],
                    'message' => 'Pagamento processado com sucesso'
                ];
            } else {
                Logger::instance()->error(
                    "Initial payment failed for subscription {$subscription->get_id()}: " .
                    ($response['message'] ?? 'Unknown error'),
                    'gateways'
                );

                return [
                    'success' => false,
                    'errors' => [$response['message'] ?? 'Erro ao processar pagamento']
                ];
            }

        } catch (\Exception $e) {
            Logger::instance()->error(
                "Rede gateway error: " . $e->getMessage(),
                'gateways'
            );

            return [
                'success' => false,
                'errors' => ['Erro ao processar pagamento: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Processa um pagamento recorrente
     */
    public function process_recurring_payment(SubscriptionInterface $subscription): array
    {
        Logger::instance()->info(
            "Processing recurring payment for subscription {$subscription->get_id()}",
            'gateways'
        );

        $card_token = $subscription->get_meta('rede_card_token');

        if (empty($card_token)) {
            return [
                'success' => false,
                'errors' => ['Token do cartão não encontrado para cobrança recorrente']
            ];
        }

        try {
            // Buscar dados do plano para obter valor
            $plan = new \UPMarket\Subscriptions\Entities\SubscriptionPlan($subscription->get_plan_id());
            $amount = $plan->exists() ? $plan->get_price() : 0;

            // Preparar dados para cobrança recorrente
            $transaction_data = [
                'capture' => true,
                'kind' => 'credit',
                'reference' => 'subscription_' . $subscription->get_id() . '_' . time(),
                'amount' => (int)($amount * 100), // Em centavos
                'cardToken' => $card_token,
                'subscription' => [
                    'subscriptionId' => (string)$subscription->get_id()
                ]
            ];

            $response = $this->create_transaction($transaction_data);

            if ($response['success']) {
                Logger::instance()->info(
                    "Recurring payment successful for subscription {$subscription->get_id()}",
                    'gateways'
                );

                return [
                    'success' => true,
                    'transaction_id' => $response['transaction_id'],
                    'message' => 'Cobrança recorrente processada com sucesso'
                ];
            } else {
                Logger::instance()->warning(
                    "Recurring payment failed for subscription {$subscription->get_id()}: " .
                    ($response['message'] ?? 'Unknown error'),
                    'gateways'
                );

                return [
                    'success' => false,
                    'errors' => [$response['message'] ?? 'Erro ao processar cobrança recorrente']
                ];
            }

        } catch (\Exception $e) {
            Logger::instance()->error(
                "Rede recurring payment error: " . $e->getMessage(),
                'gateways'
            );

            return [
                'success' => false,
                'errors' => ['Erro ao processar cobrança recorrente: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Cancela uma assinatura no gateway
     */
    public function cancel_subscription(SubscriptionInterface $subscription): bool
    {
        Logger::instance()->info(
            "Canceling subscription {$subscription->get_id()} in Rede",
            'gateways'
        );

        // Na Rede, cancelamos transações futuras, não a assinatura em si
        // Marcamos a assinatura como cancelada localmente
        return true;
    }

    /**
     * Verifica o status de um pagamento
     */
    public function check_payment_status(string $transaction_id): array
    {
        try {
            $response = $this->get_transaction_status($transaction_id);

            return [
                'success' => true,
                'status' => $response['status'],
                'message' => $response['message'] ?? 'Status verificado'
            ];

        } catch (\Exception $e) {
            Logger::instance()->error(
                "Rede status check error: " . $e->getMessage(),
                'gateways'
            );

            return [
                'success' => false,
                'errors' => ['Erro ao verificar status: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Processa webhooks da Rede
     */
    public function process_webhook(): void
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        Logger::instance()->info('Rede webhook received: ' . $input, 'webhooks');

        if (empty($data)) {
            http_response_code(400);
            exit;
        }

        try {
            // Verificar autenticação
            if (!$this->verify_webhook_signature()) {
                Logger::instance()->error('Invalid webhook signature', 'webhooks');
                http_response_code(401);
                exit;
            }

            // Processar diferentes tipos de notificação
            $this->handle_webhook_notification($data);

            http_response_code(200);
            echo 'OK';

        } catch (\Exception $e) {
            Logger::instance()->error('Webhook processing error: ' . $e->getMessage(), 'webhooks');
            http_response_code(500);
        }

        exit;
    }

    /**
     * Manipula webhook manualmente
     */
    public function handle_webhook(): void
    {
        if (get_query_var('upmkt_webhook') === 'rede') {
            $this->process_webhook();
        }
    }

    /**
     * Prepara dados da transação
     */
    private function prepare_transaction_data(array $payment_data, SubscriptionInterface $subscription): array
    {
        $plan = new \UPMarket\Subscriptions\Entities\SubscriptionPlan($subscription->get_plan_id());
        $amount = $plan->exists() ? $plan->get_price() : 0;

        return [
            'capture' => true,
            'kind' => 'credit',
            'reference' => 'subscription_' . $subscription->get_id(),
            'amount' => (int)($amount * 100), // Em centavos
            'installments' => 1,
            'cardHolderName' => $payment_data['card_holder'],
            'cardNumber' => preg_replace('/\s+/', '', $payment_data['card_number']),
            'expirationMonth' => substr($payment_data['card_expiry'], 0, 2),
            'expirationYear' => '20' . substr($payment_data['card_expiry'], 3, 2),
            'securityCode' => $payment_data['card_cvv'],
            'subscription' => true,
            'origin' => 1, // E-commerce
            'distributorAffiliation' => $this->pv,
            'softDescriptor' => 'UP Market Sub'
        ];
    }

    /**
     * Cria transação na Rede
     */
    private function create_transaction(array $data): array
    {
        $url = $this->api_url . '/v1/transactions';

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->pv . ':' . $this->token),
                'Content-Type' => 'application/json',
                'User-Agent' => 'UP Market Subscriptions/' . UPMKT_VERSION
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        Logger::instance()->debug('Rede API Response: ' . $body, 'gateways');

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200 || $status_code === 201) {
            return [
                'success' => true,
                'transaction_id' => $result['tid'] ?? '',
                'card_token' => $result['cardToken'] ?? '',
                'message' => $result['returnMessage'] ?? 'Transação criada com sucesso'
            ];
        } else {
            return [
                'success' => false,
                'message' => $result['returnMessage'] ?? 'Erro na transação',
                'error_code' => $result['returnCode'] ?? ''
            ];
        }
    }

    /**
     * Busca status da transação
     */
    private function get_transaction_status(string $transaction_id): array
    {
        $url = $this->api_url . '/v1/transactions/' . $transaction_id;

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->pv . ':' . $this->token),
                'Content-Type' => 'application/json',
                'User-Agent' => 'UP Market Subscriptions/' . UPMKT_VERSION
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        return [
            'status' => $result['authorization']['status'] ?? 'unknown',
            'message' => $result['returnMessage'] ?? ''
        ];
    }

    /**
     * Verifica assinatura do webhook
     */
    private function verify_webhook_signature(): bool
    {
        // TODO: Implementar verificação de assinatura se necessário
        return true;
    }

    /**
     * Processa notificação do webhook
     */
    private function handle_webhook_notification(array $data): void
    {
        $type = $data['event'] ?? '';
        $transaction_id = $data['transaction']['tid'] ?? '';

        switch ($type) {
            case 'transaction_approved':
                $this->handle_transaction_approved($data);
                break;

            case 'transaction_denied':
                $this->handle_transaction_denied($data);
                break;

            case 'transaction_captured':
                $this->handle_transaction_captured($data);
                break;

            default:
                Logger::instance()->info("Unhandled webhook event: {$type}", 'webhooks');
        }
    }

    /**
     * Manipula transação aprovada
     */
    private function handle_transaction_approved(array $data): void
    {
        $transaction_id = $data['transaction']['tid'] ?? '';
        $reference = $data['transaction']['reference'] ?? '';

        Logger::instance()->info("Transaction approved: {$transaction_id}", 'webhooks');

        // TODO: Atualizar status da assinatura relacionada
        do_action('upmkt_rede_transaction_approved', $transaction_id, $reference, $data);
    }

    /**
     * Manipula transação negada
     */
    private function handle_transaction_denied(array $data): void
    {
        $transaction_id = $data['transaction']['tid'] ?? '';
        $reference = $data['transaction']['reference'] ?? '';

        Logger::instance()->warning("Transaction denied: {$transaction_id}", 'webhooks');

        // TODO: Atualizar status da assinatura relacionada
        do_action('upmkt_rede_transaction_denied', $transaction_id, $reference, $data);
    }

    /**
     * Manipula transação capturada
     */
    private function handle_transaction_captured(array $data): void
    {
        $transaction_id = $data['transaction']['tid'] ?? '';

        Logger::instance()->info("Transaction captured: {$transaction_id}", 'webhooks');

        // TODO: Processar captura
        do_action('upmkt_rede_transaction_captured', $transaction_id, $data);
    }

    /**
     * Verifica se o gateway está configurado
     */
    public function is_configured(): bool
    {
        return !empty($this->pv) && !empty($this->token);
    }
}
