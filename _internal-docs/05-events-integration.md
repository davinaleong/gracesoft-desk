# Appointments/Events + Notifications — Milestone Checklist

Feature: personal appointments/events manager with one-time and recurring email
notifications (Google Calendar-style recurrence), standalone reminders, a
view-only month/week/day calendar, and a notification log.

---

## Milestone 1 — Data model

- [ ] Migration: `events` (title, description, location, starts_at, ends_at,
      all_day, timezone, recurrence_rule, recurrence_ends_at, status)
- [ ] Migration: `event_occurrences` (event_id, occurs_at, ends_at, is_exception)
- [ ] Migration: `notifications` (event_id, channel, recipient_email,
      offset_minutes, subject, body)
- [ ] Migration: `reminders` (title, message_body, recipient_email,
      recurrence_rule, fires_at, recurrence_ends_at, status)
- [ ] Migration: `reminder_occurrences` (reminder_id, occurs_at)
- [ ] Migration: `notification_logs` (notifiable_type, notifiable_id,
      occurrence_reference, scheduled_for, sent_at, status, error_message,
      attempts)
- [ ] Models + relationships (`Event`, `EventOccurrence`, `Notification`,
      `Reminder`, `ReminderOccurrence`, `NotificationLog`)
- [ ] Factories/seeders for local dev data

## Milestone 2 — Recurrence engine

- [ ] Install `simshaun/recurr`
- [ ] `RecurrenceService`: build RRULE string from picker input
- [ ] `RecurrenceService`: expand RRULE into a date range (used by both
      events and reminders)
- [ ] Support presets: does not repeat, daily, weekly on [day], monthly
      (by date / by "nth weekday"), yearly, every weekday
- [ ] Support custom: interval + unit + specific weekdays
- [ ] Support end conditions: never, on date, after N occurrences
- [ ] Unit tests covering each preset + custom + end conditions + DST edge
      cases

## Milestone 3 — Occurrence materialization

- [ ] Console command `events:sync-occurrences` — expands each active
      event's RRULE into `event_occurrences` for a rolling window (e.g.
      next 90 days)
- [ ] Console command `reminders:sync-occurrences` — same, for `reminders`
- [ ] On occurrence creation, generate matching `notification_logs` rows
      (status `pending`) from each event's `notifications` rules /
      each reminder's own schedule
- [ ] Handle edits to a recurring series (regenerate future occurrences,
      preserve past/sent logs)
- [ ] Handle single-occurrence exceptions/cancellations
- [ ] Register both commands in the scheduler (daily)

## Milestone 4 — Notification dispatch

- [ ] `SendEventNotification` job (queued) — renders and sends the email,
      updates the log row to `sent` or `failed`
- [ ] Console command `notifications:dispatch-due` — finds `pending` log
      rows with `scheduled_for <= now()`, dispatches jobs
- [ ] Register dispatch command in the scheduler (every minute)
- [ ] Retry/backoff handling for failed sends
- [ ] Mailable template(s) for event notifications and standalone reminders
- [ ] Feature tests: pipeline creates correct log rows and sends at the
      right time

## Milestone 5 — Event & reminder management (CRUD)

- [ ] Event create/edit form (details + recurrence picker)
- [ ] Attach/edit/remove multiple notification rules per event
      (offset-based, e.g. "1 day before", "1 hour before")
- [ ] Reminder create/edit form (standalone, own recurrence or one-time)
- [ ] Cancel/delete event or reminder (with occurrence + log cleanup)
- [ ] Validation (end date after start, sane recurrence limits, etc.)

## Milestone 6 — Calendar (view-only)

- [ ] Month view (grid, navigable via query params)
- [ ] Week view
- [ ] Day view
- [ ] Click occurrence → read-only detail page (event/reminder info +
      attached notifications + last-sent status)
- [ ] Consistent styling with existing report/print views

## Milestone 7 — Notification log page

- [ ] Log listing table (status, date range, event/reminder filters)
- [ ] Status badges (pending / sent / failed / cancelled)
- [ ] Error detail view for failed sends
- [ ] CSV export (reuse existing report export pattern)

## Milestone 8 — Polish & hardening

- [ ] Timezone correctness pass (event tz vs display tz)
- [ ] Guard against duplicate sends (idempotency on dispatch)
- [ ] Guard against malformed/legacy occurrence or log rows (matches
      existing "hardened against malformed cached row payloads" pattern)
- [ ] Full test suite pass (`php artisan test --compact`)
- [ ] README updates (setup, scheduler cron entry, new artisan commands)

---

## Open decisions to revisit

- [ ] Materialization window length (90 days default — adjust if needed)
- [ ] Max notifications per event/reminder (soft cap for sanity)
- [ ] What happens to a recurring series's future notifications when the
      series recurrence is edited mid-stream