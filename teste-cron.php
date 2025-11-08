<?php
/**
 * Teste de Recorrência - UpMarket Subscriptions
 * Coloque este arquivo na raíz do plugin: /wp-content/plugins/upmarket-subscriptions/teste-cron.php
 * Acesse via: /wp-content/plugins/upmarket-subscriptions/teste-cron.php
 */

// Prevenir acesso direto
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Acesso negado.');
}

// Configurar fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

// Carregar as dependências do plugin
require_once __DIR__ . '/includes/autoload.php';

// Processar formulário
$resultado = '';
$assinatura_selecionada = '';

if (isset($_POST['executar_recorrencia']) && !empty($_POST['subscription_id'])) {
    $assinatura_selecionada = intval($_POST['subscription_id']);
    $resultado = processar_recorrencia_manual($assinatura_selecionada);
}

/**
 * Processar recorrência manualmente
 */
function processar_recorrencia_manual($subscription_id)
{
    global $wpdb;

    ob_start();

    try {
        echo "<div class='resultado' style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        echo "<h3 style='color: #007cba; margin-top: 0;'>🔧 Processando Recorrência</h3>";

        echo "<p><strong>Data/Hora do Servidor:</strong> " . date('d/m/Y H:i:s') . " (America/Sao_Paulo)</p>";

        // Carregar assinatura
        $subscription = new UPMarket\Subscriptions\Entities\Subscription($subscription_id);

        if (!$subscription->exists()) {
            throw new Exception("Assinatura #{$subscription_id} não encontrada.");
        }

        // Carregar plano para obter valor
        $plan = new UPMarket\Subscriptions\Entities\SubscriptionPlan($subscription->get_plan_id());
        $valor_plano = $plan->exists() ? $plan->get_price() : 0;

        echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 6px; margin-bottom: 15px;'>";
        echo "<h4>📊 Dados da Assinatura</h4>";
        echo "<p><strong>Assinatura:</strong> #{$subscription->get_id()}</p>";
        echo "<p><strong>Plano:</strong> {$subscription->get_plan_id()} - " . ($plan->exists() ? $plan->get_name() : 'Plano não encontrado') . "</p>";
        echo "<p><strong>Valor do Plano:</strong> R$ " . number_format($valor_plano, 2, ',', '.') . "</p>";
        echo "<p><strong>Status Atual:</strong> {$subscription->get_status()}</p>";

        $next_billing = $subscription->get_next_billing_date();
        $next_billing->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        echo "<p><strong>Próxima Cobrança:</strong> {$next_billing->format('d/m/Y H:i:s')}</p>";
        echo "</div>";

        // Verificar se é uma assinatura ativa
        if (!$subscription->is_active()) {
            throw new Exception("Assinatura não está ativa. Status: {$subscription->get_status()}");
        }

        // Buscar informações do cartão
        $card_token = $subscription->get_meta('rede_card_token');
        $card_last_four = $subscription->get_meta('card_last_four');

        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 6px; margin-bottom: 15px;'>";
        echo "<h4>💳 Informações de Pagamento</h4>";
        echo "<p><strong>Token do Cartão:</strong> " . ($card_token ? substr($card_token, 0, 10) . '...' : 'Não encontrado') . "</p>";
        echo "<p><strong>Últimos 4 dígitos:</strong> " . ($card_last_four ? '**** **** **** ' . $card_last_four : 'Não disponível') . "</p>";
        echo "</div>";

        // Forçar data de cobrança para agora (para simular recorrência)
        $data_original = $subscription->get_next_billing_date()->format('Y-m-d H:i:s');

        // Criar data no fuso horário de São Paulo
        $agora_sp = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        $nova_data = $agora_sp->format('Y-m-d H:i:s');

        echo "<div style='background: #d4edda; padding: 15px; border-radius: 6px; margin-bottom: 15px;'>";
        echo "<h4>🔄 Simulando Vencimento</h4>";
        echo "<p><strong>Data Original:</strong> {$data_original}</p>";
        echo "<p><strong>Forçando data para:</strong> {$nova_data}</p>";
        echo "<p><strong>Valor a ser cobrado:</strong> R$ " . number_format($valor_plano, 2, ',', '.') . "</p>";
        echo "</div>";

        // Atualizar data no banco
        $table_name = $wpdb->prefix . 'upmkt_subscriptions';
        $wpdb->update(
            $table_name,
            ['next_billing_date' => $nova_data],
            ['id' => $subscription_id],
            ['%s'],
            ['%d']
        );

        echo "<hr style='margin: 15px 0;'>";
        echo "<h4>🔄 Executando Processamento de Recorrência...</h4>";

        // Executar o handler de recorrência
        do_action('upmkt_daily_subscription_check');

        // Aguardar um pouco para processamento
        sleep(2);

        // Recarregar assinatura para ver mudanças
        $subscription_apos = new UPMarket\Subscriptions\Entities\Subscription($subscription_id);

        echo "<div style='background: #d4edda; padding: 15px; border-radius: 6px; margin: 15px 0;'>";
        echo "<h4 style='color: #28a745;'>✅ Processamento Concluído</h4>";

        // Verificar resultado do pagamento
        $status_apos = $subscription_apos->get_status();
        $pagamento_sucesso = ($status_apos === 'active');

        if ($pagamento_sucesso) {
            echo "<p style='color: #28a745; font-weight: bold;'>💰 PAGAMENTO BEM-SUCEDIDO!</p>";
        } else {
            echo "<p style='color: #dc3545; font-weight: bold;'>❌ PAGAMENTO FALHOU!</p>";
        }

        echo "<p><strong>Status Anterior:</strong> {$subscription->get_status()}</p>";
        echo "<p><strong>Status Após:</strong> {$status_apos}</p>";

        $next_billing_apos = $subscription_apos->get_next_billing_date();
        $next_billing_apos->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        echo "<p><strong>Nova Próxima Cobrança:</strong> {$next_billing_apos->format('d/m/Y H:i:s')}</p>";

        // Buscar transação recente
        $ultima_transacao = $subscription_apos->get_meta('last_transaction_id');
        $ultimo_resultado = $subscription_apos->get_meta('last_payment_result');

        echo "<p><strong>ID da Transação:</strong> " . ($ultima_transacao ? $ultima_transacao : 'N/A') . "</p>";
        echo "<p><strong>Resultado do Pagamento:</strong> " . ($ultimo_resultado ? $ultimo_resultado : 'N/A') . "</p>";
        echo "</div>";

        // Verificar logs detalhadamente
        $log_file = WP_CONTENT_DIR . '/upmkt-debug.log';
        if (file_exists($log_file)) {
            $logs = file_get_contents($log_file);
            $linhas = explode("\n", $logs);
            $linhas_recentes = array_slice($linhas, -20); // Últimas 20 linhas

            echo "<h4>📋 Logs Detalhados do Pagamento:</h4>";
            echo "<div style='background: #fff; padding: 10px; border-radius: 4px; font-size: 12px; max-height: 300px; overflow-y: auto; border: 1px solid #ddd;'>";

            $encontrou_pagamento = false;
            foreach ($linhas_recentes as $linha) {
                if (strpos($linha, 'subscription') !== false ||
                    strpos($linha, 'payment') !== false ||
                    strpos($linha, 'transaction') !== false ||
                    strpos($linha, 'Rede') !== false) {

                    // Destacar linhas importantes
                    if (strpos($linha, 'SUCCESS') !== false || strpos($linha, 'success') !== false) {
                        echo "<div style='color: #28a745; font-weight: bold;'>" . htmlspecialchars($linha) . "</div>";
                    } elseif (strpos($linha, 'ERROR') !== false || strpos($linha, 'error') !== false || strpos($linha, 'failed') !== false) {
                        echo "<div style='color: #dc3545; font-weight: bold;'>" . htmlspecialchars($linha) . "</div>";
                    } else {
                        echo "<div>" . htmlspecialchars($linha) . "</div>";
                    }

                    $encontrou_pagamento = true;
                }
            }

            if (!$encontrou_pagamento) {
                echo "<p style='color: #6c757d;'>Nenhum log de pagamento encontrado recentemente.</p>";
            }
            echo "</div>";
        } else {
            echo "<p style='color: #dc3545;'>Arquivo de log não encontrado: {$log_file}</p>";
        }

        // Resumo final
        echo "<div style='background: " . ($pagamento_sucesso ? '#d4edda' : '#f8d7da') . "; padding: 15px; border-radius: 6px; margin-top: 15px;'>";
        echo "<h4>" . ($pagamento_sucesso ? "✅ RESUMO: PAGAMENTO RECORRENTE BEM-SUCEDIDO" : "❌ RESUMO: PAGAMENTO RECORRENTE FALHOU") . "</h4>";
        echo "<p><strong>Valor Cobrado:</strong> R$ " . number_format($valor_plano, 2, ',', '.') . "</p>";
        echo "<p><strong>Status Final:</strong> {$status_apos}</p>";
        echo "<p><strong>Próxima Cobrança:</strong> {$next_billing_apos->format('d/m/Y H:i:s')}</p>";
        echo "<p><strong>Transação:</strong> " . ($ultima_transacao ? $ultima_transacao : 'Não registrada') . "</p>";
        echo "</div>";

        echo "</div>";

    } catch (Exception $e) {
        echo "<div class='erro' style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px;'>";
        echo "<h4 style='color: #721c24; margin-top: 0;'>❌ Erro</h4>";
        echo "<p><strong>Mensagem:</strong> {$e->getMessage()}</p>";
        echo "</div>";
    }

    return ob_get_clean();
}

