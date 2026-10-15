<x-layouts.app title="Notifications">
    <x-page-header title="Notifications">
        <x-slot:actions>
            @if (auth()->user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn-secondary">Tout marquer comme lu</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="card divide-y divide-slate-100 p-0 dark:divide-slate-800">
        @forelse ($notifications as $notification)
            <a href="{{ route('notifications.open', $notification->id) }}" @class([
                'flex items-start gap-4 px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/60',
                'bg-brand-50/60 dark:bg-brand-900/20' => $notification->unread(),
            ])>
                <span @class(['mt-2 size-2 shrink-0 rounded-full', 'bg-brand-600' => $notification->unread(), 'bg-transparent' => $notification->read()])></span>
                <div class="flex-1">
                    <div class="text-sm font-medium">{{ $notification->data['message'] ?? 'Notification' }}</div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        @isset($notification->data['reference']) {{ $notification->data['reference'] }} · @endisset
                        {{ __('notifications.types.'.($notification->data['type'] ?? 'other')) }} ·
                        {{ $notification->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>
                @if ($notification->unread())
                    <x-badge color="blue">Non lu</x-badge>
                @endif
            </a>
        @empty
            <p class="px-5 py-10 text-center text-slate-500">Aucune notification.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.app>
