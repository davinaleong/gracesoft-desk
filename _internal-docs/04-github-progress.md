# GitHub Integration — Implementation Progress

## Milestone 0 — Schema Audit ✅

**Completed:** 2026-08-01

### Findings

| Question                                                   | Finding                                                                                                         |
| ---------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| Is `time_entries.stage` a free string or FK?               | **FK** — `project_stage_id` (nullable) → `project_stages.id`. Already normalised. No backfill needed.           |
| Is `projects.is_billable` a boolean flag or a rate?        | **Both.** `is_billable` (boolean) + `hourly_rate` (decimal) exist on `projects` since the 2026-08-01 migration. |
| Is `billable` on time entries a flag or a computed amount? | **Computed.** `billable_amount = (duration_minutes / 60) × project.hourly_rate`. Stored as `decimal(12,2)`.     |

### Key Schema Notes

- `project_stages` table: canonical set (Discovery → Maintenance, sort_order 1–7) already seeded by the `cleanup_project_stages_to_canonical_lifecycle` migration.
- The cleanup migration **drops `slug` and `project_id`** from `project_stages` (both MySQL and SQLite paths). Final columns: `id, uuid, name, sort_order, status, keywords, is_default, timestamps`.
- Milestone 1's "backfill migration" item is **not needed** — stage is already an FK.
- Milestone 1's "add `stage_id` FK" is **already done** (`project_stage_id` on `time_entries`).

---

## Milestone 1 — SDLC Stages (Settings) ✅

**Completed:** 2026-08-01

### What was built

| File                                                                    | Notes                                                                                                       |
| ----------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `2026_08_01_082615_add_keywords_and_is_default_to_project_stages_table` | Adds `keywords` (JSON nullable) and `is_default` (boolean, default false)                                   |
| `app/Models/ProjectStage.php`                                           | Added `keywords`, `is_default` to fillable; casts: `keywords → array`, `is_default → boolean`               |
| `app/Http/Controllers/ProjectStageController.php`                       | Full CRUD + `moveUp` / `moveDown` for sort_order reordering                                                 |
| `app/Http/Requests/StoreProjectStageRequest.php`                        | Validates name (unique), status, keywords, is_default                                                       |
| `app/Http/Requests/UpdateProjectStageRequest.php`                       | Same as Store but ignores current stage on unique check                                                     |
| `resources/views/settings/project-stages/index.blade.php`               | List with ▲▼ reorder buttons, keyword tag pills, status badge                                               |
| `resources/views/settings/project-stages/create.blade.php`              | Create form with keyword help text                                                                          |
| `resources/views/settings/project-stages/edit.blade.php`                | Edit form, keywords pre-populated as comma string                                                           |
| `resources/views/layouts/navigation.blade.php`                          | "Project Stages" link added under Settings (desktop + mobile)                                               |
| `resources/views/layouts/app.blade.php`                                 | Flash messages for stage CRUD actions                                                                       |
| `routes/web.php`                                                        | 8 routes: index, create, store, edit, update, destroy, move-up, move-down                                   |
| `tests/Feature/ProjectStagesCrudTest.php`                               | 8 tests: index, create+keywords, keyword normalisation, update, delete-unused, delete-in-use, reorder, auth |

### Design decisions

- **No slug on stages.** The cleanup migration removed `slug` from `project_stages`. Name uniqueness is enforced directly.
- **Keywords stored as JSON array**, input via comma-separated text. Normalised to lowercase trimmed strings.
- **Delete guard:** stages with assigned time entries return `project-stage-in-use` status instead of deleting.
- **Reorder:** one-step up/down via PATCH; sort_order values are swapped between adjacent pairs.

---

## Milestone 2 — GitHub Connection ✅

**Completed:** 2026-08-01

### What was built

