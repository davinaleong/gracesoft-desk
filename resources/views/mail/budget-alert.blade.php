<x-mail::message>
# {{ __('Budget alert: :threshold%', ['threshold' => $alert->threshold]) }}

@if ($alert->budget_type === 'hours')
{{ __(':project (:name) has logged :used of :budget budgeted hours.', ['project' => $project->code, 'name' => $project->name, 'used' => number_format((float) $alert->used_value, 2), 'budget' => number_format((float) $alert->budget_value, 2)]) }}
@else
{{ __(':project (:name) has billed :used of its :budget budget.', ['project' => $project->code, 'name' => $project->name, 'used' => number_format((float) $alert->used_value, 2), 'budget' => number_format((float) $alert->budget_value, 2)]) }}
@endif

<x-mail::button :url="route('projects.show', $project)">
{{ __('Open project') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
