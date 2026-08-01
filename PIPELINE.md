# Reserva Escolar — Documento de Apresentação

---

## 1. Contextualização e o Problema

### Qual é o problema social ou acadêmico escolhido?

O gerenciamento de recursos compartilhados em escolas — como laboratórios de informática, chromebooks, projetores e espaços físicos — ainda é feito, na maioria das instituições públicas brasileiras, de forma completamente manual: cadernos físicos, planilhas Excel desatualizadas ou grupos de WhatsApp improvisados entre professores.

Esse cenário gera um problema concreto e recorrente: **conflito de reservas**. Dois professores agendam o mesmo recurso para o mesmo horário sem qualquer aviso prévio, e só descobrem o conflito no momento da aula — causando perda de tempo pedagógico, constrangimento e desmotivação docente.

### Por que esse problema é relevante e merece uma solução tecnológica?

- Segundo o **Censo Escolar 2023 (INEP)**, o Brasil possui mais de 180 mil escolas de educação básica, a maioria delas com recursos tecnológicos compartilhados entre dezenas de professores.
- A ausência de um sistema de agendamento centralizado faz com que recursos subutilizados coexistam com filas de espera invisíveis — o professor não sabe se o recurso está disponível sem ligar ou ir pessoalmente verificar.
- Em escolas que participaram de programas como o **PROINFO** e o **PNLD Digital**, a quantidade de chromebooks e equipamentos audiovisuais aumentou significativamente, tornando o problema de gestão ainda mais crítico sem um sistema adequado.
- O impacto vai além da logística: a falta de planejamento previsível desestimula o uso pedagógico da tecnologia, porque o professor aprende por experiência própria que "não adianta planejar uma aula com o lab, porque nunca está disponível quando precisa".

---

## 2. A Solução e o Público-Alvo

### Apresentação conceitual do aplicativo mobile

O **Reserva Escolar** é um aplicativo mobile que centraliza o agendamento de recursos compartilhados dentro de uma unidade escolar. Com ele, professores consultam a disponibilidade em tempo real, fazem reservas em poucos toques e recebem notificações sobre o status de seus agendamentos — eliminando o conflito de reservas e tornando o uso dos recursos mais previsível e democrático.

A solução é composta por:
- **App Flutter** — interface mobile para professores e técnicos administrativos
- **API REST em Laravel** — backend que centraliza os dados e aplica as regras de negócio
- **Banco de dados PostgreSQL** — armazenamento relacional hospedado na nuvem (Supabase)

### Quem será o usuário final?

| Perfil | Papel no sistema |
|---|---|
| **Professor** | Consulta disponibilidade, realiza e cancela reservas, acompanha seus agendamentos |
| **Técnico administrativo** | Gerencia o cadastro de recursos, turmas, disciplinas e horários da escola |

O sistema é **multi-escola**: cada instituição opera de forma isolada, com seus próprios recursos e usuários, sem interferência entre unidades.

---

## 3. Requisitos e Funcionalidades Principais

### Resumo do escopo do projeto

O sistema cobre o ciclo completo de uma reserva escolar: da consulta de disponibilidade até a confirmação de uso, com histórico e notificações. O escopo exclui intencionalmente funcionalidades como pagamento, comunicação entre usuários e controle de patrimônio — o foco é resolver o problema de agendamento com o menor atrito possível.

### Features centrais que resolvem o problema

**Para o professor:**
- Consulta de recursos disponíveis por data e horário em tempo real
- Criação de reserva vinculada a turma, disciplina e horários de aula
- Visualização das próprias reservas com status (agendado / concluído / cancelado)
- Cancelamento de reserva com uma ação
- Notificações automáticas sobre mudanças no status da reserva

**Para o técnico administrativo:**
- Cadastro e gestão de recursos (audiovisual, chromebooks, espaços)
- Cadastro de professores, turmas, disciplinas e slots de horário
- Visão geral de todos os agendamentos da escola com filtros por data, recurso e status
- Confirmação de uso (marcar reserva como concluída) com campo de feedback
- Exportação em PDF e CSV de todas as listagens (recursos, professores, turmas, disciplinas, horários e relatório de agendamentos)

