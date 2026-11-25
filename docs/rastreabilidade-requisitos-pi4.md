# Mapeamento dos requisitos antigos — Deep Dish (PI4)

**Projeto Integrador 4 — Ciência da Computação**  
**Responsável:** Eduardo Gondim Marinho  
**Fonte:** issues RF antigas, [backlog do PI4](https://github.com/joaogpereira/deep-dish/blob/develop/docs/backlog-pi4.md) e arquivos da branch `develop` (22/11/2026).

## 1. Por que fizemos este mapeamento

No começo do projeto, a equipe criou várias issues com os requisitos funcionais, identificados por `RF`. Depois, no PI4, o trabalho passou a ser organizado por entregas das sprints. Como as duas listas não foram atualizadas juntas, uma RF pode parecer pendente mesmo quando o sistema já atende à necessidade, ou parecer concluída mesmo tendo sido adiada.

Esta tabela relaciona as **70 issues de requisito localizadas** com a situação encontrada no projeto. Usei a numeração das issues do GitHub porque alguns códigos RF aparecem mais de uma vez — por exemplo, `RF-69` está tanto no login quanto na confirmação de reserva.

Para indicar o resultado, usei três classificações do backlog: **entregue**, **entregue por outro caminho** (o sistema resolve a necessidade de um jeito diferente) e **fora do escopo do PI4** (a equipe decidiu adiar). Quando a descrição ou a evidência não permite escolher com segurança, marquei **precisa de decisão da equipe** em vez de inventar que o requisito foi descartado. Essas exceções são pendências para a revisão final da tabela.

## 2. Tabela de requisitos

| Issue | Requisito | O que era pedido | Situação | Onde conferir / justificativa |
|---|---|---|---|---|
| [#15](https://github.com/joaogpereira/deep-dish/issues/15) | RF-06 | Proteção de rotas por perfil | Entregue | Middlewares JWT e guards; `ProtectedRoute.tsx`. Rotas de cliente e restaurante são separadas. |
| [#16](https://github.com/joaogpereira/deep-dish/issues/16) | RF-07 | Logout | Entregue | `routes/api.php`; auth controllers. Existem endpoints de logout para os dois perfis. |
| [#17](https://github.com/joaogpereira/deep-dish/issues/17) | RF-08 | Persistência de sessão (JWT) | Entregue | `auth.service.ts`; middleware JWT. Tokens são utilizados na autenticação e na proteção de acesso. |
| [#19](https://github.com/joaogpereira/deep-dish/issues/19) | RF-1 | Setup do frontend | Entregue | `deep-dish-frontend/`; `package.json`. Projeto React/Vite disponível no repositório. |
| [#20](https://github.com/joaogpereira/deep-dish/issues/20) | RF-2 | Setup do backend | Entregue | `deep-dish-backend/`; `composer.json`. Projeto Laravel configurado. |
| [#21](https://github.com/joaogpereira/deep-dish/issues/21) | RF-3 | Setup do banco | Entregue | Migrations; `docker-compose.yml`. Banco PostgreSQL previsto nos serviços de desenvolvimento. |
| [#22](https://github.com/joaogpereira/deep-dish/issues/22) | RF-10 | Buscar restaurante por nome/local | Entregue | `pages/app/Search.tsx`; `RestauranteController`. Tela e endpoint de consulta de restaurantes. |
| [#23](https://github.com/joaogpereira/deep-dish/issues/23) | RF-11 | Detalhes do restaurante | Entregue | `pages/app/RestaurantDetail.tsx`. Tela de detalhes do estabelecimento. |
| [#24](https://github.com/joaogpereira/deep-dish/issues/24) | RF-12 | Horários disponíveis | Entregue | `HorariosController.php`; `routes/api.php`. Rota de horários por restaurante. |
| [#25](https://github.com/joaogpereira/deep-dish/issues/25) | RF-13 | Quantidade de pessoas | Entregue | `pages/app/EntrarNaFila.tsx`. Fluxo considera tamanho do grupo. |
| [#26](https://github.com/joaogpereira/deep-dish/issues/26) | RF-14 | Entrar na fila | Entregue | `FilaController.php`; `FilaService.php`. Rota POST /fila e lógica de ingresso. |
| [#27](https://github.com/joaogpereira/deep-dish/issues/27) | RF-15 | Reserva antecipada | Entregue | `ReservaController.php`. API de reservas e regras de disponibilidade. |
| [#28](https://github.com/joaogpereira/deep-dish/issues/28) | RF-16 | Posição na fila | Entregue | `FilaController.php`; `Queue.tsx`. Consulta e exibição de posição. |
| [#29](https://github.com/joaogpereira/deep-dish/issues/29) | RF-17 | Tempo de espera simples | Entregue por outro caminho | A previsão simples por posição evoluiu para o `EstimativaEsperaService.php`, que usa histórico e fallback (#163, #164 e #167). |
| [#30](https://github.com/joaogpereira/deep-dish/issues/30) | RF-18 | Status da reserva | Entregue por outro caminho | `ReservationDetail.tsx`; #172/#173. Atualização foi integrada a eventos em tempo real. |
| [#31](https://github.com/joaogpereira/deep-dish/issues/31) | RF-19 | Cancelar entrada na fila | Entregue | `FilaController.php`; `FilaService.php`. Cancelamento com registro de saída. |
| [#32](https://github.com/joaogpereira/deep-dish/issues/32) | RF-20 | Cancelar reserva | Entregue | `ReservaController.php`. Rota de cancelamento de reserva. |
| [#33](https://github.com/joaogpereira/deep-dish/issues/33) | RF-21 | Dados do restaurante | Entregue | `RestauranteAuthController.php`; `Settings.tsx`. Cadastro e edição de informações. |
| [#34](https://github.com/joaogpereira/deep-dish/issues/34) | RF-22 | Horários de funcionamento | Entregue | `HorariosController.php`; `Settings.tsx`. Configuração e consulta de horários. |
| [#35](https://github.com/joaogpereira/deep-dish/issues/35) | RF-23 | Capacidade total | Entregue por outro caminho | A capacidade das mesas existe no banco e na interface. Como o requisito pedia capacidade total do salão, vale conferir se o cálculo cobre exatamente essa necessidade. |
| [#36](https://github.com/joaogpereira/deep-dish/issues/36) | RF-24 | Regras de tolerância e cancelamento | **Precisa de decisão da equipe** | Há tolerâncias no backend, mas a edição dessas regras pelo próprio restaurante não foi confirmada na tela de configurações. É preciso decidir se fica como melhoria futura. |
| [#37](https://github.com/joaogpereira/deep-dish/issues/37) | RF-25 | Painel administrativo | Entregue | `pages/restaurant/Dashboard.tsx`. Painel web exclusivo para restaurante. |
| [#38](https://github.com/joaogpereira/deep-dish/issues/38) | RF-26 | Fila em tempo real | Entregue por outro caminho | `QueueManagement.tsx`; `useRealtime.ts`. Além de consulta da lista, usa eventos do Reverb. |
| [#39](https://github.com/joaogpereira/deep-dish/issues/39) | RF-27 | Reservas do dia | Entregue | `Reservations.tsx`. Tela de reservas do restaurante. |
| [#40](https://github.com/joaogpereira/deep-dish/issues/40) | RF-29 | Remover cliente da fila | Entregue | `FilaController.php`; #155. Operação disponível ao restaurante. |
| [#41](https://github.com/joaogpereira/deep-dish/issues/41) | RF-30 | Pausar ingressos na fila | Entregue | Há controle de fila ativa no `FilaService.php`, mas é importante testar a opção de pausar pelo painel. |
| [#42](https://github.com/joaogpereira/deep-dish/issues/42) | RF-31 | Cadastro de mesas | Entregue | `MesaController.php`; `Tables.tsx`. Criação de mesas no painel. |
| [#43](https://github.com/joaogpereira/deep-dish/issues/43) | RF-32 | Capacidade por mesa | Entregue | `Mesa.php`; `Tables.tsx`. Capacidade é atributo da mesa. |
| [#44](https://github.com/joaogpereira/deep-dish/issues/44) | RF-33 | Ativar/desativar mesas | Entregue por outro caminho | `Mesa.php`; `MesaLimpezaService.php`. Mesa usa estados operacionais como livre, ocupada e bloqueada. |
| [#45](https://github.com/joaogpereira/deep-dish/issues/45) | RF-34 | Visualizar status das mesas | Entregue | `Tables.tsx`; #171. Painel mostra estados, incluindo limpeza. |
| [#46](https://github.com/joaogpereira/deep-dish/issues/46) | RF-35 | Associar reserva a mesa | Entregue | `ReservaController.php`; `ClienteMesa.php`. Reserva/atendimento ligado a uma mesa. |
| [#47](https://github.com/joaogpereira/deep-dish/issues/47) | RF-36 | Liberar mesa manualmente | Entregue por outro caminho | `ReservaController.php`; #171. Liberação agora considera etapa de limpeza antes de disponibilizar a mesa. |
| [#48](https://github.com/joaogpereira/deep-dish/issues/48) | RF-37 | Fila por restaurante | Entregue | `Fila.php`; `FilaService.php`. Filas vinculadas a restaurantes. |
| [#50](https://github.com/joaogpereira/deep-dish/issues/50) | RF-39 | Cálculo automático de posição | Entregue por outro caminho | `FilaService.php`; #169/#170. Prioridade considera encaixe e proteção contra espera excessiva. |
| [#51](https://github.com/joaogpereira/deep-dish/issues/51) | RF-40 | Reordenar fila na saída | Entregue por outro caminho | `FilaService.php`; `useFilaAtual.ts`. Reorganização incorporada ao fluxo de alocação/atualização. |
| [#52](https://github.com/joaogpereira/deep-dish/issues/52) | RF-41 | Marcar mesa pronta | Entregue por outro caminho | `MesaLimpezaService.php`; #171. Mesa passa por limpeza antes de ficar livre e chamar próximo. |
| [#53](https://github.com/joaogpereira/deep-dish/issues/53) | RF-42 | Remover não comparecimento | Entregue | `ExpirarChamadosCommand.php`; #165. Expiração automática de chamados. |
| [#54](https://github.com/joaogpereira/deep-dish/issues/54) | RF-43 | Histórico da fila | Entregue | `ClienteFila.php`; #153/#154. Registros antigos são preservados com saída e tempo. |
| [#55](https://github.com/joaogpereira/deep-dish/issues/55) | RF-44 | Reserva futura | Entregue | `ReservaController.php`. Fluxo suporta datas e horários futuros. |
| [#58](https://github.com/joaogpereira/deep-dish/issues/58) | RF-49 | Histórico de reservas do usuário | Entregue | `ReservaController.php`; `ReservationDetail.tsx`. Dados históricos de reserva persistidos. |
| [#59](https://github.com/joaogpereira/deep-dish/issues/59) | RF-50 | Histórico de reservas do restaurante | Entregue | `ReservaController.php`; `Reservations.tsx`. Visualização e consulta das reservas. |
| [#60](https://github.com/joaogpereira/deep-dish/issues/60) | RF-51 | Estados da fila | Entregue por outro caminho | `ClienteFila.php`; #153/#154. Estados de saída reais substituem parte da enumeração inicialmente descrita. |
| [#61](https://github.com/joaogpereira/deep-dish/issues/61) | RF-52 | Estados da reserva | Entregue por outro caminho | `ClienteMesa.php`; `ReservaController.php`. Estados do fluxo foram adaptados ao modelo de atendimento. |
| [#62](https://github.com/joaogpereira/deep-dish/issues/62) | RF-53 | Transições da fila | Entregue | `FilaService.php`. As regras de entrada, chamada e saída são centralizadas no serviço. |
| [#63](https://github.com/joaogpereira/deep-dish/issues/63) | RF-54 | Transições da reserva | Entregue | `ReservaController.php`. As operações verificam as condições da reserva. |
| [#64](https://github.com/joaogpereira/deep-dish/issues/64) | RF-55 | Bloquear transições inválidas | Entregue | `FilaService.php`; `MesaLimpezaService.php`. Serviços validam estados antes de mudar a operação. |
| [#65](https://github.com/joaogpereira/deep-dish/issues/65) | RF-56 | Registrar responsável pela mudança de status | **Precisa de decisão da equipe** | A issue está fechada, mas não encontrei comprovação de um registro de quem alterou cada status. Não dá para marcar como entregue sem o grupo mostrar onde isso foi feito. |
| [#66](https://github.com/joaogpereira/deep-dish/issues/66) | RF-57 | Padronizar respostas da API | **Precisa de decisão da equipe** | Issue aberta. Existem respostas de erro na API, mas não houve uma verificação de padronização de todos os endpoints. O grupo precisa confirmar conclusão ou adiamento. |
| [#67](https://github.com/joaogpereira/deep-dish/issues/67) | RF-58 | Testes de fluxos críticos | Entregue por outro caminho | Existem testes PHP e React. A validação final da issue #181 ainda precisa ser alinhada e concluída. |
| [#68](https://github.com/joaogpereira/deep-dish/issues/68) | RF-59 | Modelo de notificação in-app | Fora do escopo PI4 | A tabela de notificações in-app ficou fora do PI4, conforme decisão registrada no backlog. |
| [#69](https://github.com/joaogpereira/deep-dish/issues/69) | RF-60 | Aviso de mudança de posição | Fora do escopo PI4 | O backlog adiou a **central de notificações dentro do aplicativo**. As atualizações pelo Reverb não substituem a notificação guardada com histórico. |
| [#70](https://github.com/joaogpereira/deep-dish/issues/70) | RF-61 | Aviso de mesa pronta | Fora do escopo PI4 | O aviso persistido dentro do aplicativo ficou fora do PI4. A issue #182 também informa que o push FCM não foi implementado. |
| [#71](https://github.com/joaogpereira/deep-dish/issues/71) | RF-62 | Aviso de confirmação de reserva | Fora do escopo PI4 | O aviso persistido de confirmação não foi incluído no escopo da central de notificações do PI4. |
| [#72](https://github.com/joaogpereira/deep-dish/issues/72) | RF-63 | Aviso de cancelamento | Fora do escopo PI4 | O aviso persistido de cancelamento não foi incluído no escopo da central de notificações do PI4. |
| [#73](https://github.com/joaogpereira/deep-dish/issues/73) | RF-64 | Listar notificações | Fora do escopo PI4 | O backlog adiou a tela de histórico de notificações dentro do aplicativo. |
| [#74](https://github.com/joaogpereira/deep-dish/issues/74) | RF-65 | Marcar notificação como lida | Fora do escopo PI4 | A função de marcar uma notificação como lida ficou junto com a central de notificações adiada. |
| [#76](https://github.com/joaogpereira/deep-dish/issues/76) | RF-67 | Fila no painel em tempo real | Entregue por outro caminho | `QueueManagement.tsx`; Reverb #172/#173. Painel recebe atualizações via WebSocket. |
| [#77](https://github.com/joaogpereira/deep-dish/issues/77) | RF-68 | Reservas do dia no painel | Entregue | `Reservations.tsx`. Visão das reservas do restaurante. |
| [#78](https://github.com/joaogpereira/deep-dish/issues/78) | RF-69 | Confirmar reserva rapidamente | Entregue | `Reservations.tsx`; `ReservaController.php`. Ação disponível no fluxo de reserva. |
| [#79](https://github.com/joaogpereira/deep-dish/issues/79) | RF-70 | Cancelar reserva pelo painel | Entregue | `Reservations.tsx`; `ReservaController.php`. Operação de cancelamento pelo restaurante. |
| [#80](https://github.com/joaogpereira/deep-dish/issues/80) | RF-71 | Marcar mesa pronta | Entregue por outro caminho | `Tables.tsx`; #171. Disponibilização passa pelo ciclo de limpeza. |
| [#81](https://github.com/joaogpereira/deep-dish/issues/81) | RF-72 | Atualização quase em tempo real | Entregue por outro caminho | `useRealtime.ts`; Laravel Reverb. Atualizações por eventos substituem parte das consultas periódicas. |
| [#82](https://github.com/joaogpereira/deep-dish/issues/82) | RF-73 | Feedback visual das ações | Entregue | O frontend possui estados de carregamento e mensagens de erro; a conferência final deve considerar também ações fora do chat. |
| [#90](https://github.com/joaogpereira/deep-dish/issues/90) | RF-01 | Migrations iniciais | Entregue | `database/migrations/`. Estrutura das tabelas do banco. |
| [#91](https://github.com/joaogpereira/deep-dish/issues/91) | RF-02 | Testes do banco | Entregue por outro caminho | `tests/Feature/` e PostgreSQL de teste. Testes incorporados à suíte de integração. |
| [#94](https://github.com/joaogpereira/deep-dish/issues/94) | RF-74 | Migração do painel | **Precisa de decisão da equipe** | Issue aberta e sem descrição. Há um painel web, mas falta entender o que seria exatamente a “migração do painel” para classificar essa entrega. |
| [#97](https://github.com/joaogpereira/deep-dish/issues/97) | RF-09 | Organização das interfaces | Entregue | `deep-dish-frontend/src/pages/`. Páginas e layouts estruturados. |
| [#101](https://github.com/joaogpereira/deep-dish/issues/101) | RF-69 (login) | Login de cliente/restaurante | Entregue | `ClienteAuthController.php`; `RestauranteAuthController.php`. Entradas distintas por perfil; número RF-69 foi reutilizado na issue #78. |
| [#104](https://github.com/joaogpereira/deep-dish/issues/104) | RF-31 (dashboard) | Dashboard administrativo | Entregue | `Dashboard.tsx`; `DashboardController.php`. Indicadores e visão gerencial. |
| [#139](https://github.com/joaogpereira/deep-dish/issues/139) | RF (sem número) | Melhoria da landing page | **Conferência visual pendente** | Issue fechada. Falta comparar a landing page atual com a mudança solicitada, pois o título da issue não explica o que deveria mudar. |

## 3. Como as RF se relacionam com as entregas novas

| Parte do sistema | Principais entregas que atendem às RF antigas |
|---|---|
| Histórico da fila e saída de clientes | [#153](https://github.com/joaogpereira/deep-dish/issues/153), [#154](https://github.com/joaogpereira/deep-dish/issues/154), [#155](https://github.com/joaogpereira/deep-dish/issues/155) |
| Histórico do atendimento, previsão e gráficos | [#156](https://github.com/joaogpereira/deep-dish/issues/156), [#161](https://github.com/joaogpereira/deep-dish/issues/161) a [#167](https://github.com/joaogpereira/deep-dish/issues/167) |
| Promoção e encaixe de grupos em mesas | [#168](https://github.com/joaogpereira/deep-dish/issues/168), [#169](https://github.com/joaogpereira/deep-dish/issues/169), [#170](https://github.com/joaogpereira/deep-dish/issues/170) |
| Limpeza de mesas | [#171](https://github.com/joaogpereira/deep-dish/issues/171) |
| Atualização automática das telas | [#172](https://github.com/joaogpereira/deep-dish/issues/172), [#173](https://github.com/joaogpereira/deep-dish/issues/173) |
| QR Code e versão Android | [#174](https://github.com/joaogpereira/deep-dish/issues/174), [#175](https://github.com/joaogpereira/deep-dish/issues/175) |
| Assistente do painel | [#177](https://github.com/joaogpereira/deep-dish/issues/177) a [#180](https://github.com/joaogpereira/deep-dish/issues/180) |
| Testes e integração contínua | [#160](https://github.com/joaogpereira/deep-dish/issues/160), [#183](https://github.com/joaogpereira/deep-dish/issues/183) a [#185](https://github.com/joaogpereira/deep-dish/issues/185) |

## 4. Requisitos que ainda precisam de uma decisão do grupo

- **[#36 — RF-24](https://github.com/joaogpereira/deep-dish/issues/36):** as regras de tolerância existem no código, mas a configuração dessas regras pelo próprio restaurante precisa ser confirmada.
- **[#65 — RF-56](https://github.com/joaogpereira/deep-dish/issues/65):** falta mostrar onde fica registrado quem mudou o status de uma fila ou reserva.
- **[#66 — RF-57](https://github.com/joaogpereira/deep-dish/issues/66):** a issue continua aberta e não há registro de conferência de todas as respostas de status da API.
- **[#94 — RF-74](https://github.com/joaogpereira/deep-dish/issues/94):** a issue não tem descrição suficiente para saber qual migração foi solicitada.
- **[#139 — melhoria da página inicial](https://github.com/joaogpereira/deep-dish/issues/139):** é preciso comparar a página atual com a alteração combinada. O título não detalha a melhoria.

A central de notificações **RF-59 a RF-65** é um caso diferente: o próprio backlog registra a decisão de deixar esse conjunto para depois do PI4. Apesar de as issues estarem fechadas, isso não significa que a central tenha sido implementada. O push FCM também não deve ser confundido com as atualizações do Reverb.

## 5. Conclusão

A maioria dos requisitos antigos está representada nas funcionalidades atuais do Deep Dish, mesmo que algumas soluções tenham mudado de forma. O maior cuidado foi separar o que está implementado do que foi apenas planejado. As cinco conferências acima precisam de resposta da equipe antes de dizer que **todas** as RF têm uma situação definitiva. Depois disso, a tabela pode ser usada como registro final do que entrou e do que ficou para uma próxima etapa.

**Referências:** [issues do repositório](https://github.com/joaogpereira/deep-dish/issues), [backlog](https://github.com/joaogpereira/deep-dish/blob/develop/docs/backlog-pi4.md) e [issue #182](https://github.com/joaogpereira/deep-dish/issues/182).
