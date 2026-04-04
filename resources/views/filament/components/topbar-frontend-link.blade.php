<a
    href="{{ url('/') }}"
    target="_blank"
    rel="noopener noreferrer"
    class="mx-2 mb-2 inline-flex items-center text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 whitespace-nowrap transition"
>
    <span>{{ $siteName ?: config('app.name') }}</span>
    <span style="margin-inline-start: 0.375rem;" aria-hidden="true">
        <x-filament::icon icon="fal-arrow-up-right-from-square" class="h-3 w-3" />
    </span>
</a>
