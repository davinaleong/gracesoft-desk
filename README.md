# GraceSoft Desk

GraceSoft Desk is an internal operations and reporting dashboard built with Laravel 13. It includes project tracking, time entries, transaction ledger reporting, CSV imports, and printable report views.

## Stack

- PHP 8.5
- Laravel 13
- Pest + PHPUnit for testing
- Tailwind CSS + Vite

## Quick Start

1. Install dependencies:

```bash
composer install
npm install
```

2. Configure environment:

```bash
cp .env.example .env
php artisan key:generate
```

3. Run migrations and seed demo data:

```bash
php artisan migrate:fresh --seed
```

4. Start development servers:

```bash
php artisan serve
npm run dev
```

## Demo Data

`DatabaseSeeder` runs `MarketingDemoSeeder`, which seeds:

- One admin account (single-seat model-safe update behavior)
- Canonical project stages
- Accounts, payment methods, transaction categories
- Demo projects, time entries, and finance transactions

You can run just the demo seeder manually:

```bash
php artisan db:seed --class=MarketingDemoSeeder --no-interaction
```

## Time Entry CSV Import

Time entry import supports the following columns:

```csv
project_uuid,project_code,stage_uuid,stage_name,entry_date,duration_minutes,is_billable,hourly_rate,notes
```

- `entry_date` and `duration_minutes` are required.
- Project is resolved by `project_uuid` first, then `project_code`.
- Stage is resolved by `stage_uuid` first, then `stage_name`.

Sample marketing import file:

- `_internal-docs/data/time-entries-marketing-sample.csv`

## Reports

Available report modules:

- Finance report
- Project report
- Monthly summary report

Features:

- CSV export for all report modules
- Printable report routes
- Finance print report uses the brand font (Montserrat)
- Views are hardened against malformed cached row payloads

## Tests

Run full test suite:

```bash
php artisan test --compact
```

Run specific report tests:

```bash
php artisan test --compact tests/Feature/ReportsModuleTest.php
```

## Notes

- If frontend assets look stale, run `npm run dev` (or `npm run build` for production).
- The app enforces a single admin user model at create-time.
