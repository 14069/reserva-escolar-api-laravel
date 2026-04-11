# Reserva Escolar API Laravel

Migração da API `reserva_escolar_api_railway` para Laravel 13.

## Status

O bootstrap real do Laravel já está organizado:
- `routes/api.php` é carregado sem prefixo `/api`
- timezone, locale e nome da aplicação foram alinhados com o projeto
- configuração de banco aceita `DB_*` e também os envs legados `RESERVA_DB_*`
- CORS foi configurado com base em `RESERVA_ALLOWED_ORIGINS`
- erros de validação, rota inexistente e método não permitido retornam JSON no formato da API

## Estratégia de rotas

A API nova agora expõe 3 camadas de rotas ao mesmo tempo:

1. Rotas canônicas Laravel
   Exemplos: `/account/change-password`, `/schools/register`, `/internal/check-database-connection`, `/admin/teachers`, `/admin/subjects`
2. Rotas de compatibilidade da migração
   Exemplos: `/change-my-password`, `/register-school`, `/check-supabase-connection`, `/teachers`, `/subjects-admin`
3. Aliases legados `.php`
   Exemplos: `/change_my_password.php`, `/register_school.php`, `/check_supabase_connection.php`, `/get_teachers.php`, `/get_subjects_admin.php`

Essa camada legada existe para permitir trocar o backend da API antiga pelo Laravel sem forçar uma mudança imediata no Flutter, já que hoje o app ainda chama endpoints como `login.php`, `get_all_bookings.php` e `change_my_password.php`.

## Superfície da API

Rotas canônicas principais:
- Auth: `POST /login`, `POST /logout`
- Health: `GET /health`
- Bookings: `GET /bookings`, `GET /my-bookings`, `POST /bookings`, `POST /bookings/cancel`, `POST /bookings/complete`
- Lookups: `GET /resources`, `GET /class-groups`, `GET /subjects`, `GET /available-lessons`
- Notifications: `GET /notifications`, `GET /notifications/unread-count`, `POST /notifications/read`, `POST /notifications/read-all`
- Account: `POST /account/change-password`
- Schools: `POST /schools/register`
- Internal: `GET /internal/check-database-connection`, `GET|POST /internal/send-booking-completion-reminders`
- Admin teachers: `GET|POST /admin/teachers`, `POST /admin/teachers/update`, `POST /admin/teachers/toggle-status`, `POST /admin/teachers/reset-password`
- Admin subjects: `GET|POST /admin/subjects`, `POST /admin/subjects/update`, `POST /admin/subjects/toggle-status`
- Admin class groups: `GET|POST /admin/class-groups`, `POST /admin/class-groups/update`, `POST /admin/class-groups/toggle-status`
- Admin resources: `GET /admin/resource-categories`, `POST /admin/resources`, `POST /admin/resources/update`, `POST /admin/resources/toggle-status`
- Admin lesson slots: `GET|POST /admin/lesson-slots`, `POST /admin/lesson-slots/update`, `POST /admin/lesson-slots/toggle-status`

Compatibilidade com a API antiga:
- aliases `.php` foram adicionados para todos os endpoints HTTP migrados
- `/index.php` e `/health.php` também respondem no Laravel
- as rotas intermediárias já usadas durante a migração continuam ativas por enquanto

## Ambiente

O `.env` atual está preparado para PostgreSQL local por padrão:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

Se quiser manter compatibilidade com a configuração antiga, você também pode usar:

```env
RESERVA_DB_URL=postgresql://...
```

ou:

```env
RESERVA_DB_DRIVER=pgsql
RESERVA_DB_HOST=...
RESERVA_DB_PORT=5432
RESERVA_DB_NAME=postgres
RESERVA_DB_USERNAME=...
RESERVA_DB_PASSWORD=...
RESERVA_DB_SSLMODE=require
```

## Como rodar

```bash
cd /home/agacy-junior/RESERVA_ESCOLAR/reserva_escolar_api_laravel
composer install
php artisan route:list
php artisan serve
```

## Observações

- O projeto continua em migração incremental, mas agora a superfície de rotas já está preparada para substituição gradual.
- A próxima etapa recomendada é migrar o Flutter dos aliases `.php` para as rotas canônicas aos poucos, começando por auth e health e depois seguindo por bookings e admin.
