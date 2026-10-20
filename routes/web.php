<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Route;

// ১ ক্লিকে অ্যাডমিন তৈরি ও সরাসরি Admin Dashboard-এ লগইন করার রুট
Route::get('/admin-login', function () {
    $user = User::firstOrNew(['email' => 'saidulislam0400@gmail.com']);

    $user->forceFill([
        'name' => 'Admin User',
        'email' => 'saidulislam0400@gmail.com',
        'password' => Hash::make('12345678'),
        'is_admin' => true,
        'is_active' => true,
        'email_verified_at' => $user->email_verified_at ?? now(),
    ])->save();

    Auth::login($user);
    request()->session()->regenerate();

    return redirect()->route('filament.admin.pages.dashboard');
});

// Route::get('/', function () {
//     return view('welcome');
// });

require __DIR__ . '/step4.php';

