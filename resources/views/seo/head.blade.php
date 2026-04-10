@php
    $title = $seo['title'] ?? config('app.name');
    $description = $seo['description'] ?? null;
    $canonicalUrl = $seo['canonical_url'] ?? null;
    $gtmId = $seo['analytics']['google_tag_manager_id'] ?? null;
    $gaId = $seo['analytics']['google_analytics_id'] ?? null;
@endphp

<title>{{ $title }}</title>

@if (filled($description))
    <meta name="description" content="{{ $description }}">
@endif

@if (($seo['noindex'] ?? false) === true)
    <meta name="robots" content="noindex, nofollow">
@endif

@if (filled($canonicalUrl))
    <link rel="canonical" href="{{ $canonicalUrl }}">
@endif

<meta property="og:title" content="{{ $title }}">

@if (filled($description))
    <meta property="og:description" content="{{ $description }}">
@endif

@if (filled($canonicalUrl))
    <meta property="og:url" content="{{ $canonicalUrl }}">
@endif

@if (filled($seo['site_name'] ?? null))
    <meta property="og:site_name" content="{{ $seo['site_name'] }}">
@endif

<meta property="og:type" content="{{ $seo['og_type'] ?? 'website' }}">
<meta property="og:locale" content="{{ $seo['og_locale'] ?? 'cs_CZ' }}">

@foreach (($seo['alternate_og_locales'] ?? []) as $alternateLocale)
    <meta property="og:locale:alternate" content="{{ $alternateLocale }}">
@endforeach

@if (filled($seo['image_url'] ?? null))
    <meta property="og:image" content="{{ $seo['image_url'] }}">

    @if (filled($seo['image_alt'] ?? null))
        <meta property="og:image:alt" content="{{ $seo['image_alt'] }}">
    @endif

    <meta name="twitter:image" content="{{ $seo['image_url'] }}">
@endif

<meta name="twitter:card" content="{{ $seo['twitter_card'] ?? 'summary_large_image' }}">
<meta name="twitter:title" content="{{ $title }}">

@if (filled($description))
    <meta name="twitter:description" content="{{ $description }}">
@endif

@if (filled($seo['twitter_site'] ?? null))
    <meta name="twitter:site" content="{{ $seo['twitter_site'] }}">
@endif

@if (filled($seo['twitter_creator'] ?? null))
    <meta name="twitter:creator" content="{{ $seo['twitter_creator'] }}">
@endif

@if (($seo['og_type'] ?? null) === 'article')
    @if (filled($seo['published_time'] ?? null))
        <meta property="article:published_time" content="{{ $seo['published_time'] }}">
    @endif

    @if (filled($seo['modified_time'] ?? null))
        <meta property="article:modified_time" content="{{ $seo['modified_time'] }}">
        <meta property="og:updated_time" content="{{ $seo['modified_time'] }}">
    @endif
@endif

@foreach (($seo['verification'] ?? []) as $name => $value)
    <meta name="{{ $name }}" content="{{ $value }}">
@endforeach

@foreach (($seo['structured_data'] ?? []) as $schema)
    @php
        $schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $schemaJson = str_replace('</script>', '<\\/script>', $schemaJson ?: '');
    @endphp
    <script type="application/ld+json">{!! $schemaJson !!}</script>
@endforeach

@if (filled($gtmId))
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $gtmId }}');
    </script>
@endif

@if (filled($gaId))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($gaId) }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag(){dataLayer.push(arguments);}

        gtag('js', new Date());
        gtag('config', '{{ $gaId }}');
    </script>
@endif
