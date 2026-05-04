<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::middleware('verify.token')->group(function () {
    Route::post('/payments', [PaymentController::class, 'store']);
});