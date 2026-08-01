# GitHub Integration — Implementation Progress

## Milestone 0 — Schema Audit ✅

**Completed:** 2026-08-01

### Findings

| Question | Finding |
|---|---|
| Is `time_entries.stage` a free string or FK? | **FK** — `project_stage_id` (nullable) → `project_stages.id`. Already normalised. No backfill needed. |
| Is `projects.is_billable` a boolean flag or a rate? | **Both.** `is_billable` (boolean) + `hourly_rate` (decimal) exist on `projects` since the 2026-08-01 migration. |
| Is `billable` on time entries a flag or a computed amount? | **Computed.** `billable_amount = (duration_minutes / 60) × project.hourly_rate`. Stored as `decimal(12,2)`. |

### Key Schema Notes

- `project_stages` table: canonical set (Discovery → Maintenance, sort_order 1–7) already seeded by the `cleanup_project_stages_to_canonical_lifecycle` migration.
- The cleanup migration **drops `slug` and `project_id`** from `project_stages` (both MySQL and SQLite paths). Final columns: `id, uuid, name, sort_order, status, keywords, is_default, timestamps`.
- Milestone 1's "backfill migration" item is **not needed** — stage is already an FK.
- Milestone 1's "add `stage_id` FK" is **already done** (`project_stage_id` on `time_entries`).

---

## Milestone 1 — SDLC Stages (Settings) ✅

**Completed:** 2026-08-01

### What was built

| File | Notes |
|---|---|
| `2026_08_01_082615_add_keywords_and_is_default_to_project_stages_table` | Adds `keywords` (JSON nullable) and `is_default` (boolean, default false) |
| `app/Models/ProjectStage.php` | Added `keywords`, `is_default` to fillable; casts: `keywords → array`, `is_default → boolean` |
| `app/Http/Controllers/ProjectStageController.php` | Full CRUD + `moveUp` / `moveDown` for sort_order reordering |
| `app/Http/Requests/StoreProjectStageRequest.php` | Validates name (unique), status, keywords, is_default |
| `app/Http/Requests/UpdateProjectStageRequest.php` | Same as Store but ignores current stage on unique check |
| `resources/views/settings/project-stages/index.blade.php` | List with ▲▼ reorder buttons, keyword tag pills, status badge |
| `resources/views/settings/project-stages/create.blade.php` | Create form with keyword help text |
| `resources/views/settings/project-stages/edit.blade.php` | Edit form, keywords pre-populated as comma string |
| `resources/views/layouts/navigation.blade.php` | "Project Stages" link added under Settings (desktop + mobile) |
| `resources/views/layouts/app.blade.php` | Flash messages for stage CRUD actions |
| `routes/web.php` | 8 routes: index, create, store, edit, update, destroy, move-up, move-down |
| `tests/Feature/ProjectStagesCrudTest.php` | 8 tests: index, create+keywords, keyword normalisation, update, delete-unused, delete-in-use, reorder, auth |

### Design decisions

- **No slug on stages.** The cleanup migration removed `slug` from `project_stages`. Name uniqueness is enforced directly.
- **Keywords stored as JSON array**, input via comma-separated text. Normalised to lowercase trimmed strings.
- **Delete guard:** stages with assigned time entries return `project-stage-in-use` status instead of deleting.
- **Reorder:** one-step up/down via PATCH; sort_order values are swapped between adjacent pairs.

---

## Next: Milestone 2 — GitHub Connection

Upcoming work:
- Install/configure Laravel Socialite (GitHub driver)
- `github_connections` table (encrypted token storage)  
- Settings page: "Connect GitHub" / "Disconnect" UI + connection status
- Tests: OAuth callback, token encryption, disconnect flow
