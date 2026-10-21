<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('support.company') }} — বাড়ি ভাড়ার হিসাব এখন সহজ</title>
    <meta name="description" content="বাড়ি ভাড়া, বিল, পেমেন্ট, রসিদ ও SMS রিমাইন্ডার এক জায়গায়। বাড়িওয়ালাদের জন্য সহজ বাংলা অ্যাপ।">
    <meta name="theme-color" content="#f59e0b">
    <meta name="background-color" content="#111827">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}" type="image/png">
    <link rel="icon" href="{{ asset('icons/icon-512.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <style>
        :root { --bg:#fffbeb; --card:#fff; --text:#1f2937; --muted:#6b7280; --line:#e5e7eb; --brand:#d97706; }
        @media (prefers-color-scheme: dark) { :root { --bg:#111827; --card:#1f2937; --text:#f3f4f6; --muted:#9ca3af; --line:#374151; } }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Noto Sans Bengali','Nirmala UI','Kalpurush',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; line-height:1.7; }
        .wrap { max-width:960px; margin:0 auto; padding:0 16px; }
        header { display:flex; justify-content:space-between; align-items:center; padding:14px 0; }
        .logo { font-weight:700; font-size:1.1rem; }
        .btn { display:inline-block; padding:11px 18px; border-radius:10px; background:var(--brand); color:#fff; text-decoration:none; font-weight:600; }
        .btn.alt { background:transparent; color:var(--brand); border:1px solid var(--brand); }
        .hero { text-align:center; padding:36px 0 26px; } .hero h1 { font-size:2rem; margin:0 0 8px; line-height:1.3; } .hero p { color:var(--muted); max-width:560px; margin:0 auto 18px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin:22px 0; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:16px; } .card h3 { margin:0 0 4px; font-size:1.02rem; }
        .muted { color:var(--muted); font-size:.93rem; } h2 { margin:34px 0 4px; font-size:1.35rem; }
        .price { font-size:1.5rem; font-weight:700; } footer { text-align:center; color:var(--muted); font-size:.9rem; padding:30px 0; } footer a { color:var(--muted); margin:0 6px; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <div class="logo">🏘️ {{ config('support.company') }}</div>
        <div><a class="btn alt" href="/owner/login">লগইন</a></div>
    </header>

    <section class="hero">
        <h1>বাড়ি ভাড়ার হিসাব এখন<br>ফোনেই, সহজে</h1>
        <p>ভাড়া, বিল, পেমেন্ট, রসিদ আর রিমাইন্ডার এক জায়গায়। খাতা-কলমের ঝামেলা শেষ।</p>
        <a class="btn" href="/owner/register">বিনামূল্যে শুরু করুন</a>
        <a class="btn alt"  href="/owner/login">লগইন</a>
    </section>

    <section class="grid">
        <div class="card"><h3>📒 ভাড়া ও বিল</h3><div class="muted">প্রতি মাসের ভাড়া নিজে নিজে তৈরি। গ্যাস, পানি, বিদ্যুৎ যোগ করুন।</div></div>
        <div class="card"><h3>💸 সহজ পেমেন্ট</h3><div class="muted">নগদ, bKash, Nagad, Rocket, ব্যাংক বা কার্ড। আংশিক পেমেন্টও চলে।</div></div>
        <div class="card"><h3>🧾 রসিদ</h3><div class="muted">প্রতিটি পেমেন্টে রসিদ, ফোনেই প্রিন্ট বা PDF।</div></div>
        <div class="card"><h3>💬 SMS রিমাইন্ডার</h3><div class="muted">শেষ তারিখের আগে ও পরে ভাড়াটিয়াকে অটো SMS।</div></div>
        <div class="card"><h3>📊 মাসিক রিপোর্ট</h3><div class="muted">আয়, বকেয়া ও খরচ এক নজরে, Excel-এও নিন।</div></div>
        <div class="card"><h3>🔒 নিরাপদ</h3><div class="muted">আপনার ডাটা শুধু আপনার। প্রতি বাড়িওয়ালার হিসাব আলাদা।</div></div>
    </section>

    @if ($plans->isNotEmpty())
        <h2>প্ল্যান</h2>
        <div class="muted">যেকোনো সময় প্ল্যান বদলানো যায়।</div>
        <section class="grid">
            @foreach ($plans as $plan)
                <div class="card">
                    <h3>{{ $plan->name }}</h3>
                    <div class="price">{{ $plan->monthly_fee > 0 ? '৳ ' . number_format((float) $plan->monthly_fee) . '/মাস' : 'বিনামূল্যে' }}</div>
                    <div class="muted">{{ $plan->max_units ? $plan->max_units . 'টি ইউনিট পর্যন্ত' : 'সীমাহীন ইউনিট' }}</div>
                </div>
            @endforeach
        </section>
    @endif

    <h2>যোগাযোগ</h2>
    <p class="muted">
        @if (config('support.whatsapp')) WhatsApp: {{ config('support.whatsapp') }} · @endif
        @if (config('support.phone')) ফোন: {{ config('support.phone') }} · @endif
        @if (config('support.email')) ইমেইল: {{ config('support.email') }} · @endif
        সময়: {{ config('support.hours') }}
    </p>

    <footer>
        <a href="{{ route('privacy') }}">গোপনীয়তা নীতি</a>
        <a href="{{ route('terms') }}">শর্তাবলী</a>
        <a href="{{ route('account.deletion') }}">অ্যাকাউন্ট মুছে ফেলা</a>
        <div>© {{ date('Y') }} {{ config('support.company') }}</div>
        <a href="/admin/login" style="font-size:12px; opacity:0.5">Admin panel</a>
    </footer>
</div>
</body>
</html>
