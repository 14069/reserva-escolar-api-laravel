# Roteiro de Fala — Reserva Escolar

> **Legenda:** 🔵 = Você (Apresentador 1) · 🟣 = Dupla (Apresentador 2)
> Tempo estimado: 15–20 minutos

---

## ABERTURA

🔵 **Você:**
> "Boa tarde a todos. Meu nome é [nome], e junto com [nome da dupla] vamos apresentar o **Reserva Escolar** — um aplicativo mobile desenvolvido para resolver um problema que acontece toda semana em praticamente qualquer escola do Brasil."

---

## SEÇÃO 1 — Contextualização e o Problema

🔵 **Você:**
> "Deixa eu começar com uma situação que qualquer professor conhece.
>
> Imagine que você planejou uma aula diferente. Vai usar o laboratório de informática, preparou atividade, avisou os alunos. Chega na hora — o lab está ocupado. Outro professor reservou o mesmo espaço, ninguém sabia, e a sua aula foi por água abaixo.
>
> Esse conflito acontece porque o agendamento de recursos em escolas ainda é feito de forma manual: caderno na secretaria, planilha no Google Drive que ninguém atualiza, grupo de WhatsApp onde a mensagem se perde. Não tem centralização, não tem verificação de conflito, não tem histórico."

🔵 **Você:**
> "E isso não é um problema pequeno. O Censo Escolar de 2023 do INEP mostra que o Brasil tem mais de 180 mil escolas de educação básica. A maioria com projetores, chromebooks e laboratórios compartilhados entre dezenas de professores — e sem nenhum sistema de agendamento adequado.
>
> Programas como o PROINFO e o PNLD Digital colocaram mais equipamentos nas escolas, mas não resolveram a gestão do uso. O resultado é que muita tecnologia fica ociosa porque o professor simplesmente não sabe se está disponível, e quando descobre que está, já é tarde.
>
> O impacto vai além da logística: o professor aprende na prática que não adianta planejar aula com tecnologia, porque o recurso nunca está garantido. Isso desmotiva o uso pedagógico da tecnologia como um todo."

---

## SEÇÃO 2 — A Solução e o Público-Alvo

🟣 **Dupla:**
> "Para resolver esse problema, desenvolvemos o **Reserva Escolar**.
>
> É um aplicativo mobile que centraliza o agendamento de recursos dentro de uma escola. O professor consulta o que está disponível em tempo real, faz a reserva em poucos toques, e recebe notificação quando algo muda. Acabou o conflito, acabou a incerteza.
>
> O sistema tem dois perfis de usuário. O **professor**, que é quem faz e acompanha as reservas do dia a dia. E o **técnico administrativo**, que é quem configura o sistema — cadastra os recursos da escola, os professores, as turmas, os horários de aula.
>
> E um detalhe importante: o sistema é **multi-escola**. Cada instituição tem seus próprios dados, isolados das outras. Então não importa quantas escolas usem o sistema — os dados de uma nunca interferem na outra."

---

## SEÇÃO 3 — Requisitos e Funcionalidades Principais

🟣 **Dupla:**
> "Vou falar rapidamente sobre o que o app faz na prática — o escopo que a gente definiu e entregou.
>
> Para o **professor**: ele consulta os recursos disponíveis por data e horário, cria uma reserva vinculando o recurso à turma e à disciplina, acompanha o histórico dos próprios agendamentos, e pode cancelar com uma ação. Quando o status de uma reserva muda, ele recebe uma notificação dentro do app.
>
> Para o **técnico**: ele tem uma visão completa de todos os agendamentos da escola, com filtros por data, recurso e status. Pode marcar reservas como concluídas com um campo de feedback. E exporta tudo — lista de professores, turmas, recursos, horários e relatório de agendamentos — em PDF ou CSV direto do celular.
>
> E a regra mais importante do sistema: **a verificação de conflito acontece antes da reserva ser criada**. Quando o professor abre a tela de nova reserva e seleciona um recurso e uma data, o app consulta a API e mostra apenas os horários que ainda estão livres. Ele nunca nem chega a tentar reservar um horário ocupado — o conflito é prevenido na origem."

