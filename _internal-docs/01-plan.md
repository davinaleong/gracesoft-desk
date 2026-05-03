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

* [ ] Keep `id` as internal SQL PK on all core tables
* [ ] Add `uuid` to all public-facing tables
* [ ] Use `uuid` for route model binding
* [ ] Never expose SQL `id` in UI / URLs / exports
* [ ] Use `uuid` for forms, URLs, route actions, exports
* [ ] Use `id` for joins, FKs, indexing, SQL performance
* [ ] Add UUID generation to all public models
* [ ] Enforce UUID route binding globally
* [ ] Add `transaction_code` to transactions
* [ ] Use `transaction_code` as visible transaction identifier
* [ ] Keep `projects.code` manually controlled
* [ ] Never auto-generate project codes
* [ ] Auto-generate UUIDs on create
* [ ] Auto-generate `transaction_code` on create

---

# Phase 1 — Project Setup & Foundation

## Laravel Foundation

* [ ] Create Laravel 12 project
* [ ] Configure environment
* [ ] Configure MySQL
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

* [ ] Install Bootstrap 5.3
* [ ] Setup SCSS / CSS build
* [ ] Setup app JS bundle
* [ ] Install ApexCharts
* [ ] Setup asset pipeline
* [ ] Add Montserrat
* [ ] Add Playfair Display
* [ ] Add Source Code Pro

## Security Foundation

* [ ] Install Laravel Breeze (Blade)
* [ ] Remove registration routes
* [ ] Remove forgot password flow (or restrict)
* [ ] Disable public user creation
* [ ] Configure auth guard
* [ ] Configure session security
* [ ] Configure login throttling

---

# Phase 2 — Auth & Security System

## Single-Seat Admin Auth

* [ ] Create single-user auth rules
* [ ] Enforce only one user may exist
* [ ] Prevent second user creation
* [ ] Remove user CRUD
* [ ] Seed default admin account

## Seeded Admin Security

* [ ] Create `AdminUserSeeder`
* [ ] Seed one default admin
* [ ] Seed default temp password
* [ ] Set `must_change_password = true`

## Forced Password Rotation

* [ ] Add `must_change_password` to users table
* [ ] Add `password_changed_at`
* [ ] Create force password change screen
* [ ] Create password rotation flow
* [ ] Block dashboard until password updated
* [ ] Add `EnsurePasswordChanged` middleware

## Mandatory QR 2FA

* [ ] Install Fortify 2FA
* [ ] Enable TOTP
* [ ] Enable QR setup
* [ ] Enable recovery codes
* [ ] Add `two_factor_confirmed_at`
* [ ] Create QR setup screen
* [ ] Create TOTP confirm flow
* [ ] Block dashboard until 2FA confirmed
* [ ] Add `EnsureTwoFactorIsConfigured` middleware

## Auth UX

* [ ] Branded login screen
* [ ] Forced password setup screen
* [ ] Mandatory 2FA setup screen
* [ ] Security completion gate
* [ ] Logout flow

---

# Phase 3 — Core Database Schema

## Schema Design

* [ ] Finalize normalized schema
* [ ] Define naming conventions
* [ ] Define FK rules
* [ ] Define enum strategy
* [ ] Define soft delete strategy
* [ ] Define audit strategy
* [ ] Define UUID strategy
* [ ] Define public vs internal identifier rules

## Tables — Core

* [ ] Create `users`
* [ ] Create `projects`
* [ ] Create `project_stages`
* [ ] Create `time_entries`
* [ ] Create `transaction_categories`
* [ ] Create `payment_methods`
* [ ] Create `accounts`
* [ ] Create `transactions`

## Core Table Rules

* [ ] All public-facing tables use `id` + `uuid`
* [ ] All public-facing tables index `uuid`
* [ ] All public-facing tables keep integer `id`
* [ ] All public-facing tables use timestamps
* [ ] Never expose SQL `id` publicly

## Constraints & Indexing

* [ ] Add unique constraints
* [ ] Add FK constraints
* [ ] Add composite indexes
* [ ] Add reporting indexes
* [ ] Add transaction date indexes
* [ ] Add project aggregation indexes
* [ ] Add UUID indexes
* [ ] Add `transaction_code` unique index
* [ ] Add `projects.code` unique index

