@php
    $statusClasses = match ($status) {
        'draft' => 'bg-gray-100 text-gray-800',
        'issued' => 'bg-yellow-100 text-yellow-800',
        'paid' => 'bg-green-100 text-green-800',
        'void' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusClasses }}">{{ ucfirst($status) }}</span>
