<div align="center">

<br/>

```
██████╗ ███████╗███████╗██████╗     ██████╗ ██╗███████╗██╗  ██╗
██╔══██╗██╔════╝██╔════╝██╔══██╗    ██╔══██╗██║██╔════╝██║  ██║
██║  ██║█████╗  █████╗  ██████╔╝    ██║  ██║██║███████╗███████║
██║  ██║██╔══╝  ██╔══╝  ██╔═══╝     ██║  ██║██║╚════██║██╔══██║
██████╔╝███████╗███████╗██║         ██████╔╝██║███████║██║  ██║
╚═════╝ ╚══════╝╚══════╝╚═╝         ╚═════╝ ╚═╝╚══════╝╚═╝  ╚═╝
```

**Fila inteligente. Reservas sem fricção. Restaurantes no controle.**

[![CI](https://github.com/joaogpereira/deep-dish/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/joaogpereira/deep-dish/actions/workflows/ci.yml)

[![React](https://img.shields.io/badge/React-18-61DAFB?style=flat-square&logo=react&logoColor=black)](https://reactjs.org/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?style=flat-square&logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![Vite](https://img.shields.io/badge/Vite-5-646CFF?style=flat-square&logo=vite&logoColor=white)](https://vitejs.dev/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-3-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker&logoColor=white)](https://www.docker.com/)

</div>

---

## 📌 Sobre o Projeto

**Deep Dish** é uma plataforma digital de **fila inteligente e reserva de mesas** para restaurantes. O sistema organiza o fluxo de atendimento em tempo real, eliminando filas físicas desorganizadas e centralizando reservas em uma única interface — moderna, rápida e intuitiva.

> Uma experiência premium para restaurantes que valorizam organização, velocidade e sofisticação.

---

## ✨ Funcionalidades

### 👤 Área do Cliente
| Funcionalidade | Status |
|---|---|
| Busca de restaurantes | ✅ MVP |
| Entrada remota na fila | ✅ MVP |
| Reservas antecipadas | ✅ MVP |
| Acompanhamento da posição na fila | ✅ MVP |
| Cancelamento de fila / reserva | ✅ MVP |
| Notificações in-app | ✅ MVP |

### 🍽️ Área do Restaurante (Admin)
| Funcionalidade | Status |
|---|---|
| Configuração do restaurante | ✅ MVP |
| Gerenciamento de mesas | ✅ MVP |
| Controle de fila em tempo real | ✅ MVP |
| Visualização de reservas do dia | ✅ MVP |
| Atualização manual de status | ✅ MVP |
| Gestão de funcionários | ✅ MVP |

---

## 🛠️ Stack Tecnológica

```
Frontend
├── ⚡ Vite           — build ultrarrápido
├── ⚛️  React 18       — UI reativa e componentizada
├── 🟦 TypeScript     — tipagem estática e segurança
├── 🎨 Tailwind CSS   — estilização utilitária mobile-first
├── 🧩 shadcn/ui      — componentes acessíveis e customizáveis
└── 🔀 React Router   — navegação SPA com proteção de rotas

Backend
├── 🐘 Laravel 11     — API REST
├── 🔐 JWT Auth       — autenticação stateless
├── 🗃️  PostgreSQL     — banco de dados relacional (Supabase)
└── ⚙️  Queue Worker   — processamento assíncrono de e-mails

Infraestrutura
└── 🐳 Docker Compose — orquestração dos serviços (frontend + backend + worker)

Arquitetura
└── React SPA → Laravel API REST → PostgreSQL
```

---

## 🗂️ Estrutura do Projeto

```
deep-dish/
├── deep-dish-frontend/   # SPA React (interface do cliente e do restaurante)
├── deep-dish-backend/    # API Laravel (autenticação, reservas, fila, mesas)
└── docker-compose.yml    # Orquestração dos serviços

deep-dish-frontend/src/
├── components/       # Componentes reutilizáveis
├── layouts/          # Layouts: público, cliente e admin
├── pages/            # Páginas da aplicação
├── routes/           # Configuração de rotas e guards
├── services/         # Camada de integração com a API
├── types/            # Tipagens TypeScript globais
└── utils/            # Funções auxiliares
```

---

## 🚀 Rodando Localmente

### 🐳 Com Docker (recomendado)

O jeito mais fácil de subir o projeto inteiro. Sobe o **frontend**, o **backend** e o **worker de filas** automaticamente — sem precisar instalar PHP, Composer ou configurar ambiente manualmente.

**Pré-requisito:** ter o [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado.

```bash
# 1. Clone o repositório
git clone <YOUR_GIT_URL>
cd deep-dish

# 2. Configure as variáveis de ambiente do backend
cp deep-dish-backend/.env.example deep-dish-backend/.env
# edite o arquivo com suas credenciais (JWT, SMTP) — o banco local já vem pronto

# 3. Suba os containers
docker compose up --build

# 4. Só na primeira vez: crie as tabelas e o cenário de exemplo
docker compose exec backend php artisan migrate --seed
```

> Até o passo 4 rodar, o `queue` e o `reverb` reiniciam em loop com
> `relation "cache" does not exist` — é esperado, eles sobem sozinhos assim que as tabelas existem.

Ou em background (libera o terminal):

```bash
docker compose up --build -d
```

Após subir:
- **Frontend:** http://localhost:8080
- **Backend (API):** http://localhost:8000

> Na primeira execução o `composer install` e o `npm install` rodam automaticamente dentro dos containers. Pode levar alguns minutos.

**Banco de desenvolvimento:** o serviço `postgres_test` guarda dois bancos — `deepdish_dev` (o que
a aplicação usa) e `deepdish_test` (o da suíte). Os dois ficam no volume `postgres_test_data` e
**sobrevivem ao `docker compose down`**; para zerar tudo, `docker compose down -v`.

**Trocar para o Supabase (homologação):** no `deep-dish-backend/.env`, comente o bloco *Local* e
descomente o bloco *Supabase* (a senha fica no painel, em Settings > Database). Depois rode
`docker compose up -d` — **não** `docker compose restart`, que não relê o `.env` e deixa os
containers no banco antigo. Os testes não são afetados: sempre usam o `deepdish_test` local.

**Comandos úteis:**

```bash
docker compose logs -f        # ver logs em tempo real
docker compose ps             # status dos containers
docker compose down           # parar tudo
docker compose restart backend  # reiniciar um serviço
```

---

### 🛠️ Sem Docker (manual)

**Pré-requisitos:** Node.js `>= 18`, PHP `>= 8.2`, Composer

**Frontend:**

```bash
cd deep-dish-frontend
npm install
npm run dev
```

Disponível em **http://localhost:8080**

**Backend:**

```bash
cd deep-dish-backend
composer install
cp .env.example .env
php artisan serve
```

Disponível em **http://localhost:8000**

**Worker de filas** (obrigatório para envio de e-mails de verificação):

```bash
# dentro de deep-dish-backend/
php artisan queue:work --tries=3 --sleep=3
```

> Sem o worker ativo, e-mails de verificação de conta **não serão entregues**.

---

### Variáveis de Ambiente

**Frontend** — crie `deep-dish-frontend/.env`:

```env
VITE_API_URL=http://127.0.0.1:8000/api
```

**Backend** — copie `deep-dish-backend/.env.example` e preencha JWT e SMTP. O banco já vem
apontado para o `postgres_test` local (ver *Banco de desenvolvimento* acima).

Gere as duas chaves obrigatórias (sem elas a aplicação não sobe):

```bash
php artisan key:generate   # APP_KEY
php artisan jwt:secret     # JWT_SECRET
```

---

## ✅ Integração Contínua

Todo Pull Request para `develop` ou `main` dispara automaticamente o workflow
[`.github/workflows/ci.yml`](.github/workflows/ci.yml), com dois jobs em paralelo:

| Job | O que verifica |
|---|---|
| **Backend** (PHP 8.2) | Formatação com Pint · migrations em banco virgem · testes PHPUnit |
| **Frontend** (Node 20) | ESLint · checagem de tipos · Vitest · build de produção |

O job de backend sobe um **PostgreSQL 16 efêmero** como *service container* — criado e destruído a
cada execução, sem nunca encostar no banco compartilhado do time.

### Reproduzindo localmente

Rode antes de abrir o PR; os seis comandos precisam passar:

```bash
# backend
cd deep-dish-backend
./vendor/bin/pint --test      # formatação (sem --test, ele corrige)
composer test                 # PHPUnit

# frontend
cd ../deep-dish-frontend
npm run lint                  # ESLint
npx tsc --noEmit              # tipos — o build NÃO checa isso
npm test                      # Vitest
npm run build                 # build de produção
```

> **Por que `npx tsc --noEmit` existe:** o `npm run build` usa esbuild, que *apaga* as anotações de
> tipo sem verificá-las. Sem esse comando, nenhum erro de tipo é detectado antes de chegar no
> navegador.

Os testes rodam em **PostgreSQL tanto na sua máquina quanto na CI** — o `phpunit.xml` já aponta
por padrão para um banco de teste local, o mesmo que o job de backend da CI usa
(`deepdish_test`, usuário `postgres`, senha `secret`). Isso existe porque parte do SQL do projeto
usa sintaxe específica do Postgres (`interval '1 hour'`, `ALTER COLUMN ... SET/DROP DEFAULT`) que
SQLite não entende — testar em Postgres local evita descobrir isso só na CI.

**Suba o banco** (serviço `postgres_test` do `docker-compose.yml`, na raiz do repo — se o stack
inteiro já estiver de pé, ele já está rodando):

```bash
docker compose up -d postgres_test
```

**Rode os testes a partir do host** (sua máquina, não de dentro de um container) — não precisa
passar nenhuma variável `DB_*` na mão, o `phpunit.xml` já resolve para `127.0.0.1:5433/deepdish_test`:

```bash
cd deep-dish-backend
composer test                 # ou: php artisan test
```

O `deepdish_test` mora no mesmo container que o `deepdish_dev`, mas o `RefreshDatabase` só limpa
o `deepdish_test` — o seu cenário de desenvolvimento não é tocado.

> ⚠️ `tests/TestCase.php` tem uma trava de segurança em três camadas, conferida antes de
> `RefreshDatabase` rodar: (1) aborta se o host for o Supabase compartilhado do time; (2) só aceita
> hosts da allowlist (`127.0.0.1`, `localhost` ou `postgres_test`); (3) só aceita bancos cujo nome
> termina em `_test`. A camada 3 existe porque, **de dentro de um container**, o `DB_*` do `.env`
> vence o `phpunit.xml` e a suíte cairia no `deepdish_dev`. O projeto não tem staging nem backup do
> Supabase, então isso é intencional e não deve ser enfraquecido para "rodar mais fácil".

---

## ⚙️ Worker de Filas

O sistema utiliza um **worker de filas** para processar tarefas assíncronas em background — principalmente o **envio de e-mails de verificação de conta**.

Quando um usuário se cadastra ou faz login sem ter verificado o e-mail, o sistema enfileira o disparo do e-mail em vez de enviar na hora. O worker fica rodando em paralelo e processa essa fila continuamente.

- **Com Docker:** o worker sobe automaticamente como o serviço `queue` — nenhum comando extra necessário.
- **Sem Docker:** é preciso rodar `php artisan queue:work` manualmente.

---

## ⏱️ Tarefas Agendadas

O backend tem rotinas que precisam rodar sozinhas, definidas em `deep-dish-backend/routes/console.php`:

- `reservas:expirar` (a cada 5 min) — expira reservas sem check-in e sessões encerradas pelo fechamento.
- `fila:expirar-chamados` (a cada minuto) — quem foi chamado da fila para a mesa e não fez check-in dentro da tolerância (`FILA_TOLERANCIA_CHAMADA_MINUTOS`, padrão 15) vira `expirado`, e a mesa vai para o próximo.

- **Com Docker:** sobem automaticamente no serviço `scheduler`.
- **Sem Docker:** é preciso rodar `php artisan schedule:work` manualmente.

---

## 🔐 Controle de Acesso

O sistema possui proteção de rotas por perfil de usuário via token **JWT**:

| Perfil | Acesso |
|---|---|
| `Cliente` | Área do cliente — fila e reservas |
| `Restaurante` | Painel admin do restaurante |

---

## 🎨 Identidade Visual

O design foi construído com base na **psicologia das cores** aplicada ao setor alimentar:

| Cor | Significado | Uso |
|---|---|---|
| 🔴 Vermelho | Energia, fome, urgência | Botões primários e CTAs |
| ⚫ Preto | Sofisticação, luxo | Headers, contrastes premium |
| 🟤 Marrom / Bege | Conforto, rústico sofisticado | Backgrounds e áreas de conteúdo |

> ⚠️ **Roxo é intencionalmente evitado** — pesquisas indicam associação negativa no contexto alimentar.

---

## 📈 Roadmap

- [x] MVP com fluxo completo cliente e restaurante
- [x] Integração com API Laravel (JWT, reservas, fila, mesas, funcionários)
- [x] Verificação de e-mail com worker de filas
- [x] Configuração Docker para onboarding simplificado
- [ ] Atualizações em tempo real via WebSockets
- [ ] Notificações push
- [ ] Analytics para restaurantes
- [ ] Sistema de pagamento integrado
- [ ] Controle avançado de capacidade por turno

---

## 📎 Links

- 🎨 [Pitch Deck no Canva](https://www.canva.com/design/DAHDZnOSuv4/Id21tAfjwMEOS-4B5mpf_w/edit)

---

## 👥 Time

| Nome | Papel |
|---|---|
| Brenda Regis Batista Bandeira | Analista de Requisitos / UX |
| Eduardo Gondim Marinho | Analista de Requisitos / UX |
| Eduardo Serra Pierre Vidal | Desenvolvedor Full Stack  |
| João Guilherme Costa Pereira | Desenvolvedor Full Stack / Scrum Master |
| João Pedro Vieira de Oliveira | Desenvolvedor Full Stack |
| Arthur Cavalcante Neves | Desenvolvedor Full Stack / UI-UX |

---

<div align="center">

**Deep Dish** — Feito com ☕ e muito cuidado pelos integrantes do time.

</div>
