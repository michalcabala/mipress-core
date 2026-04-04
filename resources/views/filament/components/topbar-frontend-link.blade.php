<a
    href="{{ url('/') }}"
    target="_blank"
    rel="noopener noreferrer"
    class="flex items-center text-sm font-semibold text-gray-700 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400 whitespace-nowrap transition"
>
    {{ $siteName ?: config('app.name') }}
    <x-filament::icon icon="fal-arrow-up-right-from-square" class="ms-1.5 h-3.5 w-3.5" />
</a>
