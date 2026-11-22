# Backlog PI4 — 29 issues em 4 sprints

**Documento de execução.** O [`roadmap-ia.md`](./roadmap-ia.md) diz *o que* construir no PI4 e *por
quê*; este arquivo quebra isso em issues prontas para o GitHub Projects, ancoradas em arquivos e
linhas reais do código atual. A direção de produto para além do PI4 — incluindo o modelo de duas
camadas que responde ao cold start — está em [`visao-produto.md`](./visao-produto.md).

---

## ⚠️ Numeração: este documento vs. GitHub

As issues foram criadas no GitHub e receberam **números próprios**, que não batem com a numeração
`#1`–`#32` usada aqui. Toda referência `#N` **neste arquivo** aponta para a numeração *deste
documento*.

| Neste documento | No GitHub | Conversão |
|---|---|---|
| `#1` – `#6` | **#153 – #158** | `GitHub = local + 152` |
| `#7` – `#32` | **#160 – #185** | `GitHub = local + 153` |

O salto de +1 acontece porque a issue **#159** foi criada no meio da leva.

**Ao colar texto daqui em comentários do GitHub, converta os `#N`** — caso contrário o GitHub
cria link para a issue errada. Exemplo real: `#7` (infra de testes) vira link para o
`[EPIC] Status & Fluxo Operacional`, que é a issue 7 do repositório.

Pares mais citados:

| Local | GitHub | Título |
|---|---|---|
| `#5` | **#157** | `chore: factories dos models + conserto do DatabaseSeeder` |
| `#7` | **#160** | `test: infraestrutura de testes + suíte do FilaService` |
| `#30` | **#183** | `chore: deixar o repositório verde antes de ligar a CI` |
| `#31` | **#184** | `chore: workflow de CI no GitHub Actions` |
| `#32` | **#185** | `chore: tornar os checks obrigatórios (branch protection)` |

## Relação com as issues RF-* antigas

O repositório tinha **34 issues abertas** anteriores (11 EPICs + 23 `RF-*`), que descreviam
**requisitos funcionais** — *o que o sistema deve fazer*. Este backlog descreve **trabalho de
execução** — *o que alguém programa nesta semana*. São documentos de naturezas diferentes, e rodar
sprint com as duas listas sobrepostas é pior que qualquer uma sozinha.

**Decisão:** as 4 sprints do PI4 são guiadas **exclusivamente por este backlog**. As RF-* saem do
board de sprint, mas continuam consultáveis para a banca.

Importante: **boa parte das RF-* já foi implementada** e apenas nunca foi fechada. Elas não são um
bloco homogêneo, e o encerramento precisa distinguir três casos:

| Caso | Como encerrar |
|---|---|
| Já implementada (ex.: painel administrativo, feedback visual de ações) | fechar como **concluída** — é evidência de trabalho entregue |
| Não implementada, coberta por uma issue deste backlog | fechar apontando **qual issue absorveu** |
| Não implementada e fora do escopo do PI4 | fechar com o **motivo do adiamento** registrado |

Fechar as três categorias com o mesmo texto destruiria a rastreabilidade em vez de preservá-la.

### Escopo das RF-* deliberadamente descartado

**Central de notificações in-app** (RF-59 a RF-65 — tabela de notificações, listar, marcar como
lida, sininho no app). Avaliado e **adiado para depois do PI4**.

*Por quê:* é um canal **diferente** do push nativo da `#23`, não uma duplicata — push aparece na
tela de bloqueio do celular; a central in-app é histórico dentro do app. Para a demo da banca, o
push já demonstra o aviso ao cliente chegando, que é o momento que importa. O Sprint 3 já está com
44 pontos (32% do backlog), e a central seria uma frente inteira a mais: migration, endpoints,
componente de UI e estado de leitura.

*Quando retomar:* depois do PI4, junto do item de "estados de mesa estendidos + tarefas de equipe"
listado em [`visao-produto.md`](./visao-produto.md).

---

## Como usar no GitHub Projects

**Milestones:** crie 4 — `Sprint 1`, `Sprint 2`, `Sprint 3`, `Sprint 4`.

**Labels sugeridas** (o repositório já tem `Sprint 1`–`Sprint 4`, `bug`, `documentation`,
`feature`, `epic`, `easy`/`medium`/`hard`, `role: junior`/`pleno`/`senior`/`teach-lead`,
`mvp`/`post-mvp`; as abaixo são complementares):

| Label | Uso |
|---|---|
| `backend` | Laravel: controllers, services, commands |
| `frontend` | React/Vite |
| `banco` | Migration, model, seeder, factory |
| `testes` | PHPUnit, Vitest, infraestrutura de teste |
| `infra` | Docker, Reverb, fila, configuração |
| `ia` | OpenRouter, tool-calling, assistente |
| `mobile` | Capacitor, push, APK |
| `fundação` | Bloqueia outras issues — priorizar |
| `bug` | Corrige comportamento errado já em produção |
| `chore` | Manutenção, ferramental, CI — não muda comportamento do produto |

**Estimativa:** pontos em escala Fibonacci (1, 2, 3, 5, 8). Total do backlog: **137 pontos** em
32 issues (as 29 do roadmap + 3 de CI, no adendo ao fim do documento).

**Convenção de título:** segue o
[`Deep_Dish_Contributing_Guide.md`](../Deep_Dish_Contributing_Guide.md) — `feature:`, `fix:`,
`chore:`, `refactor:`, `docs:`. Atenção: **não** é Conventional Commits (`feat:`); o guia deste
repositório usa `feature:` por extenso. Branches seguem `Tipo/NomeDaBranch` em PascalCase
(`Feature/HistoricoFila`), PRs sempre para `develop`.

> **Nota:** o repositório não tem diretório `.github/` — sem templates de issue/PR, sem CODEOWNERS,
> sem CI. Criar isso não está nas 29 issues abaixo; se quiserem, vira uma issue de `chore:`
> separada.

---

## Decisões travadas

Decisões já tomadas e **não sujeitas a re-discussão dentro do PI4**. Estavam espalhadas entre o
roadmap e uma sessão de planejamento anterior; ficam registradas aqui para não se perderem.

| Tema | Decisão | Por quê |
|---|---|---|
| **Escopo do app** | App é **só do cliente**; o restaurante continua no painel web | Gestão de salão precisa de tela grande — o gerente vê muitas mesas ao mesmo tempo num tablet ou computador do balcão |
| **RBAC de equipe** | **Fora do PI4** | Hoje `Funcionario` é cadastro, não login. Adicionar autenticação + permissões é uma frente inteira que competiria com o diferencial de IA |
| **Autenticação** | Mantém **JWT dual-guard** (`tymon/jwt-auth`), sem Sanctum | Funciona, está integrado ao frontend e ao `VerifyJwtTokenVersion`. Trocar seria reescrita sem ganho |
| **Processamento assíncrono** | **Laravel Queues**, sem Node/BullMQ/Redis novos | A queue nativa já roda no `docker-compose` e atende a escala atual. Um segundo runtime só se justificaria com pipeline de vídeo |
| **Visão computacional** | **Fora do PI4** | Exige câmeras físicas instaladas em restaurante parceiro e dataset de treino — depende de parceria comercial que o projeto não tem |
| **IA por voz (ligar para o restaurante)** | **Descartada** como mecanismo central | Não elimina a ligação, só transfere o custo para a plataforma; é lenta e frágil; não vira estado no banco; e tem risco regulatório de telemarketing no Brasil |
| **Provedor de LLM** | **OpenRouter**, não API direta de um provedor | Mais barato, já há acesso, e permite trocar de modelo conforme custo/desempenho sem mexer no código |
| **Como a IA responde números** | **Tool-calling sobre dados reais** — o LLM nunca inventa número | A resposta vem de uma função que consulta o banco; o modelo só formata em linguagem natural |
| **Estimativa de espera** | **Heurística estatística** (média histórica condicionada), não ML treinado | Só começamos a coletar histórico no Sprint 1; não haverá volume para treinar um modelo que supere a média |
| **Cold start / restaurantes não-parceiros** | **Pós-PI4** | Ver "Modelo de duas camadas" abaixo |

### Modelo de duas camadas (norte do produto — agenda pós-PI4)

A dependência do restaurante é **irredutível** para fila e mesas em tempo real: alguém precisa ser
a fonte da verdade do que acontece no salão. Mas essa participação deve ser **conquistada, nunca
pré-requisito**:

- **Camada 0 — não-parceiro (shadow):** o restaurante é listado a partir de dados públicos; o
  cliente pede reserva; o sistema manda WhatsApp ("Mesa para 4 às 20h? SIM/NÃO"); uma IA interpreta
  a resposta em texto livre; o cliente é notificado. Sem conta, sem painel, sem contrato. É isto
  que resolve o cold start — permite listar centenas de restaurantes no dia 1.
  **Bloqueio técnico atual:** `Restaurante` **é** a tabela de autenticação (`extends
  Authenticatable implements JWTSubject, MustVerifyEmail`), com `email`, `cnpj` e `password`
  obrigatórios. Não existe restaurante "reivindicável". Separar a identidade do estabelecimento das
  credenciais de acesso é o pré-requisito da Camada 0.
