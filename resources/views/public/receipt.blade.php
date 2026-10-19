@extends('public.layout')

@php
    $types = \App\Models\Charge::TYPES;
    $due = $charge->dueAmount();
@endphp

@section('title', 'রসিদ R' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT))

@section('content')
    <div class="card">
        <h1>পেমেন্ট রসিদ</h1>
        <div class="muted">রসিদ নং R{{ str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) }} · {{ $payment->paid_at->format('d M Y, h:i A') }}</div>
    </div>

    <div class="card">
        <div class="row"><span>বাড়ি</span><strong>{{ $property->name }}</strong></div>
        <div class="row"><span>ইউনিট</span><strong>{{ $unit->name }}</strong></div>
        <div class="row"><span>ভাড়াটিয়া</span><strong>{{ $tenant->name }}</strong></div>
        <div class="row"><span>বিবরণ</span><span>{{ $types[$charge->type] ?? $charge->type }} — {{ $charge->period->format('M Y') }}</span></div>
        <div class="row"><span>মাধ্যম</span><span>{{ \App\Models\Payment::METHODS[$payment->method] ?? $payment->method }}</span></div>
        @if ($payment->trx_id)<div class="row"><span>Transaction ID</span><span>{{ $payment->trx_id }}</span></div>@endif
        <div class="row"><span>পরিশোধিত টাকা</span><span class="big">৳ {{ number_format((float) $payment->amount) }}</span></div>
        <div class="row"><span>এই বিলের বাকি</span><strong>৳ {{ number_format($due) }}</strong></div>
    </div>

    <div class="card muted" style="text-align:center">
        গ্রহণকারী: {{ $owner->name }}<br>
        এটি কম্পিউটারে তৈরি রসিদ, স্বাক্ষর লাগবে না।
    </div>

    <button class="btn noprint" onclick="window.print()">প্রিন্ট / PDF সেভ করুন</button>
@endsection
