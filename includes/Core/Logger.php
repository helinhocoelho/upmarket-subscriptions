<?php

namespace UPMarket\Subscriptions\Core;

/**
 * Sistema de logs para o plugin
 *
 * @package UPMarket\Subscriptions\Core
 */
class Logger
{
    /**
     * @var Logger Instância única
     */
    private static $instance = null;

    /**
     * @var bool Se o debug está ativo
     */
    private $debug_enabled = false;

    /**
     * @var string Caminho do arquivo de log
     */
    private $log_file = '';

    /**
     * Construtor privado (Singleton)
     */
    private function __construct()
    {
        $this->debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
        $this->log_file = WP_CONTENT_DIR . '/upms-debug.log';

        add_action('init', [$this, 'init']);
    }

    /**
     * Retorna instância única
     *
     * @return Logger
     */
    public static function instance(): Logger
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializa o logger
     */
    public function init(): void
    {
        if ($this->debug_enabled) {
            add_action('upms_log', [$this, 'log'], 10, 3);
        }
    }

    /**
     * Registra um log
     *
     * @param string $message
     * @param string $level
     * @param string $context
     */
    public function log(string $message, string $level = 'info', string $context = 'core'): void
    {
        if (!$this->debug_enabled) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] [{$level}] [{$context}] {$message}" . PHP_EOL;

        // Log para arquivo
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);

        // Log para error_log se for erro
        if (in_array($level, ['error', 'critical'])) {
            error_log("UPMS {$level}: {$message}");
        }
    }

    /**
     * Log de debug
     *
     * @param string $message
     * @param string $context
     */
    public function debug(string $message, string $context = 'core'): void
    {
        $this->log($message, 'debug', $context);
    }

    /**
     * Log de info
     *
     * @param string $message
     * @param string $context
     */
    public function info(string $message, string $context = 'core'): void
    {
        $this->log($message, 'info', $context);
    }

    /**
     * Log de warning
     *
     * @param string $message
     * @param string $context
     */
    public function warning(string $message, string $context = 'core'): void
    {
        $this->log($message, 'warning', $context);
    }

    /**
     * Log de erro
     *
     * @param string $message
     * @param string $context
     */
    public function error(string $message, string $context = 'core'): void
    {
        $this->log($message, 'error', $context);
    }

    /**
     * Limpa os logs
     *
     * @return bool
     */
    public function clear_logs(): bool
    {
        if (file_exists($this->log_file)) {
            return unlink($this->log_file);
        }
        return true;
    }

    /**
     * Retorna o conteúdo dos logs
     *
     * @return string
     */
    public function get_logs(): string
    {
        if (file_exists($this->log_file)) {
            return file_get_contents($this->log_file);
        }
        return '';
    }
}
