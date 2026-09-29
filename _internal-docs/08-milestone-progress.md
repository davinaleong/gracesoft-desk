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
