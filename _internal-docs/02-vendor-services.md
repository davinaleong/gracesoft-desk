# GraceSoft Desk — Vendors & Services Modules Checklist

**Modules:** Vendors, Services
**Positioning:** Digital services inventory — registry of vendors and what you use from each
**Stack:** Laravel 13 + Blade + MySQL + Bootstrap 5.3
**Architecture:** Static Blade + refresh, server-rendered, SQL-driven

---

# Phase 0 — Module Definition

## Product Framing

* [x] Define Vendors as the directory of companies and providers you deal with
* [x] Define Services as the inventory of digital services you use from each vendor
* [x] Confirm modules are standalone with no finance / billing tracking
* [x] Confirm Services always belong to a Vendor (no orphan services)
* [x] Confirm plain multiline text for notes on both modules

## Data Identity Rules (Consistent with Desk Core)

* [x] Use `id` as internal SQL PK on both tables
* [x] Add `uuid` to both tables
* [x] Use `uuid` for all route model binding
* [x] Never expose SQL `id` in UI / URLs / exports
* [x] Add `vendor_code` as the visible human-readable ref for vendors
* [x] Add `service_code` as the visible human-readable ref for services
* [x] Auto-generate `vendor_code` on create (e.g. `VND-00001`)
* [x] Auto-generate `service_code` on create (e.g. `SVC-00001`)

---

# Phase 1 — Database Schema

## Vendors Table

* [x] Create `vendors` migration
* [x] Add `id` (PK, unsigned big int)
* [x] Add `uuid` (unique, indexed)
* [x] Add `vendor_code` (unique, indexed)
* [x] Add `name`
* [x] Add `category` (enum: telco, cloud, saas, professional_services, utilities, other)
* [x] Add `website` (nullable)
* [x] Add `support_url` (nullable)
* [x] Add `account_number` (nullable — your ref with the vendor)
* [x] Add `status` (enum: active, inactive)
* [x] Add `notes` (nullable, text)
* [x] Add `deleted_at` (soft delete)
* [x] Add `created_at`, `updated_at`

## Services Table

* [x] Create `services` migration
* [x] Add `id` (PK, unsigned big int)
* [x] Add `uuid` (unique, indexed)
* [x] Add `service_code` (unique, indexed)
* [x] Add `vendor_id` (FK → vendors.id)
* [x] Add `name`
* [x] Add `plan` (nullable — e.g. "All Apps", "Business", "Free Tier")
* [x] Add `category` (enum: storage, communication, design, dev_tools, security, productivity, other)
* [x] Add `status` (enum: active, paused, cancelled)
* [x] Add `notes` (nullable, text)
* [x] Add `deleted_at` (soft delete)
* [x] Add `created_at`, `updated_at`

## Constraints & Indexing

* [x] Add FK constraint: `services.vendor_id` → `vendors.id`
* [x] Add index on `vendors.status`
* [x] Add index on `services.status`
* [x] Add index on `services.vendor_id`
* [x] Add index on `services.category`
* [x] Add unique index on `vendors.vendor_code`
* [x] Add unique index on `services.service_code`
* [x] Add unique index on `vendors.uuid`
* [x] Add unique index on `services.uuid`

---

# Phase 2 — Seeders & Reference Data

## Seeders

* [x] Seed vendor categories
* [x] Seed service categories
* [x] Seed sample vendors (e.g. Singtel, Adobe, Amazon, Cloudflare)
* [x] Seed sample services tied to sample vendors

## Static Reference Sets

* [x] Vendor category enum values
* [x] Service category enum values
* [x] Vendor status enum values
* [x] Service status enum values

---

# Phase 3 — Domain Models & Business Logic

## Models

* [x] Build `Vendor` model
* [x] Build `Service` model

## Model Rules