---

# Phase 4 — Seeders & Static Reference Data

## Seeders

* [ ] Seed project stages
* [ ] Seed transaction categories
* [ ] Seed payment methods
* [ ] Seed accounts
* [ ] Seed starter projects
* [ ] Seed admin user

## Static Reference Sets

* [ ] SDLC stages
* [ ] transaction types
* [ ] transaction statuses
* [ ] payment methods
* [ ] account types
* [ ] starter project codes

---

# Phase 5 — Domain Models & Business Logic

## Models

* [ ] Build User model
* [ ] Build Project model
* [ ] Build ProjectStage model
* [ ] Build TimeEntry model
* [ ] Build Transaction model
* [ ] Build TransactionCategory model
* [ ] Build PaymentMethod model
* [ ] Build Account model

## Model Rules

* [ ] Add UUID generation trait to all public models
* [ ] Override route model binding to UUID
* [ ] Hide SQL `id` in public-facing payloads where appropriate

## Relationships

* [ ] Project → TimeEntries
* [ ] TimeEntry → Project
* [ ] TimeEntry → Stage
* [ ] Transaction → Category
* [ ] Transaction → PaymentMethod
* [ ] Transaction → Account

## Domain Rules

* [ ] One-user enforcement
* [ ] Duration calculation
* [ ] Billable cost calculation
* [ ] GST calculation
* [ ] Net amount calculation
* [ ] transaction type validation
* [ ] money in/out integrity rules
* [ ] manual project code enforcement
* [ ] auto transaction code generation

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

* [ ] Projects index
* [ ] Create project
* [ ] Edit project
* [ ] Project detail

## Time Entries

* [ ] Time entries index
* [ ] Create time entry
* [ ] Edit time entry
* [ ] Delete time entry

## Transactions

* [ ] Transactions index
* [ ] Create transaction
* [ ] Edit transaction
* [ ] View transaction

## Settings

* [ ] Profile & Security
* [ ] 2FA management
* [ ] system settings

## CRUD Rules

* [ ] All CRUD routes resolve via UUID
* [ ] All UI tables display human-readable references
* [ ] Projects display `code`
* [ ] Transactions display `transaction_code`
* [ ] Never expose SQL `id` in forms or views

---

# Phase 9 — Dashboard Query Layer

## Reporting Services

* [ ] Build DashboardService
* [ ] Build FinanceReportService
* [ ] Build ProjectReportService
* [ ] Build LedgerSummaryService

## SQL Aggregation

* [ ] Monthly cashflow query
* [ ] Expense breakdown query
* [ ] Income breakdown query
* [ ] Pending / outstanding query
* [ ] Project overview query
* [ ] Billable by project query
* [ ] Billable by stage query

## Optimization

* [ ] Add reporting indexes
* [ ] Add query scopes
* [ ] Add summary caching
* [ ] Optimize aggregate queries

---

# Phase 10 — Dashboard UI

## Dashboard Shell

* [ ] Dashboard header
* [ ] reporting month badge
* [ ] KPI row

## Finance Widgets

* [ ] Monthly cashflow chart
* [ ] Expense breakdown donut
* [ ] Income breakdown donut
* [ ] Pending / outstanding table

## Project Widgets

* [ ] Total projects card
* [ ] Total logged hours card
* [ ] Total billable value card
* [ ] Project overview table
* [ ] Billable by project donut
* [ ] Billable by stage chart

---

# Phase 11 — Reports Module

## Reports

* [ ] Finance reports page
* [ ] Project reports page
* [ ] Monthly summary page
* [ ] report filters
* [ ] printable summaries

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

* [ ] Export CSV
* [ ] Export finance report
* [ ] Export project report
* [ ] Export monthly summary
* [ ] never export SQL `id`
* [ ] export UUID only where needed
* [ ] export `transaction_code` as transaction reference
* [ ] export `projects.code` as project reference

---

# Phase 13 — UX Polish

## UX

* [ ] flash alerts
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
