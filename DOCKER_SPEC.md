# Dockerization Specification — Laravel Inventory System

You are a Senior DevOps Engineer and Senior Laravel Deployment Engineer.

I have an existing Laravel application that is already complete, tested, and working correctly.

Your task is to Dockerize the EXISTING application for reliable, production-style local use on a single Windows PC by a non-technical end user.

Reliability, data safety, simplicity, and recoverability are more important than sophisticated infrastructure.

---

# 1. Existing application architecture

This is:

- ONE Laravel application.
- ONE login.
- ONE shared database.
- ONE deployment.
- ONE URL.

After login, the user chooses between two internal modules:

1. Salami Inventory
2. Flower Inventory

They are NOT separate applications.

Do NOT split them.

Do NOT create separate databases.

Do NOT expose separate ports for the two modules.

The final application should be available through one URL such as:

```text
http://localhost:8080
```

The existing login, module selector, Salami workflows, and Flower workflows must continue behaving exactly as they do now.

---

# 2. Important project files

Before doing anything, read:

```text
AGENTS.md
PROJECT_SPEC.md
README.md
composer.json
composer.lock
package.json
package-lock.json
.env.example
config/app.php
config/database.php
routes/web.php
bootstrap/app.php
```

Also inspect:

- migrations
- models
- services
- authentication configuration
- queue configuration
- scheduler configuration
- storage usage
- Vite configuration
- existing production-readiness commands
- current `.gitignore`

These files describe an already-completed V1 application.

Dockerization must NOT reinterpret or redesign the business domain.

---

# 3. Known current development baseline

The known current project baseline is approximately:

```text
Laravel 13.26.1
PHP 8.4.12
Livewire 4.4.1
Tailwind CSS 4.3.3
Vite 8.2.2
MySQL
Brick\Math ~0.18.0
```

The repository itself remains authoritative.

If actual repository versions differ, report the difference.

Do NOT downgrade Laravel, PHP, Livewire, Node, MySQL, or other dependencies merely to simplify Docker.

---

# 4. User/environment assumptions

Initial deployment:

```text
One Windows PC
One real user
Docker Desktop
Local-only application
```

The final user is non-technical.

The user should NOT need to install or understand:

- PHP
- Composer
- Node.js
- npm
- MySQL Server
- XAMPP
- Apache separately
- Laravel commands

The final user should essentially interact with:

```text
start.bat
stop.bat
backup.bat
```

Docker Desktop may still be required.

---

# 5. CRITICAL SAFETY RULES

Before changing anything:

1. Inspect the entire repository.
2. Understand current versions and dependencies.
3. Understand the current database configuration.
4. Understand whether existing database data already exists.
5. Understand whether Laravel stores persistent uploaded files.
6. Understand whether queue/scheduler services are actually used.

Do NOT:

```text
php artisan migrate:fresh
php artisan migrate:reset
php artisan db:wipe
DROP DATABASE
DROP TABLE
docker compose down -v
```

Never use destructive SQL against the existing database.

Existing data must always be treated as valuable production data.

Do NOT delete or overwrite the current `.env`.

Do NOT commit secrets.

Do NOT generate a replacement application key for an existing installation.

Do NOT modify business logic unless absolutely necessary for Docker compatibility.

---

# 6. APP_KEY SAFETY

Preserve the existing Laravel:

```text
APP_KEY
```

If any existing data uses:

- encrypted casts
- Crypt facade
- encrypted cookies
- Laravel encryption

changing the key may make that data unreadable.

Therefore:

- never regenerate APP_KEY for an existing installation
- document how to back it up
- preserve it during PC migration
- never commit it to Git

For a truly fresh installation only, generating a new key is acceptable.

---

# 7. FIRST RESPONSE BEFORE IMPLEMENTATION

Before modifying files, provide a concise audit containing:

1. Laravel/PHP/Node/Composer/database versions detected.
2. Required PHP extensions.
3. Existing database configuration.
4. Whether queue workers are actually required.
5. Whether scheduler is actually required.
6. Whether persistent storage uploads exist.
7. Recommended Docker architecture.
8. Files you plan to create or change.
9. Risks related to existing data.
10. Any blocking issue.

Then continue with Docker implementation unless there is a genuine safety blocker.

