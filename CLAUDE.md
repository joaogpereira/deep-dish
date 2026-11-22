# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Deep Dish is a full-stack restaurant queue and reservation management system (UniCEUB PI3 project). It is a monorepo with two independent apps:

- `deep-dish-frontend/` — React 18 + TypeScript + Vite (port 8080)
- `deep-dish-backend/` — Laravel 12 + PostgreSQL + JWT Auth (port 8000)

## Commands

### Frontend (`deep-dish-frontend/`)

```bash
npm run dev          # Vite dev server on port 8080
npm run build        # Production build
npm run build:dev    # Dev build
npm test             # Vitest unit tests
npm run test:watch   # Watch mode
npm run lint         # ESLint
```

### Backend (`deep-dish-backend/`)

```bash
composer dev         # Concurrent: artisan serve + queue:listen + pail + vite
composer setup       # Full setup: install, .env, key:generate, migrate, npm install, build
composer test        # PHPUnit tests
php artisan migrate
php artisan tinker
```

### Docker (root)

```bash
docker-compose up    # Spins up backend, queue worker, and frontend
```

## Architecture

### Frontend

**Layouts** — Three layout wrappers in `src/components/layouts/`:
- `PublicLayout.tsx` — Landing and all auth pages
- `AppLayout.tsx` — Client-facing pages (restaurants, queue, reservations)
- `AdminLayout.tsx` — Restaurant admin panel (dashboard, tables, staff, etc.)

**Routing** (`src/App.tsx`):
- Public: `/`, `/login`, `/register`, `/restaurant/login`, `/restaurant/register`, etc.
- App (client): `/app/*` — protected by `ProtectedRoute` with role `cliente`
- Admin (restaurant): `/restaurant/*` — protected by `ProtectedRoute` with role `restaurante`

**Auth flow** (`src/contexts/AuthContext.tsx`):
- JWT stored in `localStorage` under key `jwt`; user type stored as `tipo_usuario` (`cliente` | `restaurante`)
- On 401, `httpClient.ts` auto-calls `/cliente/refresh` or `/restaurante/refresh` and retries
- On 403 with `email_not_verified`, redirects to `/verify-email`

**API layer** (`src/services/`):
- `httpClient.ts` — fetch wrapper that injects Bearer token, handles token refresh, and translates backend validation errors to Portuguese
- Each domain has a service file: `auth.service.ts`, `restaurants.service.ts`, `mesas.service.ts`, `reservations.service.ts`, `queue.service.ts`, `dashboard.service.ts`, `staff.service.ts`
- Base URL from `VITE_API_URL` env var (default: `http://localhost:8000/api`)

**State management**:
- React Query (TanStack) for server state
- `AuthContext` for global auth state
- React Hook Form + Zod for form validation

### Backend

**Dual JWT authentication** — two independent guards:
- `api` guard → `Cliente` model, token issued at `POST /cliente/login`
- `restaurante` guard → `Restaurante` model, token issued at `POST /restaurante/login`

**Middleware stack** on protected routes:
1. `auth:api` / `auth:restaurante` — validates Bearer token
2. `VerifyJwtTokenVersion` — checks token's `token_version` claim against DB (logout increments version, invalidating all existing tokens without a blacklist table)
3. `EnsureEmailIsVerified` — blocks unverified users with 403 + `email_not_verified`
4. `throttle:3,1` — rate limiting

**Reservation state machine** (`ReservaController.php`):
- `confirmada` → `em_andamento` (check-in by restaurant)
- `em_andamento` → `liberada` (manual release by restaurant)
- Auto-expiration: `confirmada` expires after 60 min no-show; `em_andamento` expires 60 min after restaurant closing time

**Table statuses** (`Mesa` model): `livre` | `reservada` | `ocupada` | `bloqueada`

**Queue**: One `Fila` per restaurant per time slot. `ClienteFila` entries track party size; position is calculated on-the-fly (no stored `posicao` column).
- Position is the rank by arrival across **all open `Fila`s of the restaurant** (`ClienteFila::scopeAguardandoNoRestaurante` + `scopeEmOrdemDeChegada`), the same order `FilaService::entradasAguardando` uses to call people.
- Table allocation (`FilaService::escolherAlocacoes`, #170): FCFS with a controlled "encaixe" — a group is passed over only if the table would waste more than `fila.desperdicio_maximo_lugares` seats and a later group fits it better; entries waiting ≥ `fila.espera_protegida_minutos` are protected (served first, never overtaken). Config in `config/fila.php`.
- Queue QR (#174): `GET /api/restaurante/fila/qr` returns a permanent signed URL (`fila.qr` route); scanning redirects to the frontend `/app/restaurants/{id}/entrar-na-fila`, and the unauthenticated destination is kept via `lib/redirectAposLogin`.

**All primary keys are UUIDs.**

### Key Environment Variables

**Backend** (`.env`):
- `JWT_SECRET`, `FRONTEND_URL`, `DB_*` (PostgreSQL), `MAIL_*`

**Frontend** (`.env`):
- `VITE_API_URL`

## Contributing Workflow

From `Deep_Dish_Contributing_Guide.md`:
- Branch naming: `feat/`, `fix/`, `chore/`, `hotfix/`
- Conventional commits (`feat:`, `fix:`, `chore:`, etc.)
- PRs to `develop` branch require Tech Lead review; `develop` → `main` is gated
