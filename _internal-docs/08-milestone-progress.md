# Desk 1.1 / 1.2 — Implementation Progress

Tracks delivery of `06-milestone.md` against `07-test-checklist.md`.

## Definition of done — shared checks

| Check | How it is covered |
| --- | --- |
| Routes behind `auth`, `password.changed`, `twofactor.configured` | `tests/Feature/RouteGuardsTest.php` loops over every `App\Http\Controllers` route (Auth/Api namespaces and three documented exemptions excluded) |
| Archive mode blocks non-GET routes | Same loop asserts `archive.open` on every non-GET route |
| UUID route keys | `HasPublicUuid` on every new model; numeric-ID 404 asserted per milestone |
| Audit logging | New money/time models registered with `AuditableObserver` in `AppServiceProvider` |
| Migrations roll back | Checked each milestone: fresh migrate → `MarketingDemoSeeder` → rollback → migrate, on SQLite and a throwaway MySQL database |

Not yet possible in this repo: `features.md` and the redacted demo snapshot don't exist here, and `desk:import-live-data` needs a live export that isn't available locally.

---

## M1 · Clients ✅

**Completed:** 2026-09-29

### Decision: rate precedence

Adopted the proposal: **project rate → client default rate → system `default_hourly_rate`**. A null or zero rate counts as "not set". `projects.hourly_rate` is now nullable (blank in the form means "inherit").

### What was built

