@props(['severity'])
@php
    $value = $severity instanceof \BackedEnum ? $severity->value : (string) $severity;
    $map = [
        'LOW' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'MEDIUM' => 'bg-orange-50 text-orange-700 ring-orange-200',
        'HIGH' => 'bg-red-50 text-red-700 ring-red-200',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '.($map[$value] ?? $map['LOW'])]) }}>{{ $value }}</span>
