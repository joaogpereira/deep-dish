# Deep Dish -- Contributing Guide

Este documento define as regras oficiais de contribuição do projeto
**Deep Dish**.\
Todo membro da equipe deve seguir estas diretrizes para garantir
organização, qualidade de código e fluidez no desenvolvimento.

------------------------------------------------------------------------

## 1. Estrutura de Branches

-   **main**: contém apenas código em produção.
-   **develop**: contém código estável e integrado.

------------------------------------------------------------------------

## 2. Criação de Branches

Toda nova branch deve ser criada a partir da `develop` e seguir o
padrão:

    Tipo/NomeDaBranch

### Tipos permitidos:

-   Feature\
-   Fix\
-   Chore\
-   Hotfix

### Exemplos:

    Feature/AuthLogin
    Fix/ReservaDuplicada
    Chore/ConfigJWT

------------------------------------------------------------------------

## 3. Padrão de Commits

Formato obrigatório:

    tipo: descrição clara do que foi feito

### Tipos permitidos:

-   feature
-   fix
-   chore
-   refactor
-   docs

### Exemplo:

    feature: adicionado autenticação de login ao usuário

------------------------------------------------------------------------

## 4. Pull Requests

-   Todo código deve passar por **Pull Request**.
-   PRs devem ser abertos sempre para a branch `develop`.
-   Somente o **Tech Lead** pode aprovar PRs e realizar merges para
    `develop` e `main`.

### Regras aplicadas automaticamente pelo GitHub

As exigências abaixo não dependem de ninguém lembrar: o botão de merge fica
bloqueado até que todas sejam atendidas. Valem inclusive para quem é admin do
repositório.

| Exigência | O que significa |
|---|---|
| **Pull Request** | Push direto em `develop` ou `main` é recusado pelo servidor |
| **1 aprovação** | De outra pessoa — o GitHub não permite aprovar o próprio PR |
| **Aprovação recente** | Commit novo derruba a aprovação anterior, exigindo nova revisão |
| **CI verde** | Os checks `Backend (PHP 8.2)` e `Frontend (Node 20)` precisam passar |
| **Branch atualizada** | O PR precisa conter a `develop` mais recente antes do merge |
| **Conversas resolvidas** | Todo comentário de revisão precisa ser marcado como resolvido |

**Sobre a branch atualizada:** se alguém mergear enquanto o seu PR está aberto,
aparece o botão **Update branch**. Clique nele e aguarde a CI rodar de novo
(~40s). Isso existe porque dois PRs verdes separadamente podem quebrar quando
combinados — foi o que aconteceu em agosto/2026 e custou um PR extra só de
conserto.

### Procedimento de emergência

Se o GitHub Actions estiver fora do ar, os checks nunca reportam e **ninguém
consegue mergear** — nem o admin. Nesse caso:

1.  O admin vai em **Settings → Branches → Edit** na branch travada;
2.  desmarca temporariamente *Require status checks to pass*;
3.  faz o merge;
4.  **religa a opção imediatamente**;
5.  registra no PR o motivo do desbloqueio.

Isso é exceção, não atalho. Se estiver acontecendo com frequência, o problema
é a CI, não a regra.

------------------------------------------------------------------------

## 5. Code Review Padronizado

Toda revisão realizada pelo Tech Lead seguirá o seguinte modelo
estruturado:

### Status Geral:

-   Aprovado
-   Aprovado com ajustes menores
-   Necessita correções obrigatórias

### Categorias de Avaliação:

-   Arquitetura
-   Banco de Dados
-   Frontend
-   Segurança

### Ajustes Obrigatórios:

Itens marcados como obrigatórios devem ser corrigidos antes do merge.

------------------------------------------------------------------------

## Exemplo de Code Review

**Status:** Necessita correções obrigatórias

### Arquitetura:

-   Controller contém regra de negócio. Mover para service.

### Banco de Dados:

-   Adicionar constraint para evitar duplicidade de reserva.

### Frontend:

-   Implementar tratamento de estado de loading.

### Ajustes obrigatórios:

-   Criar service para validação de reserva
-   Adicionar unique constraint
-   Implementar tratamento de erro

------------------------------------------------------------------------

## Regras Finais

-   Commits diretos em `main` ou `develop` não são permitidos — o GitHub
    recusa o push.
-   Branch fora do padrão será recusada.
-   Merge sem aprovação do Tech Lead é proibido — e agora bloqueado
    tecnicamente, não só por convenção.
-   Merge com a CI vermelha é bloqueado.
-   Notes do Tech Lead devem ser tratadas como obrigatórias.

------------------------------------------------------------------------

Estas regras existem para garantir qualidade, clareza e evolução
sustentável do projeto **Deep Dish**.
