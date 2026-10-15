@props(['user', 'size' => 'size-9'])

@if ($user->photo_path)
    <img src="{{ route('users.photo', $user) }}" alt="" {{ $attributes->class([$size, 'rounded-full object-cover']) }}>
@else
    <span {{ $attributes->class([$size, 'inline-flex items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700 dark:bg-brand-900 dark:text-brand-100']) }}>
        {{ $user->initials() }}
    </span>
@endif
