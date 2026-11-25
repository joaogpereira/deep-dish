# Relatório de entrega — Deep Dish (PI4)

**Projeto Integrador 4 — Ciência da Computação**  
**Integrante responsável pela documentação:** Eduardo Gondim Marinho  
**Repositório:** [Deep Dish](https://github.com/joaogpereira/deep-dish)  
**Base consultada:** branch `develop`, commit `313072cada2708eed243e1486664db490687c8a4` (18/11/2026), e issue [#182](https://github.com/joaogpereira/deep-dish/issues/182), consultada em 22/11/2026.

## 1. Sobre o projeto

O Deep Dish é um sistema para organizar filas e reservas de restaurantes. Pelo celular, o cliente pode procurar um restaurante, entrar na fila, acompanhar sua posição e fazer uma reserva. Já o restaurante usa um painel para controlar mesas, fila, reservas e acompanhar os números da operação.

No PI4, o foco foi melhorar o que já existia. Antes, o sistema conseguia registrar as entradas na fila e as reservas, mas ainda tinha dificuldades para aproveitar esses dados e distribuir os clientes entre as mesas. Neste semestre, a equipe trabalhou no histórico de atendimento, na previsão de espera, na alocação de mesas e no painel de indicadores. Também foram acrescentados o QR Code para entrada na fila, a estrutura do aplicativo Android e um assistente de IA para consultar os indicadores.

O frontend usa React, TypeScript e Vite; o backend é feito em Laravel 12, com PostgreSQL. O projeto também utiliza Docker Compose, Laravel Reverb para atualização em tempo real, Capacitor na versão Android e OpenRouter no assistente.

## 2. O que foi feito no PI4

Para montar este relatório, foram comparados o [backlog](https://github.com/joaogpereira/deep-dish/blob/develop/docs/backlog-pi4.md), as issues e os arquivos que estão na `develop`. A coluna **Como conferir** mostra uma sugestão de teste: não quer dizer que ele foi executado aqui. Da mesma forma, issue fechada não substitui teste no sistema funcionando. **Algumas issues têm descrição diferente do título** — por exemplo, #175 e #176. Nesses casos, o backlog e os arquivos do projeto serviram para conferir o assunto de cada entrega.

### Sprint 1 — Histórico e base para os testes

| Issue | Problema que precisávamos resolver | O que mudou | Como conferir |
|---|---|---|---|
| [153](https://github.com/joaogpereira/deep-dish/issues/153) | Os registros da fila eram apagados quando o cliente saía. | O histórico passou a guardar o motivo da saída, quando ela aconteceu e o tempo de espera, usando exclusão lógica. | Cancelar uma entrada e conferir se ela saiu da fila ativa, mas ficou registrada no histórico. |
| [154](https://github.com/joaogpereira/deep-dish/issues/154) | A saída e a chamada do cliente precisavam seguir a mesma regra. | O `FilaService` passou a registrar o atendimento ou a desistência antes de retirar a entrada da fila ativa. | Testar cancelamento e chamada e verificar se a posição dos demais continua correta. |
| [155](https://github.com/joaogpereira/deep-dish/issues/155) | O restaurante tinha um caminho separado para remover clientes. | Essa remoção foi concentrada no serviço da fila para não perder informações e evitar regras duplicadas. | Remover alguém pelo painel e verificar o motivo de saída e o encerramento da fila vazia. |
| [156](https://github.com/joaogpereira/deep-dish/issues/156) | Não era possível saber quanto tempo uma mesa ficou ocupada. | As reservas passaram a registrar horário de saída e duração do atendimento, quando houve check-in. | Fazer check-in e liberar a mesa; conferir a duração. Uma ausência não deve virar permanência de zero minuto. |
| [157](https://github.com/joaogpereira/deep-dish/issues/157) | Faltavam dados organizados para desenvolver e testar o sistema. | Foram criadas factories e um cenário de exemplo com restaurantes, clientes, mesas, fila e reservas. | Rodar o `DatabaseSeeder` em um banco descartável e abrir as telas do cenário criado. |
| [158](https://github.com/joaogpereira/deep-dish/issues/158) | Sem histórico suficiente, os gráficos ficariam vazios na demonstração. | Foi criado um gerador de movimentação simulada de filas e reservas, com identificação da origem dos registros. | Gerar dados em banco de teste e consultar o campo `origem` e a contagem de procedência da API. |
| [160](https://github.com/joaogpereira/deep-dish/issues/160) | Uma mudança na fila podia quebrar posição, saída ou atendimento. | A equipe adicionou testes automatizados para o `FilaService` e fluxos relacionados. | Executar os testes contra o PostgreSQL de teste e guardar o resultado. |

### Sprint 2 — Indicadores e previsão de espera

| Issue | Problema que precisávamos resolver | O que mudou | Como conferir |
|---|---|---|---|
| [161](https://github.com/joaogpereira/deep-dish/issues/161) | O painel mostrava números do momento, mas quase nada sobre o histórico. | O `AnalyticsService` passou a calcular espera, abandono, ocupação, giro de mesa e movimento por horário. | Consultar um período conhecido e comparar os cálculos com os registros do banco. |
| [162](https://github.com/joaogpereira/deep-dish/issues/162) | Os gráficos precisavam receber os indicadores em uma rota própria. | Foi criada a API de analytics do restaurante, incluindo a informação de procedência dos dados. | Consultar `GET /api/restaurante/analytics` com login do restaurante e conferir o controle de acesso. |
| [163](https://github.com/joaogpereira/deep-dish/issues/163) | O tempo de espera não acompanhava os dados do restaurante. | O `EstimativaEsperaService` passou a usar histórico e posição; se faltam dados, usa uma estimativa mais geral ou padrão. | Comparar as respostas com bastante histórico e com poucas amostras, observando o nível da estimativa. |
| [164](https://github.com/joaogpereira/deep-dish/issues/164) | A tela do cliente precisava receber a previsão pelo backend. | A API da fila passou a fornecer a estimativa de espera com sua origem ou nível de cálculo. | Entrar na fila, consultar a posição e conferir os campos da estimativa. |
| [165](https://github.com/joaogpereira/deep-dish/issues/165) | Clientes chamados que não apareciam poderiam continuar ocupando lugar na fila. | Foi criada uma rotina para expirar chamados após a tolerância configurada e liberar a vez. | Simular um chamado sem check-in e conferir a expiração e a continuação da fila. |
| [166](https://github.com/joaogpereira/deep-dish/issues/166) | O restaurante não tinha gráficos para analisar os horários e a ocupação. | O painel passou a mostrar gráficos de espera, abandono, ocupação e mapa de calor, com escolha de período. | Trocar o período no painel e testar carregamento, erro e ausência de histórico. |
| [167](https://github.com/joaogpereira/deep-dish/issues/167) | A previsão precisava aparecer também para quem está usando a aplicação. | O frontend passou a buscar a estimativa da API e mostrá-la na experiência da fila. | Abrir o detalhe do restaurante e a tela da fila; conferir se os valores vêm da API. |

### Sprint 3 — Fila, mesas, tempo real e Android

| Issue | Problema que precisávamos resolver | O que mudou | Como conferir |
|---|---|---|---|
| [168](https://github.com/joaogpereira/deep-dish/issues/168) | Algumas maneiras de liberar mesa não faziam a fila andar. | O processamento de chamadas foi reunido no `FilaService`, para tentar uma nova alocação quando uma mesa fica disponível. | Liberar, cancelar ou expirar atendimentos e observar se a fila é processada. |
| [169](https://github.com/joaogpereira/deep-dish/issues/169) | Uma mesa pequena ficava sem uso quando o primeiro grupo era grande demais. | A lógica passou a considerar a capacidade das mesas e os grupos que cabem nelas, em vez de travar no primeiro da fila. | Testar um grupo grande na frente, um menor atrás e uma mesa que comporta só o menor. |
| [170](https://github.com/joaogpereira/deep-dish/issues/170) | Priorizar apenas o encaixe podia deixar um grupo esperando por muito tempo. | A alocação passou a considerar tanto o aproveitamento da mesa quanto um limite de espera para proteger quem chegou antes. | Montar um cenário com grupos de tamanhos diferentes e conferir a regra de proteção. |
| [171](https://github.com/joaogpereira/deep-dish/issues/171) | A mesa voltava a ficar livre assim que o cliente saía, sem considerar a limpeza. | Entraram os estados `aguardando_limpeza` e `em_limpeza`, com registro do tempo até voltar a ficar livre. | Sair da mesa, iniciar e concluir limpeza; confirmar que ninguém é chamado enquanto ela está em limpeza. |
| [172](https://github.com/joaogpereira/deep-dish/issues/172) | A interface precisava receber alterações sem atualizar a página manualmente. | O backend passou a emitir eventos usando Laravel Reverb e canais separados por cliente e restaurante. | Fazer uma mudança no sistema com duas telas abertas e verificar se o evento chega ao perfil correto. |
| [173](https://github.com/joaogpereira/deep-dish/issues/173) | A atualização das telas dependia de consultas repetidas ao servidor. | O frontend passou a usar os eventos do Reverb para atualizar informações da fila e do painel. | Alterar a fila com as telas abertas e observar a atualização sem recarregar. |
| [174](https://github.com/joaogpereira/deep-dish/issues/174) | Quem já estava na entrada do restaurante precisava procurá-lo no aplicativo. | Foi criado um QR Code com endereço assinado para levar o cliente ao fluxo da fila daquele restaurante. | Escanear um QR válido e outro alterado; verificar destino e proteção da assinatura. |
| [175](https://github.com/joaogpereira/deep-dish/issues/175) | A parte do cliente funcionava principalmente pelo navegador. | O projeto ganhou configuração Capacitor, pasta Android e processo de build do aplicativo. | Gerar e instalar o APK em um celular físico e testar login, fila, posição e botão voltar. O resultado ainda deve ser registrado. |
| [176](https://github.com/joaogpereira/deep-dish/issues/176) | O planejamento previa avisos push com o aplicativo fechado. | A issue foi fechada, mas a descrição atual da #182 informa que o FCM não foi implementado. O tempo real disponível usa Reverb com a tela aberta. | Não apresentar push nativo como entrega. Corrigir a divergência no backlog e confirmar com a equipe. |

### Sprint 4 — Assistente e documentação

| Issue | Problema que precisávamos resolver | O que mudou | Como conferir |
|---|---|---|---|
| [177](https://github.com/joaogpereira/deep-dish/issues/177) | Faltava uma forma de integrar o sistema a um modelo de linguagem. | O backend ganhou um cliente para OpenRouter, com configuração por ambiente e tratamento de falhas. | Testar com chave de desenvolvimento e também sem chave, conferindo as mensagens de erro. |
| [178](https://github.com/joaogpereira/deep-dish/issues/178) | Um assistente não poderia responder números sem consultar o restaurante. | O assistente passou a chamar ferramentas que buscam indicadores no sistema antes de montar a resposta. | Perguntar sobre horários de pico e conferir quais dados foram consultados e de qual restaurante. |
| [179](https://github.com/joaogpereira/deep-dish/issues/179) | O assistente precisava de conversa com contexto e controle de uso. | Foi criada uma rota de chat com histórico, limite de tokens e proteção contra requisições repetidas. | Fazer perguntas seguidas e testar limite diário, acesso de outro restaurante e erros. |
| [180](https://github.com/joaogpereira/deep-dish/issues/180) | Faltava a parte visual para o gerente conversar com o assistente. | O painel ganhou o chat, sugestões de perguntas e indicação dos gráficos usados nas respostas. | Usar o chat no painel e conferir carregamento, erros e referência ao indicador. |
| [181](https://github.com/joaogpereira/deep-dish/issues/181) | Era necessário validar os fluxos completos. | Existem testes automatizados no repositório, mas a issue #181 segue aberta. A descrição da issue também cita um piloto com dados reais, diferente do teste E2E previsto no backlog. | A equipe precisa confirmar o que será feito, executar o piloto/teste combinado e registrar os resultados. |
| [182](https://github.com/joaogpereira/deep-dish/issues/182) | Faltava organizar a entrega final para a banca. | Esta documentação reúne as mudanças, o mapeamento de requisitos, a origem dos dados e a revisão das instruções do projeto. | Revisar os documentos com o grupo, testar o README em ambiente limpo e registrar as correções pendentes. |

### Apoio ao projeto — Integração contínua

| Issue | Problema que precisávamos resolver | O que mudou | Como conferir |
|---|---|---|---|
| [183](https://github.com/joaogpereira/deep-dish/issues/183) | Havia problemas de formatação e testes que atrapalhavam as checagens automáticas. | Foram feitos ajustes para preparar o projeto para a integração contínua. | Rodar os comandos de lint, testes e build ou conferir os resultados na CI. |
| [184](https://github.com/joaogpereira/deep-dish/issues/184) | Os testes dependiam de alguém lembrar de executá-los. | Foi configurado o GitHub Actions para verificar backend e frontend em Pull Requests. | Consultar os jobs da CI em um PR recente. |
| [185](https://github.com/joaogpereira/deep-dish/issues/185) | Era possível tentar juntar alterações mesmo com verificações falhando. | As regras de proteção da branch passaram a exigir revisão e checagens da CI antes do merge. | Conferir a configuração e os resultados de um Pull Request. |

## 3. O que dá para apresentar e o que ainda precisa de cuidado

O repositório tem implementação para o histórico de fila, estimativas de espera, distribuição de grupos nas mesas, controle de limpeza, gráficos, QR Code, atualização por Reverb e assistente com consulta a indicadores. Essas funcionalidades aparecem em serviços, rotas e telas do projeto. Ainda assim, os testes com a aplicação aberta são importantes para confirmar o funcionamento integrado.

Algumas situações não podem ser apresentadas como encerradas:

- **Push no celular:** a issue #176 está fechada, mas a descrição da #182 diz que o FCM não foi feito. O Reverb atualiza a aplicação enquanto a tela está conectada; isso não é a mesma coisa que uma notificação push com o aplicativo fechado.
- **Teste final e piloto:** a [#181](https://github.com/joaogpereira/deep-dish/issues/181) está aberta. O backlog fala em teste de ponta a ponta, enquanto o texto da issue fala em piloto com dados reais. O grupo precisa alinhar o que será executado e guardar o resultado.
- **Aplicativo Android:** o projeto Capacitor existe no código, mas não foi confirmado neste levantamento um teste físico de instalação e uso do APK.
- **Dados dos gráficos:** existe um gerador de histórico simulado. A API informa a procedência, mas o painel consultado não exibe, por si só, uma identificação visível de dado sintético. O aviso deve aparecer no material da banca, depois de conferir a base utilizada.

## 4. Minha conclusão sobre esta etapa

A principal mudança do PI4 foi deixar o Deep Dish menos dependente de operações manuais. A fila passou a ter regras melhores para distribuir as pessoas, o restaurante ganhou mais informações sobre os atendimentos e o painel passou a mostrar esses dados em gráficos. O assistente também aproveita esses indicadores para responder perguntas do gerente.

O projeto avançou bastante em relação ao funcionamento inicial, mas ainda precisa fechar as verificações finais, deixar a origem dos números clara e atualizar as instruções de instalação. Registrar essas pendências é parte da entrega, porque permite mostrar exatamente o que o sistema já tem e o que ainda falta testar.

## 5. Arquivos usados para conferir as entregas

- [Backlog do PI4](https://github.com/joaogpereira/deep-dish/blob/develop/docs/backlog-pi4.md): problemas e tarefas planejadas.
- [`FilaService.php`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-backend/app/Services/FilaService.php), [`MesaLimpezaService.php`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-backend/app/Services/MesaLimpezaService.php): fila, promoção e limpeza.
- [`AnalyticsService.php`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-backend/app/Services/AnalyticsService.php), [`EstimativaEsperaService.php`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-backend/app/Services/EstimativaEsperaService.php): indicadores e espera.
- [`Dashboard.tsx`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-frontend/src/pages/restaurant/Dashboard.tsx), [`useRealtime.ts`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-frontend/src/hooks/useRealtime.ts): painel e atualização das telas.
- [`AssistenteService.php`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-backend/app/Services/AssistenteService.php) e [`AssistenteChat.tsx`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-frontend/src/components/dashboard/AssistenteChat.tsx): assistente.
- [`capacitor.config.ts`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-frontend/capacitor.config.ts), [`docker-compose.yml`](https://github.com/joaogpereira/deep-dish/blob/develop/docker-compose.yml) e [GitHub Actions](https://github.com/joaogpereira/deep-dish/blob/develop/.github/workflows/ci.yml).

**Observação:** as informações deste relatório são uma revisão de documentação e código. Não substituem os resultados dos testes finais realizados pela equipe.
