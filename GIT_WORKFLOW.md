# Git Workflow — iedcsk-api (Laravel backend)

This repository uses a simple **Feature Branch Workflow**. The frontend lives in a separate repository, `iedcsk` (Next.js), which follows the same workflow.

- Remote: `origin` → `https://github.com/step-phen/iedcsk-api.git`
- Default branch: `main`
- There is **no** permanent `develop` branch. Every short-lived branch starts from `main` and returns to `main` through a Pull Request.

## 1. Branches

| Branch       | Purpose                                         | Starts from |
| ------------ | ----------------------------------------------- | ----------- |
| `main`       | Stable, production-ready code                   | —           |
| `feature/*`  | New features                                    | `main`      |
| `fix/*`      | Bug fixes                                       | `main`      |
| `refactor/*` | Code improvements with no behavior change       | `main`      |
| `docs/*`     | Documentation-only changes                      | `main`      |
| `hotfix/*`   | Urgent fixes to what is currently in production | `main`      |

Naming: lowercase, kebab-case, short and descriptive.

```
feature/attendance-export
fix/handover-accept-validation
refactor/classroom-record-queries
docs/git-workflow
hotfix/start-class-500
```

## 2. Rules

1. Never implement new features directly on `main`.
2. Check the current branch and working tree before editing files.
3. Create a feature branch when needed, after confirming the starting point is correct.
4. Use Conventional Commits.
5. Review the diff before committing.
6. Run the validation commands (section 5) before proposing a merge.
7. Never force-push to `main`.
8. Never commit sensitive files (`.env`, `.env.*` backups, credentials, keys).
9. Do not automatically commit, push, merge, or deploy.
10. Get explicit approval from the project owner before every commit, push, merge, or deployment.

## 3. Daily flow

### 3.1 Check where you are

```sh
git branch --show-current
git status
```

- On `main` with a clean tree: safe to create a new branch.
- On `main` with uncommitted or untracked changes (for example new seeders): those are someone's work in progress. Do not stash, reset, clean, or discard them. `git switch -c <branch>` carries them onto the new branch unchanged; confirm with the owner first that they belong to the new task.
- Already on a work branch: confirm it is the right branch for the task before editing.

### 3.2 Make sure the starting point is current

```sh
git fetch origin
git status          # shows "behind 'origin/main' by N commits" if local main is stale
```

If local `main` is behind and the working tree is clean, fast-forward it:

```sh
git switch main
git pull --ff-only
```

If `--ff-only` refuses (local `main` has diverged), stop and ask; do not merge or rebase `main` on your own.

### 3.3 Create the branch

```sh
git switch -c feature/<short-name>
```

### 3.4 Work, then review

```sh
git status
git diff                # unstaged
git diff --staged       # staged
```

Check that the diff contains only what the task needs: no `dd()` / `dump()` / `Log::debug` left behind, no stray files, no secrets, no unrelated formatting churn.

### 3.5 Commit (with approval)

Stage files by name rather than `git add .`, so nothing unintended slips in (this matters here: untracked `.env` backups can sit in the root):

```sh
git add app/Http/Controllers/FooController.php app/Http/Requests/StoreFooRequest.php tests/Feature/FooTest.php
git commit -m "feat(foo): add store endpoint"
```

### 3.6 Push and open a Pull Request (with approval)

```sh
git push -u origin feature/<short-name>
```

Open a PR into `main` on GitHub. After it is merged, delete the branch.

## 4. Conventional Commits

Format: `<type>(<optional scope>): <summary>`

| Type       | Use for                                        |
| ---------- | ---------------------------------------------- |
| `feat`     | New feature                                    |
| `fix`      | Bug fix                                        |
| `refactor` | Code change with no behavior change            |
| `docs`     | Documentation only                             |
| `style`    | Formatting only, no logic change               |
| `perf`     | Performance improvement                        |
| `test`     | Adding or changing tests                       |
| `build`    | Dependencies, build configuration              |
| `chore`    | Maintenance that fits none of the above        |

Rules: imperative mood ("add", not "added"), lowercase summary, no trailing period, summary under ~72 characters. Add a body when the *why* is not obvious. Mark breaking API changes with `!` (`feat(api)!: ...`) and a `BREAKING CHANGE:` footer.

Examples:

```
feat(handover): allow cancelling a pending handover
fix(attendance): reject dates outside the active quarter
test(classroom): cover start-class validation errors
build: bump laravel/framework to 13.18
```

## 5. Validation before proposing a merge

