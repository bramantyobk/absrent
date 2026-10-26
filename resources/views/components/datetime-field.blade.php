@props([
    'name' => 'start_at',
    'value' => '',
    'label' => 'Mulai',
    'timezone' => null,
    'required' => false,
    'showReturn' => false,
    'showError' => true,
    'minDate' => null,
    'labelClass' => 'form-label',
])

@php
    $id = $attributes->get('id', $name);
    $date = substr((string) $value, 0, 10);
    $time = strlen((string) $value) >= 16 ? substr((string) $value, 11, 5) : '09:00';
@endphp

<div class="datetime-field" data-datetime-field data-tz="{{ $timezone }}" @if ($showReturn) data-show-return @endif>
    <label for="{{ $id }}_date" class="{{ $labelClass }}">{{ $label }}@if ($timezone) ({{ $timezone }})@endif</label>
    <div class="row g-2">
        <div class="col-6">
            <input type="date" id="{{ $id }}_date" value="{{ $date }}" @if ($minDate) min="{{ $minDate }}" @endif
                class="form-control @error($name) is-invalid @enderror" aria-label="Tanggal" @required($required) data-dt-date>
        </div>
        <div class="col-6">
            <select class="form-select" aria-label="Jam (24 jam)" data-dt-time>
                @for ($slot = 0; $slot < 48; $slot++)
                    @php($option = sprintf('%02d:%02d', intdiv($slot, 2), $slot % 2 * 30))
                    <option value="{{ $option }}" @selected($option === $time)>{{ $option }}</option>
                @endfor
            </select>
        </div>
    </div>
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-dt-value {{ $attributes->except('id') }}>
    <div class="form-text" data-dt-summary aria-live="polite"></div>
    @if ($showError)
        @error($name)
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    @endif
</div>
