# GraceSoft Desk — Full Updated Milestone Checklist

**Product:** GraceSoft Desk
**Positioning:** SQL-powered internal operations desk (Excel → SQL cutover)
**Stack:** Laravel 12 + Blade + MySQL + Bootstrap 5.3 + ApexCharts
**Architecture:** Static Blade + refresh, server-rendered, SQL-driven dashboards

---

# Phase 0 — Product Definition & Architecture

## Product Framing

* [ ] Define GraceSoft Desk as the lightweight ops + finance sibling of GraceSoft HQ
* [ ] Position product as:

  * [ ] Excel-to-SQL operational cutover
  * [ ] single-seat internal admin tool
  * [ ] finance + project + reporting cockpit
* [ ] Finalize module scope:

  * [ ] Finance
  * [ ] Projects
  * [ ] Time Entries
  * [ ] Reports
  * [ ] Settings

## Technical Direction

* [ ] Confirm static Blade + refresh architecture
* [ ] Confirm no SPA / no API-first architecture
* [ ] Confirm MySQL as reporting engine
* [ ] Confirm SQL-first aggregation strategy
* [ ] Confirm Blade-first UI strategy
* [ ] Confirm ApexCharts for visualizations
* [ ] Confirm Laravel Excel for imports / exports

## Core Data Rules (Non-Negotiable)

* [x] Keep `id` as internal SQL PK on all core tables
* [x] Add `uuid` to all public-facing tables
* [x] Use `uuid` for route model binding
* [x] Never expose SQL `id` in UI / URLs / exports
* [x] Use `uuid` for forms, URLs, route actions, exports
* [x] Use `id` for joins, FKs, indexing, SQL performance
* [x] Add UUID generation to all public models
* [x] Enforce UUID route binding globally
* [x] Add `transaction_code` to transactions
* [x] Use `transaction_code` as visible transaction identifier
* [x] Keep `projects.code` manually controlled
* [x] Never auto-generate project codes
* [x] Auto-generate UUIDs on create
* [x] Auto-generate `transaction_code` on create

---

# Phase 1 — Project Setup & Foundation

## Laravel Foundation

* [x] Create Laravel 12 project
* [ ] Configure environment
* [x] Configure MySQL
* [ ] Setup app branding:

  * [ ] app name
  * [ ] favicon
  * [ ] logo
  * [ ] metadata
* [ ] Configure timezone / locale
* [ ] Configure mail
* [ ] Configure queues (optional)
* [ ] Configure logging

## Frontend Foundation

* [x] Install Bootstrap 5.3
* [x] Setup SCSS / CSS build
* [x] Setup app JS bundle
* [x] Install ApexCharts
* [x] Setup asset pipeline
* [x] Add Montserrat
* [x] Add Playfair Display
* [x] Add Source Code Pro

## Security Foundation

* [x] Install Laravel Breeze (Blade)
* [x] Remove registration routes
* [x] Remove forgot password flow (or restrict)
* [x] Disable public user creation
* [x] Configure auth guard
* [x] Configure session security
* [x] Configure login throttling

---

# Phase 2 — Auth & Security System

## Single-Seat Admin Auth

* [x] Create single-user auth rules
* [x] Enforce only one user may exist
* [x] Prevent second user creation
* [x] Remove user CRUD
* [x] Seed default admin account

## Seeded Admin Security

* [x] Create `AdminUserSeeder`
* [x] Seed one default admin
* [x] Seed default temp password
* [x] Set `must_change_password = true`

## Forced Password Rotation

* [x] Add `must_change_password` to users table
* [x] Add `password_changed_at`
* [x] Create force password change screen
* [x] Create password rotation flow
* [x] Block dashboard until password updated
* [x] Add `EnsurePasswordChanged` middleware

## Mandatory QR 2FA

* [x] Install Fortify 2FA
* [x] Enable TOTP
* [x] Enable QR setup
* [x] Enable recovery codes
* [x] Add `two_factor_confirmed_at`
* [x] Create QR setup screen
* [x] Create TOTP confirm flow
* [x] Block dashboard until 2FA confirmed
* [x] Add `EnsureTwoFactorIsConfigured` middleware

## Auth UX

* [ ] Branded login screen
* [ ] Forced password setup screen
* [x] Mandatory 2FA setup screen
* [x] Security completion gate
* [x] Logout flow

