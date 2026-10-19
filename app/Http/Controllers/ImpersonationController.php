<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Lets a super admin open a landlord's own panel ("view as"), and come back.
 * Start link is signed + admin-only; every start is written to subscription_logs.
 */
class ImpersonationController extends Controller
{
    public function start(int $user)
    {
        $admin = Auth::user();

        abort_unless($admin?->is_admin && ! session()->has('impersonator_id'), 403);

        $landlord = User::where('is_admin', false)->findOrFail($user);

        SubscriptionLog::create([
            'user_id' => $landlord->id,
            'admin_id' => $admin->id,
            'changes' => ['impersonation' => ['old' => null, 'new' => 'admin opened this account']],
        ]);

        session()->put('impersonator_id', $admin->id);

        Auth::login($landlord);
        session()->forget('password_hash_' . Auth::getDefaultDriver());

        return redirect('/owner');
    }

    public function leave()
    {
        $admin = User::where('is_admin', true)->find(session()->get('impersonator_id'));

        abort_unless($admin, 403);

        session()->forget('impersonator_id');

        Auth::login($admin);
        session()->forget('password_hash_' . Auth::getDefaultDriver());

        return redirect('/admin');
    }
}