| File                                                  | Notes                                                                                                                                     |
| ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `laravel/socialite`                                   | Installed via Composer (v5)                                                                                                               |
| `2026_08_01_101345_create_github_connections_table`   | `user_id` (unique FK), `github_id`, `github_login`, `access_token` (encrypted), `token_scope`, `connected_at`                             |
| `app/Models/GithubConnection.php`                     | `access_token` cast to `encrypted`; `connected_at` cast to `datetime`; belongs to User                                                    |
| `app/Models/User.php`                                 | Added `githubConnection()` HasOne relationship                                                                                            |
| `app/Http/Controllers/GitHubConnectionController.php` | `show`, `redirect`, `callback`, `destroy`                                                                                                 |
| `resources/views/settings/github/show.blade.php`      | Connected/disconnected states, connection details, disconnect confirm                                                                     |
| `resources/views/layouts/navigation.blade.php`        | "GitHub" link added under Settings                                                                                                        |
| `config/services.php`                                 | GitHub driver config (`client_id`, `client_secret`, `redirect`)                                                                           |
| `.env`                                                | `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`, `GITHUB_REDIRECT_URI` placeholders                                                            |
| `routes/web.php`                                      | `settings.github.show`, `settings.github.redirect`, `settings.github.destroy`, `settings.github.callback`                                 |
| `tests/Feature/GitHubConnectionTest.php`              | 7 tests: page access, not-connected state, connected state, callback stores connection, callback updates existing, disconnect, auth guard |

### Design decisions

- **Token encrypted at rest** using Laravel's `encrypted` cast — no plaintext token in the DB.
- **Callback route exempt from `password.changed`/`twofactor` middleware** — GitHub redirects back mid-OAuth flow before those guards run.
- **`updateOrCreate` on callback** — reconnecting replaces the old token cleanly.
- **Single connection per user** — `user_id` has a unique index; the model uses `HasOne`.
- **Scopes requested:** `repo` + `read:user` (minimum needed for Milestone 3 repo picker and webhook registration).

---

## Next: Milestone 3 — Project ↔ Repo Linking

Upcoming work:

- Add `github_repo` and `github_webhook_secret` (nullable) to `projects`
- Repo picker UI on project form (calls GitHub API using stored token)
- Auto-register webhook (`push` event) on repo link; remove on unlink
- Tests: webhook registration/removal, repo picker with mocked GitHub API

---

## Milestone 3 — Project ↔ Repo Linking ✅

**Completed:** 2026-08-03

### What was built

| File                                                    | Notes                                                                                                                                                                                 |
| ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026_08_03_022012_add_github_fields_to_projects_table` | Adds `github_repo` (string nullable), `github_webhook_id` (unsignedBigInteger nullable), `github_webhook_secret` (text nullable, encrypted)                                           |
| `app/Models/Project.php`                                | Added three new fields to fillable; casts: `github_webhook_id → integer`, `github_webhook_secret → encrypted`                                                                         |
| `app/Services/GitHubService.php`                        | `listRepositories()` (paginated), `registerWebhook()`, `removeWebhook()` (404-tolerant). Uses `Illuminate\Support\Facades\Http` with Bearer token.                                    |
| `app/Http/Controllers/ProjectGithubController.php`      | `repos()` JSON endpoint, `store()` link + register webhook, `destroy()` unlink + remove webhook                                                                                       |
| `resources/views/projects/show.blade.php`               | GitHub Repository section: shows linked repo + Unlink button; or repo picker (Alpine.js datalist) when connected; or prompt to connect GitHub                                         |
| `resources/views/layouts/app.blade.php`                 | Added `@stack('scripts')`, flash messages for `github-repo-linked` and `github-repo-unlinked`                                                                                         |
| `routes/web.php`                                        | `GET /settings/github/repos` (JSON), `POST /projects/{project}/github`, `DELETE /projects/{project}/github`, placeholder `POST /webhooks/github/{project:uuid}` (Milestone 4)         |
| `tests/Feature/ProjectGithubTest.php`                   | 12 tests: repos JSON (connected/no-connection/auth), link (success/invalid-format/no-connection/relink), unlink (success/404-tolerant), show page (linked/link prompt/connect prompt) |

### Design decisions

- **`github_webhook_secret` encrypted at rest** using Laravel's `encrypted` cast — never plaintext in DB.
- **Relink flow** calls `removeWebhook` on the previous repo before registering the new one.
- **404-tolerant `removeWebhook`** — if the webhook was already deleted on GitHub's side, the DB fields are still cleared cleanly.
- **Repo picker uses `<datalist>`** + Alpine.js `loadRepos()` — no heavy JS dependency, lazy-loaded on click.
- **`@stack('scripts')` added** to app layout to support page-level inline scripts going forward.
- **Webhook placeholder route** (`/webhooks/github/{project:uuid}`) added now so `route('webhooks.github', $project)` resolves during Milestone 3 registration; full handler implemented in Milestone 4.

---

## Next: Milestone 4 — Commit Ingestion (no AI yet)

Upcoming work:

- `commit_time_entries` table (raw commit capture + status field)
- Webhook endpoint `POST /webhooks/github/{project_uuid}` with HMAC signature verification
- Parse `push` payload, upsert commit rows as `pending`
- Fetch commit stats (additions/deletions) via GitHub API where not in payload
- Tests: signature verification (valid/invalid), payload parsing, duplicate commit handling

---

## Milestone 4 — Commit Ingestion (no AI yet) ✅

**Completed:** 2026-08-03

### What was built

| File                                                 | Notes                                                                                                                                                                                                                                           |
| ---------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026_08_03_023951_create_commit_time_entries_table` | `id, uuid, project_id FK, sha (40), branch, author_name, author_email, committed_at, message, additions, deletions, changed_files, status (pending/approved/squashed/ignored), squashed_into FK (self), converted_time_entry_id FK, timestamps` |
| `app/Models/CommitTimeEntry.php`                     | Full fillable + casts; `HasPublicUuid`; relations: `project`, `squashedInto`, `convertedTimeEntry`                                                                                                                                              |
| `database/factories/CommitTimeEntryFactory.php`      | Realistic defaults with `Project::factory()` association                                                                                                                                                                                        |
| `app/Http/Controllers/WebhookController.php`         | `github()`: HMAC verification → ingestPush(); `ingestPush()`: upserts commits with `updateOrCreate`, computes `changed_files` from added/removed/modified arrays                                                                                |
| `bootstrap/app.php`                                  | CSRF exclusion for `webhooks/github/*`                                                                                                                                                                                                          |
| `routes/web.php`                                     | `POST /webhooks/github/{project:uuid}` wired to `WebhookController@github`                                                                                                                                                                      |
| `tests/Feature/CommitIngestionTest.php`              | 8 tests: valid HMAC, bad HMAC, no signature, unknown UUID, push payload parsing, duplicate suppression, non-push event, multi-commit push                                                                                                       |