---

## SEÇÃO 4 — Arquitetura e Decisões Tecnológicas

🔵 **Você:**
> "Agora vou falar sobre as tecnologias que usamos e por que escolhemos cada uma.
>
> O app mobile foi feito em **Flutter**, com Dart. A escolha foi simples: Flutter gera um único código que roda em Android e iOS com performance nativa. Numa escola pública onde os professores usam celulares variados — diferentes marcas e versões de Android — essa portabilidade é essencial. Não dá pra restringir por plataforma.
>
> O backend é uma **API REST em Laravel 12**, com PHP 8.3. A gente já tinha uma API antiga feita com scripts PHP sem framework — arquivos soltos como `criar_reserva.php`, `listar_professores.php`. Migramos para o Laravel porque é o framework PHP mais maduro para APIs, mantendo a linguagem que a gente domina, e fizemos isso sem quebrar o app que já estava rodando nos dispositivos dos professores.
>
> O banco de dados é **PostgreSQL**, hospedado no **Supabase**. Uma reserva envolve usuário, recurso, turma, disciplina e múltiplos horários — é um modelo relacional complexo que exige integridade referencial real. O Supabase oferece PostgreSQL gerenciado com backup automático, sem custo de infraestrutura própria.
>
> Para o **deploy da API**, usamos o **Railway** conectado diretamente ao nosso repositório no GitHub. Toda vez que a gente faz push na branch principal, o Railway detecta a mudança, executa o build a partir do Dockerfile do projeto — instala as dependências, otimiza o autoloader do Composer — e sobe a nova versão automaticamente. O ciclo é: `git push` → nova versão no ar. Sem acessar servidor, sem rodar script manualmente. O Railway também gerencia HTTPS e o domínio `api.reservaescolar.app.br`.
>
> A **autenticação** é por Bearer Token com hash SHA-256, com expiração de 12 horas. Não usamos Sanctum nem JWT porque o cliente é mobile — sessões por cookie não fazem sentido. O token customizado também nos deu controle total durante a migração da API legada, que tinha senhas em texto puro no banco.
>
> E por último: integramos o **Firebase Analytics** — só o Analytics, não o push notification. Ele rastreia eventos reais de uso: login, logout, criação de reserva com categoria e número de horários selecionados, cadastro de escola, troca de senha. Isso nos dá dados concretos de como os professores usam o app sem depender de logs do servidor. E o Firebase já estava configurado — se quisermos adicionar push notifications no futuro, o `firebase_messaging` entra direto."

---

## SEÇÃO 5 — Demonstração

🟣 **Dupla:**
> "Agora vamos mostrar o app funcionando. Vou demonstrar o fluxo principal — uma reserva do começo ao fim."

> *(Mostrar o app. Narrar enquanto navega:)*

> "Aqui estamos na tela de login. O professor entra com o código da escola, o e-mail e a senha. *(faz o login)* A tela inicial mostra as reservas do dia e os atalhos principais.
>
> Vou criar uma nova reserva. *(toca em Nova Reserva)* Seleciono a categoria — vou escolher Audiovisual. *(seleciona)* Aqui aparecem os recursos disponíveis dessa categoria. *(seleciona um)*
>
> Escolho a data. *(seleciona a data)* E aqui está a parte mais importante: o app consulta a API agora, em tempo real, e mostra apenas os horários que ainda estão livres para esse recurso nessa data. Horários ocupados simplesmente não aparecem.
>
> Seleciono os horários, a turma, a disciplina. *(preenche)* Confirmo. *(confirma)* Reserva criada — status 'Agendado'. E olha aqui na aba de notificações: a confirmação já chegou.
>
> Esse é o caminho completo. Sem ligar para a secretaria, sem grupo de WhatsApp, sem conflito."

