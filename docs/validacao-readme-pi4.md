# Validação do README e testes básicos — Deep-Dish (PI4)

**Projeto:** Deep-Dish — Projeto Integrador 4  
**Curso:** Ciência da Computação  
**Responsável pela documentação:** Eduardo Gondim Marinho  
**Versão testada:** branch `develop` do repositório [`joaogpereira/deep-dish`](https://github.com/joaogpereira/deep-dish)  
**Período da validação:** noite de 22/11/2026 (horário de Brasília)  
**Ambiente:** Windows 10, Git Bash, Docker Desktop 4.99.0 com WSL 2, PostgreSQL local e navegador.

## 1. Objetivo

A proposta deste teste foi instalar o Deep-Dish em um computador que não estava preparado para rodar o projeto e verificar se as instruções do README eram suficientes. Além disso, aproveitei o ambiente local para testar alguns fluxos que aparecem na entrega do PI4, principalmente reservas, fila de espera e limpeza de mesas.

O teste foi feito **no meu computador e em um banco local de desenvolvimento**, com dados criados pelo próprio projeto. Não foi uma validação do sistema em produção, nem uma execução de todos os testes automatizados. Quando precisei fazer alguma coisa que não estava clara no README, anotei a dificuldade e o que resolveu.

## 2. Resultado geral da instalação

**Resultado:** consegui executar o sistema localmente, entrar como cliente e restaurante e usar as principais telas testadas. A instalação, porém, **não foi concluída seguindo apenas a sequência inicial do README**. Tive dificuldades com a configuração do meu computador e encontrei etapas de configuração do projeto que poderiam estar mais claras.

| Etapa | Resultado observado | Evidência |
|---|---|---|
| Baixar o repositório e mudar para `develop` | Concluído pelo Git Bash. | [E01](evidencias-validacao/01-clone-develop.png) |
| Preparar Docker/WSL 2 | Concluído depois de ativar a virtualização e ajustar o ambiente. | — |
| Construir as imagens do projeto | Concluído depois de resolver os erros locais do Docker. | [E05](evidencias-validacao/05-php-oficial-funcionando.png) |
| Iniciar os seis serviços do Compose | Frontend, backend, PostgreSQL, queue, scheduler e Reverb apareceram como ativos; PostgreSQL, como saudável. | [E06](evidencias-validacao/06-servicos-docker.png) |
| Criar as tabelas e os dados de exemplo | `php artisan migrate --seed` terminou sem erros; migrations com `DONE` e cenário de exemplo criado. | [E07](evidencias-validacao/07-migrations-seed.png) |
| Abrir o frontend | Inicialmente a página ficou branca; após criar o `.env` do frontend, carregou. | [E08](evidencias-validacao/08-erro-console-frontend.png), [E10](evidencias-validacao/10-dashboard-restaurante.png) |
| Entrar nas contas de demonstração | Acesso à área de cliente e ao painel do restaurante realizado. | [E09](evidencias-validacao/09-reserva-cliente-detalhes.png), [E10](evidencias-validacao/10-dashboard-restaurante.png) |

### Comandos principais utilizados

Na pasta do projeto, utilizei os seguintes comandos ao longo da instalação, depois de configurar o Docker:

```bash
git clone https://github.com/joaogpereira/deep-dish.git
cd deep-dish
git switch develop

cp deep-dish-backend/.env.example deep-dish-backend/.env
cp -n deep-dish-frontend/.env.example deep-dish-frontend/.env

docker compose up -d --build
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan jwt:secret
docker compose exec backend php artisan migrate --seed
docker compose restart frontend
docker compose ps
```

Esses comandos aparecem aqui **como registro do caminho que foi usado**, e não como garantia de que são a melhor sequência para qualquer computador. A criação das chaves e a configuração do frontend precisaram de atenção separada. As chaves e credenciais de uso local não foram incluídas neste documento.

## 3. Dificuldades encontradas

### 3.1. Problemas do computador e do Docker

No começo, o Docker Desktop não instalava porque o recurso **Plataforma de Máquina Virtual** estava desabilitado. Depois de configurar o WSL 2 e ativar a virtualização na BIOS, consegui iniciar o Docker.

O PowerShell também não reconhecia o comando `git`, embora o Git estivesse instalado. Por isso, usei o Git Bash para clonar o projeto e continuar os testes.

Durante a construção das imagens, apareceram os erros `input/output error` e, em outro momento, `500 Internal Server Error`. Verifiquei que o disco C: tinha aproximadamente **1,2 GB livres**. Depois de liberar espaço, ficou com aproximadamente **67 GB livres**, mas algumas imagens ainda falhavam. A imagem do PHP chegou a apresentar `exec format error` e código de saída `139` (*segmentation fault*).

Foi necessário reiniciar e limpar os dados locais do Docker para refazer as imagens. Depois disso, o teste com a imagem oficial do PHP funcionou e o projeto conseguiu iniciar. Esses erros **indicam um problema no ambiente local durante a instalação; não comprovam defeito no código do Deep-Dish**. A falta de espaço provavelmente contribuiu, mas não dá para afirmar que foi a única causa.

Evidências: [erro do Docker](evidencias-validacao/02-falha-inicial-docker.png), [disco quase cheio](evidencias-validacao/03-disco-c-quase-cheio.png), [espaço liberado](evidencias-validacao/04-disco-c-espaco-liberado.png), [PHP funcionando depois](evidencias-validacao/05-php-oficial-funcionando.png).

### 3.2. Tela branca na abertura do frontend

Depois que os containers e as migrations funcionaram, acessei `http://localhost:8080`, mas a página ficou totalmente branca. No Console do navegador apareceu:

```text
Uncaught TypeError: Cannot read properties of undefined (reading 'replace')
```

A mensagem apontava para `storage.ts`. Conferindo o arquivo, vi que ele usa `import.meta.env.VITE_API_URL`. O frontend ainda estava sem seu arquivo `.env`, então essa variável não havia sido carregada. Resolvi copiando `deep-dish-frontend/.env.example` para `deep-dish-frontend/.env` e reiniciando o serviço `frontend`.

**Ponto a melhorar no README:** a criação do `.env` do frontend **é mencionada na seção geral de variáveis de ambiente**, mas ficou separada da sequência inicial **“Com Docker (recomendado)”**, que explica apenas a cópia do `.env` do backend. Por isso, seguindo aquela sequência, eu não havia configurado o frontend. É melhor colocar os dois arquivos no mesmo passo do Docker ou fazer uma referência clara à seção de variáveis.

Evidência: [erro no Console](evidencias-validacao/08-erro-console-frontend.png).

### 3.3. Outros pontos do README que podem ser melhorados

| Ponto | O que encontrei | Sugestão para a equipe |
|---|---|---|
| Clone do projeto | O comando usa `git clone <YOUR_GIT_URL>`. | Substituir pelo link real e explicar como selecionar a branch `develop`. |
| Configuração do frontend | A instrução existe mais abaixo, mas não está junto da instalação Docker. | Adicionar a cópia de `deep-dish-frontend/.env.example` e a configuração de `VITE_API_URL` ao passo a passo principal. |
| Chaves Laravel/JWT | O README cita geração das chaves em outra seção, com comandos executados fora do contexto Docker. | Mostrar os comandos dentro do container e deixar a ordem mais clara para quem usa só Docker. |
| Serviços em tempo real | O Compose sobe o Reverb, mas o README explica pouco como conferir sua configuração. | Informar variáveis necessárias e um procedimento simples de teste da conexão WebSocket. |
| Assistente de IA | A tela do assistente existe, mas o uso do OpenRouter depende de configuração própria. | Explicar como configurar e testar a integração sem expor chave de API. |
| Funcionalidades anunciadas | A apresentação do repositório cita Laravel 11, enquanto o `composer.json` exige Laravel 12. | Atualizar a descrição de versão. |
| Notificações FCM | A [issue #182](https://github.com/joaogpereira/deep-dish/issues/182) informa que o FCM não foi implementado. | Não descrever FCM como pronto; diferenciar push de atualizações via Reverb com a tela conectada. |

Esses últimos pontos são observações de documentação/código; não significa que todos tenham causado falhas na instalação.

## 4. Funcionalidades que testei no navegador

Usei as contas criadas pelo `migrate --seed` e fiz os testes com o restaurante de demonstração **Trattoria Bella**. As capturas estão na pasta [`evidencias-validacao/`](evidencias-validacao/).

| Teste | O que aconteceu | Situação |
|---|---|---|
| Login e navegação do cliente | Entrei na conta de exemplo e abri a página de reservas. | **Funcionou** |
| Detalhes da reserva | A página mostrou restaurante, data, mesa, capacidade e status. | **Funcionou** — [E09](evidencias-validacao/09-reserva-cliente-detalhes.png) |
| Login e dashboard do restaurante | O painel carregou com contadores de fila, reservas, mesas e ocupação. | **Funcionou** — [E10](evidencias-validacao/10-dashboard-restaurante.png) |
| Lista de reservas do restaurante | Apareceram reservas com estados diferentes, incluindo aguardando check-in, em andamento, liberada, cancelada e expirada. | **Funcionou** — [E11](evidencias-validacao/11-reservas-restaurante.png) |
| Liberação de reserva | Cliquei para liberar a Mesa 2. O sistema mostrou “Mesa liberada” e alterou o estado da reserva para liberada. | **Funcionou** — [E12](evidencias-validacao/12-liberacao-reserva.png) |
| Ciclo de limpeza | A Mesa 2 passou por **Aguardando limpeza → Em limpeza → Livre**. | **Funcionou** — [E13](evidencias-validacao/13-mesa-aguardando-limpeza.png), [E14](evidencias-validacao/14-mesa-em-limpeza.png), [E15](evidencias-validacao/15-mesa-livre.png) |
| QR Code da fila | O QR apareceu, primeiro com o aviso de fila desativada; depois ativei a fila nas configurações e o aviso desapareceu. | **Geração e indicação de status funcionaram** — [E16](evidencias-validacao/16-qr-fila-fechada.png), [E17](evidencias-validacao/17-qr-fila-ativa.png) |
| Entrada na fila | Entrei com um grupo de **2 pessoas**. O sistema mostrou **posição 1** e **espera estimada de 8 minutos**. | **Funcionou** — [E18](evidencias-validacao/18-cliente-posicao-fila.png) |
| Fila vista pelo restaurante | O grupo apareceu na lista do restaurante, com a posição e as 2 pessoas. | **Funcionou** — [E19](evidencias-validacao/19-fila-visivel-no-restaurante.png) |
| Promoção da fila para reserva | O cliente recebeu “Sua vez chegou!” e a informação de que a **Mesa 1** estava reservada. A nova reserva também apareceu nas telas de cliente e restaurante. | **Funcionou no cenário testado** — [E20](evidencias-validacao/20-cliente-promovido.png), [E21](evidencias-validacao/21-reservas-cliente-apos-promocao.png), [E22](evidencias-validacao/22-reserva-criada-pela-fila-no-painel.png) |
| Check-in | O restaurante mostrou “Check-in realizado!”. A reserva mudou para “Em andamento” e a tela do cliente exibiu a mudança e uma notificação visual. | **Funcionou** — [E23](evidencias-validacao/23-checkin-restaurante.png), [E24](evidencias-validacao/24-checkin-cliente.png) |

### Observações desses testes

**Reserva utilizada no check-in:** o check-in foi feito na reserva de **23/11/2026, às 00h**, e não na nova reserva de **22/11/2026, às 22h41**, que surgiu a partir da fila. As duas apareciam na mesma Mesa 1. Isso precisa ficar explícito para não dizer que o check-in foi da reserva recém-criada.

**Atribuição da mesa:** o fluxo que testei tinha apenas um grupo de duas pessoas. Ele mostra que o sistema consegue promover um cliente da fila para uma reserva, mas **não basta para provar a regra completa de alocação inteligente**, que compara grupos de diferentes tamanhos e respeita limites de espera.

**Atualização automática:** a tela do cliente mostrou a mensagem de check-in e a mudança de estado. Isso é uma boa evidência de atualização entre as duas interfaces, mas **não fiz um teste controlado para comprovar o transporte por Reverb/WebSocket sem atualização manual**. Portanto, não marquei a integração em tempo real como totalmente validada.

**Gráficos:** no dashboard, com o filtro de 90 dias, apareceu **“Sem histórico neste período”**. Isso não prova que os gráficos estejam com defeito. O histórico sintético de aproximadamente 12 semanas, citado na issue, não foi carregado nem analisado neste teste local.

## 5. O que não foi testado

Para não dar a entender que todo o projeto foi validado, deixei registrado o que ainda depende de outros testes:

- leitura do QR Code com a câmera de um celular e abertura correta do link;
- comportamento da fila com vários grupos de tamanhos diferentes e regras de prioridade;
- atualização em tempo real por Reverb em teste controlado, sem recarregar as páginas;
- resposta do assistente de IA com uma chave OpenRouter configurada;
- gráficos com histórico sintético de 12 semanas e conferência dos campos de procedência;
- instalação e funcionamento do aplicativo Android em aparelho físico;
- testes automatizados, testes de carga e verificação completa de todas as regras do sistema.

A issue #182 pede principalmente a **validação das instruções de instalação**. Os testes acima ajudam a mostrar que o projeto funcionou no ambiente local, mas não substituem a validação técnica completa da equipe.

## 6. Ajustes que devem ser encaminhados à equipe

Sugiro que os desenvolvedores atualizem o `README.md` e os arquivos `.env.example` conforme a lista abaixo, sem publicar senhas ou chaves:

1. Colocar a configuração dos **dois `.env`** no mesmo passo da instalação Docker.
2. Explicar a geração de `APP_KEY` e `JWT_SECRET` com os comandos `docker compose exec backend ...`.
3. Substituir o placeholder de clone pela URL real do repositório e indicar o uso da `develop`.
4. Deixar mais claro como configurar e conferir Reverb e OpenRouter no ambiente local.
5. Revisar a versão do Laravel e tirar qualquer indicação de que notificações FCM já estejam implementadas.
6. Após os ajustes, pedir que alguém execute novamente o passo a passo em outro ambiente limpo, para verificar se as instruções ficaram suficientes.

No PI4, **a minha parte é entregar esta lista de dificuldades e os resultados dos testes**. A correção dos arquivos de configuração e do README fica com a equipe de desenvolvimento, conforme a tarefa #182.

## 7. Conclusão

Consegui colocar o Deep-Dish para funcionar no meu computador usando Docker e o banco local. Testei o acesso de cliente e restaurante, as reservas, o ciclo de limpeza das mesas e a entrada na fila. Também consegui acompanhar um cliente que entrou na fila, recebeu uma mesa e passou a ter uma reserva no sistema.

A principal dificuldade relacionada à documentação do projeto foi a configuração do frontend: eu segui a parte de Docker do README, mas faltou criar o `.env` do frontend antes de abrir a página, e o sistema ficou em branco. A instrução existe em outra seção, então a sugestão é deixá-la mais visível e junto dos demais passos. Os erros de armazenamento e execução de imagens que apareceram antes foram do meu ambiente Docker e precisaram ser resolvidos separadamente.

Com isso, a **validação prática da instalação e os problemas encontrados estão documentados**, mas **a correção do README pela equipe ainda precisa ser conferida** para atender completamente ao critério de aceite da issue.

---

### Fontes do projeto

- [README — branch `develop`](https://github.com/joaogpereira/deep-dish/blob/develop/README.md)
- [Issue #182 — documentação de entrega](https://github.com/joaogpereira/deep-dish/issues/182)
- [`docker-compose.yml`](https://github.com/joaogpereira/deep-dish/blob/develop/docker-compose.yml)
- [`.env.example` do frontend](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-frontend/.env.example)
- [`storage.ts`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-frontend/src/lib/storage.ts)
- [`FilaService.php`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-backend/app/Services/FilaService.php)
