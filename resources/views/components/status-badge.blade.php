@props(['status'])

<span {{ $attributes->class(['status-badge', $status->badgeClass()]) }}>{{ $status->label() }}</span>
