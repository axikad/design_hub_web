<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1f536b">
    <title>{{ $page->title }} · {{ $branding['name'] }}</title>
    @if ($branding['faviconUrl'])
        <link rel="icon" href="{{ $branding['faviconUrl'] }}">
    @endif
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f5f7f9; color: #172b3a; line-height: 1.7; }
        header { background: #fff; border-bottom: 1px solid #e3e9ed; }
        .header-inner, main, footer { width: min(100% - 32px, 820px); margin-inline: auto; }
        .header-inner { min-height: 72px; display: flex; align-items: center; gap: 12px; }
        .logo { width: 40px; height: 40px; border-radius: 12px; object-fit: contain; }
        .brand { font-size: 20px; font-weight: 800; letter-spacing: -.02em; }
        main { padding-block: 32px 48px; }
        article { background: #fff; border: 1px solid #e3e9ed; border-radius: 22px; padding: clamp(22px, 5vw, 44px); box-shadow: 0 12px 34px rgba(23, 43, 58, .06); }
        h1, h2, h3 { line-height: 1.25; letter-spacing: -.025em; color: #102b3a; }
        h1 { margin-top: 0; font-size: clamp(28px, 6vw, 42px); }
        h2 { margin-top: 32px; font-size: 23px; }
        h3 { margin-top: 24px; font-size: 18px; }
        p, li { color: #49616f; }
        a { color: #1f6685; overflow-wrap: anywhere; }
        .updated { margin: -10px 0 28px; color: #78909c; font-size: 14px; }
        footer { padding-bottom: 32px; text-align: center; color: #78909c; font-size: 13px; }
        @media (max-width: 520px) { .header-inner, main, footer { width: min(100% - 20px, 820px); } main { padding-top: 18px; } article { border-radius: 18px; } }
    </style>
</head>
<body>
    <header>
        <div class="header-inner">
            @if ($branding['logoUrl'])
                <img class="logo" src="{{ $branding['logoUrl'] }}" alt="">
            @endif
            <div class="brand">{{ $branding['name'] }}</div>
        </div>
    </header>
    <main>
        <article>
            <h1>{{ $page->title }}</h1>
            <p class="updated">Last updated {{ $page->updated_at->format('d M Y') }}</p>
            <div>{!! $contentHtml !!}</div>
        </article>
    </main>
    <footer>© {{ now()->year }} {{ $branding['name'] }}</footer>
</body>
</html>