DO NOT import the real existing populated database automatically.

---

# 8. Target Docker architecture

Prefer the simplest reliable architecture for this deployment.

Recommended:

```text
Windows PC
    |
Docker Desktop
    |
docker compose
    |
    +-- app
    |    +-- Apache
    |    +-- PHP 8.4
    |    +-- Laravel application
    |
    +-- db
         +-- MySQL
         +-- persistent named volume
```

For this one-PC deployment, prefer:

```text
Apache + PHP
```

inside a single application container unless repository inspection identifies a real incompatibility.

The priority is:

- fewer containers
- fewer failure points
- easy startup
- easy troubleshooting
- reliable local deployment

Do NOT add Nginx + PHP-FPM merely because it is common.

If Apache is appropriate, use it.

Explain the final choice briefly.

---

# 9. NOT A DEVELOPMENT DOCKER SETUP

This is critical.

The final client installation is NOT a development Docker environment.

Do NOT bind-mount the entire Laravel source directory into the application container using something like:

```yaml
volumes:
  - .:/var/www/html
```

for the final client deployment.

Application code must be baked into the Docker image.

Frontend production assets must also be baked into the image.

The client installation must not require the source tree to behave like a live development mount.

Use persistent volumes/bind mounts only for runtime data that genuinely needs persistence.

---

# 10. Docker files

Create a clean deployment setup including:

```text
Dockerfile
docker-compose.yml
.dockerignore
.env.docker.example
```

and if required:

```text
docker/
    apache/
    entrypoint/
    scripts/
```

Also create any required:

- Apache configuration
- startup/entrypoint script
- healthcheck support

Keep files organized and documented.

---

# 11. Docker secrets/environment configuration

Do NOT hard-code secrets inside:

```text
docker-compose.yml
Dockerfile
*.bat
*.ps1
```

Do not commit:

```text
APP_KEY
MYSQL_ROOT_PASSWORD
MYSQL_PASSWORD
production credentials
```

Use an untracked environment file, for example:

```text
.env.docker
```

and a committed template:

```text
.env.docker.example
```

The example contains placeholders only.

Verify `.env.docker` is ignored by Git.

---

# 12. Application database credentials

Do not use MySQL root as the normal Laravel application user.

Use something conceptually like:

```text
Database:
inventory_system

Application user:
inventory_app

Strong password:
generated/configured locally
```

Root credentials may exist for MySQL container administration but must not be Laravel's normal connection credentials.

Inside Docker:

```text
DB_HOST=db
DB_PORT=3306
```

---

# 13. Dockerfile

Build a production-style application image.

Requirements:

- PHP version compatible with the repository.
- Apache if selected.
- Required PHP extensions.
- Composer.
- correct Laravel public document root.
- writable Laravel runtime directories.
- production frontend assets.

Use multi-stage builds where useful.

A reasonable build concept:

```text
Node build stage
    ↓
npm ci
npm run build

Composer/PHP build stage
    ↓
composer install --no-dev --optimize-autoloader

Runtime
    ↓
PHP + Apache
Laravel source
vendor/
public/build/
```

Do not require Node.js inside the final runtime image unless actually required.

---

# 14. Frontend/Vite

The application uses Vite.

During Docker image build:

```text
npm ci
npm run build
```

must produce production assets.

Final runtime:

- must NOT run `npm run dev`
- must NOT run Vite dev server
- must NOT require Node installed on Windows

Verify Laravel's `@vite` setup works using built assets.

---

# 15. Laravel runtime server

Do not use:

```text
php artisan serve
```

for the client deployment.

Use the chosen web server such as Apache.

Configure Laravel's `/public` directory correctly as the web root.

---

# 16. Database container

Use ONE MySQL database service.

Use a named persistent Docker volume.

Example concept:

```text
inventory_mysql_data
```

Persistence requirements:

These must NOT delete the database:

```text
docker compose stop
docker compose start
docker compose restart
docker compose down
docker compose up -d
docker compose build
```

Only explicit volume deletion should remove the database.

Never include volume deletion in normal client scripts.

---

# 17. Database exposure

Do not expose MySQL to the host/LAN unless there is an actual reason.

Prefer Docker internal networking only:

```text
app → db:3306
```

