# Data Migration

Este projeto agora inclui um script para exportar os dados do banco legado e importar no banco novo com colunas explicitas.

Arquivo:

- [scripts/migrate_domain_data.sh](/home/agacy-junior/RESERVA_ESCOLAR/reserva_escolar_api_laravel/scripts/migrate_domain_data.sh)

## O que ele migra

- `schools`
- `users`
- `resource_categories`
- `resources`
- `class_groups`
- `subjects`
- `lesson_slots`
- `bookings`
- `booking_lessons`
- `notifications`

O script nao migra tabelas de infraestrutura como `sessions`, `cache`, `jobs`, `failed_jobs` ou `password_reset_tokens`.

## Pre-requisitos

- `psql` instalado
- banco novo ja migrado com:

```bash
php artisan migrate --force
```

- duas URLs PostgreSQL:

```bash
export OLD_DB_URL='postgresql://usuario:senha@host-antigo:5432/banco?sslmode=require'
export NEW_DB_URL='postgresql://usuario:senha@host-novo:5432/banco?sslmode=require'
```

## Uso recomendado

Exportar e importar em um fluxo unico, limpando as tabelas do banco novo antes:

```bash
OLD_DB_URL='postgresql://...' \
NEW_DB_URL='postgresql://...' \
./scripts/migrate_domain_data.sh --truncate-new --output-dir ./tmp/domain-migration
```

Exportar apenas:

```bash
OLD_DB_URL='postgresql://...' \
./scripts/migrate_domain_data.sh --skip-import --output-dir ./tmp/domain-migration
```

Importar novamente a partir dos CSVs ja exportados:

```bash
NEW_DB_URL='postgresql://...' \
./scripts/migrate_domain_data.sh --skip-export --truncate-new --output-dir ./tmp/domain-migration
```

## Como o script lida com diferencas de schema

- usa colunas explicitas no export e no import
- preserva os `id`s das tabelas
- reseta as sequences no banco novo depois da importacao
- se uma coluna obrigatoria nao existir no banco antigo, ele falha cedo
- se uma coluna opcional nao existir, ele preenche com `NULL`, `CURRENT_TIMESTAMP` ou valor padrao seguro

Colunas opcionais tratadas com fallback:

- `users.api_token`
- `users.api_token_expires_at`
- `bookings.cancelled_at`
- `bookings.completed_at`
- `bookings.completed_by_user_id`
- `bookings.cancelled_by_user_id`
- `bookings.completion_feedback`
- `bookings.idempotency_key`
- `notifications.type`
- `notifications.booking_id`
- `notifications.metadata_json`
- `notifications.read_at`

## Validacao depois da importacao

O script imprime contagens por tabela no banco de origem e no de destino. Depois disso, valide a API com pelo menos:

```bash
curl -i https://api.reservaescolar.com.br/health
curl -i -X POST https://api.reservaescolar.com.br/login
```

E teste manualmente:

- login com uma escola real
- listagem de bookings
- contagem de notificacoes nao lidas
- consulta de recursos, turmas, materias e aulas
