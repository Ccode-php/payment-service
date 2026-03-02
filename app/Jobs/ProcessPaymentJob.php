<?php

namespace App\Jobs;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function handle()
    {
        try {
            // 1️⃣ FAKE / REAL PAYMENT
            $this->payment->update([
                'status' => 'success',
                'transaction_id' => Str::uuid(),
            ]);

            // 2️⃣ ORDER PAID (ASYNC)
            Http::post(
                env('ORDER_SERVICE_URL') .
                '/api/orders/'.$this->payment->order_id.'/paid'
            );

        } catch (Throwable $e) {

            // 🧯 PAYMENT FAILED
            $this->payment->update([
                'status' => 'failed'
            ]);

            throw $e; // queue retry qiladi
        }
    }
}

