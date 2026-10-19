<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>মাসিক রিপোর্ট — {{ $month->format('M Y') }}</title>
    <style>
        body { margin:0; padding:18px; font-family:'Noto Sans Bengali','Nirmala UI','Kalpurush',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; color:#111827; background:#fff; line-height:1.55; }
        h1 { font-size:1.3rem; margin:0 0 2px; } .muted { color:#6b7280; font-size:.9rem; }
        .top { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px; }
        .btn { display:inline-block; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; color:#111827; text-decoration:none; font-size:.9rem; background:#fff; cursor:pointer; }
        .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin:14px 0; }
        .card { border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; }
        .card b { display:block; font-size:1.15rem; }
        .scroll { overflow-x:auto; } table { border-collapse:collapse; width:100%; font-size:.92rem; min-width:640px; }
        th, td { border-bottom:1px solid #e5e7eb; padding:7px 8px; text-align:left; } th { background:#f9fafb; }
        td.n, th.n { text-align:right; white-space:nowrap; }
        .paid { color:#047857; } .partial { color:#b45309; } .unpaid { color:#b91c1c; }
        @media print { .noprint { display:none !important; } body { padding:0; } }
    </style>
</head>
<body>
@php
    $prev = $month->copy()->subMonth()->format('Y-m');
    $next = $month->copy()->addMonth()->format('Y-m');
    $statusLabel = ['unpaid' => 'বাকি', 'partial' => 'আংশিক', 'paid' => 'পরিশোধ'];
    $money = fn ($v) => '৳ ' . number_format((float) $v);
@endphp

<div class="top">
    <div>
        <h1>মাসিক রিপোর্ট — {{ $month->format('F Y') }}</h1>
        <div class="muted">{{ $user->name }}</div>
    </div>
    <div class="noprint">
        <a class="btn" href="{{ route('report.month', ['month' => $prev]) }}">← আগের মাস</a>
        <a class="btn" href="{{ route('report.month', ['month' => $next]) }}">পরের মাস →</a>
        <a class="btn" href="{{ route('report.month', ['month' => $month->format('Y-m'), 'format' => 'csv']) }}">CSV (Excel)</a>
        <button class="btn" onclick="window.print()">প্রিন্ট / PDF</button>
        <a class="btn" href="/owner">← অ্যাপে ফিরুন</a>
    </div>
</div>

<div class="cards">
    <div class="card"><span class="muted">মোট বিল</span><b>{{ $money($billed) }}</b></div>
    <div class="card"><span class="muted">এই মাসে আদায় (পেমেন্ট)</span><b>{{ $money($collected) }}</b></div>
    <div class="card"><span class="muted">এই মাসের বিলের বাকি</span><b>{{ $money($due) }}</b></div>
    @if ($expenses !== null)
        <div class="card"><span class="muted">খরচ</span><b>{{ $money($expenses) }}</b></div>
        <div class="card"><span class="muted">আদায় − খরচ</span><b>{{ $money($collected - $expenses) }}</b></div>
    @endif
</div>

<div class="scroll">
<table>
    <thead>
        <tr><th>বাড়ি</th><th>ইউনিট</th><th>ভাড়াটিয়া</th><th>ধরন</th><th class="n">বিল</th><th class="n">জমা</th><th class="n">বাকি</th><th>অবস্থা</th></tr>
    </thead>
    <tbody>
    @forelse ($charges as $charge)
        <tr>
            <td>{{ $charge->lease?->unit?->property?->name }}</td>
            <td>{{ $charge->lease?->unit?->name }}</td>
            <td>{{ $charge->lease?->tenant?->name }}</td>
            <td>{{ \App\Models\Charge::TYPES[$charge->type] ?? $charge->type }}</td>
            <td class="n">{{ $money($charge->amount) }}</td>
            <td class="n">{{ $money($charge->paid_amount) }}</td>
            <td class="n">{{ $money($charge->dueAmount()) }}</td>
            <td class="{{ $charge->status }}">{{ $statusLabel[$charge->status] ?? $charge->status }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">এই মাসে কোনো বিল নেই।</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<p class="muted">"এই মাসে আদায়" হলো এই মাসের ভেতরে নেওয়া সব পেমেন্ট (আগের মাসের বকেয়াসহ)।</p>
</body>
</html>