| File | Notes |
| --- | --- |
| `2026_09_29_021538_create_clients_table` | UUID, `client_code` (`CLT-00001`), name, billing email, address, tax ID, currency, `default_hourly_rate` (decimal 12,2), status, notes, soft deletes |
| `2026_09_29_021539_add_client_id_to_projects_and_transactions_table` | Nullable `client_id` FK (null on delete) on projects and transactions; `projects.hourly_rate` made nullable |
| `app/Models/Client.php` | Sequential code generator (counts soft-deleted rows), `projects`, `transactions`, `documents` relations; audited |
| `app/Services/BillableRateResolver.php` | Single place for rate precedence; used by `TimeEntry` billing, time-entry CSV import, `desk:recalculate-billable-amounts`, project and time-entry views |
| `app/Http/Controllers/ClientController.php` | index / create / store / show / edit / update. Archiving is a status change, so it is audit-logged as an update |
| `app/Http/Controllers/ClientImportController.php` | Template → preview → commit. Blank code creates, existing code updates, unknown code is rejected. Commit runs in one DB transaction |
| `resources/views/clients/*` | List with status filter, form, detail page (projects, recent transactions, documents), import screens |
| Projects | Client picker (active clients plus the current one), client shown on detail page, effective rate shown with its source |
| Transactions | Client picker. Choosing a project fills in its client (Alpine on the form, and the server falls back to the project's client when none is given) |
| Documents | `client` accepted as a documentable type for upload, attach, delete and the index |
| `MarketingDemoSeeder` | Two demo clients with rates, linked to three demo projects |

### Tests

`tests/Feature/ClientsTest.php` (18) and `tests/Feature/RouteGuardsTest.php` (3):

- [x] Sequential codes `CLT-00001`, `CLT-00002` (also across soft deletes)
- [x] UUID URLs; a numeric ID returns 404
- [x] Projects link and unlink; client-less projects still load and report
- [x] Choosing a project on a transaction fills in its client; an explicit client wins
- [x] Billable amount follows the rate precedence; `desk:recalculate-billable-amounts` applies the same rule
- [x] CSV import: invalid rows show reasons; nothing written before commit
- [x] Create, update and archive are audit-logged with old and new values
- [x] Archive mode blocks every client write

---

## M2 · Invoicing ✅

**Completed:** 2026-09-29

### Decisions

- **PDF library:** `barryvdh/laravel-dompdf` (approved). Font subsetting is on and the fallback is Helvetica (a core PDF font). The first version used DejaVu Sans, and embedding it took over 100 MB per render. **Montserrat:** drop `Montserrat-Regular.ttf` (and optionally `Montserrat-Bold.ttf`) into `resources/fonts/` and the PDF picks it up. The TTFs aren't in the repo yet.
- **GST is charged only when a GST registration number is set** in System Settings. Without one, the rate is 0 and the PDF says "Invoice", not "Tax Invoice".
- **Money maths runs in integer cents** (`App\Support\Money`). GST is worked out per line and rounded half-up, and totals are sums of the lines, so the lines always add up to the total.
- **Time lines use each entry's stored `billable_amount`.** Grouped lines add those amounts together, so they never re-price.
- **Numbers are allocated at issue** from a row-locked `invoice_number_sequences` row (one per year), inside the issuing transaction. A rollback returns the number unused. Drafts have no number, so deleting one can't leave a gap. Voided invoices keep their number.
- **Locking:** a time entry becomes read-only as soon as it is on any invoice line, draft included. Removing the line, deleting the draft or voiding the invoice releases it. Locked entries are also skipped by `desk:recalculate-billable-amounts`, and their amounts are never re-derived.
- **Payment is full-payment only** (partial payments are deferred). The invoice row is locked, an existing payment transaction is returned if there is one, and `transactions.invoice_id` is unique as a database backstop.
- **Voiding** is allowed for draft and issued invoices, not paid ones.

### What was built

| File | Notes |
| --- | --- |
| `2026_09_29_040001_create_invoices_table` | `invoices`, `invoice_lines`, `invoice_number_sequences`; `time_entries.invoice_line_id`; unique `transactions.invoice_id` |
| `app/Models/Invoice.php`, `InvoiceLine.php` | UUID keys, audited, statuses draft / issued / paid / void |
| `app/Services/InvoiceService.php` | Unbilled-entry query, create/update/delete draft, issue, record payment, void, recalculate |
| `app/Services/InvoiceNumberAllocator.php` | Gap-free `INV-YYYY-00001` |
| `app/Services/InvoicePdfService.php` + `resources/views/invoices/pdf.blade.php` | Renders the PDF and stores it on S3 as a `Document` linked to the invoice. Regenerated on issue, payment and void (paid/void stamp) |
| `app/Mail/InvoiceMail.php` | Markdown email with the PDF attached from S3; sets `sent_at` |
| `app/Services/InvoiceSettings.php` | Company address, GST reg. no., GST rate, payment terms, footer (new fields in System Settings) |
| `app/Services/InvoiceTotals.php` | Invoiced / paid / outstanding totals, shared by the finance report, the CSV export and the bot |
| `app/Http/Controllers/InvoiceController.php` | CRUD + `issue`, `pdf` (redirects to a signed S3 URL), `send`, `payment`, `void` |
| `app/Http/Controllers/Api/Bot/InvoicesController.php` | `GET /api/bot/invoices/summary`: range totals, outstanding, overdue list |
| Dashboard | "Outstanding Receivables" KPI (red when anything is overdue); invoices added to the cache signature |
| Finance report | Invoiced / paid / outstanding cards; three summary rows at the end of the CSV |
| Time entries | Edit/delete blocked on invoiced entries, with a link to the invoice |
| Clients | Invoices list and a "New Invoice" shortcut on the client page |
| `desk:prelaunch-check` | Warnings for a log/array mailer, a missing S3 bucket and a missing company address |
| `MarketingDemoSeeder` | One paid, one outstanding and one draft invoice. Skipped once they exist, so reseeding doesn't burn numbers |

### Tests

`tests/Feature/InvoicingTest.php` (23), plus additions to the settings, prelaunch and seeder tests:

- [x] Numbers are sequential per year with no gaps; deleting a draft never consumes a number; a failed issue returns its number
- [x] Only billable, unbilled, non-deleted entries of that client are offered, and the server re-checks them
- [x] Three 20-minute entries: per-entry and grouped totals round to 2 dp and lines sum to the total
- [x] Issued invoices can't be edited or deleted
- [x] Invoiced time entries can't be edited or deleted; voiding unlocks them; billed amounts stay frozen through re-pricing
- [x] Recording payment twice creates exactly one income transaction
- [x] Tax invoice shows the IRAS fields when GST-registered; no GST appears without registration
- [x] PDF is stored on S3 (`Storage::fake`) and served only through a signed URL
- [x] Sending uses `Mail::fake`, attaches the PDF and records `sent_at`
- [x] Create, issue, payment and void are audit-logged; every write is blocked in archive mode
- [x] Paying an invoice changes the dashboard cache key
- [x] Finance report and CSV export show invoiced, paid and outstanding totals
- [x] Bot endpoint needs a Sanctum token and lists overdue invoices

The MySQL migration check caught a bug SQLite missed (`DISTINCT` combined with the relation's `ORDER BY`). It is fixed with `reorder()`.

---

## M3 · Weekly draft timesheet ✅

**Completed:** 2026-09-29

### Decisions

- **`committed_at` is now stored in true UTC** through the new `App\Casts\UtcDateTime` cast. The plain `datetime` cast used to keep the pusher's wall-clock time and drop the `+08:00` offset. `2026_09_29_050001_convert_commit_timestamps_to_utc` rewrites existing rows, assuming they were in the system timezone (reversible).
- **The week is Monday 00:00 to Sunday 23:59:59 in the system timezone.** Its bounds are converted to UTC before querying.
- **Dismiss reuses the existing `ignored` status** (`CommitTimeEntry::STATUS_DISMISSED`); restore sets the commit back to `pending`.
- **Suggestions:** the stage comes from the keyword matcher first, then the AI-suggested stage. The summary comes from the AI summary, falling back to the first line of the message. Duration defaults to 15 minutes.
- **Converting** runs in one transaction with the selected commits row-locked. Each commit's minutes are snapped to 15. A repeat submit finds nothing pending and creates nothing; a partly stale selection is rejected as a whole. Commits squashed into a converted anchor are approved with it.
- **Weekly target** is an optional `weekly_hours_target` system setting. The week total is the time entries dated in that week.

### What was built

| File | Notes |
| --- | --- |
| `app/Casts/UtcDateTime.php` + data migration | UTC storage for commit timestamps |
| `app/Services/WeeklyTimesheetService.php` | Week bounds, pending / dismissed lists, day → project grouping, suggestions, logged minutes, transactional convert |
| `app/Http/Controllers/TimesheetController.php` + `resources/views/timesheet/index.blade.php` | Week navigation, select all, per-commit stage / summary / minutes, "one entry per day & project", convert / squash / dismiss, dismissed list with restore, target progress bar |
| `desk:timesheet-reminder` + `PendingCommitsReminderMail` | Emails the user only when there are pending commits. Scheduled Fridays 16:00 system time |
| `routes/console.php` | Scheduler heartbeat every 5 minutes, used by `desk:prelaunch-check` ("Scheduler ran in the last hour") |
| `GET /api/bot/timesheet/pending` | Total, this week, oldest, per-project counts and a link |
| `MarketingDemoSeeder` | Five pending demo commits in the current week |
| `phpunit.xml` | `memory_limit=512M`: the suite had outgrown PHP's 128 MB CLI default (web requests aren't affected) |

### Tests

`tests/Feature/WeeklyTimesheetTest.php` (16):

- [x] A commit at Sunday 23:30 SGT lands in that week, not the next, and is stored in UTC
- [x] Only pending commits appear (not converted, squashed or dismissed)
- [x] Bulk convert: one entry per commit or per group, durations in multiples of 15, one DB transaction, one failure rolls back the batch
- [x] A double-clicked convert never creates duplicate entries
- [x] Dismissed commits are hidden and can be restored
- [x] Squashing from the week view queues `SummarizeSquashedCommits` (`Queue::fake`); mixed-project squash is rejected
- [x] The reminder sends only when the pending count is above zero; it is scheduled; the bot endpoint returns the count (401 without a token)
- [x] In archive mode the page is viewable and every action is blocked
- [x] The UTC data migration converts and reverts correctly

---

## M4 · AI privacy controls ✅

**Completed:** 2026-09-29

### Decisions

- **AI is off until switched on** in Settings → AI & Privacy (`ai_enabled`, default off). The null driver stays bound unless AI is enabled *and* the chosen provider is fully configured. An `OPENAI_API_KEY` in `.env` no longer turns AI on by itself; it is only used as the key once the OpenAI provider is selected.
- **All traffic goes through one door:** `CommitSummaryGateway` checks the switch and the per-project opt-out, builds the allow-listed payload, calls the driver and writes the log row. The jobs never call a driver directly.
- **Allow-list** (`AiPayloadBuilder`): commit message, branch, changed-file count, stage names and keywords, plus file paths only when "Also send changed file paths" is on. Diffs, author names and emails are never sent. Paths are now stored on ingest (`commit_time_entries.file_paths`, capped at 100) so the toggle has something to send.
- **Redaction** (`Redactor`) runs on messages, branches and paths before sending. It masks emails, JWTs, `Bearer …`, well-known key prefixes (OpenAI, Anthropic, GitHub, GitLab, Slack, Stripe, AWS), `password=`/`token:`-style pairs, and long mixed letter/digit strings.
- **Drivers:** `OpenAiCommitSummarizer` (also drives OpenAI-compatible local endpoints through a base URL, key optional), `AnthropicCommitSummarizer` and `NullCommitSummarizer`. The Anthropic driver uses raw `Http` calls like the others, which avoids adding the Anthropic PHP SDK as a dependency. It defaults to `claude-opus-5` (configurable), treats `stop_reason: refusal` as "no summary", and for Opus 5 / Fable 5.1 opts into server-side `fallbacks: "default"` with low effort.
- **Failures:** jobs retry 3 times with 30 s / 120 s backoff. Each failed attempt is logged as `error`, and `failed()` only writes an info log, so the commit stays pending with no summary.
- **API key** is stored with `Crypt::encryptString` and is write-only in the form: a blank field keeps the stored key, and there is a "Remove the saved key" option.

### What was built

| File | Notes |
| --- | --- |
| `2026_09_29_060001_create_ai_requests_table` | `ai_requests` log; `projects.ai_opt_out`; `commit_time_entries.file_paths` |
| `app/Services/Ai/*` | `AiSettings`, `AiPayloadBuilder`, `Redactor`, `SummaryPrompt`, `CommitSummaryGateway` |
| `app/Contracts/CommitSummarizer.php` | Now takes the allow-listed payload and exposes `provider()` / `model()` for logging |
| `app/Services/AnthropicCommitSummarizer.php` | New driver |
| `AiSettingsController` + `settings/ai.blade.php` | Switch, provider, model, base URL, key, file-path toggle, retention; last 20 log rows (hash prefix, byte length, outcome) |
| `desk:prune-ai-requests` | Deletes rows older than retention (default 90 days); scheduled daily at 03:15 |
| Projects | "Never send this project's commits to AI" checkbox |
| `desk:prelaunch-check` | Fails when AI is on but the provider isn't fully configured |

### Tests

`tests/Feature/AiPrivacyTest.php` (18); `AiSummaryTest` and `SquashCommitsTest` were updated to the payload contract through a shared `gatewayWith()` helper in `tests/Pest.php`:

- [x] Fresh install: null summarizer bound, no HTTP request (`Http::fake` + `assertNothingSent`), even with `OPENAI_API_KEY` set
- [x] Each driver (OpenAI, Anthropic, OpenAI-compatible) calls its own endpoint with the configured model
- [x] The body holds only allow-listed fields; author email, name, diff and (by default) file paths are absent
- [x] Token-like strings and emails are masked
- [x] An opted-out project is never summarised
- [x] A 500 leaves the commit pending without a summary, retries with backoff, and fails quietly
- [x] Every call writes one log row with a hash, not the payload
- [x] A stage keyword match still skips AI
- [x] API keys are stored encrypted and never rendered back into the form

`MarketingDemoSeeder` has nothing new for M4: AI stays off in the demo, as it should on a fresh install.

---

## M5 · Budgets and burn alerts ✅

**Completed:** 2026-09-29 (this closes release 1.1)

### Decisions

- **Budget fields on projects:** `budget_type` (none / hours / amount), `budget_value`, and `budget_thresholds` (JSON; blank means 50 / 80 / 100).
- **Fire once, re-arm on a new budget:** `budget_alerts` is unique on (project, `budget_revision`, threshold). `budget_revision` goes up only when the budget type or value changes. Changing only the thresholds keeps the alerts already fired, and newly added thresholds can still fire.
- **What counts as used:** hours budgets count every non-deleted entry, billable or not. Amount budgets count billable value only. Soft-deleted entries never count.
- **When it's checked:** `BudgetMonitor` (a singleton) re-evaluates on time-entry save, delete and restore (both projects when an entry moves), and on budget changes. The CSV import wraps its commit in `deferDuring()` so each project is evaluated once, after the whole import.
- **A failed email never re-fires the alert:** the alert row is written first, `notified_at` is set only after a successful send, and the bot endpoint still shows the alert.

### What was built

| File | Notes |
| --- | --- |
| `2026_09_29_070001_add_budgets_to_projects_table` | Project budget columns and `budget_alerts` |
| `app/Services/BudgetMonitor.php` | used / percent / thresholds / evaluate / `atRisk()` / `deferDuring()` |
| `app/Models/BudgetAlert.php`, `BudgetAlertMail` | Alert row and email |
| Project form / page | Budget fieldset; progress bar (indigo, yellow from 80%, red from 100%) |
| Dashboard | "Budgets at Risk": projects past their first threshold, most-burned first |
| `GET /api/bot/budgets/alerts` | At-risk projects plus alerts since `?since=` (default 7 days) for the Telegram assistant to push |
| `MarketingDemoSeeder` | DEMO-HQX has a 40-hour budget (75% used, 50% alert fired); DEMO-CRM has an amount budget |

### Tests

`tests/Feature/BudgetAlertsTest.php` (11):

- [x] 40 of 80 budgeted hours fires the 50% alert exactly once
- [x] One entry that crosses 50% and 80% fires both, in order
- [x] Dropping below and crossing again doesn't re-alert; raising the budget re-arms (changing only thresholds doesn't)
- [x] Non-billable hours count toward an hours budget but not a money budget
- [x] Soft-deleted entries are excluded
- [x] Alerts are evaluated after a CSV import commit
- [x] Bot endpoint needs a Sanctum token (401) and lists projects over threshold
- [x] Budget changes are audit-logged

