# GitHub Actions CI/CD Setup

Este guia explica como configurar **GitHub Actions** para rodar testes automaticamente e fazer deploy automático.

## ✅ Status Atual

### App Flutter (`reserva-escolar-app`)
- ✅ **CI configurado**: Roda `flutter analyze` e `flutter test` em cada push/PR
- ✅ **Deploy Web**: Deploy automático para Firebase quando push na `main`
- **Variáveis necessárias**:
  - `FIREBASE_PROJECT_ID` (já configurado)
  - `API_BASE_URL_PROD` (URL da API em produção)
- **Secrets necessários**:
  - `FIREBASE_SERVICE_ACCOUNT_RESERVA_ESCOLAR` (JSON da conta de serviço)

### API Laravel (`api-reserva-escolar`)
- ✅ **CI configurado**: Roda testes, linting e análise estática
- ✅ **Deploy**: Deploy automático para Railway quando push na `main`
- **Secrets necessários**:
  - `RAILWAY_TOKEN` (token de autenticação)
  - `RAILWAY_PROJECT_ID` (ID do projeto no Railway)
  - `RAILWAY_SERVICE_ID` (ID do serviço no Railway)

---

## 🔐 Como Configurar Secrets

### Para API Laravel no GitHub

1. Abra: https://github.com/14069/api-reserva-escolar/settings/secrets/actions

2. Clique em **New repository secret** e adicione:

#### `RAILWAY_TOKEN`
- Acesse https://railway.app/account/tokens
- Crie um novo token
- Copie o token completo

#### `RAILWAY_PROJECT_ID`
- No dashboard do Railway, clique no seu projeto
- Na URL: `https://railway.app/project/[PROJECT_ID]`
- Copie o ID

#### `RAILWAY_SERVICE_ID`
- No Railway, abra seu projeto
- Clique no serviço da API
- Na URL ou em "Service ID" copie o ID

### Para App Flutter no GitHub (se precisar adicionar)

1. Abra: https://github.com/14069/reserva-escolar-app/settings/secrets/actions

2. Adicione:

#### `FIREBASE_SERVICE_ACCOUNT_RESERVA_ESCOLAR`
- Acesse Firebase Console
- Projeto → Settings → Service Accounts
- Clique "Generate new private key"
- Copie JSON inteiro como secret

#### `API_BASE_URL_PROD` (como variable, não secret)
1. Vá para: https://github.com/14069/reserva-escolar-app/settings/variables/actions
2. Clique **New repository variable**
3. Nome: `API_BASE_URL_PROD`
4. Valor: `https://api.reservaescolar.com.br` (ou sua URL real)

---

## 🎯 Como Usar

### Executar CI em uma Branch

```bash
# Push para uma branch de feature
git checkout -b feature/minha-feature
git commit -m "Add nova feature"
git push origin feature/minha-feature

# Ir para GitHub → Pull Request
# CI vai rodar automaticamente
```

### Visualizar Resultados

1. **No repositório GitHub**: Abra a aba **Actions**
2. Você verá os workflows em execução
3. Clique em um workflow para ver logs detalhados

### Rodar Deploy Manual

Para fazer deploy sem esperar um push:

```bash
# No repositório local
git push origin main

# OU via GitHub Actions UI:
# 1. Vá para repo → Actions
# 2. Procure "Laravel API Deploy"
# 3. Clique "Run workflow" → "Run workflow"
```

---

## 📋 CI Pipeline da API (O que roda)

### 1. **Tests** (Job `test`)
- ✅ Instala dependências
- ✅ Copia `.env.example` → `.env`
- ✅ Gera `APP_KEY`
- ✅ Roda migrações em banco PostgreSQL temporário
- ✅ Executa todos os testes (`php artisan test`)
- ✅ Envia cobertura para Codecov

### 2. **Lint** (Job `lint`)
- ✅ Verifica estilo com **Pint** (`./vendor/bin/pint --test`)
- ✅ Análise estática com **PHPStan** (nível 5)

**Resultado**: Se algum teste falhar ou o estilo estiver errado, o PR fica com ❌

---

## 📋 Deploy Pipeline (O que roda)

### 1. **Testes** (mesmo que CI)
- Roda testes para validar código

### 2. **Deploy** (se testes passarem)
- ✅ Faz deploy para Railroad
- ✅ Roda migrações em produção
- ✅ Notifica Slack (opcional)

---

## 🚨 Se o CI Falhar

### Testes falhando?

```bash
# Rode localmente para debugar
cd reserva_escolar_api_laravel
composer install
php artisan test --verbose

# Veja qual teste está falhando
# Corrija o código
git add .
git commit -m "Fix failing test"
git push origin feature/minha-feature
```

### Estilo falhando?

```bash
# Pint pode corrigir automaticamente
./vendor/bin/pint

# Ou apenas verificar
./vendor/bin/pint --test

git add .
git commit -m "Fix code style"
git push
```

---

## 🔄 Branchs Protegidas (Recomendado)

Para **exigir** que CI passe antes de fazer merge:

1. Vá para `Settings` → `Branches`
2. Em **Branch protection rules**, clique **Add rule**
3. **Branch name pattern**: `main`
4. Marque:
   - ✅ Require a pull request before merging
   - ✅ Require status checks to pass before merging
   - Selecione: `Laravel API CI / test` e `Laravel API CI / lint`
   - ✅ Require branches to be up to date

Agora **ninguém pode fazer merge na main** sem que os testes passem! 🛡️

---

## 📊 Monitorando Deployments

### Via Railway Dashboard
- Acesse https://railway.app
- Clique no projeto
- Veja histórico de deployments
- Verifique logs em tempo real

### Via GitHub Actions
- Repositório → Actions → "Laravel API Deploy"
- Clique em um workflow para ver logs
- Se algo deu erro, os logs mostram

---

## 💡 Próximos Passos (Opcionais)

- [ ] Configurar Slack webhook para notificações
- [ ] Adicionar análise de cobertura de testes (Codecov)
- [ ] Configurar protected branches na main
- [ ] Adicionar auto-deploy para branch `develop` → staging
- [ ] Criar workflow para rodar seeders em produção (com aprovação manual)

---

## 🆘 Troubleshooting

### "RAILWAY_TOKEN is not set"
→ Verificar se secret `RAILWAY_TOKEN` foi adicionado em Settings → Secrets

### "Cannot recognize database"
→ Workflow está tentando conectar em PostgreSQL fora do container  
→ Verificar se `services.postgres` está corretamente configurado

### "Pint check failed"
→ Rodar `./vendor/bin/pint` localmente e fazer commit das correções

---

**Pronto! 🚀** Sempre que você der push, testes rodam automaticamente. Se passar, deploy é feito em produção!