If developer access is required, document how to enable it intentionally instead of exposing it by default.

---

# 18. Database healthcheck

Add a reliable MySQL healthcheck.

The application must not blindly perform DB-dependent startup operations before MySQL is ready.

Use Compose health dependencies and/or a robust wait mechanism where appropriate.

---

# 19. Application healthcheck

Add application health verification too.

Prefer an existing Laravel health route such as:

```text
/up
```

if available and suitable.

The health endpoint must not require business authentication.

The final startup script must verify HTTP health before opening the browser.

---

# 20. Existing populated database — VERY IMPORTANT

The current application may already use a populated database outside Docker.

DO NOT automatically migrate/import the real populated database during initial Docker implementation.

First:

1. detect the current engine and version
2. inspect `.env`
3. inspect migration status
4. identify the database name
5. identify safe export tooling
6. document the migration procedure
7. prepare backup/import scripts
8. test the mechanism against a disposable database where possible

STOP before importing the REAL populated database.

Ask for explicit approval before the real database import.

Never destroy or modify the original database.

---

# 21. Existing database migration plan

Prepare a safe procedure:

```text
Original MySQL
      ↓
mysqldump backup
      ↓
verify backup
      ↓
Docker MySQL
      ↓
import
      ↓
verify table counts
      ↓
verify Laravel connection
      ↓
verify migration state
      ↓
verify application
```

Do not blindly run migrations against an existing imported database before inspecting migration state.

---

# 22. Backup verification before real import

Before a real database migration:

- create a dump
- verify command exit code
- verify file exists
- verify file is non-empty
- inspect basic SQL structure
- record important row counts where practical

Examples:

```text
users
salami_products
flower_products
stock_movements
salami_invoices
flower_invoices
```

Use actual existing tables.

Never report backup success only because a file exists.

---

# 23. MySQL backup consistency

For InnoDB/MySQL backups, use an appropriate consistency method such as:

```text
--single-transaction
```

where compatible.

Preserve:

- utf8mb4
- Arabic text
- schema
- data
- foreign keys

Capture stderr and command exit code.

---

# 24. Persistent application files

Inspect whether the application stores persistent runtime/user files under:

```text
storage/app
public/storage
```

If the current application does NOT use persistent uploads:

do not add unnecessary storage volumes.

If it DOES:

persist only the required storage directories so rebuilding the image does not lose user files.

Do not persist:

```text
vendor
node_modules
application source
public/build
```

unless there is a concrete reason.

---

# 25. Storage permissions

Ensure Laravel can write to:

```text
storage/
bootstrap/cache/
```

Use sensible ownership and permissions.

Do not solve permissions using broad unsafe:

```text
chmod -R 777
```

unless absolutely unavoidable and explicitly justified.

---

# 26. Laravel caches

Support production caches:

```text
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The application already supports route/view/config caching.

Do not rebuild caches using stale environment variables.

Ensure startup/update sequencing is correct.

---

# 27. Public storage link

Inspect whether:

```text
public/storage
```

is actually required.

If required, create it safely/idempotently.

If the application does not use public file uploads, do not create unnecessary complexity.

---

# 28. Queue workers

Inspect whether Laravel queues are genuinely used.

If no queue-backed functionality exists:

do NOT create a worker service.

If queues are used:

add a dedicated worker service using the same application image.

Keep configuration minimal.

---

# 29. Scheduler

Inspect:

```text
routes/console.php
app/Console
scheduled tasks
```

If no scheduler tasks exist:

do NOT add a scheduler service.

If tasks exist:

implement scheduling cleanly and document it.

---

# 30. Windows deployment experience

The final user should not need Docker commands.

Create Windows-friendly scripts in a clear location, for example:

```text
deployment/
```

or project root if simpler.

At minimum:

```text
install.bat
start.bat
stop.bat
restart.bat
backup.bat
restore.bat
logs.bat
```

PowerShell may be used underneath if it provides better reliability.

---

# 31. start.bat

`start.bat` must be safe to run repeatedly.

Workflow:

1. detect Docker CLI/Desktop installation
2. check whether Docker Engine is running
3. if Docker Desktop is installed but not running, attempt to start it
4. wait for Docker Engine readiness
5. run:

```text
docker compose up -d
```

6. wait for database health
7. wait for application health
8. open:

```text
http://localhost:8080
```

9. show a clear success message

If an error occurs:

- show a useful Arabic/simple English error
- do not close instantly
- pause so the developer/user can read the message

Running `start.bat` twice must not break anything.

---

# 32. stop.bat

Use a non-destructive stop:

```text
docker compose stop
```

or equivalent.

Never remove volumes.

Never delete database files.

---

# 33. restart.bat

Safely restart application services.

Do not destroy data.

Wait for health after restart.

---

# 34. install.bat

First installation must NEVER guess the intended database mode.

It must clearly separate:

```text
1. Fresh installation