Run from the repository root. Stack: Laravel 13, PHP ^8.3, PHPUnit 12 (no Pest), Sanctum, Pint.

### 5.1 Automated tests (required)

```sh
composer test
```

This runs `php artisan config:clear` then `php artisan test` (Unit and Feature suites in `phpunit.xml`). The tests use PostgreSQL with database `laravel_test`; it must exist locally. To run one file or test:

```sh
php artisan test tests/Feature/ClassroomSessionTest.php
php artisan test --filter=test_name
```

### 5.2 Code style

```sh
vendor/bin/pint --test      # report only
vendor/bin/pint --dirty     # fix files changed on this branch (with approval, since it edits files)
```

### 5.3 API endpoints

```sh
php artisan route:list --path=api
```

Confirm new or changed routes have the expected method, URI, controller, and middleware. Add or update a Feature test in `tests/Feature/` for each endpoint touched.

### 5.4 Form Request validation

Validation rules live in Form Requests in `app/Http/Requests/`, not in controllers and not in the frontend. For any new or changed input:

- The controller method type-hints the Form Request.
- `rules()` covers every accepted field; `authorize()` is correct.
- Feature tests assert a 422 response with the expected error keys for invalid input, and success for valid input.

### 5.5 Authentication and authorization

- Authentication is Sanctum stateful (cookie) auth: `bootstrap/app.php` calls `statefulApi()`.
- Protected routes sit in the `Route::middleware(['auth:sanctum', EnsureTeacherCanWork::class])` group in `routes/api.php`.
- There is no `app/Policies` directory; authorization is handled by that middleware, Form Request `authorize()`, and query scoping in controllers.

For each change, check that new routes are inside the protected group unless intentionally public, that a teacher cannot read or change another teacher's data, and that tests cover the unauthenticated (401) and forbidden (403/404) cases where relevant.

### 5.6 Database migration safety

```sh
php artisan migrate:status
php artisan migrate --pretend     # prints the SQL without running it
```

- Never edit a migration that has already run in production; add a new migration instead.
- Every migration has a working `down()`.
- Dropping or renaming columns/tables, or changing types, loses data: call it out in the PR and get approval before running it anywhere.
- Adding a `NOT NULL` column to a table with rows needs a default or a backfill.
- Do not run `migrate:fresh`, `migrate:reset`, `migrate:rollback`, or `db:wipe` against a real database without approval.

### 5.7 Front-end assets

This repo's `package.json` only has `vite` `dev`/`build` scripts for Laravel's own views. The real UI is the `iedcsk` repository, so run `npm run build` here only if files under `resources/` changed.

## 6. Coordinated frontend + backend changes

When a feature needs both `iedcsk-api` and `iedcsk`:

1. **Matching branch names** in both repositories, for example `feature/attendance-export` in each.
2. **Backend first**: implement routes, Form Requests, controller, migrations, and Feature tests here.
3. **Frontend second**: implement the UI and API calls in `iedcsk`.
4. **Verify the API contract**: method, path, request fields, response JSON shape, and status codes match what the frontend sends and expects (`src/lib/api.ts`, `src/types/` in `iedcsk`). Validation errors return 422 with Laravel's `errors` object; the frontend only displays them.
5. **Test the integration** with both apps running locally (the frontend reaches the API on port 8000 of the same host by default; the system runs on a local network, so CORS/Sanctum stateful domains must allow the host in use).
6. **Separate commits and separate PRs**, one per repository. Link each PR to the other in its description.
7. **Deployment compatibility**: merge and deploy the backend first when the frontend depends on new endpoints, fields, or migrations. Prefer additive changes so the currently deployed frontend keeps working. If a change is breaking, say so in both PRs and plan the order.

## 7. Hotfixes

```sh
git switch main
git pull --ff-only
git switch -c hotfix/<short-name>
```

Keep the change minimal, add a regression test, run section 5 checks, open a PR into `main`, and merge with approval. If the same bug exists on an open feature branch, bring the fix in after it lands on `main`.

## 8. Never

- Force-push to `main` (`git push --force` / `--force-with-lease` on `main`).
- Commit `.env` or any `.env.*` copy other than `.env.example`. Check `git status` for untracked env backups before staging.
- Run `git reset --hard`, `git checkout -- <file>`, `git restore`, `git clean`, or `git stash drop` on someone's uncommitted work without permission.
- Run destructive database commands against a real database without approval.
- Skip hooks with `--no-verify`.
- Commit, push, merge, or deploy without explicit approval.