**Regra central anti-conflito:**
Antes de confirmar qualquer reserva, o sistema verifica se o recurso já está ocupado naquele horário e data — impedindo duplicidade na origem, não no momento da descoberta.

---

## 4. Arquitetura e Decisões Tecnológicas

### Stack tecnológico

| Camada | Tecnologia | Versão |
|---|---|---|
| App mobile | Flutter + Dart | SDK 3.x |
| HTTP client | Dio | ^5.9.2 |
| Gerenciamento de estado | Provider | — |
| Analytics | Firebase Analytics | ^12.0.1 |
| Backend / API | Laravel | 12.0 |
| Linguagem backend | PHP | ^8.3 |
| Banco de dados | PostgreSQL | — |
| Hospedagem do banco | Supabase | — |
| Deploy da API | Railway | — |
| Autenticação | Bearer Token (custom, SHA-256) | — |

### Justificativa das escolhas

**Flutter:** permite entregar um único código para Android e iOS com performance nativa e UI consistente. Para uma escola pública onde professores usam dispositivos variados (Android de diferentes fabricantes), a portabilidade é essencial. O Flutter também oferece widgets ricos que facilitam a construção de formulários e listas — elementos centrais neste app.

**Laravel:** o projeto migrou de uma API PHP legada sem framework (scripts `.php` individuais). Laravel foi escolhido por ser o framework PHP mais maduro para APIs REST, mantendo a linguagem já dominada pela equipe e permitindo uma migração gradual sem quebrar o app em produção.

**PostgreSQL via Supabase:** o modelo de dados envolve relacionamentos complexos (uma reserva conecta usuário, recurso, turma, disciplina e múltiplos horários). PostgreSQL garante integridade referencial e suporte robusto a joins. O Supabase oferece PostgreSQL gerenciado com backups automáticos e painel visual, sem custo operacional de infraestrutura própria.

**Bearer Token customizado (sem Sanctum/Passport/JWT):** o cliente é mobile e stateless — sessões por cookie não se aplicam. O token customizado permite controle total sobre expiração (12 horas) e viabilizou a migração gradual de senhas do sistema legado (suporte a bcrypt, hash simples e plaintext durante a transição).

**Autenticação multi-tenant por `school_id`:** todo acesso aos dados é filtrado pelo `school_id` do usuário autenticado, garantindo isolamento completo entre escolas sem necessidade de bancos separados.

**Firebase Analytics (somente Analytics, sem FCM):** o app integra `firebase_analytics` para rastrear eventos de uso reais: login com sucesso e falha, logout, visualização de telas, criação de reserva (com categoria do recurso e número de horários selecionados), cadastro de escola e troca de senha. Esses dados permitem entender como os professores usam o app sem depender de logs de servidor. O Firebase foi escolhido por ser gratuito, ter SDK Flutter oficial e não exigir infraestrutura própria de coleta de dados. Push notifications (FCM) não foram implementadas nesta versão — ficam como próximo passo.

**Railway para deploy da API:** o código da API está hospedado no GitHub. O Railway se conecta diretamente ao repositório e, a cada push na branch principal, executa o build automaticamente a partir do `Dockerfile` presente no projeto — instalando dependências PHP, rodando o `composer install` otimizado para produção e subindo o servidor Laravel na porta configurada. Essa integração elimina etapas manuais de deploy: o ciclo completo é `git push → build automático → nova versão no ar`, sem necessidade de acessar servidor, rodar scripts ou configurar pipelines de CI/CD separados. O Railway também gerencia variáveis de ambiente, HTTPS e domínio customizado (`api.reservaescolar.app.br`), reduzindo o custo operacional de infraestrutura a zero para um projeto acadêmico.

---

## 5. Demonstração do Aplicativo

### Fluxo principal — "caminho feliz" de uma reserva

