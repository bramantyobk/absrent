@props(['href' => null, 'size' => 40, 'showName' => true])

<a href="{{ $href ?? route('home') }}" {{ $attributes->class('brand-logo') }}>
    <img src="{{ asset('images/icons/absrent-logo.svg') }}" alt="" width="{{ $size }}" height="{{ $size }}">
    @if ($showName)
        <span>{{ config('app.name') }}</span>
    @endif
</a>