2. Existing database import
```

These flows must be separate.

---

# 35. Fresh installation

For a truly fresh empty installation:

Potential workflow:

```text
validate Docker
create environment config
build image
start database
wait for DB
run migrations
prepare Laravel
create first admin
start application
open browser
```

Do not seed development/demo credentials into the client installation.

Use the existing safe command:

```text
php artisan app:create-admin
```

for the first real administrator.

---

# 36. Existing-data installation

Existing data mode must NOT automatically execute database import.

It should:

- explain what is required
- require an existing backup
- verify backup
- optionally call a dedicated import workflow
- preserve original data
- never call fresh migrations destructively

---

# 37. Manual database backup

Create:

```text
backup.bat
```

and/or a PowerShell implementation.

Store backups on the Windows host outside the database container.

Suggested folder:

```text
backups/
```

Example:

```text
backups/inventory_2026-08-25_183000.sql
```

Backup requirements:

1. entire application database
2. timestamped filename
3. UTF-8/Arabic-safe
4. check dump exit code
5. verify resulting file exists
6. verify file is non-empty
7. print exact backup path
8. do not expose secrets in output

Do not hard-code database password repeatedly across scripts.

Reuse environment configuration safely.

---

# 38. Restore workflow

Create:

```text
restore.bat
```

or a reliable PowerShell equivalent.

Restore must be intentionally harder to trigger accidentally than backup.

Requirements:

1. list available backup files or allow selecting one
2. show the selected backup clearly
3. require explicit confirmation
4. automatically create a safety backup of the CURRENT database first
5. verify that safety backup succeeded
6. restore selected SQL
7. detect import errors
8. verify Laravel database connection
9. verify migration state
10. verify important table counts
11. verify application health
12. report success/failure clearly

Never silently overwrite the database.

---

# 39. Restore safety

If the automatic safety backup before restore fails:

STOP.

Do NOT continue with restore.

---

# 40. Automatic backups

Create optional:

```text
install-daily-backup-task.ps1
```

It may configure Windows Task Scheduler to run backups daily.

This must be optional.

Document how to enable/disable it.

Suggested retention:

```text
30 daily backups
```

Make retention configurable.

Never delete old backups if creation of the newest backup failed.

---

# 41. Backup location

Backups must live outside the database container.

They should remain available even if containers are recreated.

Prefer a host directory:

```text
backups/
```

that can easily be copied to USB/external storage.

Document that off-PC copies are recommended.

---

# 42. Update workflow

Create:

```text
update.bat
```

but keep it conservative.

Before updating:

1. check Git/project state
2. create database backup
3. verify backup succeeded
4. inspect pending migrations
5. rebuild image
6. run only safe pending migrations
7. restart application
8. rebuild caches
9. perform healthcheck

Never use destructive migrations automatically.

Never run:

```text
git reset --hard
```

against unknown local changes.

Do not blindly use `git pull`.

If the final client package will not use a Git remote, document the actual supported update mechanism instead.

---

# 43. Migration updates

Before update migrations:

Show pending migration names where practical.

Backup must be completed FIRST.

Then use only normal safe:

```text
php artisan migrate --force
```

Never:

```text
migrate:fresh
migrate:reset
db:wipe
```

---

# 44. logs.bat

Create developer troubleshooting support.

`logs.bat` should help inspect:

- docker compose ps
- application logs
- app container logs
- database logs/status

Do not dump secrets.

This is mainly for the developer, not the client.

---

# 45. Application production environment

Final client environment should use:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8080
```

Timezone:

```text
APP_TIMEZONE=Africa/Tripoli
```

