<?php

use App\Http\Controllers\CronController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\OnlinePaymentController;
use App\Http\Controllers\PublicBillController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Public tenant pages (unguessable tokens, rate limited)
Route::get('/p/{token}', [PublicBillController::class, 'show'])->middleware('throttle:60,1')->name('bill.show');
Route::post('/p/{token}/pay', [PublicBillController::class, 'pay'])->middleware('throttle:10,1')->name('bill.pay');
Route::post('/p/{token}/claim', [PublicBillController::class, 'claim'])->middleware('throttle:5,1')->name('bill.claim');
Route::get('/r/{token}', [ReceiptController::class, 'show'])->middleware('throttle:60,1')->name('receipt.show');

// Payment gateway return + IPN (CSRF-exempt, see README)
Route::match(['get', 'post'], '/pay/return/{result}/{tranId}', [OnlinePaymentController::class, 'back'])
    ->whereIn('result', ['success', 'fail', 'cancel'])
    ->name('pay.return');
Route::post('/pay/ipn', [OnlinePaymentController::class, 'ipn'])->name('pay.ipn');

// Super admin: open a landlord's panel ("view as") and leave again
Route::get('/impersonate/start/{user}', [ImpersonationController::class, 'start'])
    ->middleware('signed')
    ->name('impersonate.start');
Route::post('/impersonate/leave', [ImpersonationController::class, 'leave'])->name('impersonate.leave');

// Landlord report (login required inside the controller; feature: reports)
Route::get('/report/month', [ReportController::class, 'month'])->name('report.month');

// Public site: home/landing + legal pages (needed for the Play Store listing)
// IMPORTANT: delete the default `Route::get('/', ...)` welcome route from routes/web.php
Route::get('/', [LegalController::class, 'home'])->name('home');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('terms');
Route::get('/account-deletion', [LegalController::class, 'accountDeletion'])->name('account.deletion');

// For hosts without cron (Render free): external pinger calls this every 5 minutes (secret in CRON_TOKEN)
Route::get('/cron/{token}', [CronController::class, 'run'])->middleware('throttle:30,1')->name('cron.run');

// Fallback storage route (ensures public storage files can be served even if symlink fails on Render or shared hosting)
Route::get('/storage/{path}', function (string $path) {
    try {
        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    } catch (Throwable) {
        abort(404);
    }
})->where('path', '.*')->name('public.storage');
