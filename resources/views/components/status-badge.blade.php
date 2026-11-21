@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $map = [
        'CREATED' => 'bg-navy-50 text-navy-700 ring-navy-200',
        'BOOKED' => 'bg-navy-100 text-navy-800 ring-navy-300',
        'PICKED_UP' => 'bg-navy-200 text-navy-900 ring-navy-300',
        'IN_TRANSIT' => 'bg-navy-800 text-white ring-navy-900',
        'DELIVERED' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'CANCELLED' => 'bg-slate-100 text-slate-600 ring-slate-300',
        'OPEN' => 'bg-red-50 text-red-700 ring-red-200',
        'INVESTIGATING' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'RESOLVED' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '.($map[$value] ?? $map['CREATED'])]) }}>{{ str_replace('_', ' ', $value) }}</span>
