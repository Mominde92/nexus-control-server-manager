# Nexus Control — Enterprise Server Manager

A production-oriented platform for managing hosting accounts, internal servers, domains, providers, clients, projects, credentials, expiry dates, users, and access policies from one secure workspace.

Nexus Control is implemented as two fully separated applications: a Laravel REST API and a React single-page application.

## Highlights

- Premium responsive SaaS interface with light and dark themes
- Hosting and internal server inventory
- Domain registration and expiry tracking
- Provider, client, and project management
- Encrypted hosting, FTP, and registrar credentials
- Secure on-demand password reveal and clipboard copy
- Custom roles with module-level permission matrices
- Per-record access control for hostings and domains
- Expiry dashboard and recent activity timeline
- Search, sorting, pagination, filters, and Excel-compatible exports
- Full authentication, password recovery, audit logging, and soft deletion
- Docker, Nginx, queue, and production deployment configuration

## Technology stack

| Application | Technologies |
| --- | --- |
| API | PHP 8.3, Laravel 13, Sanctum, Eloquent, Spatie Permission, MySQL/PostgreSQL |
| UI | React 19, TypeScript, Vite, Tailwind CSS 4, shadcn/ui, TanStack Query, Axios, Framer Motion |
| Infrastructure | Docker Compose, Nginx, PHP-FPM, Laravel Queue |

## Architecture

```text
server-manager-api/               Laravel REST API
├── app/Contracts/                Service and repository interfaces
├── app/Http/Controllers/Api/     Thin HTTP controllers
├── app/Http/Requests/            Request validation
├── app/Http/Resources/           Stable API response shapes
├── app/Models/                   Eloquent models and reusable concerns
├── app/Policies/                 Module and record authorization
├── app/Repositories/             Persistence and query composition
├── app/Services/                 Business rules and transactions
└── database/migrations/          Indexed relational schema

server-manager-ui/                React SPA
├── src/components/common/        Tables, credentials, forms, actions
├── src/components/layout/        Sidebar and application shell
├── src/components/ui/            shadcn primitives
├── src/contexts/                 Authentication state
├── src/hooks/                    Reusable query hooks
├── src/pages/                    Feature and CRUD pages
├── src/services/                 Typed REST clients
└── src/types/                    API and domain types
```

The API follows a service-repository architecture:

```text
Route → Middleware → Form Request → Controller → Service → Repository → Eloquent
                                  ↓
                              API Resource
```

## Security model

- SPA authentication uses Laravel Sanctum with HTTP-only session cookies.
- CSRF protection is enabled for stateful browser requests.
- User passwords are one-way hashed and never returned by the API.
- Infrastructure credentials are encrypted at rest using Laravel encryption casts.
- Credentials are excluded from list and detail responses.
- Passwords are revealed only through an audited, permission-protected endpoint.
- Rate limiting protects authentication, exports, and credential reveal endpoints.
- Search, sorting, and filters use explicit allow-lists.
- Activity logs redact credential values.

### Authorization levels

Nexus Control combines two authorization layers:

1. **Module permissions** — view, create, update, delete, export, and credential access.
2. **Record access** — optional per-user rules for viewing, updating, deleting, or revealing credentials on an individual hosting or domain record.

When record rules exist, a user must have both the module permission and the corresponding record permission.

### Default roles

| Role | Access |
| --- | --- |
| Admin | Full platform, user, role, permission, and credential administration |
| Manager | Daily operational CRUD, exports, activity access, and credential reveal |
| Employee | Read and create access only; no update, delete, export, or password reveal |

Custom roles can be created from the Roles and Permissions screen.

## Core modules

### Hosting management

Stores server type, location, IP address, control panel, username, encrypted password, provider, plan, price, lifecycle dates, status, SSL information, and reminder settings. The table provides masked credentials, secure reveal, and copy controls.

### Domain management

Tracks domain type, registrar, linked hosting, registration and expiry dates, renewal settings, WHOIS privacy, encrypted credentials, project, client, and status.

### Dashboard

Displays hosting and domain totals, active assets, approaching expirations, and an audited recent-activity timeline.

### Roles and permissions

Provides a dedicated data table and separate create, view, edit, and delete pages. Permissions are grouped by module and synchronized through Spatie Permission.

## Local installation

### Requirements

- PHP 8.3 or newer
- Composer 2
- Node.js 20 or newer
- MySQL 8+ or PostgreSQL 14+

### API

```bash
cd server-manager-api
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database connection in `.env`, then run:

```bash
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Seeding creates the three roles, the full permission set, and an initial
administrator:

| Email | Password |
| --- | --- |
| `admin@servermanager.test` | `password` |

**Change this password immediately after the first sign-in.** It exists only to
bootstrap local development.

Never commit the generated `.env` or publish production credentials.

### UI

```bash
cd server-manager-ui
npm install
npm run dev -- --host 0.0.0.0 --port 5173
```

Open [http://localhost:5173](http://localhost:5173).

During local development, Vite proxies `/api` and `/sanctum` to Laravel on port `8000`.

## Quality checks

```bash
# API
cd server-manager-api
php artisan test
php vendor/bin/pint

# UI
cd server-manager-ui
npm run build
npm run lint
```

## Docker

Copy the environment examples, configure secrets, and run:

```bash
docker compose up -d --build
docker compose exec api php artisan migrate --seed --force
```

See [Deployment Guide](docs/DEPLOYMENT.md) for the production checklist, HTTPS, queue workers, scheduler, and backup guidance.

## API response format

All JSON endpoints use a consistent envelope:

```json
{
  "success": true,
  "data": {},
  "message": "Operation completed successfully."
}
```

Paginated responses also include `meta` with the current page, page size, total records, and last page.

## Environment safety

The repository intentionally excludes:

- `.env` files and application keys
- database dumps and local SQLite databases
- logs, caches, sessions, and generated framework files
- `vendor`, `node_modules`, and production build artifacts
- editor and operating-system metadata

Before publishing, verify that no real password, token, application key, database credential, or private certificate has been staged.

## License

Proprietary internal software. Add an explicit license before distributing the project outside your organization.