* [x] Add UUID generation trait to `Vendor`
* [x] Add UUID generation trait to `Service`
* [x] Add `vendor_code` auto-generation to `Vendor` on create
* [x] Add `service_code` auto-generation to `Service` on create
* [x] Override route model binding to UUID on both models
* [x] Hide SQL `id` in public-facing payloads
* [x] Add soft delete trait to both models

## Relationships

* [x] Vendor → Services (hasMany)
* [x] Service → Vendor (belongsTo)

## Scopes

* [x] `Vendor::active()` scope
* [x] `Vendor::inactive()` scope
* [x] `Service::active()` scope
* [x] `Service::byVendor()` scope
* [x] `Service::byCategory()` scope

---

# Phase 4 — Controllers & Routes

## Vendors

* [x] Create `VendorController`
* [x] Route: vendors index (`GET /vendors`)
* [x] Route: create vendor form (`GET /vendors/create`)
* [x] Route: store vendor (`POST /vendors`)
* [x] Route: edit vendor form (`GET /vendors/{uuid}/edit`)
* [x] Route: update vendor (`PUT /vendors/{uuid}`)
* [x] Route: vendor detail (`GET /vendors/{uuid}`)
* [x] Route: delete vendor (`DELETE /vendors/{uuid}`)

## Services

* [x] Create `ServiceController`
* [x] Route: services index (`GET /services`)
* [x] Route: create service form (`GET /services/create`)
* [x] Route: store service (`POST /services`)
* [x] Route: edit service form (`GET /services/{uuid}/edit`)
* [x] Route: update service (`PUT /services/{uuid}`)
* [x] Route: service detail (`GET /services/{uuid}`)
* [x] Route: delete service (`DELETE /services/{uuid}`)

## Route Rules

* [x] All routes resolve via UUID
* [x] All routes behind auth middleware
* [x] Resource route group for vendors
* [x] Resource route group for services

---

# Phase 5 — Views

## Vendors Views

* [x] `vendors/index.blade.php` — table of all vendors with status badge, category badge, vendor code
* [x] `vendors/create.blade.php` — create form
* [x] `vendors/edit.blade.php` — edit form
* [x] `vendors/show.blade.php` — vendor detail with linked services list

## Services Views

* [x] `services/index.blade.php` — table of all services with vendor name, category badge, status badge, service code
* [x] `services/create.blade.php` — create form (vendor selector dropdown)
* [x] `services/edit.blade.php` — edit form
* [x] `services/show.blade.php` — service detail with vendor reference link

## Shared View Rules

* [x] Display `vendor_code` as the visible vendor reference (mono font)
* [x] Display `service_code` as the visible service reference (mono font)
* [x] Never display SQL `id` in any view
* [x] Use UUID in all action URLs (edit, delete, show)
* [x] Status rendered as styled pill (active = green, inactive / paused = yellow, cancelled = red)
* [x] Category rendered as badge

---

# Phase 6 — Forms & Validation

## Vendor Form Fields

* [x] Name (required)
* [x] Category (required, select)
* [x] Website (optional, URL)
* [x] Support URL (optional, URL)
* [x] Account Number (optional, plain text)
* [x] Status (required, select — default: active)
* [x] Notes (optional, textarea)

## Service Form Fields

* [x] Vendor (required, select — resolved by UUID)
* [x] Name (required)
* [x] Plan / Tier (optional, plain text)
* [x] Category (required, select)
* [x] Status (required, select — default: active)
* [x] Notes (optional, textarea)

## Validation Rules

* [x] Create `StoreVendorRequest`
* [x] Create `UpdateVendorRequest`
* [x] Create `StoreServiceRequest`
* [x] Create `UpdateServiceRequest`
* [x] Validate name required on both
* [x] Validate category is valid enum on both
* [x] Validate status is valid enum on both
* [x] Validate website and support URL as valid URL format when provided
* [x] Validate vendor exists (by UUID) on service create / update

---

# Phase 7 — UX Polish

## Vendors

* [x] Empty state for vendors index (no vendors yet)
* [x] Delete confirmation for vendor (warn if vendor has active services)
* [x] Flash success on create / update / delete
* [x] Vendor detail shows services count badge
* [x] Vendor detail shows linked services in a table

