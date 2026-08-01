# GitHub-Driven Time Entries — Milestone Checklist

## Milestone 0: Schema audit (do first)

- [ ] Inspect current `time_entries` table — confirm whether `stage` is a free string or already a foreign key
- [ ] Inspect current `projects` table — confirm existing billable field (boolean vs rate-based)
- [ ] Confirm whether `billable` on the Time Entries table is a flag or a computed currency amount (duration × rate)
- [ ] Document findings before touching migrations

## Milestone 1: SDLC Stages (Settings)

- [ ] Create `sdlc_stages` migration: `id, uuid, name, slug, keywords (json), sort_order, is_default, timestamps`
- [ ] If `time_entries.stage` is currently a free string, write backfill migration mapping existing values (Development, Testing, Deployment, Maintenance, Analysis, etc.) to new `sdlc_stages` rows
- [ ] Add `stage_id` foreign key to `time_entries`, deprecate/drop old string column after backfill
- [ ] Build Settings → SDLC Stages CRUD page (list, reorder, add/edit/delete, keyword tags per stage)
- [ ] Tests: seeding, reordering, keyword storage, backfill migration correctness

## Milestone 2: GitHub connection

- [ ] Install/configure Laravel Socialite (GitHub driver)
- [ ] `github_connections` table (encrypted token storage)
- [ ] Settings page: "Connect GitHub" / "Disconnect" UI + connection status
- [ ] Tests: OAuth callback handling, token encryption, disconnect flow

## Milestone 3: Project ↔ Repo linking

- [ ] Add `github_repo`, `github_webhook_secret` (nullable) to `projects`
- [ ] Add `default_hourly_rate` / confirm billable field per Milestone 0 findings
- [ ] Repo picker UI on project form (calls GitHub API using stored token)
- [ ] Auto-register webhook (`push` event) on repo link; remove webhook on unlink
- [ ] Tests: webhook registration/removal, repo picker with mocked GitHub API

## Milestone 4: Commit ingestion (no AI yet)

- [ ] `commit_time_entries` table (raw commit capture + status field)
- [ ] Webhook endpoint `POST /webhooks/github/{project_uuid}` with HMAC signature verification
- [ ] Parse `push` payload, upsert commit rows as `pending`
- [ ] Fetch commit stats (additions/deletions) via GitHub API where not in payload
- [ ] Tests: signature verification (valid/invalid), payload parsing, duplicate commit handling

## Milestone 5: Rule-based stage matching + manual review screen

- [ ] Keyword matcher: commit message/branch → `sdlc_stages.keywords`, ordered by `sort_order`
- [ ] Review screen `/projects/{project}/pending-commits` — reuse Time Entries table layout/columns
- [ ] Action: convert single commit → `time_entries` row (stage prefilled, billable from project, duration manual)
- [ ] Duration input snaps to existing increments (5/15-min steps) to match table conventions
- [ ] Tests: keyword matching accuracy, manual conversion flow, duration snapping

## Milestone 6: AI summary + AI stage fallback

- [ ] `CommitSummarizer` interface (swappable AI provider)
- [ ] Job: generate human-readable note from commit message(s)/diff stats
- [ ] AI stage fallback: only invoked when no keyword match; passes defined stage list to LLM
- [ ] Unmatched-and-unresolved commits stay `assigned_stage_id = null` for manual review (no silent guessing)
- [ ] Tests: summarizer interface contract, fallback trigger conditions, provider mocking

## Milestone 7: Squash UI

- [ ] Multi-select commits on review screen → "Squash into one entry"
- [ ] Squash action: create one `time_entries` row, mark children `processed` with `squashed_into` set
- [ ] AI summary runs on squashed set (not per-commit) for a coherent note
- [ ] Tests: squash grouping, child-row state transitions, duration handling on squash

## Milestone 8: Smart batching/queueing

- [ ] Rolling commits-per-push stat per project (last N pushes)
- [ ] Threshold logic (avg + 2×stddev, fallback constant for cold start)
- [ ] Large pushes dispatched to queued batch job instead of sync processing
- [ ] Large pushes default to review screen as suggested squash group
- [ ] Tests: threshold calculation, queue dispatch, cold-start fallback

## Open questions to resolve before Milestone 1

- [ ] Is `stage` on `time_entries` already normalized, or free text?
- [ ] Is `billable` a boolean flag or a computed rate × duration amount?
- [ ] What AI provider should `CommitSummarizer` target by default?
