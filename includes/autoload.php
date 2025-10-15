<?php

/**
 * Autoloader PSR-4 manual para o plugin
 *
 * @package UPMarket\Subscriptions
 */

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Autoloader manual para classes do plugin
 *
 * @param string $class Nome da classe
 */
spl_autoload_register(function ($class) {
    // Namespace do nosso plugin
    $prefix = 'UPMarket\\Subscriptions\\';

    // Verifica se a classe pertence ao nosso namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Obtém o nome relativo da classe
    $relative_class = substr($class, $len);

    // Converte namespace para caminho de arquivo
    $file = UPMS_PLUGIN_PATH . 'includes/' . str_replace('\\', '/', $relative_class) . '.php';

    // Se o arquivo existe, carrega
    if (file_exists($file)) {
        require_once $file;
    }
});