## Services

* [x] Empty state for services index (no services yet)
* [x] Empty state within vendor detail (no services for this vendor)
* [x] Delete confirmation for service
* [x] Flash success on create / update / delete
* [x] Service detail shows vendor name as a link to vendor detail

## Shared UX

* [x] Consistent pagination on both index tables
* [x] Filter by status on vendors index
* [x] Filter by status on services index
* [x] Filter by vendor on services index
* [x] Filter by category on services index
* [x] "Add new" shortcut button on both index pages
* [x] After-create prompt: offer to create another (consistent with `99-todo.md` UX pattern)

---

# Phase 8 — Sidebar & Navigation

* [x] Add Vendors link to sidebar
* [x] Add Services link to sidebar
* [x] Group under a "Registry" or "Directory" sidebar section
* [x] Set active state on sidebar links for both modules

---

# Phase 9 — Audit & Safety

## Audit

* [ ] Log create events for vendors
* [ ] Log update events for vendors
* [ ] Log delete events for vendors
* [ ] Log create events for services
* [ ] Log update events for services
* [ ] Log delete events for services

## Safety

* [x] Soft delete on both models (no hard deletes from UI)
* [x] Prevent vendor deletion if it has active services (warn, require cancellation first)
* [x] Destructive action confirmations on all delete buttons

---

# Phase 10 — Brand & QA

## Brand Consistency

* [x] Matches Desk card hierarchy
* [x] Matches Desk table density
* [x] Matches Desk typography (Montserrat / Playfair Display)
* [x] Matches Desk badge and pill patterns
* [x] Matches Desk form layout and input styling
* [x] `vendor_code` and `service_code` rendered in Source Code Pro (consistent with `transaction_code` / `projects.code`)

## Module QA

* [x] Vendors CRUD fully functional
* [x] Services CRUD fully functional
* [x] Vendor → Services relationship renders correctly on vendor detail
* [x] Service → Vendor link renders correctly on service detail
* [x] All routes resolve via UUID

---

# Phase 1 — Database Schema

## Vendors Table

* [ ] Create `vendors` migration
* [ ] Add `id` (PK, unsigned big int)
* [ ] Add `uuid` (unique, indexed)
* [ ] Add `vendor_code` (unique, indexed)
* [ ] Add `name`
* [ ] Add `category` (enum: telco, cloud, saas, professional_services, utilities, other)
* [ ] Add `website` (nullable)
* [ ] Add `support_url` (nullable)
* [ ] Add `account_number` (nullable — your ref with the vendor)
* [ ] Add `status` (enum: active, inactive)
* [ ] Add `notes` (nullable, text)
* [ ] Add `deleted_at` (soft delete)
* [ ] Add `created_at`, `updated_at`

## Services Table

* [ ] Create `services` migration
* [ ] Add `id` (PK, unsigned big int)
* [ ] Add `uuid` (unique, indexed)
* [ ] Add `service_code` (unique, indexed)
* [ ] Add `vendor_id` (FK → vendors.id)
* [ ] Add `name`
* [ ] Add `plan` (nullable — e.g. "All Apps", "Business", "Free Tier")
* [ ] Add `category` (enum: storage, communication, design, dev_tools, security, productivity, other)
* [ ] Add `status` (enum: active, paused, cancelled)
* [ ] Add `notes` (nullable, text)
* [ ] Add `deleted_at` (soft delete)
* [ ] Add `created_at`, `updated_at`

## Constraints & Indexing

* [ ] Add FK constraint: `services.vendor_id` → `vendors.id`
* [ ] Add index on `vendors.status`
* [ ] Add index on `services.status`
* [ ] Add index on `services.vendor_id`
* [ ] Add index on `services.category`
* [ ] Add unique index on `vendors.vendor_code`
* [ ] Add unique index on `services.service_code`
* [ ] Add unique index on `vendors.uuid`
* [ ] Add unique index on `services.uuid`

