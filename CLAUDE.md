# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

> **Note:** All commands should be run inside the Docker container.

```bash
# Run all tests
composer test

# Run a single test file
vendor/bin/testbench package:test --filter=TaskHandlerTest

# Run a specific test method
vendor/bin/testbench package:test --filter="it_executes_the_incoming_command"

# Lint (PHPStan)
composer lint

# Serve the workbench app locally
composer serve
```

## Architecture

This is a Laravel package that allows Google Cloud Scheduler to invoke Laravel Artisan commands via HTTP.

**Flow:** Cloud Scheduler sends an HTTP POST to `/cloud-scheduler-job` with the Artisan command as the request body (e.g., `php artisan schedule:run`). The package verifies the Google OIDC token, then executes the command.

### Core components (`src/`)

- **`CloudSchedulerServiceProvider`** — Registers the `/cloud-scheduler-job` POST route and binds `open-id-verificator-gcs` to `OpenIdVerificatorConcrete`.
- **`TaskHandler`** — The route handler. Verifies the token, then calls `runCommand()`. If the command is found in the Laravel `Schedule`, it honors `withoutOverlapping`, `before`/`after` callbacks. Otherwise it runs it directly via `Artisan::call()`.
- **`Command`** — Reads the raw request body and strips the `php artisan` prefix to extract the command name.
- **`OpenIdVerificatorConcrete`** — Uses `Google\Auth\AccessToken` to verify the OIDC JWT token, checking that the audience matches `CLOUD_SCHEDULER_APP_URL` and the email matches `CLOUD_SCHEDULER_SERVICE_ACCOUNT`.
- **`OpenIdVerificator`** — Laravel Facade wrapping `open-id-verificator-gcs`. Call `OpenIdVerificator::fake()` in tests to bypass token verification.

### Testing

Tests use [Orchestra Testbench](https://orchestraplatform.com/docs/testbench/) with a workbench app at `workbench/`. The workbench `Kernel.php` registers `TestCommand` (with `withoutOverlapping`) and `TestCommand2` (with before/after callbacks) for integration testing. Test assertions use a `storage/log.txt` file to verify command execution.

### Config (`config/cloud-scheduler.php`)

| Key | Env var | Purpose |
|-----|---------|---------|
| `app_url` | `CLOUD_SCHEDULER_APP_URL` | Audience for OIDC token verification |
| `service_account` | `CLOUD_SCHEDULER_SERVICE_ACCOUNT` | Expected service account email in token |
| `disable_task_handler` | — | Returns 404 if true |
| `disable_token_verification` | — | Skips OIDC verification if true |
