<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title')</title>
    <style>
        :root { --bg:#f4f6f8; --card:#fff; --text:#1f2937; --muted:#6b7280; --line:#e5e7eb; --brand:#059669; --danger:#dc2626; --warn:#d97706; }
        @media (prefers-color-scheme: dark) { :root { --bg:#111827; --card:#1f2937; --text:#f3f4f6; --muted:#9ca3af; --line:#374151; } }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Noto Sans Bengali','Nirmala UI','Kalpurush','Siyam Rupali',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; line-height:1.6; }
        .wrap { max-width:520px; margin:0 auto; padding:16px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:18px; margin-bottom:14px; }
        h1 { font-size:1.15rem; margin:0 0 2px; } h2 { font-size:1rem; margin:0 0 10px; }
        .muted { color:var(--muted); font-size:.9rem; }
        .row { display:flex; justify-content:space-between; gap:12px; padding:7px 0; border-bottom:1px dashed var(--line); }
        .row:last-child { border-bottom:0; }
        .big { font-size:1.6rem; font-weight:700; }
        .badge { display:inline-block; padding:2px 10px; border-radius:99px; font-size:.8rem; color:#fff; }
        .b-paid { background:var(--brand); } .b-partial { background:var(--warn); } .b-unpaid { background:var(--danger); }
        .btn { display:block; width:100%; text-align:center; background:var(--brand); color:#fff; border:0; border-radius:10px; padding:13px; font-size:1rem; font-family:inherit; cursor:pointer; text-decoration:none; }
        .btn.alt { background:transparent; color:var(--brand); border:1px solid var(--brand); }
        input[type=text], select { width:100%; padding:12px; border:1px solid var(--line); border-radius:10px; font-size:1rem; background:var(--bg); color:var(--text); margin:6px 0 12px; font-family:inherit; }
        label { font-size:.9rem; color:var(--muted); display:block; }
        .note { padding:10px 12px; border-radius:10px; margin-bottom:8px; font-size:.92rem; }
        .n-pending { background:#fef3c7; color:#92400e; } .n-rejected { background:#fee2e2; color:#991b1b; }
        input[type=number] { width:100%; padding:12px; border:1px solid var(--line); border-radius:10px; font-size:1rem; background:var(--bg); color:var(--text); margin:6px 0 12px; font-family:inherit; }
        .flash { padding:12px 14px; border-radius:10px; margin-bottom:14px; font-size:.95rem; }
        .ok { background:#d1fae5; color:#065f46; } .err { background:#fee2e2; color:#991b1b; }
        .small { font-size:.8rem; color:var(--muted); text-align:center; margin-top:8px; }
        @media print { body { background:#fff; color:#000; } .noprint { display:none !important; } .card { border:1px solid #999; } }
    </style>
</head>
<body>
<div class="wrap">
    @if (session('status')) <div class="flash ok">{{ session('status') }}</div> @endif
    @if (session('error')) <div class="flash err">{{ session('error') }}</div> @endif
    @if ($errors->any()) <div class="flash err">{{ $errors->first() }}</div> @endif
    @yield('content')
</div>
</body>
</html>