```
1. Professor abre o app e faz login com código da escola + e-mail + senha
        ↓
2. Tela inicial exibe resumo das reservas do dia e atalhos rápidos
        ↓
3. Professor toca em "Nova Reserva"
        ↓
4. Seleciona o tipo de recurso (ex: Audiovisual)
        ↓
5. Escolhe o recurso específico disponível
        ↓
6. Seleciona a data desejada
        ↓
7. App consulta a API e exibe apenas os horários LIVRES para aquele recurso/data
        ↓
8. Professor seleciona os horários, turma e disciplina
        ↓
9. Confirma a reserva — API verifica conflito em tempo real antes de persistir
        ↓
10. Reserva criada com status "Agendado"
        ↓
11. Professor recebe notificação de confirmação
        ↓
12. No dia da aula: técnico ou professor marca como "Concluída" com feedback opcional
```

Cenário de conflito (caminho alternativo): se outro professor já reservou o mesmo recurso no mesmo horário, os slots conflitantes **não aparecem** na etapa 7 — o professor nunca chega a criar uma reserva inválida.

---

## 6. Desafios e Limitações Técnicas

### O que foi mais difícil de implementar no ambiente mobile

**Sincronização de disponibilidade em tempo real:** o maior desafio foi garantir que os horários exibidos na tela de nova reserva reflitam o estado atual do servidor — não um cache desatualizado. A solução foi implementar cancelamento de requisições via `CancelToken` do Dio: toda vez que o usuário muda a data ou o recurso, a requisição anterior é cancelada e uma nova é disparada imediatamente, evitando condições de corrida na UI.

**Migração da API legada sem downtime:** a API antiga era um conjunto de scripts PHP sem framework, com senhas em plaintext e tokens sem expiração. A migração exigiu suporte simultâneo a três camadas de rotas (canônicas, de compatibilidade e aliases `.php`) e três formatos de senha (plaintext, bcrypt antigo e bcrypt novo), garantindo que o app já instalado nos dispositivos continuasse funcionando durante a transição.

**Consistência de timezone:** a escola está em Araguaína (TO), no fuso `America/Araguaina` (UTC-3 sem horário de verão). Datas de reserva trafegam como strings `YYYY-MM-DD` para evitar conversões implícitas de timezone que poderiam deslocar o dia da reserva ao serializar/desserializar timestamps.

### O que ficou como limitação da versão atual

- **Sem funcionamento offline:** o app exige conexão ativa com a internet para qualquer operação. Em escolas com infraestrutura de rede instável, isso pode ser um obstáculo.
- **Notificações push não implementadas:** as notificações existem dentro do app (feed de notificações), mas não há push notification via FCM/APNs — o professor precisa abrir o app para ver atualizações.
- **Sem visualização de calendário:** a agenda de reservas é exibida em lista. Uma visão de calendário por recurso facilitaria a leitura da ocupação semanal.
- **Sem controle de permissões granular:** todos os professores de uma escola têm o mesmo nível de acesso — não é possível restringir um professor a determinados recursos ou turmas.

---

## 7. Conclusão e Trabalhos Futuros

### Impacto esperado em adoção em larga escala

Se adotado em uma escola com 40 professores e 10 recursos compartilhados:
- Eliminação dos conflitos de reserva, que hoje consomem tempo de coordenação pedagógica e desgastam relações entre docentes
- Aumento do uso efetivo dos recursos tecnológicos, que hoje ficam ociosos por falta de visibilidade de disponibilidade
- Geração de dados históricos de uso que permitem à gestão escolar tomar decisões de compra baseadas em demanda real (quais recursos têm fila de espera? quais ficam sem uso?)

Em escala de rede municipal ou estadual, o sistema permitiria comparar a taxa de utilização de recursos entre escolas e redistribuir equipamentos subutilizados para onde há maior demanda.

### Próximos passos para evolução do software

| Prioridade | Evolução |
|---|---|
| Alta | Notificações push via FCM (Firebase já integrado, falta adicionar `firebase_messaging`) |
| Alta | Modo offline com sincronização ao reconectar (SQLite local + sync queue) |
| Média | Visão de calendário semanal por recurso |
| Média | Permissões granulares por recurso e turma |
| Baixa | Painel web para gestores com métricas de uso |
| Baixa | Integração com sistemas de gestão escolar (SIGE, SIGAE) via API |