### Design decisions

- **HMAC via `hash_equals`** — constant-time comparison prevents timing attacks.
- **CSRF excluded** for webhook path — receives from GitHub, no session.
- **`updateOrCreate` on `(project_id, sha)`** — makes ingestion idempotent; re-delivered webhooks don't duplicate rows.
- **`changed_files` computed from payload arrays** (`added + removed + modified` count) — avoids extra API call when GitHub includes file lists in push payload.
- **`squashed_into` / `converted_time_entry_id`** nullable FKs pre-wired for Milestones 5 and 7.
- **Test server vars** (`HTTP_X_HUB_SIGNATURE_256`) passed directly to `call()` — reliable header delivery in SQLite test environment.

---

## Next: Milestone 5 — Rule-Based Stage Matching + Manual Review Screen

---

## Milestone 5 — Rule-Based Stage Matching + Manual Review Screen ✅

**Completed:** 2026-08-03

### What was built

| File                                                         | Notes                                                                                                                                                                                                                 |
| ------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Services/CommitStageMatcherService.php`                 | `match(message, branch)`: scans `project_stages` ordered by `sort_order`, returns first stage whose keywords appear in message+branch (case-insensitive). `snapDuration(minutes)`: rounds to nearest 15 min (min 15). |
| `app/Http/Controllers/PendingCommitController.php`           | `index()` lists pending commits; `create()` shows convert form with suggested stage; `store()` snaps duration, creates `TimeEntry`, marks commit `approved`                                                           |
| `resources/views/projects/pending-commits/index.blade.php`   | Table: date, branch, author, message, diff stats, Convert link                                                                                                                                                        |
| `resources/views/projects/pending-commits/convert.blade.php` | Conversion form: stage picker pre-selected by keyword match, date from commit, duration with step=15, billable from project, notes prefilled from commit message                                                      |
| `resources/views/projects/show.blade.php`                    | "Review Commits" link added to GitHub section                                                                                                                                                                         |
| `resources/views/layouts/app.blade.php`                      | `commit-converted` flash message                                                                                                                                                                                      |
| `routes/web.php`                                             | `GET/POST /projects/{project}/pending-commits/{commit}/convert`, `GET /projects/{project}/pending-commits`                                                                                                            |
| `tests/Feature/PendingCommitReviewTest.php`                  | 12 tests: keyword matching, branch matching, case-insensitivity, null match, duration snapping, review screen access/filtering, convert form, conversion flow, 15-min snapping, cross-project 404 guard               |

### Design decisions

- **Keyword matcher is stateless service** — no DB write, easily mockable.
- **`snapDuration` is a static method** — used in both controller and tests without DI.
- **Stage pre-selected but editable** — the `suggested_stage` sets the default `<select>` value but the user can override.
- **Duration uses `step=15` in HTML** — enforces 15-min increments in the browser; server also snaps on save.
- **Billable default from project** — `$project->is_billable` pre-ticks the checkbox.
- **`abort_unless` cross-project guard** — prevents converting a commit from project B via project A's route.

---

## Milestone 6 — AI Summary + AI Stage Fallback ✅

**Completed:** 2026-08-03

### What was built

| File                                                            | Notes                                                                                                                                                     |
| ---------------------------------------------------------------| ---------------------------------------------------------------------------------------------------------------------------------------------------------|
| `app/Contracts/CommitSummarizer.php`                            | Interface: `summarize(array $commits, array $stageNames): SummaryResult`                                                                                  |
| `app/Support/SummaryResult.php`                                 | Readonly DTO: `summary`, `suggestedStageName`                                                                                                             |
| `app/Services/OpenAiCommitSummarizer.php`                       | Calls OpenAI Chat Completions (`response_format: json_object`), prompts for a 1–2 sentence note + stage suggestion from the project's stage list          |
| `app/Services/NullCommitSummarizer.php`                         | No-op fallback — returns an empty summary/no suggestion when no API key is configured                                                                     |
| `app/Providers/AppServiceProvider.php`                          | Binds `CommitSummarizer` → `OpenAiCommitSummarizer` when `services.openai.api_key` is set, else `NullCommitSummarizer`                                    |
| `app/Jobs/SummarizeCommit.php`                                  | Queued job; skips AI entirely if `CommitStageMatcherService` already found a keyword match; otherwise calls the bound summarizer and stores the result    |
| `2026_08_03_025743_add_ai_fields_to_commit_time_entries_table`  | Adds `ai_summary` (text, nullable) and `ai_suggested_stage_id` (FK → `project_stages`, nullable, `nullOnDelete`)                                          |
| `app/Models/CommitTimeEntry.php`                                | Added `ai_summary`, `ai_suggested_stage_id` to fillable; `aiSuggestedStage()` relation                                                                    |
| `app/Http/Controllers/WebhookController.php`                   | Dispatches `SummarizeCommit` for newly-created commit rows only (not re-deliveries)                                                                       |
| `app/Http/Controllers/PendingCommitController.php`              | `create()` falls back to `$commit->aiSuggestedStage` when the keyword matcher finds nothing                                                               |
| `resources/views/projects/pending-commits/convert.blade.php`   | Shows the AI-generated note ("AI note: …") when present                                                                                                   |
| `config/services.php`, `.env.example`                          | `OPENAI_API_KEY` / `OPENAI_MODEL` (default `gpt-4o-mini`); `GITHUB_*` vars also backfilled into `.env.example` (missed in Milestone 2)                    |
| `tests/Feature/AiSummaryTest.php`                               | 5 tests: interface contract (both implementations), skip-on-keyword-match, provider called on no-match, unknown-stage-name → null, dispatch-on-webhook   |

### Design decisions

- **AI is a fallback, never a silent override.** `SummarizeCommit` only calls the LLM when `CommitStageMatcherService` finds no keyword match — keyword rules always win when present.
- **Unknown stage names from the LLM resolve to `null`**, not a guess — `ai_suggested_stage_id` is only set when the returned name matches an existing `project_stages.name` exactly. A bad/unavailable suggestion still leaves the commit fully visible for manual review; nothing is auto-approved.
- **Swappable provider via DI binding**, not a config `match()` — `AppServiceProvider` binds `CommitSummarizer` to `OpenAiCommitSummarizer` or `NullCommitSummarizer` based solely on whether an API key is present, so tests and local dev without a key never make network calls.
- **`NullCommitSummarizer` as the safe default** — a fresh checkout with no `OPENAI_API_KEY` still ingests and reviews commits normally; it just won't get AI notes/suggestions.

---

## Next: Milestone 7 — Squash UI
