# Desk (Laravel) — Test Checklist

29 Sep 2026 · D.L.

A milestone ships only when its own tests and the definition of done both pass. Tests are written as behaviours so the same list becomes the acceptance suite for the Spring edition. Scope for each milestone is in `MILESTONES.md`.

## Definition of done (every milestone)

These checks guard the rules Desk already promises; copy them into each milestone's pull request.

- [ ] Every new route sits behind `auth`, `password.changed` and `twofactor.configured` (one test loops over the route list)
- [ ] In archive mode every new non-GET route is blocked (same loop)
- [ ] New models use UUID route keys; no internal ID appears in a URL
- [ ] Create, update and delete on new money or time records are audit-logged with old and new values
- [ ] Money is stored as decimal or integer minor units, never float, and rounding has a test
- [ ] The dashboard cache is invalidated by every change that affects a metric
- [ ] Migrations roll back cleanly and run against a copy of live data loaded with `desk:import-live-data`
- [ ] New config (mail, queue, scheduler, AI provider) is covered by `desk:prelaunch-check`
- [ ] `MarketingDemoSeeder` shows the feature, and the redacted demo snapshot is refreshed
- [ ] `features.md` is updated
- [ ] Pest suite green in CI; manual smoke test on the preview server before release

## M1 · Clients (release 1.1, size S)

- [ ] New clients get sequential codes `CLT-00001`, `CLT-00002`
- [ ] Client URLs resolve by UUID; a numeric ID returns 404
- [ ] Projects link and unlink from a client; projects with no client still load and report
- [ ] Choosing a project on a transaction prefills that project's client
- [ ] Billable amount follows the agreed rate precedence, and `desk:recalculate-billable-amounts` applies the same rule
- [ ] CSV import: invalid rows show reasons in preview; nothing is written before commit
- [ ] Create, update and archive are recorded in the audit log with old and new values
- [ ] In archive mode every client write is blocked

## M2 · Invoicing (release 1.1, size M)

- [ ] Numbers are sequential per year with no gaps; deleting a draft never consumes a number
- [ ] Only billable, unbilled, non-deleted entries are offered for selection
- [ ] Line, GST and invoice totals round consistently to 2 dp, and lines always sum to the total (test awkward cases such as three 20-minute entries)
- [ ] Issued invoices can't be edited
- [ ] Invoiced time entries can't be edited or deleted; voiding the invoice unlocks them
- [ ] Recording payment creates exactly one income transaction, even on double submit
- [ ] With GST registration set, the PDF shows the fields IRAS requires on a tax invoice; without it, no GST appears
- [ ] PDF is saved to S3 (`Storage::fake`) and served only through a signed URL
- [ ] Sending uses `Mail::fake`, attaches the PDF and records `sent_at`
- [ ] Create, issue, payment and void are audit-logged; all writes blocked in archive mode
- [ ] Paying an invoice invalidates the dashboard cache
- [ ] Finance report and CSV export include invoiced, paid and outstanding totals

## M3 · Weekly draft timesheet (release 1.1, size S)

- [ ] A commit at Sunday 23:30 SGT lands in that week, not the next (timestamps stored in UTC)
- [ ] Only pending commits appear: not converted, not dismissed
- [ ] Bulk convert creates one entry per commit or group, all durations multiples of 15, inside one DB transaction; one failure rolls back the batch
- [ ] A double-clicked convert never creates duplicate entries
- [ ] Dismissed commits are hidden and can be restored
- [ ] Squashing from the week view queues `SummarizeSquashedCommits`, same as the project view (`Queue::fake`)
- [ ] The reminder sends only when the pending count is above zero; the bot endpoint returns that count
- [ ] In archive mode the page is viewable and every action is blocked

## M4 · AI privacy controls (release 1.1, size S)