- **Camada 1 — parceiro (claim):** ao ver demanda chegando, o restaurante reivindica a listagem e
  **ganha** fila virtual, QR e dashboard. A obrigação de operar o painel só recai sobre quem optou
  por ela.

**O PI4 constrói a melhor Camada 1 possível.** A Camada 0 é o primeiro item grande depois.

### Ressalva de tese (decisão consciente adiada)

O documento de visão externo que originou parte deste roadmap descreve um produto **vendido ao
restaurante** (SaaS de eficiência operacional). O Deep Dish, como está, é **voltado ao consumidor**
(matar a fila). O MVP é quase o mesmo; o cliente pagante é diferente. Não precisa ser resolvido
para entregar o PI4, mas precisa ser resolvido antes de tratar o projeto como produto.

---

## Mobile: Capacitor agora, React Native em aberto

**Esta é a única decisão do documento que está *em teste*, não travada.** O projeto vai para mobile
no PI4 via **Capacitor**; **React Native permanece como alternativa viva**, com critérios objetivos
de reavaliação definidos abaixo.

> Esta seção **substitui** a entrada anterior do [`visao-produto.md`](./visao-produto.md), que
> registrava React Native como "descartado — decisão travada". Os dois documentos foram
> reconciliados: RN passa a ser alternativa em aberto, com gatilho de reavaliação definido.

### A diferença entre os dois

**Capacitor** pega o resultado do `npm run build` — a pasta `dist/` — e o empacota dentro de um
projeto Android/iOS nativo. O app roda numa WebView em tela cheia: sem barra de navegador, ícone
próprio, APK instalável, publicável em loja. Por cima disso ele expõe **plugins nativos** ao
JavaScript: push FCM, câmera (para o scanner de QR), deep link, splash, haptics.

**React Native** renderiza componentes nativos de verdade, sem WebView. O preço é que ele não roda
DOM: `<div>` vira `<View>`, `<p>` vira `<Text>`, `react-router-dom` vira React Navigation,
`recharts` vira `victory-native`, shadcn/Radix vira outra biblioteca, `framer-motion` vira
Reanimated.

### O custo, medido neste repositório

| Camada | Linhas | Sobrevive numa migração para React Native? |
|---|---:|---|
| `src/services/` + `src/contexts/` + `src/types/` | ~1.000 | **Sim** — trocando `localStorage` por `AsyncStorage` |
| `src/pages/app/` (7 telas do cliente) | 1.740 | Não — reescrita |
| Telas de auth (login, registro, verify, reset…) | parte de 2.526 | Não — reescrita |
| `src/components/ui/` (53 componentes shadcn) | 4.952 | Não — troca de biblioteca |
| `src/pages/restaurant/` (painel) | 1.642 | Permanece na web (decisão travada) |

**~1.000 linhas migram; ~12 telas seriam reescritas.** Com Expo + NativeWind boa parte das classes
Tailwind sobreviveria, então não é catastrófico — mas é um sprint inteiro, no semestre cujo
diferencial prometido é a IA. E passaria a existir um segundo frontend para manter, ao lado do
painel web.

### Por que Capacitor primeiro

Entrega APK real, com push nativo e câmera, **sem reescrever nada** — cabe em 2 issues do Sprint 3
em vez de consumir um sprint. E as duas opções não se anulam: a camada que sobreviveria a uma
migração para RN (`services/`, `AuthContext`, `types/`, schemas Zod) é exatamente a que o Capacitor
não toca. **Testar Capacitor primeiro não desperdiça trabalho caso a decisão mude.**

### O que faria mudar para React Native

Critérios explícitos, para que a reavaliação seja objetiva e não uma questão de gosto:

1. Lentidão perceptível em rolagem de lista ou transição de tela no APK, em **aparelho físico**
   (não emulador).
2. Necessidade de um recurso nativo sem plugin Capacitor maduro.
3. Push ou câmera instáveis através da WebView.
4. Exigência de publicação em loja com padrão de UX nativo que a WebView não alcance.

### Quando reavaliar

**Ao fim da issue #22**, com o APK instalado num celular de verdade. Não antes, e não por
especulação. O critério de aceite da #22 exige que o veredito seja anotado no próprio card.

Se o veredito for negativo, o caminho é abrir um **spike de React Native no backlog pós-PI4**
(Expo + NativeWind + React Navigation, mantendo o painel do restaurante na web) — **não** tentar a
migração dentro do semestre.

---

## Estado atual do código (verificado)

As issues citam estes fatos. Todos foram confirmados no código antes de escrever este documento.

**Fila**
- `clientefila` tem **4 colunas de dados**: `id`, `fila_id`, `cliente_id`, `qntd_pessoas`. Sem
  status, sem posição, sem tempo. Um campo `estimated_time` existiu entre 2026-03-10 e 2026-03-31 e
  desapareceu quando a migration de UUID recriou a tabela — indício de uma tentativa anterior de
  estimar espera, abandonada por falta de dado que a sustentasse.
- `ClienteFila` é **hard-deleted em 3 pontos**: `FilaService::cancelarPosicao` (linha 68),
  `FilaService::promoverProximoParaMesa` (linha 125) e `FilaController::removerRestaurante`
  (linha 93 — este ainda duplica inline a lógica de `encerrarFilaSeVazia`, linhas 95-97).
- Posição não é persistida: `ClienteFila::getPosicaoAttribute()` conta por `created_at` a cada
  serialização.
- `promoverProximoParaMesa` tem **um único call site**: `ReservaController::liberar`, linha 384.
  Cancelamento e expiração de reserva liberam a mesa mas **não** promovem a fila.
- A mesma função **trava a fila** quando o primeiro da vez não cabe na mesa: `FilaService.php:112`
  retorna `null` sem tentar o próximo (head-of-line blocking).
- `FilaController::store` não verifica a flag `fila_ativa` do restaurante.

**Reserva e mesa**
- Não existe model `Reserva` — reservas vivem em `ClienteMesa` (tabela `clientemesa`).
- `horario_checkin` é gravado no check-in e **nunca lido** por nada.
- Duração fixa de 60 min; tolerância de no-show de 60 min; `reservas:expirar` roda a cada 5 min
  (`routes/console.php:11`).
- Defaults do banco divergem do vocabulário do código: `fila.status='pendente'`,
  `mesa.status='disponível'`, `clientemesa.status='pendente'`.

**Infra**
- **Zero** broadcasting — `config/broadcasting.php` não existe, não há eventos nem listeners.
- **Zero** jobs próprios; as únicas 2 notificações do sistema são de autenticação, por e-mail.
- **Zero** integração com LLM.
- `docker-compose.yml` tem 3 serviços: `backend`, `queue`, `frontend`.

**Frontend**
- `DashboardController::stats()` devolve 4 contadores instantâneos; a taxa de ocupação é calculada
  no navegador em `pages/restaurant/Dashboard.tsx`.
- `recharts` está instalado e `components/ui/chart.tsx` existe, mas **nenhuma página os usa**.
- Polling manual com `setInterval(30_000)` em `pages/app/Queue.tsx` e `pages/app/Home.tsx`. Não há
  websocket em lugar nenhum.
- A espera estimada exibida ao cliente é o literal `'~30'` (`pages/app/Queue.tsx:290`).
- `QueryClientProvider` está montado (`App.tsx:44`), mas as páginas usam `useState`/`useEffect`.

**Testes e dados**
- Testes são stubs. `tests/Feature/ExampleTest.php` faz `GET /`, mas `routes/web.php` está vazio.
- `phpunit.xml` aponta para sqlite, incompatível com o SQL Postgres cru em
  `ReservaController.php:180,194` (`interval '1 hour'`).
- `DatabaseSeeder` e `UserFactory` referenciam `App\Models\User`, **que não existe** — `db:seed`
  quebra. Não há factory para nenhum model real.

---

# Sprint 1 — Fundação: histórico operacional

**Semanas 1-3 · 10 issues · 35 pontos** (7 abaixo + as issues de CI **#30**, **#31** e **#32**, no adendo ao
fim do documento)

Sem nenhuma IA visível. Este sprint existe porque **hoje o sistema apaga o dado que os outros três
sprints precisam consumir**. Sem ele, analytics, estimativa de espera e assistente não têm sobre o
que operar.

