# GitHub Actions CI/CD - API Laravel

Este guia documenta como o CI/CD automático funciona após as melhorias para usar **variáveis de ambiente customizadas** do projeto anterior (API Railway).

---

## 📊 O Que Foi Adaptado

O novo CI do Laravel agora imitates o workflow do PHP anterior:

| Aspecto | Antes (API Railway PHP) | Agora (API Laravel) |
|---------|---|---|
| ✅ Criar BD testes | SQL manual | Migrations Laravel |
| ✅ Dados de teste | INSERT manual | DatabaseSeeder + TestDataSeeder |
| ✅ Env vars customizadas | `RESERVA_*` | Integradas ao `.env.example` |
| ✅ Verificar sintaxe PHP | `php -l` em arquivos | Inline no workflow |
| ✅ Smoke tests | `smoke_test_api.sh` | `SmokeTestApiTest.php` |
| ✅ Testes de integração | `integration_test_api.sh` | PHPUnit Feature tests |
| ✅ Server local | PHP built-in | PHPUnit já testa inline |
| ✅ Cache | ❌ Não tinha | ✅ Composer cache adicionado |
| ✅ Coverage | ❌ Não tinha | ✅ Codecov integrado |

---

## 🔧 Variáveis de Ambiente (Customizadas)

O CI agora define:

```env
APP_ENV=testing
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
RESERVA_APP_TIMEZONE=America/Araguaina
RESERVA_ALLOWED_ORIGINS=http://127.0.0.1:8000
RESERVA_TEST_SCHOOL_CODE=CI001
RESERVA_TEST_EMAIL=tecnico.ci@example.com
RESERVA_TEST_PASSWORD=teste123
DB_CONNECTION=pgsql
DB_DATABASE=reserva_escolar_test
DB_HOST=localhost
DB_PORT=5432
```

---

## 🌱 Dados de Teste (Seeder Automático)

Após as migrations, o CI automaticamente cria:

### Escola de Teste
```
Código: CI001
Nome: Escola CI
Senha: teste123
```

### Usuários
```
1. tecnico.ci@example.com (technician)
   Senha: teste123
   
2. admin.ci@example.com (admin)
   Senha: teste123
```

Criados via `TestDataSeeder` → `DatabaseSeeder`

---

## 🎯 Pipeline em Detalhes

### Step-by-Step do Teste

```
1. Checkout código
   ↓
2. Setup PHP 8.3 + extensões PostgreSQL
   ↓
3. Cache dependências Composer
   ↓
4. Install composer dependencies
   ↓
5. Setup .env para testes
   ↓
6. Install PostgreSQL client
   ↓
7. Wait for PostgreSQL (health check)
   ↓
8. Run migrations (cria schema)
   ↓
9. Seed test data (cria escola + users)
   ↓
10. Lint PHP files (syntax validation)
    ↓
11. Run tests (PHPUnit com coverage)
    ↓
12. Upload coverage (Codecov)
```

### Testes Executados

**Smoke Tests** (`tests/Feature/SmokeTestApiTest.php`):
- ✅ GET `/health` retorna 200
- ✅ POST `/login` com credenciais válidas retorna token
- ✅ POST `/login` com credenciais inválidas retorna 401
- ✅ GET `/bookings` com Bearer token funciona

**Feature Tests**:
- Outros testes em `tests/Feature/`

**Unit Tests**:
- Testes em `tests/Unit/`

---

## 🚀 Como Usar

### 1. Fazer Push e Testar Automaticamente

```bash
cd /home/agacy-junior/RESERVA_ESCOLAR/reserva_escolar_api_laravel

# Fazer mudanças
echo "// melhoria" >> app/Http/Controllers/BookingController.php

# Commit e push
git add .
git commit -m "Add booking feature"
git push origin feature/booking
```

### 2. Acompanhar CI no GitHub

1. Vá para: https://github.com/14069/api-reserva-escolar/actions
2. Procure por seu commit
3. Clique para ver logs detalhados

### 3. Verificar Cobertura

- Codecov badge no README
- Relatório completo: https://codecov.io/gh/14069/api-reserva-escolar

---

## 🆘 Troubleshooting

### erro: "Testing database does not exist"
→ PostgreSQL service não iniciou ou health check falhou
→ Verificar logs do "Wait for PostgreSQL" step

### Erro: "Seed failed: Could not insert school"
→ School já existe (talvez de exclusão incompleta)
→ Usar `firstOrCreate` em vez de `create` (já implementado)

### Erro: "Login test failed"
→ User não foi criado no seeding
→ Verificar se `TestDataSeeder` foi executado
→ Rodar localmente: `php artisan migrate:fresh --seed`

### Teste passa localmente mas falha no CI
→ Possivelmente env vars diferentes
→ Rodar localmente com `APP_ENV=testing`: 
   ```bash
   APP_ENV=testing php artisan test --verbose
   ```

---

## 📝 Checklist Antes de Fazer Push

- [ ] Rodar testes localmente: `composer test`
- [ ] Seed funciona: `php artisan migrate:fresh --seed`
- [ ] Pint OK: `./vendor/bin/pint --test`
- [ ] Código revisado
- [ ] Commit message é descritiva

---

## 🔄 Branchs Protegidas (Recomendado)

Para forçar CI antes de merge:

1. GitHub → Settings → Branches → Add Rule
2. Pattern: `main`
3. ✅ Require status checks: `test`, `lint`
4. ✅ Require PR review before merging

---

## 📊 Status Checks

O CI estabelece dois status checks:

- ✅ **test** - Roda testes, migrações, seeds
- ✅ **lint** - Pint + PHPStan

Ambos devem passar antes de merge na `main`.

---

## 💡 Próximos Passos

- [ ] Configurar Slack notifications
- [ ] Setup de staging deploy em `develop` branch
- [ ] DB backup antes de migrations em produção
- [ ] Performance testing de endpoints críticos
- [ ] Testes de concorrência para bookings

---

**Resumo**: CI agora roda completo com dados de teste, validação, cobertura e smoke tests! 🚀