---

## SEÇÃO 6 — Desafios e Limitações

🔵 **Você:**
> "Vou ser honesto sobre o que foi difícil e o que não conseguimos entregar nessa versão.
>
> O maior desafio técnico foi garantir que os horários disponíveis na tela de nova reserva fossem sempre os dados reais do momento — não um cache antigo. Se dois professores abrissem o app ao mesmo tempo, um não podia ver horários que o outro já estava reservando. A solução foi usar o `CancelToken` do Dio: toda vez que o professor muda a data ou o recurso, a requisição anterior é cancelada imediatamente e uma nova começa, sem condição de corrida.
>
> Outro desafio foi a migração da API antiga sem derrubar o app. A API legada tinha senhas em texto puro no banco e endpoints no estilo de script PHP. Precisamos manter compatibilidade com os dispositivos que já tinham o app instalado enquanto migravamos tudo para o Laravel — e ainda lidar com três formatos diferentes de senha durante a transição.
>
> E teve o problema de timezone: a escola fica em Araguaína, que tem um fuso específico sem horário de verão. Datas de reserva trafegam como string no formato ano-mês-dia pra evitar que a serialização automática de timestamp deslocasse o dia da reserva."

🔵 **Você:**
> "Das limitações que ficaram nessa versão: o app precisa de internet para funcionar — em escola com rede instável isso pode ser um problema. As notificações existem dentro do app, mas não tem push notification — o professor precisa abrir o app pra ver. E não tem visão de calendário, só lista de reservas."

---

## SEÇÃO 7 — Conclusão e Trabalhos Futuros

🟣 **Dupla:**
> "Para fechar: qual é o impacto real se esse app for adotado?
>
> Numa escola com 40 professores e 10 recursos compartilhados, você elimina os conflitos de reserva que hoje consomem tempo da coordenação e geram atrito entre colegas. Você aumenta o uso efetivo dos equipamentos que ficam ociosos por falta de visibilidade. E você começa a gerar dados históricos de uso — quais recursos têm fila de espera, quais ficam sem uso — que permitem à gestão tomar decisões de compra baseadas em demanda real, não em intuição.
>
> Em escala de rede municipal ou estadual, dá pra comparar a taxa de utilização entre escolas e redistribuir equipamentos subutilizados para onde há mais demanda.
>
> Nos próximos passos, as prioridades mais altas são: push notifications — o Firebase já está integrado no app, precisamos só adicionar o pacote `firebase_messaging` e configurar o backend para disparar os tokens — e o modo offline, com um banco local SQLite no dispositivo que sincroniza quando a conexão volta.
>
> Médio prazo: visão de calendário semanal por recurso e permissões granulares por professor. Longo prazo: painel web para gestores e integração com sistemas de gestão escolar já existentes.
>
> O Reserva Escolar não é uma solução genérica de agendamento — é uma solução desenhada especificamente para a realidade das escolas brasileiras: dispositivos variados, conexão instável, processo hoje completamente manual. Obrigado."

---

## FECHAMENTO

🔵 **Você:**
> "Ficamos à disposição para perguntas."

---

## DICAS PARA A APRESENTAÇÃO

- **Seção 4 (arquitetura):** se a banca for técnica, aprofunde. Se não for, encurte para: "usamos Flutter no app, Laravel na API, PostgreSQL no banco e Railway pra deploy automático pelo GitHub."
- **Seção 5 (demo):** pratiquem o fluxo com o app real antes. Se travar, narrem o que estaria acontecendo.
- **Seção 6 (desafios):** falar de dificuldade com honestidade conta ponto. Não skip.
- **Evitem ler o roteiro.** Usem como referência, não como script palavra por palavra.
- **Tempo por seção aproximado:** Problema: 2min · Solução: 1min30 · Features: 2min · Arquitetura: 3min · Demo: 4min · Desafios: 2min · Conclusão: 2min
