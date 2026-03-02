<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function store(Request $request)
{
    $data = $request->validate([
        'order_id' => 'required|integer',
        'amount'   => 'required|numeric|min:1',
    ]);

    // 1️⃣ Order service’dan totalni olamiz
    $orderRes = Http::withHeaders([
        'Authorization' => $request->header('Authorization')
    ])->get(
        env('ORDER_SERVICE_URL') . '/api/internal/orders/' . $data['order_id'] . '/total'
    );

    if (!$orderRes->ok()) {
        return response()->json(['error' => 'Order not found'], 404);
    }

    $order = $orderRes->json();

    // 2️⃣ Amount tekshiramiz
    if ((float)$data['amount'] !== (float)$order['total']) {
        return response()->json([
            'error' => 'Payment amount mismatch',
            'expected' => $order['total'],
            'sent' => $data['amount'],
        ], 422);
    }

    // 3️⃣ Payment pending
    $payment = Payment::create([
        'order_id' => $data['order_id'],
        'amount'   => $order['total'], // 🔒 faqat server total
        'status'   => 'pending',
        'provider' => 'fake',
    ]);

    // 4️⃣ Fake gateway
    $payment->update([
        'status' => 'success',
        'transaction_id' => Str::uuid(),
    ]);

    // 5️⃣ Order PAID
    Http::withHeaders([
        'Authorization' => $request->header('Authorization')
    ])->post(
        env('ORDER_SERVICE_URL') . '/api/orders/' . $payment->order_id . '/paid'
    );

    return response()->json([
        'message' => 'Payment success',
        'payment' => $payment
    ], 201);
}

}
