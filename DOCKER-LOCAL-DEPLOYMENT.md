# Local Docker deployment

## Architecture and security

The deployment has exactly two services: Apache/PHP Laravel (`app`) and MySQL 8.4 (`db`). Apache serves Laravel's `public` directory; the image builds Vite production assets. MySQL is internal only; HTTP is bound to `127.0.0.1:8080`. Production values are `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=http://localhost:8080`, `APP_TIMEZONE=Africa/Tripoli`, `utf8mb4`, and `utf8mb4_0900_ai_ci`.

`.env.docker` and `backups/` are Git-ignored. Preserve `APP_KEY`; changing it can make existing encrypted/session data unreadable. Do not print or commit credentials.

## Volumes

`inventory_mysql_data` is the intended final client database volume and is currently empty. It must not be populated until explicit Final Cutover approval. Preserved verification volumes are `inventory_phase1_verification_mysql_data`, `inventory_phase2_restore_test_mysql_data`, and `inventory_phase3_verification_mysql_data`. No script deletes volumes.

`docker compose down -v`, `migrate:fresh`, `migrate:reset`, and `db:wipe` are destructive and are never normal operational commands.

## Windows requirements

Use supported Windows with virtualization/WSL2 available and Docker Desktop installed. Confirm `docker version` succeeds. Docker Desktop must have been started once by the user; normal `start.bat` attempts to start it and waits for the engine.

## Daily backup and retention

Run PowerShell: `./deployment/install-daily-backup-task.ps1` (default 20:00), optionally with `-Time 21:00 -RetentionCount 30`. It schedules the existing Docker backup workflow only for final `.env.docker` / `inventory_system`; it logs under `backups/logs/`. The scheduled task runs as the current interactive user, so that user must be logged in. Remove it with `./deployment/remove-daily-backup-task.ps1`.

Retention runs only after a verified successful backup. It manages only `inventory_system-YYYYMMDD-HHMMSS.sql`, retaining 30 by default; it never removes unrelated files or prunes after backup failure.

## Update

`update.bat` stops immediately on Git local changes. It backs up first, lists pending migrations, rebuilds only the app image, starts services, runs only normal `php artisan migrate --force` when pending migrations exist, rebuilds caches, and verifies `/up` and `/login`. It never pulls/reset/stashes automatically and never removes the MySQL volume. Use `update.bat --no-pause -DryRun` to check its safe path.

## Backup, restore, and troubleshooting

`backup.bat` creates and validates an UTF-8 logical dump outside Docker. `restore.bat` requires explicit confirmation, makes and validates a safety backup first, verifies DB connection/migrations, runs the ledger reconciler read-only, then checks health. `logs.bat` shows Compose, app, and DB logs. Container deletion does not delete the named volume; run `start.bat` to recreate containers.

## Fresh and existing data

`install.bat` offers Fresh or Existing modes. Fresh mode requires an empty schema and creates the first administrator through `app:create-admin`. Existing mode never imports automatically. The real `inventory_system` source must remain untouched until Final Cutover approval.

## Moving to another PC

On the old PC: stop writes, take/verify a backup, then copy the deployment package, `backups/`, `.env.docker` securely (including the same APP_KEY), and persistent uploads if introduced later. On the new PC: install Docker Desktop, copy those files, start a clean final Docker DB, restore the backup, verify migrations, run `StockLedgerReconciler`, `/up`, login, and read-only invoice/receipt checks. Never regenerate APP_KEY during the move.

## Recovery

If Docker Desktop fails, restart it and inspect `logs.bat`. If app/db fails, inspect health/logs before any change. If containers/image disappear, preserve the volume and run `start.bat`/rebuild; do not delete the volume. After an unexpected reboot, start Docker then `start.bat`. For restore, use `restore.bat`; for disk failure use a verified off-PC backup on a clean Docker DB.

## Final cutover checklist — prepare only

1. Stop old-system writes; create and verify the final source backup.
2. Preserve APP_KEY and `.env.docker`; verify `inventory_mysql_data` is the intended empty final volume.
3. Start final DB and restore the verified backup.
4. Compare source/target counts; verify 30 migrations, ledger reconciliation, `/up`, login, Salami and Flower dashboards, and read-only invoice/receipt lookup.
5. Keep the old host DB untouched for rollback; take a post-cutover Docker backup only after all checks pass.

## Rollback

If validation fails, stop only the Docker app, do not delete its volume, return users to the original host application/database, retain the final source backup and failed Docker volume for diagnosis, and do not attempt an automatic reverse migration.
