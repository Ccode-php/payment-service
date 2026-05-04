<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SERVICE TOKEN OLISH
    |--------------------------------------------------------------------------
    */
    private function getServiceToken()
    {
        $response = Http::asForm()->post(
            env('AUTH_URL') . '/oauth/token',
            [
                'grant_type' => 'client_credentials',
                'client_id' => env('AUTH_CLIENT_ID'),
                'client_secret' => env('AUTH_CLIENT_SECRET'),
                'scope' => '',
            ]
        );

        if (!$response->ok()) {
            throw new \Exception('Service token olishda xatolik');
        }

        return $response['access_token'];
    }

    /*
    |--------------------------------------------------------------------------
    | USER: Payment qilish
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|integer',
            'amount'   => 'required|numeric|min:1',
        ]);

        $serviceToken = $this->getServiceToken();

        // 1️⃣ Order totalni internal endpointdan olamiz
        $orderRes = Http::withToken($serviceToken)->get(
            env('ORDER_SERVICE_URL') .
                '/api/internal/orders/' .
                $data['order_id'] .
                '/total'
        );

        if (!$orderRes->ok()) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $order = $orderRes->json();

        if ((float)$data['amount'] !== (float)$order['total']) {
            return response()->json([
                'error' => 'Payment amount mismatch',
                'expected' => $order['total'],
                'sent' => $data['amount'],
            ], 422);
        }

        // 2️⃣ Payment yaratamiz
        $payment = Payment::create([
            'order_id' => $data['order_id'],
            'amount'   => $order['total'],
            'status'   => 'pending',
            'provider' => 'fake',
        ]);

        // 3️⃣ Fake gateway success
        $payment->update([
            'status' => 'success',
            'transaction_id' => Str::uuid(),
        ]);

        // 4️⃣ Order paid qilamiz (internal)
        Http::withToken($serviceToken)->post(
            env('ORDER_SERVICE_URL') .
                '/api/internal/orders/' .
                $payment->order_id .
                '/paid'
        );

        return response()->json([
            'message' => 'Payment success',
            'payment' => $payment
        ], 201);
    }
}