@props(['dueAt' => null, 'closed' => false])

@php($deadline = \App\Support\Deadline::describe($dueAt, $closed))

@if ($deadline)
    <x-badge :color="$deadline['color']" {{ $attributes }}>
        @if ($deadline['overdue'])⚠ @endif{{ $deadline['label'] }}
    </x-badge>
@elseif ($dueAt)
    <span {{ $attributes->class('text-xs text-slate-500 dark:text-slate-400') }}>{{ $dueAt->format('d/m/Y') }}</span>
@endif