---

# Phase 2 — Seeders & Reference Data

## Seeders

* [ ] Seed vendor categories
* [ ] Seed service categories
* [ ] Seed sample vendors (e.g. Singtel, Adobe, Amazon, Cloudflare)
* [ ] Seed sample services tied to sample vendors

## Static Reference Sets

* [ ] Vendor category enum values
* [ ] Service category enum values
* [ ] Vendor status enum values
* [ ] Service status enum values

---

# Phase 3 — Domain Models & Business Logic

## Models

* [ ] Build `Vendor` model
* [ ] Build `Service` model

## Model Rules

* [ ] Add UUID generation trait to `Vendor`
* [ ] Add UUID generation trait to `Service`
* [ ] Add `vendor_code` auto-generation to `Vendor` on create
* [ ] Add `service_code` auto-generation to `Service` on create
* [ ] Override route model binding to UUID on both models
* [ ] Hide SQL `id` in public-facing payloads
* [ ] Add soft delete trait to both models

## Relationships

* [ ] Vendor → Services (hasMany)
* [ ] Service → Vendor (belongsTo)

## Scopes

* [ ] `Vendor::active()` scope
* [ ] `Vendor::inactive()` scope
* [ ] `Service::active()` scope
* [ ] `Service::byVendor()` scope
* [ ] `Service::byCategory()` scope

---

# Phase 4 — Controllers & Routes

## Vendors

* [ ] Create `VendorController`
* [ ] Route: vendors index (`GET /vendors`)
* [ ] Route: create vendor form (`GET /vendors/create`)
* [ ] Route: store vendor (`POST /vendors`)
* [ ] Route: edit vendor form (`GET /vendors/{uuid}/edit`)
* [ ] Route: update vendor (`PUT /vendors/{uuid}`)
* [ ] Route: vendor detail (`GET /vendors/{uuid}`)
* [ ] Route: delete vendor (`DELETE /vendors/{uuid}`)

## Services

* [ ] Create `ServiceController`
* [ ] Route: services index (`GET /services`)
* [ ] Route: create service form (`GET /services/create`)
* [ ] Route: store service (`POST /services`)
* [ ] Route: edit service form (`GET /services/{uuid}/edit`)
* [ ] Route: update service (`PUT /services/{uuid}`)
* [ ] Route: service detail (`GET /services/{uuid}`)
* [ ] Route: delete service (`DELETE /services/{uuid}`)

## Route Rules

* [ ] All routes resolve via UUID
* [ ] All routes behind auth middleware
* [ ] Resource route group for vendors
* [ ] Resource route group for services

---

# Phase 5 — Views

## Vendors Views

* [ ] `vendors/index.blade.php` — table of all vendors with status badge, category badge, vendor code
* [ ] `vendors/create.blade.php` — create form
* [ ] `vendors/edit.blade.php` — edit form
* [ ] `vendors/show.blade.php` — vendor detail with linked services list

## Services Views

* [ ] `services/index.blade.php` — table of all services with vendor name, category badge, status badge, service code
* [ ] `services/create.blade.php` — create form (vendor selector dropdown)
* [ ] `services/edit.blade.php` — edit form
* [ ] `services/show.blade.php` — service detail with vendor reference link

## Shared View Rules

* [ ] Display `vendor_code` as the visible vendor reference (mono font)
* [ ] Display `service_code` as the visible service reference (mono font)
* [ ] Never display SQL `id` in any view
* [ ] Use UUID in all action URLs (edit, delete, show)
* [ ] Status rendered as styled pill (active = green, inactive / paused = yellow, cancelled = red)
* [ ] Category rendered as badge

---

# Phase 6 — Forms & Validation

## Vendor Form Fields

* [ ] Name (required)
* [ ] Category (required, select)
* [ ] Website (optional, URL)
* [ ] Support URL (optional, URL)
* [ ] Account Number (optional, plain text)
* [ ] Status (required, select — default: active)
* [ ] Notes (optional, textarea)

