<?php

namespace UPMarket\Subscriptions\Admin;

use UPMarket\Subscriptions\Testing\TestRunner;

/**
 * Página de testes no admin
 *
 * @package UPMarket\Subscriptions\Admin
 */
class TestPage
{
    /**
     * Construtor
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_test_page']);
        add_action('admin_init', [$this, 'handle_export']);
    }

    /**
     * Adiciona página de testes
     */
    public function add_test_page(): void
    {
        add_submenu_page(
            'upmkt-subscriptions',
            'Testes - Assinaturas',
            'Testes',
            'manage_upmkt_subscriptions',
            'upmkt-tests',
            [$this, 'render_test_page']
        );
    }

    /**
     * Manipula exportação de relatório (executado antes de qualquer output)
     */
    public function handle_export(): void
    {
        if (!isset($_POST['export_report']) || !isset($_POST['upmkt_export_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['upmkt_export_nonce'], 'upmkt_export_report')) {
            wp_die('Erro de segurança. Nonce inválido.');
        }

        if (!current_user_can('manage_upmkt_subscriptions')) {
            wp_die('Sem permissão para exportar relatórios.');
        }

        // Buscar resultados dos testes da sessão ou recriá-los
        $test_results = $this->get_test_results();

        $this->export_test_report($test_results);
    }

    /**
     * Renderiza página de testes
     */
    public function render_test_page(): void
    {
        if (!current_user_can('manage_upmkt_subscriptions')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        $test_results = [];

        if (isset($_POST['run_tests']) && wp_verify_nonce($_POST['upmkt_test_nonce'], 'upmkt_run_tests')) {
            $test_runner = new TestRunner();
            $test_results = $test_runner->run_all_tests();

            // Salvar resultados na sessão temporária para possível exportação
            $this->save_test_results($test_results);
        } else {
            // Tentar carregar resultados salvos
            $test_results = $this->get_test_results();
        }
        ?>
        <div class="wrap upmkt-admin">
            <h1>Testes - UP Market Subscriptions</h1>
            
            <div class="upmkt-card">
                <h2>Testes Automatizados</h2>
                <p>Execute testes automatizados para verificar se todas as funcionalidades do plugin estão funcionando corretamente.</p>
                
                <form method="post">
                    <?php wp_nonce_field('upmkt_run_tests', 'upmkt_test_nonce'); ?>
                    <input type="submit" name="run_tests" class="button button-primary" value="Executar Testes">
                </form>
            </div>
            
            <?php if (!empty($test_results)): ?>
                <div class="upmkt-card">
                    <h2>Resultados dos Testes</h2>
                    
                    <?php
                    $passed = array_filter($test_results, function ($result) {
                        return $result['passed'];
                    });

                $total = count($test_results);
                $passed_count = count($passed);
                $success_rate = $total > 0 ? round(($passed_count / $total) * 100, 2) : 0;
                ?>
                    
                    <div class="upmkt-test-summary">
                        <div class="upmkt-test-stat">
                            <span class="upmkt-stat-number"><?php echo $total; ?></span>
                            <span class="upmkt-stat-label">Total de Testes</span>
                        </div>
                        
                        <div class="upmkt-test-stat">
                            <span class="upmkt-stat-number" style="color: #46b450;"><?php echo $passed_count; ?></span>
                            <span class="upmkt-stat-label">Aprovados</span>
                        </div>
                        
                        <div class="upmkt-test-stat">
                            <span class="upmkt-stat-number" style="color: #dc3232;"><?php echo $total - $passed_count; ?></span>
                            <span class="upmkt-stat-label">Falhas</span>
                        </div>
                        
                        <div class="upmkt-test-stat">
                            <span class="upmkt-stat-number"><?php echo $success_rate; ?>%</span>
                            <span class="upmkt-stat-label">Taxa de Sucesso</span>
                        </div>
                    </div>
                    
                    <div class="upmkt-test-results">
                        <?php foreach ($test_results as $result): ?>
                            <div class="upmkt-test-result <?php echo $result['passed'] ? 'test-passed' : 'test-failed'; ?>">
                                <div class="test-header">
                                    <span class="test-status">
                                        <?php echo $result['passed'] ? '✅' : '❌'; ?>
                                    </span>
                                    <strong class="test-name"><?php echo esc_html($result['test']); ?></strong>
                                </div>
                                <div class="test-message"><?php echo esc_html($result['message']); ?></div>
                                <div class="test-timestamp">
                                    <small><?php echo esc_html($result['timestamp']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($total > 0 && $passed_count === $total): ?>
                        <div class="notice notice-success">
                            <p>🎉 Todos os testes passaram! O plugin está funcionando corretamente.</p>
                        </div>
                    <?php elseif ($total > 0): ?>
                        <div class="notice notice-error">
                            <p>⚠️ Alguns testes falharam. Verifique os logs para mais detalhes.</p>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-warning">
                            <p>ℹ️ Nenhum teste foi executado ainda.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="upmkt-card">
                    <h3>Exportar Relatório</h3>
                    <p>Exporte um relatório completo dos testes para análise.</p>
                    <form method="post">
                        <input type="hidden" name="export_report" value="1">
                        <?php wp_nonce_field('upmkt_export_report', 'upmkt_export_nonce'); ?>
                        <input type="submit" class="button" value="Exportar Relatório em TXT">
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .upmkt-test-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .upmkt-test-stat {
            text-align: center;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .upmkt-stat-number {
            display: block;
            font-size: 2em;
            font-weight: bold;
        }
        
        .upmkt-stat-label {
            font-size: 0.9em;
            color: #666;
        }
        
        .upmkt-test-results {
            margin-top: 20px;
        }
        
        .upmkt-test-result {
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid;
            border-radius: 4px;
        }
        
        .test-passed {
            background: #d4edda;
            border-left-color: #28a745;
        }
        
        .test-failed {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        
        .test-header {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .test-status {
            margin-right: 10px;
            font-size: 1.2em;
        }
        
        .test-name {
            flex: 1;
        }
        
        .test-message {
            margin-bottom: 5px;
        }
        
        .test-timestamp {
            font-size: 0.8em;
            color: #666;
        }
        </style>
        <?php
    }

    /**
     * Salva resultados dos testes temporariamente
     */
    private function save_test_results(array $test_results): void
    {
        update_option('upmkt_last_test_results', $test_results, false);
    }

    /**
     * Obtém resultados dos testes salvos
     */
    private function get_test_results(): array
    {
        return get_option('upmkt_last_test_results', []);
    }

    /**
     * Exporta relatório de testes
     */
    private function export_test_report(array $test_results): void
    {
        try {
            $test_runner = new TestRunner();
            $test_runner->set_results($test_results);
            $report = $test_runner->generate_report();

            // Limpar buffer de output para evitar problemas com headers
            if (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="upmkt-test-report-' . date('Y-m-d-H-i-s') . '.txt"');
            header('Content-Length: ' . strlen($report));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo $report;
            exit;

        } catch (\Exception $e) {
            wp_die('Erro ao gerar relatório: ' . $e->getMessage());
        }
    }
}