- [ ] On a fresh install the null summarizer is bound and no HTTP request is made (`Http::fake`, `assertNothingSent`)
- [ ] Each driver calls its own endpoint with the configured model
- [ ] The request body holds only allow-listed fields; author email and diff are never present
- [ ] Token-like strings and emails in commit messages are masked
- [ ] A project that opted out is never summarised, even with AI on
- [ ] A provider timeout or 500 leaves the commit pending without a summary, retries with backoff, then fails quietly
- [ ] Every call writes one log row holding a hash, not the payload
- [ ] A stage keyword match still skips AI
- [ ] API keys are stored encrypted and never rendered back into the settings form

## M5 · Budgets and burn alerts (release 1.1, size S)

- [ ] 40 of 80 budgeted hours fires the 50% alert exactly once
- [ ] One entry that crosses 50% and 80% fires both, in order
- [ ] Dropping below a threshold and crossing it again doesn't re-alert; raising the budget re-arms
- [ ] Non-billable hours count toward an hours budget but not a money budget
- [ ] Soft-deleted entries are excluded
- [ ] Alerts are evaluated after a CSV import commit, not just manual entry
- [ ] Bot endpoint requires a Sanctum token (401 without) and lists projects over threshold
- [ ] Budget changes are audit-logged

## M6 · More Git providers (release 1.2, size M)

- [ ] Before refactoring, freeze today's GitHub webhook tests as contract tests; they pass unchanged afterwards
- [ ] For each provider, a valid request is accepted and an invalid or missing signature is rejected with no commits stored
- [ ] Recorded push payloads from each provider normalise to the same commit shape (SHA, author, timestamp, message, branch, files changed)
- [ ] Pushes to untracked branches are ignored for every provider
- [ ] Deduplication keys on provider, repo and SHA
- [ ] The large-push threshold and queued ingest work for every provider
- [ ] Unlinking removes the remote webhook (`Http::fake` asserts the delete call)
- [ ] Tokens are encrypted at rest and deleted on disconnect

## M7 · Billing models (release 1.2, size M)

- [ ] Existing projects default to hourly and their billable amounts don't change
- [ ] Milestone amounts can't exceed the fixed fee
- [ ] A milestone can be invoiced once only
- [ ] Fixed-fee time entries follow the agreed billable rule
- [ ] The retainer draft is generated once per month; re-running the command creates nothing new
- [ ] Overage equals (hours used minus included hours) times the overage rate, and only when positive
- [ ] Rollover carries unused hours forward one month only
- [ ] Month boundaries use the system timezone
- [ ] The projects report shows revenue by billing model

## M8 · Renewals and recurring charges (release 1.2, size S)

- [ ] A monthly service renewing on the 31st moves to 28 or 29 February, then back to the 31st
- [ ] A yearly renewal on 29 February lands on 28 February in non-leap years
- [ ] Running the command twice on the same day creates one transaction
- [ ] Paused and cancelled services never generate anything
- [ ] Generated transactions are expense, out, pending, linked to the service, with its category
- [ ] Each renewal sends one reminder
- [ ] Spend report totals equal the sum of completed transactions linked to services

## M9 · Accounting export (release 1.2, size M)

- [ ] Headers and column order match each tool's template exactly (golden-file tests against a downloaded template)
- [ ] An unmapped category blocks export and lists what still needs mapping
- [ ] Dates, amounts and GST are formatted as each tool expects
- [ ] Re-exporting skips rows already exported unless "include exported" is chosen
- [ ] Voided transactions are excluded
- [ ] Exports are audit-logged
- [ ] Manual: a test import into a Xero demo company and a QuickBooks sandbox succeeds

## M10 · Saved reports and client share links (release 1.2, size M)

- [ ] Relative ranges resolve correctly across month and year boundaries in the system timezone
- [ ] A scheduled report sends once at its due time (`Mail::fake`, time travel)
- [ ] Share tokens are long and random and stored hashed; the raw token never appears in the database
- [ ] With two clients' data seeded, a link only ever shows its own client's records
- [ ] Expired or revoked links return 404, not 403
- [ ] Shared pages send noindex and no-referrer headers and never show internal IDs, costs, ledger data or internal notes
- [ ] The share endpoint is rate-limited; wrong passcodes are throttled
- [ ] Every view is logged