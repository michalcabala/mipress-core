<section class="mx-auto w-full max-w-4xl px-4 sm:px-6">
    <div class="rounded-3xl border border-blue-200 bg-gradient-to-br from-blue-600 to-cyan-500 p-8 text-white shadow-lg shadow-blue-500/25 dark:border-blue-700">
        <blockquote @class(['text-left' => $alignment === 'start', 'text-center' => $alignment !== 'start'])>
            <p class="text-3xl font-semibold leading-tight sm:text-4xl" style="font-family: 'Space Grotesk', sans-serif;">“{{ $quote }}”</p>

            @if(filled($author) || filled($role))
                <footer class="mt-6 space-y-1 text-sm text-blue-100/95">
                    @if(filled($author))
                        <strong class="block text-base text-white">{{ $author }}</strong>
                    @endif

                    @if(filled($role))
                        <span class="block">{{ $role }}</span>
                    @endif
                </footer>
            @endif
        </blockquote>
    </div>
</section>
