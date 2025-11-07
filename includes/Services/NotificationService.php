<?php

namespace UPMarket\Subscriptions\Services;

use UPMarket\Subscriptions\Entities\Subscription;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Serviço de notificações por e-mail
 *
 * @package UPMarket\Subscriptions\Services
 */
class NotificationService
{
    /**
     * Construtor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Inicializa os hooks
     */
    private function init_hooks(): void
    {
        // Notificações de sucesso
        add_action('upmkt_recurring_payment_success', [$this, 'send_payment_success_email'], 10, 2);

        // Notificações de falha
        add_action('upmkt_recurring_payment_failed', [$this, 'send_payment_failed_email'], 10, 3);

        // Notificações de cancelamento
        add_action('upmkt_subscription_cancelled', [$this, 'send_cancellation_email'], 10, 2);

        // Notificação de nova doação
        add_action('upmkt_subscription_created', [$this, 'send_new_subscription_email'], 10, 1);
    }

    /**
     * Envia e-mail de pagamento bem-sucedido
     *
     * @param Subscription $subscription
     * @param array $payment_result
     */
    public function send_payment_success_email(Subscription $subscription, array $payment_result): void
    {
        $user = get_userdata($subscription->get_user_id());
        if (!$user) {
            return;
        }

        $to = $user->user_email;
        $subject = 'Pagamento da sua doação foi processado';

        $message = $this->get_email_template('payment_success', [
            'user_name' => $user->display_name,
            'subscription_id' => $subscription->get_id(),
            'amount' => $this->get_subscription_amount($subscription),
            'next_billing_date' => $subscription->get_next_billing_date()->format('d/m/Y'),
            'transaction_id' => $payment_result['transaction_id'] ?? 'N/A'
        ]);

        $this->send_email($to, $subject, $message);

        Logger::instance()->info("Payment success email sent to user {$user->ID}", 'notifications');
    }

    /**
     * Envia e-mail de falha no pagamento
     *
     * @param Subscription $subscription
     * @param array $payment_result
     * @param int $retry_count
     */
    public function send_payment_failed_email(Subscription $subscription, array $payment_result, int $retry_count): void
    {
        $user = get_userdata($subscription->get_user_id());
        if (!$user) {
            return;
        }

        $to = $user->user_email;
        $subject = 'Problema com o pagamento da sua doação';

        $message = $this->get_email_template('payment_failed', [
            'user_name' => $user->display_name,
            'subscription_id' => $subscription->get_id(),
            'amount' => $this->get_subscription_amount($subscription),
            'error_message' => $payment_result['errors'][0] ?? 'Erro desconhecido',
            'retry_count' => $retry_count,
            'max_retries' => 3
        ]);

        $this->send_email($to, $subject, $message);

        // Também notifica admin se for a última tentativa
        if ($retry_count >= 3) {
            $this->send_admin_notification($subscription, $payment_result);
        }

        Logger::instance()->info("Payment failed email sent to user {$user->ID}, retry {$retry_count}", 'notifications');
    }

    /**
     * Envia e-mail de cancelamento
     *
     * @param Subscription $subscription
     * @param string $reason
     */
    public function send_cancellation_email(Subscription $subscription, string $reason): void
    {
        $user = get_userdata($subscription->get_user_id());
        if (!$user) {
            return;
        }

        $to = $user->user_email;
        $subject = 'Sua doação foi cancelada';

        $message = $this->get_email_template('cancellation', [
            'user_name' => $user->display_name,
            'subscription_id' => $subscription->get_id(),
            'cancellation_reason' => $this->get_cancellation_reason_text($reason),
            'cancellation_date' => current_time('d/m/Y')
        ]);

        $this->send_email($to, $subject, $message);

        Logger::instance()->info("Cancellation email sent to user {$user->ID}", 'notifications');
    }

    /**
     * Envia e-mail de nova doação
     *
     * @param Subscription $subscription
     */
    public function send_new_subscription_email(Subscription $subscription): void
    {
        $user = get_userdata($subscription->get_user_id());
        if (!$user) {
            return;
        }

        $to = $user->user_email;
        $subject = 'Bem-vindo à sua nova doação!';

        $message = $this->get_email_template('new_subscription', [
            'user_name' => $user->display_name,
            'subscription_id' => $subscription->get_id(),
            'plan_name' => $this->get_plan_name($subscription->get_plan_id()),
            'start_date' => $subscription->get_start_date()->format('d/m/Y'),
            'next_billing_date' => $subscription->get_next_billing_date()->format('d/m/Y')
        ]);

        $this->send_email($to, $subject, $message);

        Logger::instance()->info("New subscription email sent to user {$user->ID}", 'notifications');
    }

