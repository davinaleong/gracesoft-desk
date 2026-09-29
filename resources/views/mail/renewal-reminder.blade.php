<x-mail::message>
# {{ __('Upcoming renewal') }}

{{ __(':vendor :service (:plan) renews on :date for :currency :amount.', [
    'vendor' => $service->vendor?->name,
    'service' => $service->name,
    'plan' => $service->plan ?: __('no plan'),
    'date' => $service->next_renewal_date?->format('d M Y'),
    'currency' => $service->currency,
    'amount' => number_format((float) $service->expected_amount, 2),
]) }}

@if ($service->auto_create_expense)
{{ __('A pending expense will be created on the renewal date.') }}
@endif

<x-mail::button :url="route('services.show', $service)">
{{ __('Review service') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
