# Azana Farms ERP: Production Deployment Preparation for Hostinger VPS

## Project Context

The Azana Farms ERP and public website have been completed using Laravel, Livewire and Filament. The Expo React Native mobile application is still under development.

The production domain is `azanafarms.com`.

The application will be deployed to a Hostinger VPS. The existing domain currently uses Syskay cPanel hosting, and the company actively uses its email accounts on that service. Email continuity is mandatory.

The planned hostnames are:

- `azanafarms.com`: Public company website
- `www.azanafarms.com`: Public website alias
- `erp.azanafarms.com`: Internal Filament ERP
- `api.azanafarms.com`: Laravel API for the Expo mobile application

The intended architecture is a modular Laravel monolith with shared business logic and a central database. Do not create separate Laravel applications or databases unless the existing implementation and documented architecture explicitly require them.

The mobile application relies on authenticated API requests and offline mutation synchronisation. The production API must preserve the existing documented API contracts.

## Your Role

Act as a senior Laravel DevOps engineer and production-readiness reviewer.

Prepare the EXISTING application for secure deployment to a Hostinger VPS.

This task is not permission to redesign the ERP, rewrite completed modules, replace the application architecture or perform a live deployment.

## Stage 1: Read Project Documentation

Before modifying anything, inspect the repository and read the relevant documentation, including:

- `CLAUDE.md`
- `docs/ERP_MASTER_PLAN.md`
- `docs/ARCHITECTURE.md`
- `docs/DATABASE_ARCHITECTURE.md`
- `docs/DOMAIN_RULES.md`
- `docs/SECURITY.md`
- `docs/OFFLINE_SYNC.md`
- `docs/IMPLEMENTATION_STATUS.md`
- Existing deployment, environment, API and operational documentation
- The current phase documents relevant to deployment and production hardening

Some paths may not exist. Discover the actual repository structure instead of assuming every path exists.

Use the documentation and the current implementation as the source of truth. Do not silently invent missing infrastructure requirements or business rules.

## Stage 2: Audit the Existing Application

Inspect the codebase before proposing changes.

Identify:

1. Laravel and PHP versions required by `composer.json` and `composer.lock`.
2. Required PHP extensions.
3. Database engine and version assumptions.
4. Composer dependencies and frontend build requirements.
5. Vite configuration and generated production assets.
6. File uploads, public storage, private documents and symbolic links.
7. Email configuration and notification delivery.
8. Scheduled commands and Laravel scheduler requirements.
9. Queued jobs, queue drivers, failed jobs and retry policies.
10. Cache, sessions, locks and any Redis dependencies.
11. Database migrations, seeders and production-data assumptions.
12. API authentication, rate limits, device tokens and offline synchronisation.
13. Logging, exception reporting and health checks.
14. External integrations and required environment variables.
15. Any hardcoded development URLs, local filesystem paths, credentials, debug settings or development-only dependencies.
16. Any application feature that requires a persistent worker, WebSocket service or other long-running process.

Report the findings before making potentially disruptive changes.

Classify issues as:
- Critical: blocks a safe production deployment.
- High: should be resolved before launch.
- Medium: should be addressed before or shortly after launch.
- Low: optional improvement.

## Stage 3: Define the Deployment Architecture

Prepare a deployment design for a Hostinger VPS running a supported Ubuntu LTS release.

Recommend a suitable web server, PHP-FPM version, database engine, Composer version, Node.js build environment, queue driver, cache/session driver and process supervisor based on the actual project dependencies.

The design must include:

- Nginx or another justified production web server.
- PHP-FPM and all required PHP extensions.
- MySQL or the database engine supported by the existing project.
- Composer dependency installation using the committed lock file.
- Production frontend asset compilation.
- HTTPS for all public hostnames.
- Laravel scheduler configured through cron.
- Persistent queue workers managed by a process supervisor when the project needs them.
- Database and file backup procedures.
- Log rotation and disk-space monitoring.
- Firewall configuration and secure SSH access.
- A documented recovery and rollback procedure.

Do not install every possible service. Only include services required by the current application or justified by measured operational needs.

Do not assume that a Hostinger operating-system template will be compatible with the project without inspecting what it installs. Do not reinstall or reset an already configured VPS without explicit approval.

## Stage 4: Prepare Production Configuration

Inspect the existing `.env.example` and configuration files.

