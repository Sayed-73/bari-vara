<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('support.company') }}</title>
    <style>
        :root { --bg:#f9fafb; --card:#fff; --text:#1f2937; --muted:#6b7280; --line:#e5e7eb; --brand:#d97706; }
        @media (prefers-color-scheme: dark) { :root { --bg:#111827; --card:#1f2937; --text:#f3f4f6; --muted:#9ca3af; --line:#374151; } }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Noto Sans Bengali','Nirmala UI','Kalpurush',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; line-height:1.75; }
        .wrap { max-width:760px; margin:0 auto; padding:20px 16px 50px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:22px; }
        h1 { font-size:1.45rem; margin:0 0 4px; } h2 { font-size:1.08rem; margin:22px 0 6px; }
        .muted { color:var(--muted); font-size:.9rem; } a { color:var(--brand); }
        nav { margin-bottom:14px; font-size:.92rem; } nav a { margin-right:12px; }
        ul { padding-left:20px; }
    </style>
</head>
<body>
<div class="wrap">
    <nav>
        <a href="{{ route('home') }}">হোম</a>
        <a href="{{ route('privacy') }}">গোপনীয়তা নীতি</a>
        <a href="{{ route('terms') }}">শর্তাবলী</a>
        <a href="{{ route('account.deletion') }}">অ্যাকাউন্ট মুছে ফেলা</a>
    </nav>
    <div class="card">
        @yield('content')
    </div>
    <p class="muted" style="text-align:center">
        {{ config('support.company') }}
        @if (config('support.address')) · {{ config('support.address') }} @endif
        @if (config('support.email')) · {{ config('support.email') }} @endif
    </p>
</div>
</body>
</html>
