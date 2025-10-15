# 📚 UP Market Subscriptions - Documentação de Uso

## 📋 Visão Geral

Sistema completo de assinaturas recorrentes para WordPress com integração com a Rede.

## 🚀 Instalação Rápida

1. **Upload do Plugin**

   ```bash
   # Via WordPress Admin
   - Vá em Plugins > Adicionar Novo
   - Faça upload do arquivo ZIP
   - Ative o plugin

   # Via FTP
   - Extraia o plugin para /wp-content/plugins/
   - Ative no painel WordPress
   ```

2. **Configuração Inicial**
   - Acesse `Assinaturas > Configurações`
   - Configure o gateway da Rede
   - Crie seus planos de assinatura

## 🎯 Shortcodes Disponíveis

### 1. Lista de Planos

```php
[upmkt_subscription_plans]
```

**Parâmetros:**

- `layout`: `grid` ou `list` (padrão: `grid`)
- `columns`: Número de colunas (padrão: `3`)
- `show_trial`: `yes` ou `no` (padrão: `yes`)
- `class`: Classe CSS adicional

**Exemplo:**

```php
[upmkt_subscription_plans layout="grid" columns="2" class="meus-planos"]
```

### 2. Checkout

```php
[upmkt_checkout]
```

**Uso:**

- Acessado via URL: `?plan_id=123`
- Requer usuário logado
- Processa pagamento via Rede

### 3. Área do Cliente

```php
[upmkt_customer_area]
```

**Funcionalidades:**

- Lista assinaturas do usuário
- Cancelamento de assinaturas
- Histórico de pagamentos

## ⚙️ Configuração da Rede

### 1. Credenciais

- **PV (Affiliation)**: Fornecido pela Rede
- **Token**: Token de autenticação
- **Ambiente**: Sandbox ou Produção

### 2. Webhooks

Configure na Rede o webhook:

```
https://seusite.com/upmkt-webhook/rede/
```

### 3. Testes

Use o ambiente Sandbox para testes:

- Cartão de teste: `4111 1111 1111 1111`
- CVV: `123`
- Validade: data futura

## 🔧 API para Desenvolvedores

### Criar Assinatura Programaticamente

```php
$subscription_manager = new UPMarket\Subscriptions\Services\SubscriptionManager();

$result = $subscription_manager->create_subscription(
    $user_id,
    $plan_id,
    [
        'payment_method' => 'rede',
        'card_number' => '4111111111111111',
        'card_expiry' => '12/25',
        'card_cvv' => '123',
        'card_holder' => 'Nome do Cliente'
    ]
);
```

### Gerenciar Assinatura

```php
$subscription = new UPMarket\Subscriptions\Entities\Subscription($subscription_id);

// Cancelar
$subscription->cancel();

// Pausar
$subscription->pause();

// Retomar
$subscription->resume();
```

## 🗃️ Estrutura de Dados

### Tabelas do Banco

- `wp_upmkt_subscription_plans` - Planos de assinatura
- `wp_upmkt_subscriptions` - Assinaturas dos usuários
- `wp_upmkt_subscription_meta` - Metadados das assinaturas

### Status de Assinatura

- `active` - Ativa e em dia
- `pending` - Aguardando pagamento
- `cancelled` - Cancelada pelo usuário
- `expired` - Expirada
- `paused` - Temporariamente pausada

## 🔍 Troubleshooting

### Problemas Comuns

1. **Gateway não aparece**

   - Verifique se está habilitado nas configurações
   - Confirme se as credenciais estão corretas

2. **Pagamento recusado**

   - Teste com cartão de teste no Sandbox
   - Verifique logs em `Assinaturas > Testes`

3. **Webhook não funciona**
   - Confirme URL configurada na Rede
   - Verifique se permalinks estão ativos

### Logs e Debug

- Logs são salvos em `wp-content/upmkt-debug.log`
- Use `Assinaturas > Testes` para diagnóstico
- Habilite `WP_DEBUG` para mais detalhes

## 📞 Suporte

- **Documentação**: [Link para documentação completa]
- **Suporte Técnico**: suporte@upmarket.com
- **GitHub**: [Repositório do projeto]

---

_Última atualização: <?php echo date('Y-m-d'); ?>_
