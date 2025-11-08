<?php

namespace UPMarket\Subscriptions\Services;

use UPMarket\Subscriptions\Core\Logger;

class OAuthService
{
    private $clientId;
    private $clientSecret;
    private $sandbox;
    private $access_token;
    private $token_expires;

    public function __construct(string $clientId, string $clientSecret, bool $sandbox = false)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->sandbox = $sandbox;
        $this->access_token = null;
        $this->token_expires = null;
    }

    /**
     * Obtém access token usando OAuth 2.0
     */
    public function get_access_token(): string
    {
        // Verifica se o token atual ainda é válido (com margem de 5 minutos)
        if ($this->access_token && $this->token_expires && time() < ($this->token_expires - 300)) {
            return $this->access_token;
        }

        // URLs CORRETAS baseadas na documentação
        $auth_url = $this->sandbox
            ? 'https://rl7-sandbox-api.useredecloud.com.br/oauth2/token'
            : 'https://api.userede.com.br/redelabs/oauth2/token';

        $credentials = base64_encode($this->clientId . ':' . $this->clientSecret);

        $response = wp_remote_post($auth_url, [
            'headers' => [
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Erro na autenticação OAuth: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            Logger::instance()->error("OAuth error - Code: {$status_code}, Response: {$body}", 'gateways');
            throw new \Exception('Falha na autenticação OAuth: ' . ($data['error_description'] ?? 'Unknown error'));
        }

        $this->access_token = $data['access_token'];
        $this->token_expires = time() + $data['expires_in']; // 24 minutos (1439 segundos)

        // Salva o token temporariamente em transiente
        set_transient('upmkt_rede_oauth_token', [
            'access_token' => $this->access_token,
            'expires' => $this->token_expires
        ], $data['expires_in'] - 300); // Expira 5 minutos antes

        return $this->access_token;
    }

    /**
     * Tenta carregar token do cache
     */
    public function load_cached_token(): bool
    {
        $cached = get_transient('upmkt_rede_oauth_token');
        if ($cached && isset($cached['access_token']) && $cached['expires'] > time()) {
            $this->access_token = $cached['access_token'];
            $this->token_expires = $cached['expires'];
            return true;
        }
        return false;
    }
}
