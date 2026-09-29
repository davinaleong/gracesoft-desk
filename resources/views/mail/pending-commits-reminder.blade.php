<x-mail::message>
# {{ __('Timesheet reminder') }}

{{ trans_choice('{1} You have :count pending commit that is not a time entry yet.|[2,*] You have :count pending commits that are not time entries yet.', $pendingCount) }}

<x-mail::button :url="route('timesheet.index')">
{{ __('Open weekly timesheet') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