---

## M6 · More Git providers ✅

**Completed:** 2026-09-29

### Decisions

- **The GitHub contract tests stayed frozen.** Before refactoring, the hashes of `CommitIngestionTest`, `SmartBatchingTest`, `ProjectGithubTest`, `GitHubConnectionTest`, `AiSummaryTest` and `SquashCommitsTest` were recorded. After the refactor they match byte for byte and all pass. That ruled out renaming anything they touch, so the generalisation is additive:
  - `github_connections` keeps its name but gains `provider`, `refresh_token` (encrypted) and `token_expires_at`. It is now unique on (user, provider, account id).
  - `projects.github_repo`, `github_branch` and `github_webhook_secret` hold the repository for whichever provider `source_provider` names; `null` means GitHub for rows linked earlier. Webhook ids are stored as strings in `source_webhook_ref`, because Bitbucket uses UUIDs; GitHub still also fills `github_webhook_id`.
  - GitHub's routes (`settings.github.*`, `projects.github.*`, `webhooks.github`) are unchanged. GitLab and Bitbucket use `settings.git.{redirect,callback}` and `webhooks.receive` at `/webhooks/{provider}/{project}`.
- **Provider interface:** `App\Contracts\SourceProvider` covers OAuth connect, token refresh, listing repos and branches, registering and removing webhooks, verifying requests, and parsing a push into normalised commits (`App\Support\PushEvent`). GitHub was moved onto it first, then GitLab and Bitbucket Cloud were added.
- **How each provider's webhook is checked:** GitHub uses the HMAC in `X-Hub-Signature-256`; GitLab compares `X-Gitlab-Token` to the secret in constant time; Bitbucket uses the HMAC in `X-Hub-Signature`. A project only accepts pushes on its own provider's route (404 otherwise).
- **Dedup key is provider + repo + SHA** (new unique index; existing rows were backfilled with their repo). Rows without a repo still match on project + SHA and get backfilled on the next push.
- **Token refresh:** GitLab and Bitbucket tokens expire, so they are refreshed just before use (`RefreshesOAuthTokens`). GitLab can be self-hosted via `GITLAB_HOST`.
- **Differences between payloads:** Bitbucket sends no file lists, so `files_changed` is `null`. Bitbucket lists commits newest-first, so they are reversed. GitLab repos can be nested (`group/subgroup/project`), so each provider has its own repo-name pattern.

