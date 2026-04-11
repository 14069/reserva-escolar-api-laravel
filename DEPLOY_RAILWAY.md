# Deploy Laravel no Railway

Este guia prepara a API Laravel para substituir a API antiga em producao.

## 1. Estado esperado

Antes do deploy, este projeto ja deve:

- responder as rotas canônicas como `/login`, `/bookings`, `/notifications`
- responder os aliases legados `.php` ainda usados por clientes antigos
- passar em `php artisan test`

## 2. Variaveis de ambiente

Use [.env.railway.example](/home/agacy-junior/RESERVA_ESCOLAR/reserva_escolar_api_laravel/.env.railway.example) como base.

Minimo recomendado no Railway:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://api.reservaescolar.com.br`
- `APP_KEY=` valor gerado por `php artisan key:generate --show`
- `LOG_CHANNEL=stderr`
- `LOG_LEVEL=info`
- `DB_CONNECTION=pgsql`
- `DB_HOST=...`
- `DB_PORT=5432`
- `DB_DATABASE=postgres`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`
- `DB_SSLMODE=require`
- `RESERVA_ALLOWED_ORIGINS=https://app.reservaescolar.com.br,https://painel.reservaescolar.com.br`
- `RESERVA_DIAGNOSTIC_TOKEN=` token longo e seguro
- `RESERVA_CRON_TOKEN=` token longo e seguro

Se preferir manter compatibilidade total com a configuracao antiga, preencha tambem:

- `RESERVA_DB_URL=postgresql://...`

Para homologacao, use:

- `APP_URL=https://api-hml.reservaescolar.com.br`
- `RESERVA_ALLOWED_ORIGINS=https://app-hml.reservaescolar.com.br,https://painel-hml.reservaescolar.com.br`

Durante a virada, so inclua dominios antigos `.app.br` em `RESERVA_ALLOWED_ORIGINS` se eles ainda estiverem servindo frontend real. A configuracao de CORS agora falha fechada em producao quando essa variavel nao e definida.

## 3. Banco de dados

Hoje o projeto Laravel esta pronto para usar o schema existente da API atual, mas ainda nao possui migrations completas do dominio real.

Isso significa:

- para homologacao imediata, aponte o Laravel para o mesmo banco/schema ja usado pela API atual
- para um ambiente novo do zero, ainda sera necessario migrar o schema completo para migrations Laravel

## 4. Subida local para validacao

```bash
cp .env.railway.example .env
php artisan key:generate --show
php artisan test
php artisan serve --host=0.0.0.0 --port=8080
```

Checks uteis:

```bash
curl -i http://127.0.0.1:8080/health
curl -i http://127.0.0.1:8080/health.php
curl -i -X POST http://127.0.0.1:8080/login
```

## 5. Deploy no Railway

O repositório agora inclui [Dockerfile](/home/agacy-junior/RESERVA_ESCOLAR/reserva_escolar_api_laravel/Dockerfile), entao o Railway pode buildar direto a aplicacao.

Passos:

1. Criar ou abrir o projeto no Railway.
2. Conectar este repositório.
3. Preencher as variáveis acima.
4. Adicionar o domínio `api.reservaescolar.com.br`.
5. Fazer o primeiro deploy.

Se o log de build mostrar `composer install --optimize-autoloader --no-scripts --no-interaction` sem `--no-dev`, o Railway provavelmente nao esta usando o [Dockerfile](/home/agacy-junior/RESERVA_ESCOLAR/reserva_escolar_api_laravel/Dockerfile) deste repositório. Nesse caso, ajuste o serviço para buildar pelo Dockerfile antes de validar o deploy.

## 6. Validacao apos deploy

Executar pelo menos:

- `GET /health`
- `GET /health.php`
- `POST /login` com credenciais válidas
- `GET /bookings` com Bearer token
- `GET /get_all_bookings.php` com Bearer token
- `GET /notifications/unread-count` com Bearer token

## 7. Cuidados importantes

- `APP_DEBUG` deve ficar `false` em produção.
- o `.env` local atual ainda usa o dominio antigo `.app.br`; nao replique esse valor no Railway sem revisar.
- `.env` nao deve ser versionado nem reaproveitado com segredos antigos.
- Se o repositório ja teve segredos reais commitados, o ideal e rotacionar credenciais antes da virada.
- So faça a troca do Flutter para esta API depois de validar login, bookings e notifications em homologacao.
