@php
    $gtmId = $seo['analytics']['google_tag_manager_id'] ?? null;
@endphp

@if (filled($gtmId))
    <noscript>
        <iframe
            src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
            height="0"
            width="0"
            style="display:none;visibility:hidden"
        ></iframe>
    </noscript>
@endif
