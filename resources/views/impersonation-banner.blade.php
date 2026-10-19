<div style="position:sticky;top:0;z-index:9999;background:#b91c1c;color:#fff;padding:8px 14px;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;font-size:14px;">
    <span>অ্যাডমিন মোড: আপনি <strong>{{ auth()->user()->name }}</strong> এর অ্যাকাউন্ট দেখছেন। এখানে করা পরিবর্তন তার ডাটায় সেভ হবে।</span>
    <form method="POST" action="{{ route('impersonate.leave') }}" style="margin:0">
        @csrf
        <button type="submit" style="background:#fff;color:#b91c1c;border:0;border-radius:8px;padding:6px 12px;cursor:pointer;font-weight:600">অ্যাডমিনে ফিরে যাও</button>
    </form>
</div>
