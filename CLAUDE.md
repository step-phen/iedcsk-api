<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Git Workflow

Follow [GIT_WORKFLOW.md](GIT_WORKFLOW.md). Read it before starting any development task.

Before editing files:

- Run `git branch --show-current` and `git status` and report the branch and the state of the working tree.
- Do not make feature changes directly on `main`. If on `main`, propose a branch name (`feature/*`, `fix/*`, `refactor/*`, `docs/*`, `hotfix/*`) and create it from an up-to-date `main` before editing.
- Preserve existing uncommitted and untracked changes. Never run `git reset --hard`, `git checkout -- <file>`, `git restore`, `git clean`, `git stash drop`, or anything else that discards work without explicit permission.
- Do not change remotes, rename or delete branches, rewrite history, or force-push. Never force-push to `main`.
- Never stage or commit `.env` files, env backups, credentials, or keys. Stage files by name, not with `git add .`.

After making changes:

- Run the validation commands from GIT_WORKFLOW.md section 5: `composer test` (PHPUnit, needs the PostgreSQL `laravel_test` database), `vendor/bin/pint --test`, `php artisan route:list --path=api` for route changes, and `php artisan migrate --pretend` for migration changes. Check Form Request validation, `auth:sanctum` / `EnsureTeacherCanWork` coverage, and migration safety as described there.
- Never run destructive database commands (`migrate:fresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`) against a real database without approval.
- For changes that also touch `iedcsk`, use the same branch name there and verify the API contract (GIT_WORKFLOW.md section 6).
- Summarize what changed (files and why) and the exact validation results, including any failures.
- Propose a Conventional Commit message, then stop and ask for explicit approval before any commit, push, merge, PR creation, or deployment. Approval for one of these actions does not cover the others.