Prepare a production environment-variable checklist covering at least:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` and any application-specific URL settings
- Database connection settings
- Session and cache configuration
- Queue configuration
- Mail transport and credentials
- Filesystem disks and private storage
- API authentication and token settings
- Any external services used by the project

Determine the correct application URL for the main site and whether the ERP and API need explicit host configuration.

Never commit production `.env` files, database passwords, API secrets, private keys or real credentials.

Do not generate a new `APP_KEY` for an existing production database without a documented migration plan. Changing an existing key can invalidate encrypted data and sessions.

Preserve the current application's authentication, authorisation, CSRF protections, API rate limits and business rules.

## Stage 5: Review Database and Migration Safety

Inspect every relevant migration and seeder.

Prepare a migration procedure that:

1. Takes a verified database backup.
2. Checks the current database schema and migration status.
3. Runs pending migrations using the appropriate production command.
4. Avoids running development seeders against production.
5. Does not truncate, reset, drop or recreate production tables.
6. Includes verification and recovery steps.

Do not execute migrations against a live database during this preparation task.

Ensure that animal records, inventory ledgers, finance records, audit trails, movements, production records and traceability relationships are preserved.

## Stage 6: Review Background Processing

Identify every scheduled command, queued job and notification process.

Prepare the appropriate configuration for:

- Laravel scheduler.
- Queue workers and automatic restart.
- Failed-job inspection and recovery.
- Queue retry and timeout settings.
- Long-running reports or imports.
- Email delivery and operational alerts.

Do not assume the application requires Redis. If a database-backed queue is sufficient, document that option and its limitations. If Redis is necessary, explain why.

Ensure that queue processing cannot duplicate important inventory, finance or offline-sync mutations.

## Stage 7: Security and File Storage

Verify:

- Only Laravel's `public` directory is exposed as the web document root.
- `.env`, source code, private documents, logs and backups cannot be downloaded publicly.
- Storage and cache directories have the minimum necessary permissions.
- Upload validation and file access controls are preserved.
- Administrative and API authentication remain enforced.
- Sensitive operations remain protected by the existing permission system.
- Debug output and stack traces are not exposed to public users.
- HTTPS and secure-cookie settings are configured correctly.
- Backups are stored outside publicly accessible directories and protected against unauthorised access.

Do not make the entire application directory world-writable.

## Stage 8: DNS and Existing Email Protection

The current Syskay email service must continue working.

Produce a DNS cutover checklist that distinguishes website records from email records.

The deployment must not require changing nameservers unless there is a separately approved reason.

Preserve and verify the existing:

- MX records.
- SPF policy.
- DKIM records.
- DMARC policy.
- Mail-related hostnames and any other required mail records.

Prepare the required DNS records for the website, ERP and API, using placeholders for the VPS IP address until it is known.

Do not invent IP addresses or actual DNS values.

## Stage 9: Deployment Files and Documentation

After the audit, prepare the deployment artifacts appropriate to the existing project.

Possible artifacts include:

- `docs/deployment/PRODUCTION_READINESS_AUDIT.md`
- `docs/deployment/HOSTINGER_VPS_ARCHITECTURE.md`
- `docs/deployment/HOSTINGER_DEPLOYMENT_GUIDE.md`
- `docs/deployment/PRODUCTION_ENVIRONMENT_CHECKLIST.md`
- `docs/deployment/DNS_AND_EMAIL_CUTOVER.md`
- `docs/deployment/BACKUP_AND_RESTORE.md`
- `docs/deployment/ROLLBACK_PROCEDURE.md`
- A deployment verification checklist.
- An example Nginx site configuration using clearly marked placeholders.
- A queue-worker supervisor configuration if required.
- A scheduler configuration.
- A deployment script only if it can be made safe, repeatable and appropriate for the actual repository.

Adapt filenames to the existing documentation conventions rather than creating unnecessary duplicate documents.

Commands and configuration must match the actual Laravel version, PHP version, operating system and dependencies discovered during the audit.

Never include real secrets or assume production credentials.

## Stage 10: Testing and Acceptance Criteria

Prepare a test plan covering:

- Composer dependency installation.
- Production frontend build.
- Laravel configuration and route caching compatibility.
- Database connectivity and migration status.
- Authentication and Filament access.
- Public website pages and assets.
- File uploads and private document access.
- Email sending.
- Scheduled tasks.
- Queue worker operation and recovery.
- API authentication and rate limiting.
- Mobile API response compatibility.
- Offline mutation idempotency and conflict handling.
- Inventory and finance transaction integrity.
- Audit logging.
- Backup restoration.
- HTTPS and hostname routing.
- Production error handling.

Do not claim a test passed unless it was actually executed and its result was inspected.

## Strict Restrictions

- Do not rebuild the completed ERP or public website.
- Do not change business logic just to simplify deployment.
- Do not replace Laravel, Livewire or Filament.
- Do not introduce microservices.
- Do not create duplicate business logic for the mobile API.
- Do not alter documented API contracts without identifying the reason and impact.
- Do not run destructive database commands.
- Do not run migrations against a live database.
- Do not edit production DNS.
- Do not change the existing email configuration.
- Do not install software on a production server.
- Do not expose secrets in documentation, terminal output or Git.
- Do not mark deployment complete when only preparation has been performed.

## Execution Order

Work in this order:

1. Read documentation.
2. Inspect the repository.
3. Audit production readiness.
4. Report blockers and risks.
5. Propose the target architecture.
6. Prepare deployment documentation and configuration templates.
7. Make only low-risk, necessary code changes after explaining their purpose.
8. Run available tests and build checks.
9. Record all changes and test results.
10. Update `docs/IMPLEMENTATION_STATUS.md` if appropriate.

At completion, provide a concise report containing:

- Application versions and actual dependencies.
- Critical and high-priority blockers.
- Files inspected and changed.
- Deployment artifacts created.
- Tests actually run and their results.
- Hostinger account or server information still needed.
- Steps that must wait for explicit approval.
- A clear statement that the application has NOT been deployed to production.

The objective is to make the existing Azana Farms application deployment-ready while protecting its data, business logic, existing email service and mobile API compatibility.