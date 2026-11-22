# Deep Dish — Visão de Produto e Roadmap de Longo Prazo

> Este documento consolida a visão de evolução do Deep Dish, incorporando as ideias do documento
> **"RestaurantOS AI — Documento de Arquitetura e Visão"** (avaliado em jul/2026) naquilo que
> fortalece o projeto, e registrando explicitamente o que foi **adiado** ou **descartado** e por quê.
> Aqui fica a direção de produto; a execução do semestre está em [`backlog-pi4.md`](./backlog-pi4.md),
> e o recorte técnico do PI4 em [`roadmap-ia.md`](./roadmap-ia.md).

---

## 1. Tese do Deep Dish (inalterada)

O Deep Dish é um produto **voltado ao consumidor**: acabar com filas físicas e reservas por telefone,
via fila virtual + reservas, com o mínimo de fricção para o restaurante aderir.

O documento RestaurantOS AI descreve um produto **voltado ao restaurante** (SaaS de eficiência
operacional: visão computacional, digital twin, gestão de equipe). São teses diferentes, mas o
**MVP proposto por ele é ~90% o que o Deep Dish já é ou está construindo** — cadastro, mesas,
filas, reservas, painel administrativo, app do cliente, notificações e previsão básica de espera.
Isso valida a direção atual: primeiro completar esse núcleo com excelência; a camada "OS do
restaurante" é evolução, não substituição.

**Decisão consciente pendente (pós-TCC):** MVP igual, cliente pagante diferente. Não precisa ser
resolvido para entregar o PI4, mas precisa ser resolvido antes de tratar o Deep Dish como produto
comercial.

## 2. Princípios adotados do RestaurantOS AI

- **IA não substitui regras determinísticas** — disponibilidade, sobreposição de horários e
  capacidade continuam em código; IA atua só onde há previsão/otimização.
- **O sistema deve funcionar mesmo sem os módulos avançados** — nenhuma feature de IA/visão
  pode ser pré-requisito do fluxo básico.
- **Separar IA generativa (LLM) de IA preditiva** — previsão de espera é estatística simples
  (média de giro), não LLM.

## 3. Incorporado ao PI4

