@php
    $toneClasses = match ($tone) {
        'muted' => 'border-slate-200 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-900/70',
        'accent' => 'border-blue-200 bg-blue-50/70 dark:border-blue-900 dark:bg-blue-950/40',
        default => 'border-slate-200 bg-white/90 dark:border-slate-800 dark:bg-slate-900/85',
    };
    $widthClass = $width === 'narrow' ? 'max-w-3xl' : 'max-w-5xl';
@endphp

<section class="mx-auto w-full {{ $widthClass }} px-4 sm:px-6">
    <div class="rounded-3xl border p-6 shadow-sm sm:p-8 {{ $toneClasses }}">
        @if(filled($eyebrow))
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600 dark:text-blue-300">{{ $eyebrow }}</p>
        @endif

        @if(filled($heading))
            <h2 class="mt-3 text-3xl font-semibold text-slate-900 dark:text-white" style="font-family: 'Space Grotesk', sans-serif;">{{ $heading }}</h2>
        @endif

        @if(filled($content))
            <div class="prose prose-slate mt-5 max-w-none prose-headings:font-semibold prose-p:leading-8 dark:prose-invert" style="font-family: 'Plus Jakarta Sans', sans-serif;">
                {!! $content !!}
            </div>
        @endif
    </div>
</section>
