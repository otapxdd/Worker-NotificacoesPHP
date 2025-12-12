# Sistema de Notificações Delivery - PHP 7.1.1

Sistema de notificações push para delivery, totalmente compatível com PHP 7.1.1.

## Estrutura

```
NotificacoesDeliveryPHP/
├── src/
│   ├── Config/
│   │   └── Database.php          # Conexão com banco de dados
│   ├── Controllers/
│   │   └── UserController.php    # Controller de usuários
│   ├── Helpers/
│   │   └── InputValidator.php    # Validação de inputs
│   ├── Security/
│   │   └── JwtAuth.php           # Autenticação JWT
│   └── Services/
│       └── FirebaseService.php    # Serviço Firebase
├── index.php                      # API principal
├── sync.php                       # Endpoint de sincronização
├── worker.php                     # Worker de notificações
└── composer.json                  # Dependências
```

## Instalação

1. Instalar dependências:
```bash
composer install
```

2. Configurar variáveis de ambiente (criar arquivo `.env`):
```
DB_HOST=192.168.1.122
DB_USER=root
DB_PASSWORD=
AUTH0_AUDIENCE=https://api-delivery.com
AUTH0_ISSUER_BASE_URL=https://dev-bj0bt4o68ttf3egu.us.auth0.com/
FIREBASE_PROJECT_ID=seu-project-id
GOOGLE_CREDENTIALS_PATH=./firebase-credentials.json
```

3. Colocar arquivo `firebase-credentials.json` na raiz do projeto.

## Uso

### Endpoint de Sincronização
- URL: `http://localhost/NotificacoesDeliveryPHP/sync.php`
- Método: POST
- Headers: `Authorization: Bearer {token}`
- Body: `{"fcmToken": "token_do_firebase"}`

### Worker de Notificações

O worker fica rodando em loop verificando pedidos pendentes a cada 5 segundos.

**Executar diretamente:**
```bash
php worker.php
```

**Executar em background (Windows):**

**Opção 1 - Rodar escondido (Recomendado - sem janela visível):**
- Duplo clique em `start-worker-hidden.bat` para iniciar escondido
- Duplo clique em `stop-worker.bat` para parar
- O worker roda completamente escondido (sem janela)

**Opção 2 - Rodar minimizado:**
- Duplo clique em `start-worker.bat` para iniciar minimizado
- Duplo clique em `stop-worker.bat` para parar

**Opção 3 - Usar PowerShell:**
```powershell
.\start-worker.ps1    # Iniciar
.\stop-worker.ps1      # Parar
```

**Opção 4 - Manual:**
```bash
start /MIN php worker.php
```

**Executar em background (Linux):**
```bash
nohup php worker.php > /dev/null 2>&1 &
```

**O que o worker faz:**
1. Procura bancos de dados com padrão `delivery_%`
2. Busca pedidos com `pedidoEnviado IS NOT NULL` e `notificacao_enviada IS NULL`
3. Envia notificação Firebase para cada pedido
4. Atualiza `notificacao_enviada = NOW()` após sucesso
5. Repete a cada 5 segundos

## Compatibilidade

- PHP 7.1.1+
- MySQL 5.7+
- Extensões PHP necessárias: PDO, cURL, JSON, OpenSSL