**Divisão sugerida:** 2 pessoas em backend (#1-#4), 1 em testes (#7), 1 em dados + wireframe do
dashboard do Sprint 2 (#5, #6).

---

### #1 — `feature:` histórico de saída da fila em `clientefila`

`labels: banco, backend, fundação` · `3 pontos` · **Sem dependências**

**Contexto.** `clientefila` tem só `id`, `fila_id`, `cliente_id`, `qntd_pessoas`. Quando o cliente
sai da fila — por qualquer motivo — o registro é apagado. Não existe tempo de espera, taxa de
abandono nem conversão fila→mesa em lugar nenhum do sistema.

**Decisão de modelagem:** colunas na própria tabela + soft delete, **não** uma tabela de histórico
separada. Motivo: preserva o cálculo de posição por `created_at` sem duplicar dados, e um scope
resolve a separação entre ativo e histórico.

**Tarefas**
- Migration adicionando a `clientefila`:
  - `status_saida` (nullable) — `atendido` | `desistiu` | `removido` | `expirado`
  - `chamado_em` (timestamp, nullable) — quando o cliente foi chamado (usado pela #12)
  - `saiu_em` (timestamp, nullable)
  - `tempo_espera_segundos` (integer, nullable)
  - `deleted_at` (soft delete)
- No model `ClienteFila`: trait `SoftDeletes`, casts de data, e scope `ativas()`
  (`whereNull('status_saida')`).
- Documentar no PR o vocabulário dos 4 valores de `status_saida` — ele é usado por #2, #3, #12 e
  por todo o Analytics do Sprint 2.

**Critérios de aceite**
- [ ] `php artisan migrate` roda limpo e `migrate:rollback` desfaz sem erro.
- [ ] `ClienteFila::ativas()` retorna só quem ainda está na fila.
- [ ] Registro com `status_saida` preenchido some das listagens atuais sem mudança nos controllers.
- [ ] Nenhum comportamento visível muda ainda — esta issue só abre espaço no banco.

---

### #2 — `refactor:` `FilaService` grava histórico em vez de apagar

`labels: backend, fundação` · `5 pontos` · **Depende de: #1**

**Contexto.** `FilaService::cancelarPosicao` (linha 68) e `promoverProximoParaMesa` (linha 125)
chamam `$registro->delete()` num model sem soft delete — o dado evapora.

**Tarefas**
- `cancelarPosicao`: preencher `status_saida='desistiu'`, `saiu_em`, `tempo_espera_segundos`
  (calculado de `created_at` até agora) **antes** do `delete()` — que agora é soft.
- `promoverProximoParaMesa`: mesma coisa com `status_saida='atendido'`.
- Trocar por `ativas()` todas as consultas que hoje presumem que "existir na tabela" significa
  "estar na fila": `enfileirar` (checagem de entrada dupla), `consultarPosicao`,
  `promoverProximoParaMesa`, `encerrarFilaSeVazia`, `ClienteFila::getPosicaoAttribute()` e o
  accessor `Restaurante::getTamanhoFilaAtualAttribute`.

**Atenção:** este é o ponto de maior risco do sprint. Uma consulta esquecida sem `ativas()` faz
gente que já saiu continuar contando na posição da fila. Varra todos os usos de `ClienteFila`
antes de fechar o PR.

**Critérios de aceite**
- [ ] Cancelar posição não apaga a linha; ela passa a ter `status_saida='desistiu'` e
      `tempo_espera_segundos` coerente.
- [ ] Promover para mesa grava `status_saida='atendido'`.
- [ ] Cliente que saiu **não** conta na posição de quem ficou, nem no `tamanho_fila_atual`.
- [ ] Cliente que saiu consegue entrar na fila de novo (a checagem de entrada dupla usa `ativas()`).

---

### #3 — `fix:` `removerRestaurante` delega ao `FilaService`

`labels: backend, bug` · `2 pontos` · **Depende de: #2**

**Contexto.** `FilaController::removerRestaurante` (linhas 92-97) faz `delete()` direto e
**reimplementa inline** a lógica de encerrar fila vazia que já existe em
`FilaService::encerrarFilaSeVazia`. Com a #2, essa duplicação vira um caminho que não grava
histórico.

**Tarefas**
- Criar `FilaService::removerPeloRestaurante(string $clienteFilaId, string $restauranteId)` que
  valida a posse, grava `status_saida='removido'` e reusa `encerrarFilaSeVazia`.
- Reduzir o controller a validação de request + chamada do service.

**Critérios de aceite**
- [ ] Remoção pelo restaurante grava `status_saida='removido'`.
- [ ] Não há mais `->delete()` de `ClienteFila` fora do `FilaService`.
- [ ] Fila que fica vazia continua sendo encerrada.
- [ ] Remover entrada de outro restaurante continua devolvendo 404.

---

### #4 — `feature:` duração real de permanência em `clientemesa`

`labels: banco, backend, fundação` · `3 pontos` · **Sem dependências**

**Contexto.** `horario_checkin` é gravado no check-in e nunca lido. Sem o horário de saída não há
como calcular giro de mesa nem duração média — que é o insumo da estimativa de espera (#10).

**Tarefas**
- Migration: `horario_saida` (timestamp, nullable) e `duracao_segundos` (integer, nullable) em
  `clientemesa`.
- Preencher ambos em `ReservaController::liberar` e em `ReservaController::expirarReservasVencidas`
  (nos dois ramos: no-show e sessão estourada).
- Quando não houve check-in (no-show), `duracao_segundos` fica `null` — não zero. Zero seria lido
  como "sentou e saiu na hora" e contaminaria a média.

**Critérios de aceite**
- [x] Liberar mesa grava `horario_saida` e `duracao_segundos` = diferença até o `horario_checkin`.
- [x] Reserva expirada por no-show fica com `duracao_segundos = null`.
- [ ] Sessão expirada pelo command grava a duração normalmente.
- [ ] Backfill não se aplica — não há dado histórico a recuperar.

---

### #5 — `chore:` factories dos models + conserto do `DatabaseSeeder`

`labels: banco, testes` · `3 pontos` · **Sem dependências**

**Contexto.** `DatabaseSeeder` e `UserFactory` referenciam `App\Models\User`, um model que **não
existe** neste projeto — os models de autenticação são `Cliente` e `Restaurante`. `php artisan
db:seed` lança erro de classe não encontrada. Não existe factory para nenhum model real, o que
impede escrever teste ou seeder.

**Tarefas**
- Apagar `database/factories/UserFactory.php` e limpar o `DatabaseSeeder`.
- **Adicionar o trait `HasFactory`** a `Mesa`, `Funcionario`, `Fila`, `ClienteFila` e `ClienteMesa`
  — descoberto durante a execução: só `Cliente` e `Restaurante` tinham, e sem ele o método
  `Model::factory()` nem existe.
- Criar factories para os 7 models, com dados brasileiros plausíveis.
- Seeder de cenário clicável: restaurantes com fila povoada e reservas em estados variados.

**Critérios de aceite**
- [x] `php artisan db:seed` roda sem erro num banco limpo.
- [x] Toda factory gera model persistível sem violar constraint (inclusive os uniques
      `restaurante_id + numero` em `mesa` e `restaurante_id + cpf` em `funcionario`).
- [x] Nenhuma referência a `App\Models\User` sobra no repositório.

**Decisões de execução**

- **`fake('pt_BR')` fixo no código**, não via `APP_FAKER_LOCALE`. `.env` já existentes não mudam
  quando o `.env.example` muda — depender do env faria cada pessoa gerar dados diferentes, e um
  teste passaria numa máquina e falharia noutra.
- **Os states de saída passam pelo `registrarSaida()` do model**, em vez de escreverem
  `status_saida`/`saiu_em` direto. Escrever direto burlaria o hook `deleting` do `ClienteFila` e
  produziria dado que não corresponde ao que o sistema real gera.
- **`created_at` no passado antes do `registrarSaida()`.** Sem isso todo registro histórico sai com
  `tempo_espera_segundos = 0` — pego durante a verificação, e teria inutilizado as métricas da
  `#8` (AnalyticsService). Os states aceitam o tempo: `atendido(20)`, `desistiu(35)`.

⚠️ **Achado que afeta a `#12`** (detecção automática de desistência): a coluna **`chamado_em` não
existe**. O plano previa que a `#1` a criasse, mas o PR entregue adicionou apenas `status_saida`,
`saiu_em`, `tempo_espera_segundos` e `deleted_at`. Sem saber *quando* o cliente foi chamado, não há
como decidir que ele desistiu — a `#12` precisa criar a coluna ou adotar outro critério.

---

### #6 — `feature:` seeder de histórico sintético

`labels: banco, fundação` · `5 pontos` · **Depende de: #1, #4, #5**

**Contexto.** 12 semanas de uso real não geram volume suficiente para o dashboard do Sprint 2 e o
assistente do Sprint 4 ficarem interessantes na apresentação. Precisamos de histórico plausível
gerado.

**Tarefas**
- Seeder que produz ~12 semanas de fila e reservas encerradas, com curvas realistas:
  - picos de almoço (11h30-14h) e jantar (19h-22h);
  - fim de semana mais cheio que dia de semana;
  - taxa de abandono maior quando a fila estava longa;
  - duração de permanência variando com o tamanho do grupo.
- Marcar a origem do dado de forma inequívoca — flag no seeder e/ou faixa de datas documentada — de
  modo que qualquer gráfico da entrega possa declarar o que é sintético.
- Comando parametrizável (restaurante alvo, número de semanas) para poder regerar.

**Critérios de aceite**
- [ ] Rodar o seeder popula histórico coerente: todo `ClienteFila` fechado tem `status_saida`,
      `saiu_em` e `tempo_espera_segundos`; todo `ClienteMesa` fechado tem `duracao_segundos`.
- [ ] Distribuição por hora do dia reproduz os dois picos ao ser plotada.
- [ ] Rodar duas vezes não duplica nem corrompe o dado.
- [ ] O documento de entrega (#29) consegue apontar exatamente qual dado é sintético.

---

### #7 — `test:` infraestrutura de testes + suíte do `FilaService`

`labels: testes, fundação` · `5 pontos` · **Depende de: #2, #3**

**Contexto.** A suíte é composta de dois stubs. `tests/Feature/ExampleTest.php` faz `GET /`, mas
`routes/web.php` está vazio. E `phpunit.xml` aponta para sqlite em memória, incompatível com o SQL
Postgres cru de `ReservaController.php:180,194` (`interval '1 hour'`) — qualquer teste de reserva
falharia por motivo errado.

**Tarefas**
- Configurar `phpunit.xml` para um banco Postgres de teste (o projeto já usa Postgres em
  desenvolvimento); documentar no README como criá-lo.
- Remover os `ExampleTest` e adotar `RefreshDatabase`.
- Testes do `FilaService` cobrindo os **três** caminhos de saída (#2, #3): cancelamento pelo
  cliente, promoção para mesa, remoção pelo restaurante — verificando `status_saida`, `saiu_em` e
  `tempo_espera_segundos` em cada um.
- Testes de que quem saiu não afeta a posição de quem ficou, e de que pode reentrar na fila.

**Critérios de aceite**
- [ ] `composer test` passa do zero num banco limpo.
- [ ] Os 3 caminhos de saída têm teste que falharia se o histórico deixasse de ser gravado.
- [ ] Nenhum teste depende de dado deixado por outro (`RefreshDatabase` em todos).

---

# Sprint 2 — Analytics real e estimativa de espera

**Semanas 4-6 · 7 issues · 29 pontos**

O histórico do Sprint 1 vira número visível — para o restaurante (dashboard) e para o cliente
(quanto vou esperar).

---

### #8 — `feature:` `AnalyticsService`

`labels: backend` · `8 pontos` · **Depende de: #1, #4**

**Contexto.** O único agregado que existe hoje é `DashboardController::stats()`, com 4 contadores
instantâneos. Nada olha para trás no tempo.

**Tarefas** — implementar como métodos independentes, cada um recebendo `restaurante_id` e um
período:
- tempo médio de espera por dia da semana e por faixa horária;
- taxa de abandono (`desistiu` + `expirado` sobre o total de entradas);
- taxa de ocupação ao longo do tempo;
- giro de mesa (atendimentos por mesa por período) e duração média de permanência;
- mapa de calor de demanda: volume por dia da semana × hora.

**Restrição de projeto importante:** as assinaturas destes métodos serão expostas como *tools* do
LLM no Sprint 4 (#25). Cada método deve ter parâmetros explícitos e tipados, retorno serializável e
docblock em português descrevendo o que responde — esse texto vira a descrição da ferramenta.

**Critérios de aceite**
- [ ] Cada método tem teste com dado controlado, verificando o número calculado.
- [ ] Período sem dado devolve estrutura vazia bem-formada, nunca erro nem divisão por zero.
- [ ] Consultas são agregadas em SQL, não em laço PHP sobre coleção carregada.
- [ ] Nenhum método vaza dado de outro restaurante.

---

### #9 — `feature:` endpoint de analytics

`labels: backend` · `3 pontos` · **Depende de: #8**

**Tarefas**
- `GET /restaurante/analytics` com filtro de período (`data_inicio`, `data_fim`, com padrão
  sensato), atrás dos mesmos middlewares dos demais endpoints de restaurante.
- Manter `GET /restaurante/dashboard` funcionando — o frontend atual depende dele.

**Critérios de aceite**
- [ ] Responde com os indicadores do #8 num payload único e documentado.
- [ ] Sem token de restaurante → 401; com token de cliente → 401/403.
- [ ] O dashboard atual continua carregando sem alteração no frontend.

---

### #10 — `feature:` `EstimativaEsperaService`

`labels: backend` · `5 pontos` · **Depende de: #1**

**Contexto.** O número mostrado ao cliente hoje é o literal `'~30'` no JSX
(`pages/app/Queue.tsx:290`). Um campo `estimated_time` já existiu na tabela e foi removido —
provavelmente porque não havia dado para sustentá-lo. Agora há.

**Tarefas**
- Estimativa condicionada a: restaurante + dia da semana + faixa horária + tamanho atual da fila +
  tamanho do grupo.
- Degradação explícita quando a amostra é pequena: cair para uma média mais ampla (só o
  restaurante, depois um padrão global) e **retornar junto qual nível foi usado**.
- Registrar em docblock que é heurística estatística, não ML — inclusive para a redação do TCC.

**Critérios de aceite**
- [ ] Com histórico farto, a estimativa acompanha a média observada daquela faixa.
- [ ] Com histórico ralo, cai para o nível mais amplo sem erro e sinaliza isso na resposta.
- [ ] Sem nenhum histórico, devolve o padrão declarado — nunca `null` silencioso nem exceção.
- [ ] Testes cobrem os três níveis de degradação.

---

### #11 — `feature:` expor espera estimada na API

`labels: backend` · `2 pontos` · **Depende de: #10**

**Tarefas**
- `POST /fila` e `GET /fila/posicao` passam a devolver `espera_estimada_minutos` e a origem do
  número (histórico específico / média ampla / padrão).
- Expor também na listagem do restaurante (`GET /restaurante/fila`), útil ao gerente.

**Critérios de aceite**
- [ ] Entrar na fila devolve a estimativa junto da posição.
- [ ] Consultar posição devolve estimativa recalculada com a fila atual.
- [ ] A origem do número vem no payload, para o frontend poder ser honesto sobre a confiança.

---

### #12 — `feature:` detecção automática de desistência na fila

`labels: backend, infra` · `3 pontos` · **Depende de: #1**

**Contexto.** Existe expiração automática para *reserva* (`reservas:expirar`, a cada 5 min em
`routes/console.php:11`), mas **não** para fila. Sem isso, "taxa de abandono" só captura quem
cancelou manualmente — quem simplesmente foi embora fica registrado como se ainda estivesse
esperando, e sub-registra a métrica.

**Tarefas**
- Command `fila:expirar-chamados`, agendado como o de reservas.
- Regra: entrada com `chamado_em` preenchido há mais de X minutos sem confirmação vira
  `status_saida='expirado'`. X configurável, com padrão documentado.
- Ao expirar, disparar a promoção da fila (integra com #15 quando ele existir; até lá, no mínimo
  liberar o lugar).

**Critérios de aceite**
- [ ] Com o relógio avançado no teste, entrada chamada e não confirmada vira `expirado`.
- [ ] Entrada não chamada **nunca** expira por este command.
- [ ] O command é idempotente — rodar duas vezes seguidas não altera nada a mais.
- [ ] O agendamento aparece em `php artisan schedule:list`.

---

### #13 — `feature:` dashboard do restaurante com gráficos

`labels: frontend` · `5 pontos` · **Depende de: #9**

**Contexto.** `pages/restaurant/Dashboard.tsx` são 4 cards estáticos, com a taxa de ocupação
calculada no navegador, um `useEffect` no mount e sem react-query. `recharts` e
`components/ui/chart.tsx` já estão instalados e **nunca foram usados** — não é preciso instalar
nada.

**Tarefas**
- Migrar a página para react-query (o `QueryClientProvider` já está montado em `App.tsx:44`).
- Gráficos sobre o endpoint do #9: espera média por faixa horária, mapa de calor dia × hora,
  abandono e ocupação ao longo do período.
- Seletor de período.
- Estados de carregando, erro e **vazio** (o atual não tem empty state).
- Mover a conta da taxa de ocupação para o backend.

**Critérios de aceite**
- [ ] Gráficos legíveis em telas de tablet e desktop — o painel é usado em tela grande.
- [ ] Trocar o período refaz a consulta sem recarregar a página.
- [ ] Restaurante sem histórico vê mensagem de vazio, não gráfico quebrado nem `NaN`.
- [ ] Erro de rede mostra estado de erro com opção de tentar de novo.

---

### #14 — `feature:` espera estimada real nas telas do cliente

`labels: frontend` · `3 pontos` · **Depende de: #11**

**Tarefas**
- Substituir o literal `'~30'` em `pages/app/Queue.tsx:290` pelo valor da API.
- Mostrar a estimativa em `pages/app/RestaurantDetail.tsx` **antes** de entrar na fila — é aí que a
  informação muda a decisão do cliente.
- Comunicar a confiança do número usando a origem que vem do #11 (ex.: "~25 min" quando há
  histórico; "estimativa aproximada" quando é fallback). Não apresentar chute com cara de precisão.

**Critérios de aceite**
- [ ] Nenhum número de espera hardcoded sobra no frontend.
- [ ] Estimativa aparece antes de entrar na fila e se atualiza junto com a posição.
- [ ] Estimativa em fallback é visualmente distinguível de estimativa com histórico.

---

# Sprint 3 — Alocação inteligente, tempo real e app

**Semanas 7-9 · 9 issues · 44 pontos**

O maior sprint do semestre, e o que mais aparece na banca: a fila passa a andar sozinha, o painel
atualiza sem F5 e o cliente recebe push no celular.

⚠️ **Sprint sobrecarregado — e o número deixa isso explícito.** São 44 dos 128 pontos do backlog:
**34% do semestre inteiro em 3 semanas**, contra 26 pontos do Sprint 1. Trate o replanejamento como
provável, não como imprevisto.

Se algo precisar escorregar, a ordem de corte é **#17 → #21 → #18** (as duas primeiras são
melhorias sobre um fluxo que já funciona; a #18 alimenta uma métrica do Analytics, então cortá-la
tem efeito colateral). As issues **#15, #16, #22 e #23 são intocáveis** — são elas que sustentam a
demo da banca.

---

### #15 — `refactor:` ponto único de promoção da fila

`labels: backend, bug, fundação` · `5 pontos` · **Depende de: #2**

**Contexto — este é o bug de produto mais grave do sistema.**
`FilaService::promoverProximoParaMesa` tem **um único call site**: `ReservaController::liberar`,
linha 384. Ou seja: se o restaurante cancela uma reserva, ou se ela expira pelo cron, a mesa fica
livre e **a fila não anda**. Na prática o produto assume um restaurante logado clicando no painel o
tempo todo; sem isso, a fila trava.

**Tarefas**
- Extrair um ponto único de promoção (ex.: `FilaService::processarPromocoes(string $restauranteId)`)
  que varre as mesas livres e promove quem couber.
- Chamá-lo em **todos** os caminhos que liberam mesa:
  - `ReservaController::liberar` (já chama),
  - `ReservaController::destroy` (cancelamento) — hoje não chama,
  - `ReservaController::expirarReservasVencidas` (cron) — hoje não chama,
  - expiração de fila do #12.
- Respeitar a flag `fila_ativa` do restaurante (hoje ignorada até no `FilaController::store`).

**Critérios de aceite**
- [ ] Cancelar reserva libera a mesa **e** promove o próximo da fila.
- [ ] `reservas:expirar` promove a fila ao liberar mesas — teste com relógio avançado.
- [ ] Restaurante com `fila_ativa = false` não sofre promoção automática.
- [ ] Não há mais de um lugar no código chamando a promoção diretamente.

---

### #16 — `feature:` alocação otimizada de mesa

`labels: backend, ia` · `8 pontos` · **Depende de: #15**

**Contexto.** Hoje a alocação é *greedy* e ainda por cima trava: `FilaService.php:112` retorna
`null` se o **primeiro** da fila não couber na mesa liberada — sem tentar o segundo. Uma família de
6 na frente bloqueia indefinidamente um casal atrás, mesmo com mesa de 2 vaga.

**Tarefas**
- Substituir por uma passada que considera **todas** as mesas livres × **todas** as entradas
  abertas, escolhendo a combinação que minimiza capacidade desperdiçada.
- Eliminar o head-of-line blocking como consequência.
- Registrar a métrica de desperdício (lugares ociosos por alocação) para poder apresentar o
  antes/depois com número — este é o diferencial mais defensável tecnicamente do semestre.
- Manter o `lockForUpdate` e a transação; a passada não pode abrir janela de corrida.

**Critérios de aceite**
- [ ] Grupo grande na frente **não** bloqueia mais grupo pequeno atrás quando há mesa que o comporta.
- [ ] Teste comparando a alocação nova com a greedy no mesmo cenário mostra desperdício menor.
- [ ] Duas promoções concorrentes não atribuem a mesma mesa a dois clientes (teste de concorrência).
- [ ] A métrica de desperdício fica registrada e consultável para a apresentação.

---

### #17 — `feature:` priorização da fila por encaixe

`labels: backend, ia` · `5 pontos` · **Depende de: #16**

**Contexto.** A ordem é FCFS puro, derivada de `created_at`. A mesma "IA de otimização" do #16
aplicada à *ordem* da fila, não só à alocação.

**Tarefas**
- Critério de reordenação: grupo que encaixa exatamente numa mesa prestes a vagar sobe.
- **Anti-starvation obrigatório:** tempo máximo de espera acima do qual a entrada volta a ter
  prioridade absoluta. Sem essa regra, um grupo de 6 pode nunca ser chamado.
- Deixar a ordem exibida ao cliente coerente com a ordem real de chamada — se alguém "passou na
  frente", a tela não pode mostrar posição estável e mentirosa.

**Critérios de aceite**
- [ ] Reordenação melhora a ocupação num cenário de teste com números.
- [ ] Nenhuma entrada espera acima do limite configurado — teste explícito de starvation.
- [ ] A posição mostrada ao cliente reflete a ordem real de chamada.

---

### #18 — `feature:` estados intermediários de mesa

`labels: backend, frontend, banco` · `3 pontos` · **Sem dependências**

**Contexto.** Mesa hoje é `livre` | `ocupada` | `bloqueada` (`reservada` está no vocabulário mas
nenhum código escreve). Falta o intervalo entre "cliente saiu" e "mesa pronta", que é justamente
onde o tempo se perde. Não depende de RBAC nem de câmera — a própria conta do restaurante marca.

**Tarefas**
- Adicionar `aguardando_limpeza` e `em_limpeza`; endpoint de transição no painel.
- **Normalizar de passagem os defaults divergentes do banco**, que hoje contradizem o código:
  `mesa.status='disponível'`, `fila.status='pendente'`, `clientemesa.status='pendente'`. Migration
  corrigindo o default e os registros remanescentes.
- Registrar o tempo em limpeza, alimentando a métrica de tempo médio de limpeza no Analytics.

**Critérios de aceite**
- [ ] Fluxo `ocupada → aguardando_limpeza → em_limpeza → livre` funciona pelo painel.
- [ ] Mesa em limpeza **não** é candidata a promoção da fila.
- [ ] Defaults do banco batem com o vocabulário usado no código.
- [ ] Tempo médio de limpeza aparece no Analytics do #8.

---

### #19 — `feature:` Laravel Reverb e eventos de broadcast

`labels: infra, backend` · `5 pontos` · **Depende de: #15, #18**

**Contexto.** Não há broadcasting nenhum — `config/broadcasting.php` não existe, não há `app/Events`
nem `app/Listeners`, e `BROADCAST_CONNECTION=log`.

**Tarefas**
- Instalar e configurar Reverb; criar `config/broadcasting.php`.
- Eventos `FilaAtualizada` e `MesaStatusMudou`, disparados nos pontos de mudança de estado (#15,
  #18), em canais privados por restaurante.
- **Autorização de canal:** cliente só ouve a própria posição; restaurante só ouve o próprio salão.
  Vale o mesmo cuidado dos endpoints — canal privado, não público.
- Serviço no `docker-compose.yml` (hoje só `backend`, `queue`, `frontend`) e variáveis no
  `.env.example`.

**Critérios de aceite**
- [ ] `docker-compose up` sobe o Reverb junto do resto.
- [ ] Liberar mesa dispara evento recebido por um cliente conectado.
- [ ] Cliente autenticado **não** consegue assinar o canal de outro restaurante nem de outro cliente.
- [ ] Reverb fora do ar degrada para o comportamento atual sem derrubar a aplicação.

---

### #20 — `feature:` tempo real no frontend

`labels: frontend` · `5 pontos` · **Depende de: #19**

**Contexto.** O polling manual está em **quatro** telas, não duas:

| Arquivo | Linha | O que faz |
|---|---|---|
| `pages/app/Queue.tsx` | 102 | posição na fila, a cada 30 s |
| `pages/app/Home.tsx` | 353 | mesma leitura, duplicada no card da home |
| `pages/app/ReservationDetail.tsx` | 55 | estado da reserva |
| `pages/restaurant/QueueManagement.tsx` | 33 | fila do lado do restaurante |

Pior: quando a consulta de posição dá 404, `Queue.tsx` chama `verificarPromocao()`, que **lista as
reservas do cliente e procura uma `confirmada`** para adivinhar que ele foi promovido. É uma
heurística frágil que o broadcast torna desnecessária.

**Tarefas**
- Instalar `laravel-echo` e conectar ao Reverb.
- Posição da fila ao vivo para o cliente; mapa de mesas e fila ao vivo no painel.
- Substituir o polling nas **quatro** telas acima e remover a heurística `verificarPromocao()`.
- Extrair a leitura de fila duplicada entre `Queue.tsx` e `Home.tsx` para um hook único — hoje a
  mesma lógica (e a mesma chave `deepdish_fila` no `localStorage`) está escrita duas vezes.
- Manter um polling de baixa frequência como rede de segurança caso o socket caia — sem isso, uma
  desconexão silenciosa congela a tela.

**Critérios de aceite**
- [ ] Posição do cliente muda sozinha quando alguém à frente sai, sem F5.
- [ ] Painel reflete mudança de status de mesa e de fila sem recarregar.
- [ ] Nenhum `setInterval(30_000)` sobra nas quatro telas listadas.
- [ ] A leitura de fila existe num lugar só, consumida por `Queue.tsx` e `Home.tsx`.
- [ ] Socket derrubado no meio da sessão reconecta ou cai no fallback sem tela travada.

---

### #21 — `feature:` QR de entrada na fila

`labels: backend, frontend` · `3 pontos` · **Sem dependências**

**Contexto.** Não há nenhuma biblioteca de QR no projeto. A entrada na fila hoje exige achar o
restaurante no app — atrito desnecessário para quem já está na porta.

**Tarefas**
- QR fixo por restaurante com payload assinado (o Laravel já assina rotas — mesmo mecanismo do
  `VerifyEmailNotification`).
- Tela "imprimir QR" no painel do restaurante.
- Rota que leva direto à entrada na fila daquele restaurante, tratando o caso de usuário não
  logado (login e depois voltar ao destino).

**Critérios de aceite**
- [ ] QR gerado no painel é imprimível e legível em papel.
- [ ] Escanear leva à tela de entrar na fila do restaurante certo.
- [ ] Payload adulterado é rejeitado.
- [ ] Usuário deslogado é levado ao login e retorna ao fluxo, sem perder o destino.

---

### #22 — `feature:` Capacitor — APK do app do cliente

`labels: mobile, frontend` · `5 pontos` · **Sem dependências**

**Contexto.** Decisão de arquitetura em teste — ver a seção *Mobile* no topo deste documento.
Empacota o React/Vite atual como app Android nativo, sem reescrever telas.

**Tarefas**
- Auditoria mobile-first das telas `/app/*` e do `AppLayout`: alvos de toque, área segura, teclado
  cobrindo campo, rolagem.
- Integrar Capacitor ao build do Vite; gerar o projeto Android.
- Resolver o acesso à API a partir do aparelho (`VITE_API_URL` com IP da máquina de
  desenvolvimento ou túnel) — `localhost` no celular aponta para o próprio celular.
- Ícone, splash e nome do app.
- Gerar APK de debug instalável. **Publicação em loja não é pré-requisito** (iOS exigiria conta
  paga).

**Critérios de aceite**
- [ ] APK instala e abre num aparelho Android físico.
- [ ] Login, entrar na fila e ver posição funcionam pelo app contra a API real.
- [ ] Gesto de voltar do Android navega, não fecha o app.
- [ ] **Veredito Capacitor anotado neste card:** desempenho de rolagem de lista, comportamento do
      teclado e fluidez das transições, testados em aparelho físico. Se negativo, abrir issue de
      spike de React Native no backlog pós-PI4 — **não** migrar dentro do semestre.

---

### #23 — `feature:` push nativo

`labels: mobile, backend` · `5 pontos` · **Depende de: #15, #22**

**Contexto.** O sistema não tem nenhuma notificação de domínio — as duas únicas notificações são de
autenticação, por e-mail. O cliente só descobre que foi chamado se estiver com o app aberto. Isto
fecha o ciclo: a fila anda sozinha (#15) e o cliente **fica sabendo**.

**Tarefas**
- Tabela `device_tokens` (`cliente_id`, token, plataforma) e endpoint de registro.
- Canal FCM sobre a queue já existente (`queue:work` já roda no compose).
- `@capacitor/push-notifications` no app, registrando o token após o login.
- Notificações "você é o próximo" e "sua mesa está pronta", disparadas pelos eventos do #15.
- Deep link: tocar na notificação abre a tela da fila/reserva certa.
- Limpeza de token inválido quando o FCM devolve erro de registro.

**Riscos:** exige projeto Firebase configurado e aparelho com Google Play Services. Provisionar o
Firebase **antes** da semana da issue, não na véspera.

**Critérios de aceite**
- [ ] Push chega no aparelho com o app **em segundo plano** — é esse o caso que importa.
- [ ] Tocar na notificação abre a tela correta.
- [ ] Token é registrado no login e removido no logout.
- [ ] Falha no envio não derruba a promoção da fila (a notificação é efeito colateral, não etapa
      bloqueante).

---

# Sprint 4 — Assistente conversacional e fechamento

**Semanas 10-12 · 6 issues · 29 pontos**

O diferencial de IA generativa, construído sobre os números reais do Sprint 2 — e o fechamento da
entrega.

---

### #24 — `feature:` integração com OpenRouter

`labels: ia, backend` · `3 pontos` · **Sem dependências**

**Contexto.** Não há nenhuma integração com LLM no projeto. OpenRouter foi escolhido em vez da API
direta de um provedor por ser mais barato e permitir trocar de modelo sem mexer no código.

**Tarefas**
- Client HTTP com a chave em `config/services.php` (nunca hardcoded); modelo configurável por env.
- Timeout curto e tratamento de falha que **degrada sem derrubar o dashboard**.
- Log de uso: tokens consumidos por chamada, para acompanhar custo desde o primeiro dia.

**Critérios de aceite**
- [ ] Chamada de ida e volta funciona com modelo econômico configurado por env.
- [ ] Chave ausente ou inválida produz erro tratado, não exceção não capturada.
- [ ] Timeout não trava a requisição do usuário.
- [ ] Consumo de tokens fica registrado.

---

### #25 — `feature:` tool-calling sobre o `AnalyticsService`

`labels: ia, backend` · `8 pontos` · **Depende de: #8, #24**

**Contexto.** Este é o item que separa "IA de verdade" de "IA cosmética". O modelo **nunca** produz
um número por conta própria: ele escolhe uma função, a função consulta o banco, e o modelo só
transforma o retorno em frase.

**Tarefas**
- Expor os métodos do #8 como ferramentas, com descrição em português derivada dos docblocks.
- Loop de tool-calling: modelo pede ferramenta → executa → devolve resultado → modelo responde.
- **Escopo obrigatório:** toda ferramenta recebe o `restaurante_id` do token autenticado, **nunca**
  um vindo do texto do usuário. Sem isso, um gerente pode pedir os números do concorrente.
- Limite de iterações do loop, para pergunta ambígua não virar chamada infinita.

**Critérios de aceite**
- [ ] "Qual meu horário de pico?" resulta em chamada de ferramenta, não em número inventado.
- [ ] Nenhuma ferramenta aceita `restaurante_id` do texto do usuário — teste explícito de tentativa
      de acesso cruzado.
- [ ] Pergunta fora de escopo recebe recusa clara em vez de resposta fabricada.
- [ ] O loop tem teto de iterações.

---

### #26 — `feature:` endpoint de chat do assistente

`labels: ia, backend` · `5 pontos` · **Depende de: #25**

**Tarefas**
- `POST /restaurante/assistente/chat`, atrás dos middlewares de restaurante.
- Persistência da conversa (contexto entre mensagens) com limite de histórico enviado ao modelo.
- **Orçamento e rate limit por restaurante** — o roadmap marca custo de API como risco a definir
  *antes* do sprint, não na véspera. Teto de tokens por restaurante por período, com resposta clara
  ao ser atingido.

**Critérios de aceite**
- [ ] Conversa mantém contexto entre mensagens.
- [ ] Teto de tokens atingido devolve mensagem clara, não erro genérico.
- [ ] Rate limit impede abuso acidental (clique repetido não multiplica custo).
- [ ] Restaurante não acessa a conversa de outro.

---

### #27 — `feature:` tela de chat no painel

`labels: frontend, ia` · `5 pontos` · **Depende de: #26**

**Tarefas**
- Interface conversacional no dashboard do restaurante, com histórico e estados de
  carregando/erro.
- **Mostrar em qual indicador a resposta se baseou** — o gerente precisa poder conferir o número no
  gráfico ao lado. É isso que torna o assistente confiável em vez de oráculo.
- Sugestões de pergunta inicial, para a tela não nascer como caixa de texto vazia.

**Critérios de aceite**
- [ ] Perguntar e receber resposta funciona ponta a ponta contra dado real.
- [ ] A origem do número é visível na resposta.
- [ ] Erro e limite de uso têm tratamento visual, não toast genérico.
- [ ] Usável em tablet — é onde o painel roda.

---

### #28 — `test:` E2E dos fluxos críticos e hardening

`labels: testes` · `5 pontos` · **Depende de: #15, #16, #23**

**Tarefas**
- Fluxo completo em teste: entrar na fila → promoção automática → check-in → liberação → próximo
  promovido, com relógio avançado e `Notification::fake`.
- Cobertura dos caminhos de expiração (reserva e fila) e de concorrência na alocação.
- Rodada de correção dos bugs encontrados — reservar tempo para isso, não só para escrever o teste.

**Critérios de aceite**
- [ ] `composer test` passa do zero num banco limpo.
- [ ] O fluxo completo tem teste que falharia se a promoção automática regredisse.
- [ ] Bugs encontrados viram correção ou issue registrada, nenhum fica só no relato verbal.

---

### #29 — `docs:` roteiro de demo e documentação de entrega

`labels: docs` · `3 pontos` · **Depende de: #27, #28**

**Tarefas**
- **Roteiro da banca:** dois celulares na fila + painel num tablet; o restaurante libera uma mesa e
  a plateia vê o push chegar e a fila andar sozinha. Ensaiar com plano B para falha de rede — sala
  de apresentação é ambiente hostil.
- **Declaração de dado sintético vs real** — risco explícito no roadmap. Todo gráfico da
  apresentação precisa deixar claro de onde vem o dado.
- Atualizar `README` e `.env.example` com Reverb, FCM e OpenRouter.
- Registrar o veredito Capacitor da #22 na seção *Mobile* deste documento e no
  [`roadmap-ia.md`](./roadmap-ia.md) — se o React Native voltar à mesa, a decisão fica registrada
  para quem continuar o projeto.

**Critérios de aceite**
- [ ] Um integrante que não escreveu o código consegue rodar o projeto do zero seguindo o README.
- [ ] Roteiro de demo ensaiado ponta a ponta pelo menos uma vez com o hardware real.
- [ ] Origem dos dados declarada em todo material da apresentação.
- [ ] Veredito Capacitor registrado nos dois documentos.

---

# Adendo — CI no GitHub Actions (issues #30-#32)

**3 issues · 9 pontos.** Numeradas a partir da #30 porque as 29 anteriores já foram criadas no
GitHub. As três ficam no **Sprint 1**: elas protegem todo o resto do semestre, e separar a
ativação dos checks (#32) do workflow que os produz (#31) só criaria dependência entre sprints
sem ganho nenhum.

A ordem entre elas é obrigatória — **#30 → #31 → #32** — mas a #32 só deve ser ativada depois de
a CI rodar verde num PR real, não por cronograma.

### Por que só CI, sem CD

Não existe **nenhum** alvo de deploy configurado no repositório — sem Vercel, Netlify, Railway,
Render, Fly ou Procfile. O único artefato de build é o `Dockerfile` do backend, e o
`docker-compose.yml` não tem serviço de banco (o Postgres é o Supabase externo). Automatizar deploy
para lugar nenhum é trabalho jogado fora.

**Pré-requisito para retomar o CD:** decidir onde hospedar. Quando chegar a hora, o frontend é um
SPA Vite estático (Vercel ou Netlify resolvem sem configuração); o backend precisa de PHP + queue
worker + scheduler, então Railway ou Render rodando o `Dockerfile` existente. E não ligar deploy
automático em `main` antes dos testes de verdade da #7 e da #28 — senão é automação para publicar
bug mais rápido.

### Estado medido hoje (por que a ordem importa)

Os três checks que a CI rodaria estão **reprovando agora**:

| Check | Estado |
|---|---|
| `./vendor/bin/pint --test` | ✗ 42 arquivos fora do padrão |
| `npm run lint` | ✗ 7 erros (o comando sai com código 1) |
| `composer test` | ✗ `ExampleTest` faz `GET /`, mas `routes/web.php` está vazio → 404 ≠ 200 |

Uma CI que nasce vermelha ensina o time a ignorar o ✗. Por isso a #30 vem antes da #31.

---

### #30 — `chore:` deixar o repositório verde antes de ligar a CI

`labels: chore, testes, fundação` · `3 pontos` · **Sem dependências**

**Contexto.** Pré-requisito da #31. Nenhuma das correções abaixo é difícil — o valor está em fazer
todas antes de existir um check automático, para a CI nascer verde.

**Tarefas**
- Rodar `./vendor/bin/pint` (sem `--test`) e commitar os 42 arquivos reformatados. Vai poluir o
  histórico uma vez; combine com o time para não colidir com PR aberto.
- Corrigir os **7 erros** de ESLint:
  - `no-empty-object-type` em `components/ui/command.tsx:24` e `components/ui/textarea.tsx:5` —
    arquivos gerados do shadcn; a correção honesta é trocar a `interface` vazia por `type`.
  - `no-explicit-any` em `pages/restaurant/Tables.tsx` linhas 57, 70 e 80 — tipar de verdade, não
    silenciar com comentário.
  - `no-require-imports` em `tailwind.config.ts:165-166` — converter para `import`.
- Remover `tests/Feature/ExampleTest.php` e `tests/Unit/ExampleTest.php` (o primeiro testa uma rota
  que não existe). Sobrepõe-se à #7 — se a #7 já tiver rodado, esta tarefa é no-op.
- **Escolher um gerenciador de pacotes no frontend.** Hoje coexistem `package-lock.json` e
  `bun.lockb`; o `docker-compose` usa `npm install`. Dois lockfiles é armadilha de
  reprodutibilidade — apagar o que não for usado.

**Critérios de aceite**
- [ ] `./vendor/bin/pint --test` passa.
- [ ] `npm run lint` sai com código 0 (avisos podem permanecer; erros não).
- [ ] `composer test` passa.
- [ ] Existe **um** lockfile no frontend, e o `Dockerfile`/`docker-compose` usa o gerenciador
      correspondente.

---

### #31 — `chore:` workflow de CI no GitHub Actions

`labels: chore, infra, testes` · `5 pontos` · **Depende de: #7, #30**

**Contexto.** O repositório não tem diretório `.github/`. Não há verificação automática nenhuma:
lint, teste e build só rodam se alguém lembrar de rodar na própria máquina.

**Depende da #7** porque o job de backend precisa rodar contra **Postgres**, não sqlite — o
`phpunit.xml` aponta para sqlite em memória, mas o código tem SQL cru de Postgres
(`interval '1 hour'` em `ReservaController.php:180,194`). CI e #7 são a mesma correção; façam juntas.

**Tarefas**
- Criar `.github/workflows/ci.yml` disparando em `pull_request` para `develop` e `main`, e em
  `push` nessas duas branches.
- `concurrency` com `cancel-in-progress` para cancelar runs antigos do mesmo PR.
- ~~Filtro por `paths`~~ — **revertido durante a execução.** Se um job não roda por causa de filtro,
  ele nunca reporta status, e o check obrigatório da `#32` deixa o PR travado para sempre em
  *waiting for status*. Os dois jobs rodam sempre; custa ~4 min por PR.
- **Job `backend`:** `postgres:16` como service container; `setup-php` 8.2 com `pdo_pgsql`,
  `bcmath`, `zip` e `pcntl` (mesmas extensões do `Dockerfile`); cache do Composer;
  `composer install`; gerar `.env` a partir do `.env.example` com `key:generate` e **`jwt:secret`**;
  `php artisan migrate --force` (valida a cadeia de migrations em banco virgem); `pint --test`;
  `php artisan test`.
- **Job `frontend`:** `setup-node` 20 (mesma versão do `docker-compose`) com cache de npm;
  `npm ci`; `npm run lint`; **`npx tsc --noEmit`**; `npm test`; `npm run build`.
- Os dois jobs rodam em paralelo e são independentes.
- Documentar no `README` o que a CI verifica e como reproduzir localmente.

**Critérios de aceite**
- [ ] Abrir PR para `develop` dispara os dois jobs e ambos passam no código atual.
- [ ] PR que quebra lint ou teste fica vermelho, com o erro legível no log.
- [ ] Nenhum segredo aparece em log; nenhuma credencial real é necessária (o Postgres é efêmero).
- [ ] Run completo em menos de ~5 min com cache quente.

**Descobertas durante a execução**

- **Os testes rodam contra Postgres sem alterar o `phpunit.xml`.** O PHPUnit só aplica um `<env>`
  se a variável ainda não existir no ambiente (`PhpHandler.php:112`:
  `if ($force || getenv($name) === false)`), e o `phpunit.xml` deste projeto não usa `force="true"`.
  Logo, o `env:` do job vence o XML. Verificado: com as variáveis, `database.default` é `pgsql`;
  sem elas, `sqlite`. Isso desacopla esta issue da `#7`. **Se a `#7` adicionar `force="true"`, a CI
  volta silenciosamente para SQLite.**
- **`npx tsc --noEmit` entrou no job de frontend.** O `vite build` usa esbuild, que apaga os tipos
  sem verificá-los — hoje nada no projeto valida TypeScript. Ressalva: o `tsconfig.json` tem
  `strictNullChecks` e `noImplicitAny` desligados, então a checagem é de malha larga (ver a seção
  de ideias futuras).
- **Bug encontrado e corrigido junto:** o `.env.example` define `BROADCAST_CONNECTION=reverb`, mas
  o `pusher/pusher-php-server` não estava instalado. Como o broadcaster do Reverb fala o protocolo
  Pusher, **qualquer comando `artisan` falhava** após um `cp .env.example .env` — quem clonasse o
  repositório travava no primeiro comando. Resolvido com
  `composer require pusher/pusher-php-server`.

---

### #32 — `chore:` tornar os checks obrigatórios (branch protection)

`labels: chore, infra` · `1 ponto` · **Depende de: #31**

**Contexto.** A CI da `#31` já roda em todo PR, mas o resultado é **apenas informativo** — nada
impede mergear com o check vermelho. Esta issue transforma o aviso em trava.

**Só executar depois de a CI rodar verde num PR real.** Tornar obrigatório cedo demais transforma
falso positivo em bloqueio de trabalho.

**Descoberta que reduziu o escopo:** a branch protection **já existia** em `develop` e `main`, e bem
configurada — PR obrigatório, 1 aprovação, `dismiss_stale_reviews`, `enforce_admins`, sem force
push, sem deleção, conversas resolvidas. **Faltava só `required_status_checks`.** Ou seja, a issue
é habilitar uma opção num conjunto que já estava de pé, não montar o conjunto.

Confirmado também que a regra de aprovação já vinha sendo cumprida na prática: os quatro PRs
mergeados até aqui (#187, #190, #191, #192) foram aprovados pelo `jpvoliveir` — o `enforce_admins`
impede o dono do repositório de mergear o próprio trabalho sem revisão, e o GitHub não permite
aprovar o próprio PR.

**Tarefas**
- Habilitar `required_status_checks` em `develop` e `main`, com os contextos exatos
  `Backend (PHP 8.2)` e `Frontend (Node 20)`.
- Registrar no `Deep_Dish_Contributing_Guide.md` as regras automatizadas e o procedimento de
  emergência.

**Decisões**
- **`strict: true`** (branch atualizada antes do merge). Pega a falha de agosto/2026: os PRs #187,
  #188 e #189 estavam verdes separadamente e a `develop` combinada quebrou, exigindo o #190 só para
  consertar. Custo: `Update branch` + ~40s de CI sempre que alguém mergear antes.
- **`enforce_admins` mantido ativo.** A regra vale para todos. Se o Actions cair, o caminho é
  desligar a proteção em Settings, mergear e religar — ato deliberado e visível, documentado no
  guia.
- **Branch protection clássica, não rulesets.** O repositório já usa o mecanismo clássico; misturar
  os dois criaria duas fontes de regra concorrendo.

⚠️ **Cuidado ao aplicar por API:** o `PATCH` em `/branches/{branch}/protection` **substitui o
objeto inteiro** — omitir um campo o desliga. Enviar só `required_status_checks` derrubaria a
exigência de PR e o `enforce_admins` sem aviso. Pela interface esse risco não existe.

**Critérios de aceite**
- [ ] Push direto em `develop` é rejeitado pelo GitHub.
- [ ] PR com CI vermelha não pode ser mergeada.
- [ ] PR desatualizado exige `Update branch` antes do merge.
- [ ] Nenhuma das regras que já existiam foi perdida (comparar a proteção antes e depois).
- [ ] O guia de contribuição reflete as regras automatizadas e o procedimento de emergência.

---

## Tabela-resumo

| # | Título | Sprint | Labels | Pts | Depende de |
|---:|---|:---:|---|---:|---|
| 1 | histórico de saída da fila em `clientefila` | 1 | banco, backend, fundação | 3 | — |
| 2 | `FilaService` grava histórico em vez de apagar | 1 | backend, fundação | 5 | 1 |
| 3 | `removerRestaurante` delega ao `FilaService` | 1 | backend, bug | 2 | 2 |
| 4 | duração real de permanência em `clientemesa` | 1 | banco, backend, fundação | 3 | — |
| 5 | factories + conserto do `DatabaseSeeder` | 1 | banco, testes | 3 | — |
| 6 | seeder de histórico sintético | 1 | banco, fundação | 5 | 1, 4, 5 |
| 7 | infra de testes + suíte do `FilaService` | 1 | testes, fundação | 5 | 2, 3 |
| 8 | `AnalyticsService` | 2 | backend | 8 | 1, 4 |
| 9 | endpoint de analytics | 2 | backend | 3 | 8 |
| 10 | `EstimativaEsperaService` | 2 | backend | 5 | 1 |
| 11 | expor espera estimada na API | 2 | backend | 2 | 10 |
| 12 | detecção automática de desistência | 2 | backend, infra | 3 | 1 |
| 13 | dashboard com gráficos | 2 | frontend | 5 | 9 |
| 14 | espera estimada nas telas do cliente | 2 | frontend | 3 | 11 |
| 15 | ponto único de promoção da fila | 3 | backend, bug, fundação | 5 | 2 |
| 16 | alocação otimizada de mesa | 3 | backend, ia | 8 | 15 |
| 17 | priorização da fila por encaixe | 3 | backend, ia | 5 | 16 |
| 18 | estados intermediários de mesa | 3 | backend, frontend, banco | 3 | — |
| 19 | Laravel Reverb e eventos | 3 | infra, backend | 5 | 15, 18 |
| 20 | tempo real no frontend | 3 | frontend | 5 | 19 |
| 21 | QR de entrada na fila | 3 | backend, frontend | 3 | — |
| 22 | Capacitor — APK do cliente | 3 | mobile, frontend | 5 | — |
| 23 | push nativo | 3 | mobile, backend | 5 | 15, 22 |
| 24 | integração com OpenRouter | 4 | ia, backend | 3 | — |
| 25 | tool-calling sobre o `AnalyticsService` | 4 | ia, backend | 8 | 8, 24 |
| 26 | endpoint de chat do assistente | 4 | ia, backend | 5 | 25 |
| 27 | tela de chat no painel | 4 | frontend, ia | 5 | 26 |
| 28 | E2E dos fluxos críticos | 4 | testes | 5 | 15, 16, 23 |
| 29 | roteiro de demo e documentação | 4 | docs | 3 | 27, 28 |
| 30 | deixar o repositório verde | 1 | chore, testes, fundação | 3 | — |
| 31 | workflow de CI no GitHub Actions | 1 | chore, infra, testes | 5 | 7, 30 |
| 32 | checks obrigatórios (branch protection) | 1 | chore, infra | 1 | 31 |

**Distribuição:** Sprint 1 = 9 issues / 34 pts · Sprint 2 = 8 / 30 · Sprint 3 = 9 / **44** ·
Sprint 4 = 6 / 29. **Total: 32 issues, 137 pontos.**

O Sprint 3 concentra 32% dos pontos em 3 das 12 semanas. Ou ele é redistribuído no planejamento
(empurrando #21 e #18 para o Sprint 2), ou entra assumindo que vai escorregar.

---

## Caminho crítico

```
#1 histórico ──▶ #2 FilaService ──▶ #15 promoção única ──▶ #16 alocação ótima ──▶ #23 push
   │                                                                                 ▲
   ├──▶ #10 estimativa ──▶ #11 API ──▶ #14 tela do cliente                           │
   │                                                                       #22 Capacitor
   └──▶ #8 Analytics ──▶ #9 endpoint ──▶ #13 dashboard
                      └──▶ #25 tool-calling ──▶ #26 chat ──▶ #27 tela
```

**A espinha dorsal é `#1 → #2 → #15 → #16`.** Se a #1 atrasar, os três sprints seguintes escorregam
juntos — ela é a primeira coisa a começar e não deve ser paralelizada com nada que dependa dela.

**Trilhas que correm em paralelo** desde o começo, sem esperar a espinha dorsal: #5 (factories),
#18 (estados de mesa), #21 (QR), #22 (Capacitor) e #24 (OpenRouter). Boas candidatas para quem
ficar bloqueado.

**Ponto de maior risco de calendário:** o Sprint 3, com 9 issues e 39 pontos. Provisionar o
Firebase (#23) e subir o Reverb no Compose (#19) **antes** da semana em que essas issues começam.
