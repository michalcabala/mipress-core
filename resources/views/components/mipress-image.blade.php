@if($shouldRender)
<img
    src="{{ $src }}"
    @if($srcset) srcset="{{ $srcset }}" @endif
    @if($srcset) sizes="(max-width: 400px) 400px, (max-width: 800px) 800px, 1600px" @endif
    alt="{{ $alt }}"
    @if($width) width="{{ $width }}" @endif
    @if($height) height="{{ $height }}" @endif
    @if($lazy) loading="lazy" decoding="async" @endif
    @if($class) class="{{ $class }}" @endif
/>
@endif