---

# Phase 3 — Core Database Schema

## Schema Design

* [ ] Finalize normalized schema
* [ ] Define naming conventions
* [x] Define FK rules
* [x] Define enum strategy
* [x] Define soft delete strategy
* [ ] Define audit strategy
* [x] Define UUID strategy
* [x] Define public vs internal identifier rules

## Tables — Core

* [x] Create `users`
* [x] Create `projects`
* [x] Create `project_stages`
* [x] Create `time_entries`
* [x] Create `transaction_categories`
* [x] Create `payment_methods`
* [x] Create `accounts`
* [x] Create `transactions`

## Core Table Rules

* [x] All public-facing tables use `id` + `uuid`
* [x] All public-facing tables index `uuid`
* [x] All public-facing tables keep integer `id`
* [x] All public-facing tables use timestamps
* [x] Never expose SQL `id` publicly

## Constraints & Indexing

* [x] Add unique constraints
* [x] Add FK constraints
* [x] Add composite indexes
* [x] Add reporting indexes
* [x] Add transaction date indexes
* [x] Add project aggregation indexes
* [x] Add UUID indexes
* [x] Add `transaction_code` unique index
* [x] Add `projects.code` unique index

---

# Phase 4 — Seeders & Static Reference Data

## Seeders

* [x] Seed project stages
* [x] Seed transaction categories
* [x] Seed payment methods
* [x] Seed accounts
* [x] Seed starter projects
* [x] Seed admin user

## Static Reference Sets

* [x] SDLC stages
* [x] transaction types
* [x] transaction statuses
* [x] payment methods
* [x] account types
* [x] starter project codes

---

# Phase 5 — Domain Models & Business Logic

## Models

* [x] Build User model
* [x] Build Project model
* [x] Build ProjectStage model
* [x] Build TimeEntry model
* [x] Build Transaction model
* [x] Build TransactionCategory model
* [x] Build PaymentMethod model
* [x] Build Account model

## Model Rules

* [x] Add UUID generation trait to all public models
* [x] Override route model binding to UUID
* [x] Hide SQL `id` in public-facing payloads where appropriate

## Relationships

* [x] Project → TimeEntries
* [x] TimeEntry → Project
* [x] TimeEntry → Stage
* [x] Transaction → Category
* [x] Transaction → PaymentMethod
* [x] Transaction → Account

## Domain Rules

* [x] One-user enforcement
* [ ] Duration calculation
* [x] Billable cost calculation
* [ ] GST calculation
* [x] Net amount calculation
* [x] transaction type validation
* [ ] money in/out integrity rules
* [x] manual project code enforcement
* [x] auto transaction code generation

---

# Phase 6 — App Shell & Design System (UI)

## Design Tokens

* [ ] Create design tokens
* [ ] Define font tokens
* [ ] Define color tokens
* [ ] Define spacing scale
* [ ] Define radius scale
* [ ] Define shadows
* [ ] Define semantic colors

## Global Styles

* [ ] Build app styles
* [ ] Normalize typography
* [ ] Normalize forms
* [ ] Normalize tables
* [ ] Normalize buttons
* [ ] Normalize cards

## Layout Shell

* [ ] Build `layouts/app.blade.php`
* [ ] Build `layouts/auth.blade.php`
* [ ] Build sidebar
* [ ] Build topbar
* [ ] Build flash message zone
* [ ] Build content shell

---

# Phase 7 — Reusable UI Components

## Core Components

* [ ] KPI card
* [ ] chart card
* [ ] table card
* [ ] stat card
* [ ] insight card

## UI Elements

* [ ] buttons
* [ ] badges
* [ ] status pills
* [ ] alerts
* [ ] forms
* [ ] inputs
* [ ] selects
* [ ] date fields
* [ ] textareas

## Data Components

* [ ] standard table
* [ ] dense finance table
* [ ] empty states
* [ ] pagination footer

---

# Phase 8 — CRUD Modules

## Projects

* [x] Projects index
* [x] Create project
* [x] Edit project
* [x] Project detail

## Time Entries

* [x] Time entries index
* [x] Create time entry
* [x] Edit time entry
* [x] Delete time entry

## Transactions