/**
 * Buscar todas as assinaturas para o select
 */
function buscar_assinaturas()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'upmkt_subscriptions';
    $assinaturas = $wpdb->get_results("
        SELECT s.id, s.status, s.next_billing_date, p.post_title as plan_name 
        FROM {$table_name} s 
        LEFT JOIN {$wpdb->posts} p ON s.plan_id = p.ID 
        ORDER BY s.id DESC
    ");

    return $assinaturas;
}

$assinaturas = buscar_assinaturas();

// Função para formatar data no formato brasileiro
function formatar_data_brasil($data_mysql)
{
    if (empty($data_mysql)) {
        return 'N/A';
    }

    $data = DateTime::createFromFormat('Y-m-d H:i:s', $data_mysql, new DateTimeZone('UTC'));
    if ($data) {
        $data->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        return $data->format('d/m/Y H:i:s');
    }

    return $data_mysql;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Recorrência - UpMarket Subscriptions</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f1f1f1;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #007cba;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .header p {
            opacity: 0.9;
            margin-top: 10px;
        }
        .content {
            padding: 30px;
        }
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #007cba;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3338;
        }
        select, button {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        select {
            background: white;
        }
        button {
            background: #007cba;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        button:hover {
            background: #005a87;
        }
        .assinatura-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 14px;
        }
        .assinatura-item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .assinatura-item:last-child {
            border-bottom: none;
        }
        .status-ativo { color: #28a745; font-weight: 600; }
        .status-pausado { color: #ffc107; font-weight: 600; }
        .status-cancelado { color: #dc3545; font-weight: 600; }
        .status-pendente { color: #6c757d; font-weight: 600; }
        .timezone-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #007cba;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Teste de Recorrência</h1>
            <p>UpMarket Subscriptions - Simule pagamentos recorrentes manualmente</p>
            <p><small>Fuso Horário: America/Sao_Paulo - Horário de Brasília</small></p>
        </div>
        
        <div class="content">
            <div class="timezone-info">
                <strong>⏰ Fuso Horário Configurado:</strong> America/Sao_Paulo (Horário de Brasília)<br>
                <strong>Data/Hora Atual:</strong> <?php echo date('d/m/Y H:i:s'); ?>
            </div>

            <div class="form-section">
                <h2>Selecionar Assinatura para Teste</h2>
                <form method="post">
                    <div class="form-group">
                        <label for="subscription_id">Escolha uma assinatura:</label>
                        <select name="subscription_id" id="subscription_id" required>
                            <option value="">-- Selecione uma assinatura --</option>
                            <?php foreach ($assinaturas as $assinatura):
                                $status_class = 'status-' . $assinatura->status;
                                ?>
                            <option value="<?php echo $assinatura->id; ?>" 
                                    <?php selected($assinatura_selecionada, $assinatura->id); ?>>
                                #<?php echo $assinatura->id; ?> - 
                                <?php echo $assinatura->plan_name ?: 'Plano ' . $assinatura->id; ?> - 
                                <span class="<?php echo $status_class; ?>">
                                    <?php echo ucfirst($assinatura->status); ?>
                                </span>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="executar_recorrencia">
                            🔄 Executar Recorrência
                        </button>
                    </div>
                </form>
            </div>

            <?php if (!empty($resultado)): ?>
                <?php echo $resultado; ?>
            <?php endif; ?>

            <div class="form-section">
                <h3>📋 Assinaturas Disponíveis</h3>
                <div class="assinatura-info">
                    <?php if (empty($assinaturas)): ?>
                        <p>Nenhuma assinatura encontrada no banco de dados.</p>
                    <?php else: ?>
                        <?php foreach ($assinaturas as $assinatura):
                            $status_class = 'status-' . $assinatura->status;
                            ?>
                        <div class="assinatura-item">
                            <strong>#<?php echo $assinatura->id; ?></strong> - 
                            <?php echo $assinatura->plan_name ?: 'Plano ' . $assinatura->id; ?> - 
                            <span class="<?php echo $status_class; ?>">
                                <?php echo ucfirst($assinatura->status); ?>
                            </span> - 
                            Próxima: <?php echo formatar_data_brasil($assinatura->next_billing_date); ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <a href="<?php echo admin_url(); ?>" class="back-link">← Voltar para o Admin</a>
        </div>
    </div>

    <script>
        // Atualizar informações quando selecionar uma assinatura
        document.getElementById('subscription_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                console.log('Assinatura selecionada:', selectedOption.text);
            }
        });
    </script>
</body>
</html>