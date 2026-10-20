<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Route;

Route::get('/create-admin-user', function () {
    // ইতিমধ্যে ইউজার তৈরি করা আছে কিনা চেক করবে
    $userExists = User::where('email', 'saidulislam0400@gmail.com')->exists();
    
    if (!$userExists) {
        User::create([
            'name' => 'Admin User',
            'email' => 'saidulislam0400@gmail.com', // এখানে আপনার আসল জিমেইলটি দিন
            'password' => Hash::make('12345678'), // এখানে আপনার পাসওয়ার্ড দিন
        ]);
        return 'Admin user created successfully!';
    }
    
    return 'User already exists!';
});

// Route::get('/', function () {
//     return view('welcome');
// });

require __DIR__ . '/step4.php';

