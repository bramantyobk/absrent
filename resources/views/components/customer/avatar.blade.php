@props(['user', 'large' => false])

@if ($user->photo_url)
    <img src="{{ $user->photo_url }}" alt="" @class(['avatar', 'avatar-lg' => $large])>
@else
    <span @class(['avatar', 'avatar-lg' => $large]) aria-hidden="true">{{ $user->initials() }}</span>
@endif
