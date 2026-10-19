<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>প্রবেশ করা যাচ্ছে না</title>
    <style>
        :root { --bg:#f4f6f8; --card:#fff; --text:#1f2937; --muted:#6b7280; --line:#e5e7eb; --brand:#d97706; }
        @media (prefers-color-scheme: dark) { :root { --bg:#111827; --card:#1f2937; --text:#f3f4f6; --muted:#9ca3af; --line:#374151; } }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Noto Sans Bengali','Nirmala UI','Kalpurush',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; line-height:1.7; }
        .wrap { max-width:480px; margin:0 auto; padding:24px 16px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:22px; }
        h1 { font-size:1.25rem; margin:0 0 8px; } .muted { color:var(--muted); font-size:.93rem; }
        .btn { display:block; text-align:center; width:100%; padding:12px; border-radius:10px; border:0; background:var(--brand); color:#fff; text-decoration:none; font-size:1rem; font-family:inherit; cursor:pointer; margin-top:10px; }
        .btn.alt { background:transparent; color:var(--brand); border:1px solid var(--brand); }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        @php
            $user = auth()->user();
            $inactive = $user && ! $user->is_admin && method_exists($user, 'hasActiveSubscription') && ! $user->hasActiveSubscription();
            $wa = preg_replace('/\D+/', '', (string) config('support.whatsapp'));
        @endphp

        @if ($inactive)
            <h1>আপনার অ্যাকাউন্ট এখন চালু নেই</h1>
            <p>অ্যাকাউন্টটি বন্ধ করা আছে অথবা সাবস্ক্রিপশনের মেয়াদ শেষ হয়ে গেছে। আপনার ডাটা সুরক্ষিত আছে। চালু করতে আমাদের সাথে যোগাযোগ করুন।</p>
        @else
            <h1>এই পেজে প্রবেশের অনুমতি নেই</h1>
            <p>আপনি ভুল অ্যাকাউন্টে লগইন করে থাকতে পারেন, অথবা এই পেজ দেখার অনুমতি আপনার নেই।</p>
        @endif

        <div class="muted">
            @if (config('support.phone')) ফোন: {{ config('support.phone') }}<br> @endif
            @if (config('support.whatsapp')) WhatsApp: {{ config('support.whatsapp') }}<br> @endif
            @if (config('support.email')) ইমেইল: {{ config('support.email') }}<br> @endif
            সময়: {{ config('support.hours') }}
        </div>

        @if (strlen($wa) === 11)
            <a class="btn" href="https://wa.me/88{{ $wa }}">WhatsApp-এ যোগাযোগ করুন</a>
        @endif

        @auth
            @if (\Illuminate\Support\Facades\Route::has('filament.owner.auth.logout'))
                <form method="POST" action="{{ route('filament.owner.auth.logout') }}">
                    @csrf
                    <button class="btn alt" type="submit">লগআউট</button>
                </form>
            @endif
        @endauth
        <a class="btn alt" href="/">হোম পেজ</a>
    </div>
</div>
</body>
</html>
