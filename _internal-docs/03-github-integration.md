# GitHub-Driven Time Entries — Milestone Checklist

## Milestone 0: Schema audit (do first)

- [x] Inspect current `time_entries` table — confirm whether `stage` is a free string or already a foreign key
- [x] Inspect current `projects` table — confirm existing billable field (boolean vs rate-based)
- [x] Confirm whether `billable` on the Time Entries table is a flag or a computed currency amount (duration × rate)
- [x] Document findings before touching migrations

## Milestone 1: SDLC Stages (Settings)

- [x] Create `sdlc_stages` migration: `id, uuid, name, slug, keywords (json), sort_order, is_default, timestamps`
- [x] If `time_entries.stage` is currently a free string, write backfill migration mapping existing values (Development, Testing, Deployment, Maintenance, Analysis, etc.) to new `sdlc_stages` rows
- [x] Add `stage_id` foreign key to `time_entries`, deprecate/drop old string column after backfill
- [x] Build Settings → SDLC Stages CRUD page (list, reorder, add/edit/delete, keyword tags per stage)
- [x] Tests: seeding, reordering, keyword storage, backfill migration correctness

## Milestone 2: GitHub connection

- [x] Install/configure Laravel Socialite (GitHub driver)
- [x] `github_connections` table (encrypted token storage)
- [x] Settings page: "Connect GitHub" / "Disconnect" UI + connection status
- [x] Tests: OAuth callback handling, token encryption, disconnect flow

## Milestone 3: Project ↔ Repo linking

- [x] Add `github_repo`, `github_webhook_secret` (nullable) to `projects`
- [x] Add `default_hourly_rate` / confirm billable field per Milestone 0 findings
- [x] Repo picker UI on project form (calls GitHub API using stored token)
- [x] Auto-register webhook (`push` event) on repo link; remove webhook on unlink
- [x] Tests: webhook registration/removal, repo picker with mocked GitHub API

## Milestone 4: Commit ingestion (no AI yet)

- [x] `commit_time_entries` table (raw commit capture + status field)
- [x] Webhook endpoint `POST /webhooks/github/{project_uuid}` with HMAC signature verification
- [x] Parse `push` payload, upsert commit rows as `pending`
- [x] Fetch commit stats (additions/deletions) via GitHub API where not in payload
- [x] Tests: signature verification (valid/invalid), payload parsing, duplicate commit handling

## Milestone 5: Rule-based stage matching + manual review screen

- [x] Keyword matcher: commit message/branch → `sdlc_stages.keywords`, ordered by `sort_order`
- [x] Review screen `/projects/{project}/pending-commits` — reuse Time Entries table layout/columns
- [x] Action: convert single commit → `time_entries` row (stage prefilled, billable from project, duration manual)
- [x] Duration input snaps to existing increments (5/15-min steps) to match table conventions
- [x] Tests: keyword matching accuracy, manual conversion flow, duration snapping

## Milestone 6: AI summary + AI stage fallback

- [x] `CommitSummarizer` interface (swappable AI provider)
- [x] Job: generate human-readable note from commit message(s)/diff stats
- [x] AI stage fallback: only invoked when no keyword match; passes defined stage list to LLM
- [x] Unmatched-and-unresolved commits stay `assigned_stage_id = null` for manual review (no silent guessing)
- [x] Tests: summarizer interface contract, fallback trigger conditions, provider mocking

## Milestone 7: Squash UI

- [x] Multi-select commits on review screen → "Squash into one entry"
- [x] Squash action: create one `time_entries` row, mark children `processed` with `squashed_into` set
- [x] AI summary runs on squashed set (not per-commit) for a coherent note
- [x] Tests: squash grouping, child-row state transitions, duration handling on squash

## Milestone 8: Smart batching/queueing

- [x] Rolling commits-per-push stat per project (last N pushes)
- [x] Threshold logic (avg + 2×stddev, fallback constant for cold start)
- [x] Large pushes dispatched to queued batch job instead of sync processing
- [x] Large pushes default to review screen as suggested squash group
- [x] Tests: threshold calculation, queue dispatch, cold-start fallback

## Open questions to resolve before Milestone 1

- [x] Is `stage` on `time_entries` already normalized, or free text? → **FK to `project_stages`**
- [x] Is `billable` a boolean flag or a computed rate × duration amount? → **Both: `is_billable` flag + `billable_amount` computed field**
- [x] What AI provider should `CommitSummarizer` target by default? → **OpenAI** (`gpt-4o-mini`), swappable via the `CommitSummarizer` interface; falls back to `NullCommitSummarizer` when no API key is configured.