    /**
     * Notifica administrador sobre falha crítica
     *
     * @param Subscription $subscription
     * @param array $payment_result
     */
    private function send_admin_notification(Subscription $subscription, array $payment_result): void
    {
        $to = get_option('admin_email');
        $subject = '[UP Market] Falha crítica em doação';

        $user = get_userdata($subscription->get_user_id());
        $user_name = $user ? $user->display_name : 'Usuário desconhecido';

        $message = "Falha crítica no processamento da doação:\n\n";
        $message .= "Doação ID: {$subscription->get_id()}\n";
        $message .= "Usuário: {$user_name} (ID: {$subscription->get_user_id()})\n";
        $message .= "Plano: {$this->get_plan_name($subscription->get_plan_id())}\n";
        $message .= "Erro: " . ($payment_result['errors'][0] ?? 'Erro desconhecido') . "\n";
        $message .= "Data: " . current_time('d/m/Y H:i:s') . "\n";

        wp_mail($to, $subject, $message);

        Logger::instance()->warning("Admin notification sent for failed subscription {$subscription->get_id()}", 'notifications');
    }

    /**
     * Retorna template de e-mail
     *
     * @param string $template
     * @param array $data
     * @return string
     */
    private function get_email_template(string $template, array $data): string
    {
        // Templates básicos - podem ser melhorados com HTML
        $templates = [
            'payment_success' => "
Olá {user_name},

Seu pagamento da doação #{subscription_id} no valor de {amount} foi processado com sucesso.

Próxima cobrança: {next_billing_date}
ID da transação: {transaction_id}

Atenciosamente,
Equipe UP Market
            ",

            'payment_failed' => "
Olá {user_name},

Ocorreu um problema ao processar o pagamento da sua doação #{subscription_id}.

Valor: {amount}
Erro: {error_message}
Tentativa: {retry_count} de {max_retries}

Por favor, verifique seus dados de pagamento.

Atenciosamente,
Equipe UP Market
            ",

            'cancellation' => "
Olá {user_name},

Sua doação #{subscription_id} foi cancelada.

Motivo: {cancellation_reason}
Data do cancelamento: {cancellation_date}

Esperamos vê-lo novamente em breve!

Atenciosamente,
Equipe UP Market
            ",

            'new_subscription' => "
Olá {user_name},

Bem-vindo à sua nova doação!

Plano: {plan_name}
ID da doação: #{subscription_id}
Data de início: {start_date}
Próxima cobrança: {next_billing_date}

Obrigado por escolher nossos serviços.

Atenciosamente,
Equipe UP Market
            "
        ];

        $message = $templates[$template] ?? '';

        // Substitui placeholders
        foreach ($data as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }

        return trim($message);
    }

    /**
     * Envia e-mail
     *
     * @param string $to
     * @param string $subject
     * @param string $message
     * @return bool
     */
    private function send_email(string $to, string $subject, string $message): bool
    {
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->get_from_header()
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Retorna header From para e-mails
     *
     * @return string
     */
    private function get_from_header(): string
    {
        $site_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');

        return "{$site_name} <{$admin_email}>";
    }

    /**
     * Retorna o valor formatado da doação
     *
     * @param Subscription $subscription
     * @return string
     */
    private function get_subscription_amount(Subscription $subscription): string
    {
        // TODO: Buscar valor do plano
        return 'R$ 0,00';
    }

    /**
     * Retorna nome do plano
     *
     * @param int $plan_id
     * @return string
     */
    private function get_plan_name(int $plan_id): string
    {
        // TODO: Buscar nome do plano
        return 'Plano Básico';
    }

    /**
     * Retorna texto do motivo de cancelamento
     *
     * @param string $reason
     * @return string
     */
    private function get_cancellation_reason_text(string $reason): string
    {
        $reasons = [
            'user_request' => 'Solicitação do usuário',
            'payment_failure' => 'Falha no pagamento',
            'expired' => 'Doação expirada',
            'admin_cancelled' => 'Cancelado pelo administrador'
        ];

        return $reasons[$reason] ?? $reason;
    }
}