## Service Form Fields

* [ ] Vendor (required, select — resolved by UUID)
* [ ] Name (required)
* [ ] Plan / Tier (optional, plain text)
* [ ] Category (required, select)
* [ ] Status (required, select — default: active)
* [ ] Notes (optional, textarea)

## Validation Rules

* [ ] Create `StoreVendorRequest`
* [ ] Create `UpdateVendorRequest`
* [ ] Create `StoreServiceRequest`
* [ ] Create `UpdateServiceRequest`
* [ ] Validate name required on both
* [ ] Validate category is valid enum on both
* [ ] Validate status is valid enum on both
* [ ] Validate website and support URL as valid URL format when provided
* [ ] Validate vendor exists (by UUID) on service create / update

---

# Phase 7 — UX Polish

## Vendors

* [ ] Empty state for vendors index (no vendors yet)
* [ ] Delete confirmation for vendor (warn if vendor has active services)
* [ ] Flash success on create / update / delete
* [ ] Vendor detail shows services count badge
* [ ] Vendor detail shows linked services in a table

## Services

* [ ] Empty state for services index (no services yet)
* [ ] Empty state within vendor detail (no services for this vendor)
* [ ] Delete confirmation for service
* [ ] Flash success on create / update / delete
* [ ] Service detail shows vendor name as a link to vendor detail

## Shared UX

* [ ] Consistent pagination on both index tables
* [ ] Filter by status on vendors index
* [ ] Filter by status on services index
* [ ] Filter by vendor on services index
* [ ] Filter by category on services index
* [ ] "Add new" shortcut button on both index pages
* [ ] After-create prompt: offer to create another (consistent with `99-todo.md` UX pattern)

---

# Phase 8 — Sidebar & Navigation

* [ ] Add Vendors link to sidebar
* [ ] Add Services link to sidebar
* [ ] Group under a "Registry" or "Directory" sidebar section
* [ ] Set active state on sidebar links for both modules

---

# Phase 9 — Audit & Safety

## Audit

* [ ] Log create events for vendors
* [ ] Log update events for vendors
* [ ] Log delete events for vendors
* [ ] Log create events for services
* [ ] Log update events for services
* [ ] Log delete events for services

## Safety

* [ ] Soft delete on both models (no hard deletes from UI)
* [ ] Prevent vendor deletion if it has active services (warn, require cancellation first)
* [ ] Destructive action confirmations on all delete buttons

---

# Phase 10 — Brand & QA

## Brand Consistency

* [ ] Matches Desk card hierarchy
* [ ] Matches Desk table density
* [ ] Matches Desk typography (Montserrat / Playfair Display)
* [ ] Matches Desk badge and pill patterns
* [ ] Matches Desk form layout and input styling
* [ ] `vendor_code` and `service_code` rendered in Source Code Pro (consistent with `transaction_code` / `projects.code`)

## Module QA

* [ ] Vendors CRUD fully functional
* [ ] Services CRUD fully functional
* [ ] Vendor → Services relationship renders correctly on vendor detail
* [ ] Service → Vendor link renders correctly on service detail
* [ ] All routes resolve via UUID
* [ ] No SQL `id` exposed anywhere in UI or URLs
* [ ] Soft deletes working on both
* [ ] Audit logs recording on both
* [ ] Filters working on both index pages

---

# Phase 11 — Category Management Panel (Follow-up, TODO item)

**Completed:** 2026-09-02

Implements the outstanding TODO item "Panel: Manage vendor and service categories" —
categories were previously hardcoded DB enums with no admin UI to add, rename, or
retire them.

## Schema change

