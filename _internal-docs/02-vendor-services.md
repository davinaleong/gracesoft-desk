# GraceSoft Desk — Vendors & Services Modules Checklist

**Modules:** Vendors, Services
**Positioning:** Digital services inventory — registry of vendors and what you use from each
**Stack:** Laravel 13 + Blade + MySQL + Bootstrap 5.3
**Architecture:** Static Blade + refresh, server-rendered, SQL-driven

---

# Phase 0 — Module Definition

## Product Framing

* [ ] Define Vendors as the directory of companies and providers you deal with
* [ ] Define Services as the inventory of digital services you use from each vendor
* [ ] Confirm modules are standalone with no finance / billing tracking
* [ ] Confirm Services always belong to a Vendor (no orphan services)
* [ ] Confirm plain multiline text for notes on both modules

## Data Identity Rules (Consistent with Desk Core)

* [ ] Use `id` as internal SQL PK on both tables
* [ ] Add `uuid` to both tables
* [ ] Use `uuid` for all route model binding
* [ ] Never expose SQL `id` in UI / URLs / exports
* [ ] Add `vendor_code` as the visible human-readable ref for vendors
* [ ] Add `service_code` as the visible human-readable ref for services
* [ ] Auto-generate `vendor_code` on create (e.g. `VND-00001`)
* [ ] Auto-generate `service_code` on create (e.g. `SVC-00001`)

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
