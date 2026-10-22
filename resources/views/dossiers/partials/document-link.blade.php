@if (Route::has('documents.download') && auth()->user()->can('download', $document))
    <a href="{{ route('documents.download', $document) }}" class="inline-flex items-center gap-1 font-semibold text-brand-600 hover:underline dark:text-brand-300">
        @if ($withIcon ?? true)<x-icon name="paperclip" class="size-4" />@endif {{ $document->original_name }}
    </a>
@else
    <span class="inline-flex items-center gap-1">@if ($withIcon ?? true)<x-icon name="paperclip" class="size-4" />@endif {{ $document->original_name }}</span>
@endif