| Ideia (origem RestaurantOS AI) | Como entra no Deep Dish |
|---|---|
| QR Code no restaurante | Uma infra, dois usos: o mesmo QR permite **entrar na fila** presencialmente (walk-in) e serve de base para o check-in. Issue #21 do [`backlog-pi4.md`](./backlog-pi4.md). |
| Previsão básica de espera no MVP | Estimativa por média histórica condicionada, sem ML. Issues #10, #11 e #14 (Sprint 2). |
| Indicadores operacionais | Dashboard enriquecido com tempo médio de espera, giro de mesas, taxa de abandono e ocupação por horário. Issues #8, #9 e #13 (Sprint 2). |
| Estados de mesa estendidos | `aguardando_limpeza` / `em_limpeza`, com marcação manual pela conta do restaurante — sem depender de RBAC nem de câmera. Issue #18, antecipada do backlog pós-semestre. |
| IA conversacional do gestor | Assistente no painel com tool-calling sobre dados reais. Sprint 4 (issues #24-#27), também antecipado. |
| Detecção de desistência na fila | Expiração automática de quem foi chamado e não confirmou. Issue #12. |

> **Nota:** os três últimos itens estavam originalmente no backlog pós-semestre desta página e
> foram promovidos ao PI4 quando o roadmap técnico foi fechado. O que sobrou de backlog está na
> seção 4.

## 4. Backlog pós-PI4 (ordem sugerida)

1. **Diretório "shadow" + claim** — restaurantes importados de dados públicos, reivindicáveis,
   para eliminar o atrito de aquisição.
2. **WhatsApp Business + IA** — confirmação de reservas por WhatsApp para restaurantes
   não-parceiros ou pouco engajados no painel.
3. **RBAC completo de equipe** — login de garçom/recepcionista/limpeza com permissões por papel, e
   o fluxo automático "mesa esvaziou → tarefa de limpeza → funcionário confirma → mesa liberada →
   próximo chamado". Hoje `Funcionario` é só cadastro, não é autenticável.
4. **União de mesas** — combinar mesas adjacentes para grupos grandes. Quebra a suposição atual de
   que uma reserva ocupa exatamente uma `Mesa`.
5. **Previsão de espera com ML treinado** — só faz sentido depois de meses de dado real coletado
   pelo Sprint 1; a heurística do Sprint 2 já deixa a base pronta para essa evolução.
6. **Migração para React Native** — ver seção 5.1.

> Os itens 1 e 2 formam juntos o **modelo de duas camadas** que responde ao problema de
> aquisição ("N contratos"): **Camada 0** — restaurante não-parceiro confirma reservas
> respondendo WhatsApp (IA interpreta texto livre), sem conta, painel ou contrato; popula o app no
> dia 1 e gera demanda. **Camada 1** — ao ver a demanda, o restaurante reivindica a listagem (claim)
> e ganha fila virtual, QR e dashboard. A gestão em tempo real só é exigida de quem optou por
> ela; a participação do restaurante é conquistada, nunca pré-requisito.
>
> **Bloqueio técnico da Camada 0:** `Restaurante` **é** a tabela de autenticação (`extends
> Authenticatable implements JWTSubject, MustVerifyEmail`), com `email`, `cnpj` e `password`
> obrigatórios. Separar a identidade do estabelecimento das credenciais de acesso é pré-requisito.

## 5. Avaliado e descartado (ou adiado indefinidamente)

| Proposta | Decisão | Motivo |
|---|---|---|
| Laravel Sanctum | **Descartado** | O JWT dual-guard atual funciona e está integrado ao frontend (refresh, token_version). Trocar auth é churn sem ganho. |
| Workers Node.js + BullMQ + Redis | **Adiado** | A queue nativa do Laravel atende a escala atual. Só se justificaria com pipeline de vídeo em tempo real. |
| Visão computacional (YOLO etc.) | **Adiado (pós-TCC)** | Custo/complexidade altos; o próprio documento exige que o sistema funcione sem ela. Depende de restaurante piloto disposto a instalar câmeras. |
| Digital Twin do salão | **Adiado** | Depende de RBAC (para saber o garçom da mesa) e de integração com PDV (para saber os pedidos em aberto). |
| Integração com PDVs de terceiros | **Adiado** | Cada restaurante usa um PDV diferente; exige conector por fornecedor. Prioriza-se por demanda real, não especulativamente. |
| Notificações por WhatsApp/SMS | **Adiado** | Depende de contrato com provedor externo (Twilio, WhatsApp Business API) — custo recorrente e burocracia, não só código. |
| Receita estimada no dashboard | **Estruturalmente impossível hoje** | O Deep Dish não tem cardápio, pedido nem preço no modelo de dados. Não existe dado de receita a estimar. |
| IA por voz (ligar para o restaurante) | **Descartada** como mecanismo central | Não elimina a ligação, só transfere o custo para a plataforma; frágil, não vira estado no banco, e tem risco regulatório de telemarketing no Brasil. No máximo, fallback. |
| Kubernetes, CDN, GraphQL, billing SaaS | **Adiado** | Infraestrutura de escala para um produto que ainda valida adoção. Docker Compose atual basta. |

### 5.1 React Native — em aberto, não descartado

Esta entrada **substitui** a decisão anterior deste documento, que registrava React Native como
descartado em definitivo.

O PI4 leva o produto para mobile via **Capacitor**, empacotando o React/Vite atual como app Android
nativo (issue #22). Isso é um **experimento**, não uma escolha definitiva de arquitetura — e o
React Native **permanece como alternativa viva**.

**Por que Capacitor primeiro:** entrega APK real com push nativo e câmera sem reescrever nenhuma
tela. Migrar para RN custaria a reescrita de ~12 telas do cliente (`pages/app/` + telas de auth +
53 componentes shadcn), enquanto `services/`, `contexts/`, `types/` e os schemas Zod migrariam sem
alteração relevante. Seria um sprint inteiro no semestre cujo diferencial prometido é a IA.

**As duas opções não se anulam:** a camada que sobreviveria a uma migração para RN é exatamente a
que o Capacitor não toca. Testar Capacitor primeiro não desperdiça trabalho caso a decisão mude.

**Gatilhos para reabrir a decisão** (avaliados ao fim da issue #22, com o APK rodando em aparelho
físico — não antes, e não por especulação):

1. Lentidão perceptível em rolagem de lista ou transição de tela no aparelho real.
2. Recurso nativo necessário sem plugin Capacitor maduro.
3. Push ou câmera instáveis através da WebView.
4. Exigência de publicação em loja com padrão de UX nativo que a WebView não alcance.

Se algum gatilho disparar, o caminho é um **spike de RN no backlog pós-PI4** (Expo + NativeWind +
React Navigation, mantendo o painel do restaurante na web) — **não** uma migração dentro do
semestre. O detalhamento completo, com a tabela de custo por camada, está na seção *Mobile* do
[`backlog-pi4.md`](./backlog-pi4.md).

## 6. Critério para promover itens do backlog

Um item só sobe de fase quando: (a) o núcleo consumidor (fila autônoma + push + check-in QR)
estiver estável em produção; e (b) houver evidência de demanda — reclamação de usuário, pedido
de restaurante parceiro ou métrica que o justifique.