* [x] Transactions index
* [x] Create transaction
* [x] Edit transaction
* [x] View transaction

## Settings

* [x] Profile & Security
* [x] 2FA management
* [x] system settings

## CRUD Rules

* [x] All CRUD routes resolve via UUID
* [ ] All UI tables display human-readable references
* [x] Projects display `code`
* [x] Transactions display `transaction_code`
* [ ] Never expose SQL `id` in forms or views

---

# Phase 9 — Dashboard Query Layer

## Reporting Services

* [ ] Build DashboardService
* [x] Build FinanceReportService
* [x] Build ProjectReportService
* [x] Build LedgerSummaryService

## SQL Aggregation

* [x] Monthly cashflow query
* [x] Expense breakdown query
* [x] Income breakdown query
* [x] Pending / outstanding query
* [x] Project overview query
* [x] Billable by project query
* [x] Billable by stage query

## Optimization

* [ ] Add reporting indexes
* [ ] Add query scopes
* [ ] Add summary caching
* [ ] Optimize aggregate queries

---

# Phase 10 — Dashboard UI

## Dashboard Shell

* [x] Dashboard header
* [x] reporting month badge
* [x] KPI row

## Finance Widgets

* [x] Monthly cashflow chart
* [x] Expense breakdown donut
* [x] Income breakdown donut
* [x] Pending / outstanding table

## Project Widgets

* [x] Total projects card
* [x] Total logged hours card
* [x] Total billable value card
* [x] Project overview table
* [x] Billable by project donut
* [x] Billable by stage chart

---

# Phase 11 — Reports Module

## Reports

* [x] Finance reports page
* [x] Project reports page
* [x] Monthly summary page
* [x] report filters
* [x] printable summaries

---

# Phase 12 — Imports / Exports

## Import

* [ ] Import projects CSV
* [ ] Import time entries CSV
* [ ] Import transactions CSV
* [ ] import validation
* [ ] import preview
* [ ] import mapping
* [ ] ignore incoming SQL IDs
* [ ] map by UUID where valid
* [ ] generate UUID where absent
* [ ] map projects by manual `code`
* [ ] map transactions by `transaction_code`

## Export

* [x] Export CSV
* [x] Export finance report
* [x] Export project report
* [x] Export monthly summary
* [x] never export SQL `id`
* [x] export UUID only where needed
* [x] export `transaction_code` as transaction reference
* [x] export `projects.code` as project reference

---

# Phase 13 — UX Polish

## UX

* [x] flash alerts
* [ ] validation states
* [ ] success states
* [ ] empty states
* [ ] delete confirmations
* [ ] loading states
* [ ] keyboard accessibility
* [ ] table overflow handling

## Data Formatting

* [ ] SGD formatting
* [ ] date formatting
* [ ] duration formatting
* [ ] mono formatting for refs / IDs

---

# Phase 14 — Audit, Integrity & Safety

## Audit

* [ ] last login tracking
* [ ] failed login tracking
* [ ] audit log table
* [ ] create/update/delete audit logs

## Safety

* [ ] soft deletes
* [ ] archive mode
* [ ] transaction integrity checks
* [ ] destructive action confirmations

---

# Phase 15 — Brand Consistency Pass

## GraceSoft Brand QA

* [ ] Match HQ auth styling
* [ ] Match HQ sidebar proportions
* [ ] Match HQ topbar density
* [ ] Match HQ card hierarchy
* [ ] Match HQ typography
* [ ] Match HQ visual rhythm
* [ ] Match HQ interaction patterns

## Desk Product QA

* [ ] Feels like GraceSoft
* [ ] Feels lighter than HQ
* [ ] Feels finance-first
* [ ] Feels operationally dense
* [ ] Feels cleaner than Excel
* [ ] Feels production-ready

---

# Phase 16 — Launch Readiness

## Pre-Launch

* [ ] seed production admin
* [ ] verify forced password flow
* [ ] verify 2FA enforcement
* [ ] verify dashboard accuracy
* [ ] verify imports
* [ ] verify exports
* [ ] verify backups
* [ ] verify env hardening

## Launch

* [ ] production deploy
* [ ] first login security flow
* [ ] import live Excel data
* [ ] verify reports
* [ ] verify dashboard totals
* [ ] begin SQL-first operations
