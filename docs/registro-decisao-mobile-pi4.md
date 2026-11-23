# Por que usamos Capacitor no aplicativo — Deep Dish (PI4)

**Projeto Integrador 4 — Ciência da Computação**  
**Responsável pelo registro:** Eduardo Gondim Marinho  
**Base:** [backlog](https://github.com/joaogpereira/deep-dish/blob/develop/docs/backlog-pi4.md), [roadmap](https://github.com/joaogpereira/deep-dish/blob/develop/docs/roadmap-ia.md) e código da `develop` (22/11/2026).

## 1. Escolha da equipe

No PI4, a equipe decidiu utilizar **Capacitor** para criar a versão Android do Deep Dish, aproveitando as telas React que já estavam prontas. O aplicativo é voltado ao cliente, enquanto o restaurante continua usando o painel web, que funciona melhor para acompanhar várias mesas e gráficos ao mesmo tempo.

A outra opção discutida foi **React Native**. Ela permitiria criar interfaces com componentes nativos, mas exigiria reescrever boa parte das telas do cliente e adaptar componentes da aplicação. Como o semestre também tinha melhorias na fila e o assistente de IA, a equipe preferiu aproveitar o código existente primeiro.

## 2. O que o Capacitor aproveita

O Capacitor pega a versão gerada pelo Vite e a coloca dentro de um projeto Android. O repositório já tem [`capacitor.config.ts`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-frontend/capacitor.config.ts), uma pasta `android` e o script `build:mobile` no [`package.json`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-frontend/package.json). Isso confirma que a estrutura para compilar o aplicativo foi acrescentada.

Essa escolha evita manter duas interfaces completamente separadas agora. As regras de fila, login e comunicação com a API continuam sendo aproveitadas pelo aplicativo.

## 3. O que ainda precisa ser testado

O aplicativo usa uma WebView para exibir as telas. Por isso, é importante verificar em **celular físico** o desempenho da navegação, o teclado, o botão de voltar, o login e a entrada na fila. O repositório mostra a estrutura Android, mas este levantamento não comprovou a instalação de um APK no aparelho ou o resultado do teste feito pelo time.

Também não devemos apresentar **notificação FCM** como pronta. A issue #182 informa que esse recurso não foi implementado. A atualização das telas utiliza Laravel Reverb, que funciona quando o aplicativo está conectado e com a tela aberta; isso não equivale a um push com o aplicativo fechado.

## 4. Por que React Native ainda é uma possibilidade

O React Native não foi descartado para sempre. Ele pode ser reavaliado se o aplicativo tiver lentidão, se faltar algum recurso nativo necessário ou se o funcionamento da WebView prejudicar a experiência de uso. A decisão seria para uma fase futura, depois de testar o que foi feito com Capacitor.

## 5. Registro nos documentos oficiais

A explicação da escolha **já aparece** na seção *Mobile: Capacitor agora, React Native em aberto* do [`backlog-pi4.md`](https://github.com/joaogpereira/deep-dish/blob/develop/docs/backlog-pi4.md) e no [`roadmap-ia.md`](https://github.com/joaogpereira/deep-dish/blob/develop/docs/roadmap-ia.md). Por isso, não é necessário duplicar a mesma justificativa inteira. O que falta é conferir se a equipe já registrou o resultado do teste em aparelho físico e corrigir trechos que dão a entender que FCM está funcionando.

### Texto curto para atualizar os arquivos, se o grupo aprovar

> No PI4, foi usado Capacitor para aproveitar o frontend React na versão Android do aplicativo do cliente. A decisão reduziu o trabalho de reescrever telas e permitiu manter o foco nas melhorias da fila e no assistente. O painel do restaurante continua na web. React Native permanece como opção futura, caso os testes do aplicativo mostrem limitações importantes. A estrutura Android está no repositório, mas o resultado do teste em aparelho físico precisa ficar registrado. O FCM não faz parte da implementação concluída: as atualizações atuais usam Reverb/WebSocket com a aplicação conectada.

Esse texto só precisa ser ajustado depois que a equipe confirmar o resultado do teste real do APK.
