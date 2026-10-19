@extends('public.layout')

@php
    $types = \App\Models\Charge::TYPES;
    $statusLabel = ['unpaid' => 'বাকি', 'partial' => 'আংশিক পরিশোধ', 'paid' => 'পরিশোধিত'];
    $due = $charge->dueAmount();
@endphp

@section('title', 'ভাড়া/বিল — ' . $property->name)

@section('content')
    <div class="card">
        <h1>{{ $property->name }}</h1>
        <div class="muted">ইউনিট {{ $unit->name }} · {{ $tenant->name }}</div>
    </div>

    <div class="card">
        <div class="muted">{{ $types[$charge->type] ?? $charge->type }} — {{ $charge->period->format('M Y') }}</div>
        <div class="big">৳ {{ number_format($due) }} <span class="muted" style="font-size:.9rem;font-weight:400">বাকি</span></div>
        <span class="badge b-{{ $charge->status }}">{{ $statusLabel[$charge->status] ?? $charge->status }}</span>

        <div style="margin-top:12px">
            <div class="row"><span>মোট বিল</span><strong>৳ {{ number_format((float) $charge->amount) }}</strong></div>
            <div class="row"><span>জমা হয়েছে</span><strong>৳ {{ number_format((float) $charge->paid_amount) }}</strong></div>
            <div class="row"><span>শেষ তারিখ</span><strong>{{ $charge->due_on->format('d M Y') }}</strong></div>
            @if ($charge->description)<div class="row"><span>বিবরণ</span><span>{{ $charge->description }}</span></div>@endif
        </div>
    </div>

    @if ($canPayOnline)
        <div class="card">
            <h2>অনলাইনে পেমেন্ট করুন</h2>
            <div class="muted">bKash, Nagad, Rocket, Visa/Master কার্ড বা ইন্টারনেট ব্যাংকিং</div>
            <form method="POST" action="{{ route('bill.pay', $charge->pay_token) }}">
                @csrf
                <input type="number" name="amount" min="1" max="{{ $due }}" step="0.01" value="{{ $due }}" required>
                <button class="btn" type="submit">পেমেন্ট করুন</button>
            </form>
            <div class="small">নিরাপদ পেমেন্ট গেটওয়েতে নিয়ে যাওয়া হবে। আংশিক টাকাও দেওয়া যায়।</div>
        </div>
    @endif

    @if ($due > 0 && $instructions)
        <div class="card">
            <h2>{{ $canPayOnline ? 'অথবা সরাসরি পাঠান' : 'কীভাবে পেমেন্ট করবেন' }}</h2>
            <div>{!! nl2br(e($instructions)) !!}</div>
            <div class="small">পেমেন্ট করার পর বাড়িওয়ালাকে Transaction ID জানান।</div>
        </div>
    @endif

    @if ($claims->isNotEmpty())
        <div class="card">
            <h2>আপনার জমা দেওয়া তথ্য</h2>
            @foreach ($claims as $claim)
                @if ($claim->status === 'pending')
                    <div class="note n-pending">যাচাইয়ের অপেক্ষায়: ৳ {{ number_format((float) $claim->amount) }} · {{ $claimMethods[$claim->method] ?? $claim->method }} · {{ $claim->trx_id }}</div>
                @else
                    <div class="note n-rejected">গ্রহণ করা হয়নি: {{ $claim->trx_id }}@if ($claim->review_note) — {{ $claim->review_note }}@endif</div>
                @endif
            @endforeach
        </div>
    @endif

    @if ($due > 0)
        <div class="card">
            <h2>পেমেন্ট পাঠিয়েছেন? জানিয়ে দিন</h2>
            <div class="muted">bKash, Nagad, Rocket, ব্যাংক বা কার্ডে পাঠানোর পর Transaction ID দিন। বাড়িওয়ালা যাচাই করে রসিদ দেবেন।</div>
            <form method="POST" action="{{ route('bill.claim', $charge->pay_token) }}" style="margin-top:10px">
                @csrf
                <label for="c_amount">কত টাকা পাঠিয়েছেন</label>
                <input id="c_amount" type="number" name="amount" min="1" max="{{ $due }}" step="0.01" value="{{ old('amount', $due) }}" required>
                <label for="c_method">মাধ্যম</label>
                <select id="c_method" name="method" required>
                    @foreach ($claimMethods as $key => $label)
                        <option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <label for="c_trx">Transaction ID</label>
                <input id="c_trx" type="text" name="trx_id" maxlength="60" value="{{ old('trx_id') }}" required autocomplete="off">
                <label for="c_sender">যে নম্বর/অ্যাকাউন্ট থেকে পাঠিয়েছেন (ঐচ্ছিক)</label>
                <input id="c_sender" type="text" name="sender_number" maxlength="20" value="{{ old('sender_number') }}" inputmode="numeric">
                <button class="btn alt" type="submit">তথ্য জমা দিন</button>
            </form>
        </div>
    @endif

    @if ($payments->isNotEmpty())
        <div class="card">
            <h2>পেমেন্টের ইতিহাস</h2>
            @foreach ($payments as $payment)
                <div class="row">
                    <span>{{ $payment->paid_at->format('d M Y') }} · {{ \App\Models\Payment::METHODS[$payment->method] ?? $payment->method }}</span>
                    <span><strong>৳ {{ number_format((float) $payment->amount) }}</strong>
                        @if ($owner->hasFeature('receipt_pdf')) · <a href="{{ $payment->receiptUrl() }}">রসিদ</a> @endif</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="small">বাড়িওয়ালা: {{ $owner->name }}</div>
@endsection
