<?php
/**
 * Teste de Recorrência - UpMarket Subscriptions
 * Acesse: /wp-content/plugins/upmarket-subscriptions/teste-recorrencia.php
 */

// Carregar o WordPress
require_once('../../../wp-load.php');

// Verificar permissões
if (!current_user_can('manage_options')) {
    wp_die('Acesso negado. Você precisa ser administrador.');
}

// Carregar as classes do plugin
require_once __DIR__ . '/includes/autoload.php';

use UPMarket\Subscriptions\Entities\Subscription;
use UPMarket\Subscriptions\Services\SubscriptionManager;
use UPMarket\Subscriptions\Core\Logger;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Teste de Recorrência - UpMarket</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f4f4; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; background: #e8f5e8; padding: 10px; border-radius: 4px; }
        .error { color: red; background: #ffe8e8; padding: 10px; border-radius: 4px; }
        .info { color: blue; background: #e8f4ff; padding: 10px; border-radius: 4px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow: auto; }
        .btn { background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Teste de Recorrência - UpMarket</h1>
        
        <?php
        // ID da assinatura para teste
        $subscription_id = 76;

echo "<div class='info'>";
echo "<h3>📋 Informações da Assinatura #{$subscription_id}</h3>";

try {
    $subscription = new Subscription($subscription_id);

    if (!$subscription->exists()) {
        throw new Exception("Assinatura #{$subscription_id} não encontrada");
    }

    echo "<p><strong>Status:</strong> " . $subscription->get_status() . "</p>";
    echo "<p><strong>BrandTid:</strong> " . $subscription->get_meta('rede_brand_tid') . "</p>";
    echo "<p><strong>Últimos 4 dígitos:</strong> " . $subscription->get_meta('card_last_four') . "</p>";
    echo "<p><strong>Próxima cobrança:</strong> " . $subscription->get_next_billing_date()->format('d/m/Y') . "</p>";

    echo "</div>";

    // Botão para executar o teste
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='?run_test=1' class='btn'>🚀 EXECUTAR TESTE DE RECORRÊNCIA</a>";
    echo "</div>";

    // Executar teste se solicitado
    if (isset($_GET['run_test'])) {
        echo "<div class='info'>";
        echo "<h3>🔄 Executando Cobrança Recorrente...</h3>";

        $manager = new SubscriptionManager();
        $result = $manager->process_recurring_payment($subscription);

        echo "<h4>📊 Resultado:</h4>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";

        if ($result['success']) {
            echo "<div class='success'>";
            echo "✅ <strong>SUCESSO!</strong> Cobrança recorrente processada com sucesso!";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "❌ <strong>FALHA!</strong> Erro na cobrança recorrente.";
            echo "</div>";
        }

        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "❌ <strong>ERRO:</strong> " . $e->getMessage();
    echo "</div>";
}
?>
        
        <hr>
        <div style="margin-top: 20px;">
            <h4>📝 Logs Recentes:</h4>
            <?php
    // Mostrar logs recentes para debug
    $log_file = WP_CONTENT_DIR . '/upmkt-debug.log';
if (file_exists($log_file)) {
    $logs = file_get_contents($log_file);
    echo "<pre style='max-height: 300px;'>" . esc_html($logs) . "</pre>";
} else {
    echo "<p>Nenhum log encontrado.</p>";
}

echo "reference:".upmkt_build_reference(76);
?>
        </div>
    </div>
</body>
</html>