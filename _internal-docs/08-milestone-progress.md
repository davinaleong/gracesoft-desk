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
