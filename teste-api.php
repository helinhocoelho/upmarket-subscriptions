<?php
/**
 * Teste Fluxo Correto - Card-on-File com brandTid
 */

define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Acesso negado.');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Dados OFICIAIS
$pv = '12673681';
$token = '28175d7051e54599a0fb2857021d60ae';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Teste Fluxo Correto Rede</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h1>🔍 Teste Fluxo Correto - Card-on-File</h1>

    <?php
    $url = 'https://sandbox-erede.useredecloud.com.br/v1/transactions';
$headers = [
    'Authorization' => 'Basic ' . base64_encode($pv . ':' . $token),
    'Content-Type' => 'application/json',
    'Accept' => 'application/json'
];

echo "<div class='info'>";
echo "<h2>📋 Fluxo Baseado na Documentação:</h2>";
echo "<ol>";
echo "<li><strong>Primeira transação:</strong> storageCard=1 + securityCode (registrar cartão)</li>";
echo "<li><strong>Salvar:</strong> brandTid e transactionLinkId da resposta</li>";
echo "<li><strong>Transações seguintes:</strong> storageCard=2 + brandTid (sem securityCode)</li>";
echo "</ol>";
echo "</div>";

echo "<h2>1. Primeira Transação - Registrar Cartão (storageCard=1)</h2>";

$transacao1 = [
    'capture' => true,
    'kind' => 'credit',
    'reference' => 'registro' . time(),
    'amount' => 100,
    'installments' => 1,
    'cardholderName' => 'TITULAR CARTAO',
    'cardNumber' => '5448280000000007',
    'expirationMonth' => '01',
    'expirationYear' => '2035',
    'securityCode' => '123', // ⭐ Obrigatório na primeira
    'subscription' => true,
    'origin' => 1,
    'storageCard' => "1", // ⭐ Registrar cartão
    'transactionCredentials' => [
        'credentialId' => "01" // ⭐ Para Mastercard
    ],
    'softDescriptor' => 'UP Market Reg'
];

echo "<pre>";
print_r($transacao1);
echo "</pre>";

$response1 = wp_remote_post($url, [
    'headers' => $headers,
    'body' => json_encode($transacao1),
    'timeout' => 30
]);

if (is_wp_error($response1)) {
    echo "<p class='error'>❌ Erro: " . $response1->get_error_message() . "</p>";
} else {
    $status1 = wp_remote_retrieve_response_code($response1);
    $body1 = wp_remote_retrieve_body($response1);
    $result1 = json_decode($body1, true);

    echo "<h3>Resposta:</h3>";
    echo "<pre>";
    print_r($result1);
    echo "</pre>";

    if ($status1 === 200) {
        $brand_tid = $result1['brandTid'] ?? '';
        // $transaction_link_id = $result1['transactionLinkId'] ?? ''; // Disponível a partir de out/2025

        echo "<p class='success'>✅ Primeira transação OK!</p>";
        echo "<p><strong>BrandTid recebido:</strong> " . $brand_tid . "</p>";
        // echo "<p><strong>TransactionLinkId:</strong> " . $transaction_link_id . "</p>";

        // ⭐ SALVAR NO BANCO (simulação)
        echo "<h3>💾 Simulando salvamento no banco:</h3>";
        echo "<pre>";
        echo "INSERT INTO upmkt_subscription_meta:\n";
        echo "- subscription_id: 123\n";
        echo "- meta_key: 'rede_brand_tid'\n";
        echo "- meta_value: '" . $brand_tid . "'\n";
        // echo "- meta_key: 'rede_transaction_link_id'\n";
        // echo "- meta_value: '" . $transaction_link_id . "'\n";
        echo "- meta_key: 'card_last_four'\n";
        echo "- meta_value: '0007'\n";
        echo "</pre>";

        sleep(2);

        echo "<h2>2. Segunda Transação - Usar Cartão Registrado (storageCard=2)</h2>";

        $transacao2 = [
            'capture' => true,
            'kind' => 'credit',
            'reference' => 'usar' . time(),
            'amount' => 200,
            'installments' => 1,
            'cardholderName' => 'TITULAR CARTAO',
            'cardNumber' => '5448280000000007',
            'expirationMonth' => '01',
            'expirationYear' => '2035',
            // ⭐ SEM securityCode (já registrado)
            'subscription' => true,
            'origin' => 1,
            'storageCard' => "2", // ⭐ Cartão já registrado
            'brandTid' => $brand_tid, // ⭐ BrandTid da primeira transação
            'transactionCredentials' => [
                'credentialId' => "02" // ⭐ Para transações seguintes
            ],
            'softDescriptor' => 'UP Market Use'
        ];

        echo "<pre>";
        print_r($transacao2);
        echo "</pre>";

        $response2 = wp_remote_post($url, [
            'headers' => $headers,
            'body' => json_encode($transacao2),
            'timeout' => 30
        ]);

        if (is_wp_error($response2)) {
            echo "<p class='error'>❌ Erro: " . $response2->get_error_message() . "</p>";
        } else {
            $status2 = wp_remote_retrieve_response_code($response2);
            $body2 = wp_remote_retrieve_body($response2);
            $result2 = json_decode($body2, true);

            echo "<h3>Resposta:</h3>";
            echo "<pre>";
            print_r($result2);
            echo "</pre>";

            if ($status2 === 200) {
                echo "<p class='success'>🎉 🎉 SUCESSO TOTAL! Card-on-File funcionando!</p>";
                echo "<p><strong>Recorrência confirmada com:</strong></p>";
                echo "<ul>";
                echo "<li>storageCard=2</li>";
                echo "<li>brandTid: " . $brand_tid . "</li>";
                echo "<li>Sem securityCode</li>";
                echo "</ul>";
            } else {
                echo "<p class='error'>❌ Falhou: " . ($result2['returnMessage'] ?? 'Erro') . "</p>";
            }
        }
    }
}
?>

    <h2>3. 🎯 Implementação Final no Plugin</h2>
    
    <h3>Fluxo Correto:</h3>
    <pre>
process_initial_payment():
  - storageCard = "1"
  - securityCode = [obrigatório]
  - credentialId = "01"
  - Salvar brandTid da resposta

process_recurring_payment():
  - storageCard = "2" 
  - brandTid = [salvo anteriormente]
  - credentialId = "02"
  - SEM securityCode
    </pre>

    <h3>Modificações no Rede.php:</h3>
    <ol>
        <li>Adicionar <code>storageCard</code> e <code>transactionCredentials</code></li>
        <li>Salvar <code>brandTid</code> da resposta da primeira transação</li>
        <li>Usar <code>brandTid</code> salvo nas recorrências</li>
        <li>Remover <code>securityCode</code> das recorrências</li>
    </ol>

</body>
</html>