* [x] New `categories` table: `id, uuid, type (vendor|service), name, code (nullable, legacy-migration key), status (active|inactive), timestamps`, unique on `(type, name)`
* [x] `2026_09_02_000001_create_categories_table` — creates the table and seeds it with the original enum values (6 vendor categories, 7 service categories), preserving each original value in `code` for the backfill step
* [x] `2026_09_02_000002_migrate_vendor_and_service_categories_to_lookup_table` — adds nullable `category_id` (FK → `categories`, `nullOnDelete`) to `vendors` and `services`, backfills every existing row by matching its old `category` string to the seeded row via `code`, then drops the old enum column. Reversible (`down()` recreates the enum columns and backfills from `categories.code`).
* [x] `category_id` is nullable specifically so `nullOnDelete` can apply — deleting a category in use should not be reachable via the UI (see destroy guard below), but the FK is a safety net rather than a hard block

## Model & relations

* [x] `App\Models\Category` — `HasPublicUuid`, `scopeOfType`, `scopeActive`, `hasMany` to both `Vendor` and `Service`
* [x] `Vendor::category()` / `Service::category()` — `belongsTo(Category::class)`, replacing the old plain string attribute
* [x] `Service::scopeByCategory()` now filters by `category_id` (int) instead of the old enum string

## Settings panel (`/settings/categories`)

* [x] `CategoryController` — index (grouped into Vendor Categories / Service Categories, both listed alphabetically), create (type fixed via `?type=vendor|service` query param), store, edit, update, destroy
* [x] Type is immutable after creation — the edit form only allows renaming and active/inactive toggling, never moving a category between vendor and service
* [x] `destroy()` blocks deletion (flashes `category-in-use`) when any vendor or service still references the category
* [x] No manual reordering (no `sort_order`/move-up/move-down) — unlike `ProjectStage`, category order has no functional dependency (no keyword-priority matching), so categories just list alphabetically; adding reorder UI here would be unused surface area
* [x] Routes, views (`settings/categories/{index,create,edit}.blade.php`), and "Categories" nav link (under Registry, desktop + mobile) follow the same conventions as the Milestone 1 `ProjectStage` settings page

## Vendor/Service forms & listings

* [x] `vendors._form` / `services._form` — category `<select>` now populated from `Category::ofType(...)->active()`, submitting `category_id` instead of a hardcoded string list
* [x] Edit forms additionally include the record's *current* category even if it has since been deactivated, so an existing assignment is never silently dropped from the dropdown
* [x] All category badges (index/show pages, vendor's linked-services table) read `$model->category?->name` with a `—` fallback for a null relation
* [x] Services index category filter (`?category_id=`) now built from the live `categories` table instead of a hardcoded array

## Data fixed up

* [x] `VendorServiceSeeder` and `MarketingDemoSeeder::seedVendorsAndServices()` updated to resolve `category_id` from the seeded `categories` rows (by `code`) instead of passing enum strings
* [x] Verified against the real local dev database (not just the sqlite test DB): ran the two new migrations, confirmed all 8 existing demo vendors/services backfilled to the correct category by name, and confirmed `migrate:rollback --step=2` cleanly reverses both migrations on a throwaway sqlite file

## Tests

* [x] `tests/Feature/CategoriesCrudTest.php` — 10 tests: grouped index listing, vendor category create, service category create, same name allowed across types but not within a type, update, delete when unused, delete blocked when referenced by a vendor, delete blocked when referenced by a service, UUID-only routing, auth guard
* [x] `tests/Feature/VendorsCrudTest.php` / `ServicesCrudTest.php` updated to create real `Category` rows via the new `CategoryFactory` instead of passing enum strings, plus new tests asserting a vendor can't be saved with a service-type category (and vice versa) — enforced by `Rule::exists('categories', 'id')->where('type', ...)` in the form requests
* [x] Full suite: 226 passing (up from 213 before this change)

## Known pre-existing issue (not touched)

This file (`02-vendor-services.md`) contains a duplicated checklist — Phases 1–10
appear twice, the first pass fully checked off, the second an unchecked copy
starting again at line 304. That duplication predates this change and wasn't
part of the TODO item being implemented, so it was left as-is rather than
edited under an unrelated task.