### What was built

| File | Notes |
| --- | --- |
| `2026_09_29_080001_add_source_providers` | Provider columns and the dedup index (reversible) |
| `app/Contracts/SourceProvider.php`, `app/Support/PushEvent.php`, `ProviderAccount.php` | Contract and normalised types |
| `app/Services/SourceProviders/*` | `GitHubSourceProvider` (wraps `GitHubService`), `GitLabSourceProvider`, `BitbucketSourceProvider`, `SourceProviderRegistry`, `RefreshesOAuthTokens` |
| `WebhookController` | One `receive()` for every provider; `github()` delegates to it. Same branch filter, large-push threshold and queued ingest for all |
| `CommitIngestionService` | Takes normalised commits and dedups on provider / repo / SHA |
| `ProjectGithubController`, `GitHubConnectionController` | Dispatch on the connection's provider |
| Settings → Git Providers | Provider shown per connection; Connect GitLab / Bitbucket buttons (or a hint when not configured) |
| `config/services.php`, `.env.example` | `GITLAB_*`, `BITBUCKET_*` |
| `desk:prelaunch-check` | Warns when a provider in use has no OAuth credentials |

### Tests

`tests/Feature/SourceProvidersTest.php` (21), with recorded-style push fixtures in `tests/Fixtures/webhooks/`:

- [x] GitHub webhook tests frozen as contract tests; they pass unchanged afterwards (hashes checked)
- [x] For each provider, a valid request is accepted, and an invalid or missing signature is rejected with no commits stored
- [x] Push payloads from each provider normalise to the same commit shape
- [x] Pushes to untracked branches are ignored for every provider
- [x] Dedup keys on provider, repo and SHA
- [x] The large-push threshold and queued ingest work for every provider
- [x] Unlinking removes the remote webhook (`Http::fake` asserts the DELETE) for GitLab and Bitbucket
- [x] Tokens are encrypted at rest, refreshed when expired, and deleted on disconnect

Not tested against the live GitLab and Bitbucket APIs: that needs real OAuth apps.