unless configuration/environment requires another value.

Use correct Laravel locale settings already present in the project.

---

# 46. Security

Even though the app is local:

- do not expose unnecessary ports
- do not expose MySQL to LAN
- use strong DB credentials
- preserve APP_KEY
- disable Laravel debug
- do not commit secrets
- keep normal Laravel CSRF/session protections
- do not add unnecessary remote services

Bind the HTTP service appropriately for local access.

If binding specifically to localhost is practical and compatible with Docker Desktop, prefer avoiding unnecessary LAN exposure.

---

# 47. Docker Desktop Windows requirements

Document:

- supported Windows version
- Docker Desktop requirement
- virtualization requirement
- WSL2/backend expectations where applicable
- how to verify Docker is running

The client should not need to understand Docker internals.

---

# 48. Application URL

Final application URL should be:

```text
http://localhost:8080
```

unless port 8080 is unavailable.

If unavailable, use a clearly configurable host port.

Do NOT create separate URLs for Salami and Flowers.

---

# 49. Data durability verification

Explicitly verify that database data survives:

```text
docker compose stop
docker compose start
docker compose restart
docker compose down
docker compose up -d
docker compose build
```

Do NOT test `down -v` against real data.

Document:

```text
docker compose down -v
```

as destructive.

Do not put it in normal scripts.

---

# 50. Verification — Docker

Do not declare Dockerization complete merely because files were created.

Actually verify as much as the environment permits.

At minimum:

1. `docker compose config`
2. build application image
3. start services
4. inspect `docker compose ps`
5. database health succeeds
6. application health succeeds
7. Laravel returns HTTP success
8. login page loads
9. application connects to MySQL
10. migration status can be read safely
11. data survives container restart
12. production frontend assets exist
13. Laravel logs contain no startup errors

---

# 51. Verification — backups

Create a test backup against a disposable/safe Docker database.

Verify:

- file exists
- non-zero size
- SQL structure appears valid
- Arabic/UTF-8 data survives where test data exists

Test restore only using:

- a disposable database
- or dedicated temporary Docker database

Never test destructive restore against the real database.

---

# 52. Verification — Laravel

Run existing application validation.

At minimum:

```text
php artisan about
php artisan route:list --except-vendor
php artisan migrate:status
php artisan test
```

Run project's opt-in MySQL integration tests where practical.

Dockerization must not break the completed V1 behavior.

---

# 53. Verification — Laravel caches

Inside the final Docker environment verify:

