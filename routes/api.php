<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PayPalController;

// Group routes with the 'paypal' prefix
Route::prefix('paypal')->group(function () {
    // Route to create payment
    Route::get('/payment', [PayPalController::class, 'createPayment'])->name('paypal.payment');

    Route::post('/pay-with-card', [PayPalController::class, 'payWithCard'])->name('paypal.payWithCard');


    // Route to check payment status
    Route::get('/status', [PayPalController::class, 'paymentStatus'])->name('paypal.status');
});
