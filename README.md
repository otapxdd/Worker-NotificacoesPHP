# Worker-NotificacoesPHP - Sistema de Notificações Delivery

Sistema de automação para enviar notificações push via Firebase, otimizado e compatível com PHP 7.1.1.

## Estrutura

```text
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
│       └── FirebaseService.php   # Serviço Firebase
├── index.php                     # API principal
├── sync.php                      # Endpoint de sincronização
├── worker.php                    # Worker de notificações
└── composer.json                 # Dependências
Instalação
Instalar dependências:

Bash

composer install
Configurar variáveis de ambiente (criar arquivo .env baseado no exemplo abaixo):

Ini, TOML

DB_HOST=127.0.0.1
DB_NAME=seu_banco
DB_USER=root
DB_PASSWORD=sua_senha
AUTH0_AUDIENCE=[https://sua-api.com](https://sua-api.com)
AUTH0_ISSUER_BASE_URL=[https://seu-tenant.us.auth0.com/](https://seu-tenant.us.auth0.com/)
FIREBASE_PROJECT_ID=seu-project-id
GOOGLE_CREDENTIALS_PATH=./firebase-credentials.json
Colocar o arquivo firebase-credentials.json na raiz do projeto.

Uso
Endpoint de Sincronização
URL: http://localhost/NotificacoesDeliveryPHP/sync.php

Método: POST

Headers: Authorization: Bearer {token}

Body: {"fcmToken": "token_do_firebase"}

Worker de Notificações
O worker fica rodando em loop verificando pedidos pendentes a cada 5 segundos.

Executar diretamente:

Bash

php worker.php
Executar em background (Windows):

Opção 1 (Recomendado): Duplo clique em start-worker-hidden.bat (sem janela visível).

Opção 2: Duplo clique em start-worker.bat (minimizado).

Parar o processo: Duplo clique em stop-worker.bat.

Opção 3 - Usar PowerShell:

PowerShell

.\start-worker.ps1     # Iniciar
.\stop-worker.ps1      # Parar
Executar em background (Linux):

Bash

nohup php worker.php > /dev/null 2>&1 &
O que o worker faz:

Procura bancos de dados com padrão delivery_%.

Busca pedidos com pedidoEnviado IS NOT NULL e notificacao_enviada IS NULL.

Envia notificação Firebase para cada pedido.

Atualiza notificacao_enviada = NOW() após sucesso.

Repete a cada 5 segundos.

Compatibilidade
PHP 7.1.1+

MySQL 5.7+

Extensões PHP necessárias: PDO, cURL, JSON, OpenSSL


---

### O que fazer agora?
Depois de salvar o arquivo com o conteúdo acima, você precisa rodar os comandos no terminal para confirmar essa mudança, já que você editou o arquivo:

```bash
git add README.md
git commit -m "Corrigindo README e removendo dados sensiveis"
git push