```text
php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

All must succeed.

---

# 54. Verification — frontend

Verify:

```text
npm ci
npm run build
```

during image build.

Verify runtime does not depend on Vite dev server.

---

# 55. Client documentation

Create:

```text
CLIENT-INSTRUCTIONS-AR.md
```

Very simple Arabic documentation.

The client should mainly learn:

## تشغيل النظام

Double-click:

```text
start.bat
```

## إيقاف النظام

Double-click:

```text
stop.bat
```

## أخذ نسخة احتياطية

Double-click:

```text
backup.bat
```

Explain where backups are stored.

Do NOT overwhelm the client with Docker terminology.

---

# 56. Developer Docker documentation

Create:

```text
DOCKER-LOCAL-DEPLOYMENT.md
```

For a developer who has never used Docker.

Include:

1. architecture
2. why Docker is used
3. Windows requirements
4. project structure
5. Docker services
6. environment files
7. building images
8. first fresh installation
9. existing DB import
10. starting
11. stopping
12. restarting
13. database persistence
14. backup
15. restore
16. automatic backups
17. updates
18. moving to another PC
19. preserving APP_KEY
20. recovering after Windows/Docker failure
21. Docker troubleshooting
22. useful developer commands
23. what must never be deleted
24. destructive removal procedure clearly marked DANGER
25. backup validation
26. production admin creation
27. application health checks

---

# 57. Moving to another PC

Document a safe migration process.

Must include preserving:

```text
application package/images
database backup
APP_KEY
environment configuration
persistent uploaded files if applicable
```

Recommended process:

```text
backup old PC
↓
copy deployment package
↓
install Docker Desktop new PC
↓
configure same APP_KEY
↓
start clean Docker DB
↓
restore backup
↓
verify app
```

Never regenerate APP_KEY during machine migration.

---

# 58. Disaster recovery documentation

Document what to do if:

- Docker Desktop stops working
- Windows restarts unexpectedly
- app container fails
- database container fails
- Docker containers disappear but volume remains
- PC must be replaced
- database restore is required

Keep client documentation simple; detailed recovery belongs in developer docs.

---

# 59. Files/folders that must never be deleted accidentally

Clearly document:

- database Docker volume
- backups/
- `.env.docker`
- preserved APP_KEY
- persistent Laravel storage volume/folder if used

Do not confuse application image rebuilds with data deletion.

---

# 60. Final deployment simplicity

Prefer:

```text
2 services
```

if possible:

```text
app
db
```

Do not add:

- Redis
- queue worker
- scheduler
- nginx
- phpMyAdmin
- Mailpit
- monitoring
- admin database GUI

unless the existing application genuinely requires them.

---

# 61. Docker implementation phases

Do NOT attempt everything as one uncontrolled change.

Use these phases.

## Docker Phase 1 — Core Docker runtime

Implement and verify:

- Dockerfile
- Compose
- Apache/PHP
- MySQL
- environment template
- Docker ignore
- healthchecks
- production Vite build
- application boot
- database connectivity
- persistent DB volume

Do NOT import the real existing database.

Stop and report.

## Docker Phase 2 — Existing-data migration tooling

Implement:

- backup/export tooling
- import tooling
- disposable restore test
- database verification
- real migration plan

STOP before importing the real populated database.

Ask for explicit approval.

## Docker Phase 3 — Windows client scripts

Implement and test:

```text
install.bat
start.bat
stop.bat
restart.bat
backup.bat
restore.bat
logs.bat
```

## Docker Phase 4 — Operations and documentation

Implement:

- automatic backup task
- retention
- update workflow
- moving-PC documentation
- recovery documentation
- client instructions
- full final verification

Do not merge these phases into one giant uncontrolled implementation.

---

# 62. Important real database approval gate

Even if Docker Phase 1 and Phase 2 are successful:

DO NOT import the real populated database.

Before real import, report:

1. source database identified
2. source DB version
3. target DB version
4. backup command
5. backup path
6. backup size
7. table counts
8. expected import command
9. rollback/recovery strategy
10. whether APP_KEY is safely preserved

Then stop.

Wait for my explicit approval.

---

# 63. Git safety

Inspect Git status before changes.

Do not destroy local modifications.

Do not expose secrets.

Recommend a commit checkpoint before Dockerization if the working tree is not clean.

Do not commit generated database backups.

Ensure:

```text
backups/
.env.docker
```

or sensitive equivalents are handled appropriately in `.gitignore`.

---

# 64. Do not change completed application behavior

Dockerization must not modify:

- Salami stock logic
- Flower stock logic
- invoice behavior
- receiving behavior
- stock ledger
- authorization rules
- database domain schema unnecessarily
- reports
- Livewire workflows
- application UI

Only change application code when genuinely required for deployment compatibility.

Any such change must be explained.

---

# 65. Final expected client experience

After installation:

The user turns on the Windows PC.

They double-click:

```text
start.bat
```

Docker Desktop starts if necessary.

Containers start.

The script waits.

Browser opens:

```text
http://localhost:8080
```

The user logs in.

They choose:

```text
Salami
```

or:

```text
Flowers
```

They use the system normally.

When finished they may use:

```text
stop.bat
```

For safety they may use:

```text
backup.bat
```

The user should not need to know anything else about Docker.

---

# 66. Final completion report

At the end of EACH Docker phase, report:

1. what was implemented
2. files created
3. files modified
4. commands run
5. verification results
6. unresolved issue
7. risk to existing data
8. whether real database was touched
9. exact next step

At the final completion report include:

1. final architecture
2. Docker services
3. persistence strategy
4. backup strategy
5. restore strategy
6. Windows startup process
7. update process
8. first installation process
9. existing DB import process
10. application URL
11. client instructions
12. developer instructions
13. verification results
14. unresolved risks
15. exact personal steps I must perform

Do not merely say:

```text
Dockerization completed.
```

I need a deployment that can realistically be handed to a non-technical Windows user.

Prefer reliability, recoverability, and simplicity over clever infrastructure.