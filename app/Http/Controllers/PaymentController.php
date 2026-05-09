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
    try {

        $data = $request->validate([
            'order_id' => 'required|integer',
            'amount'   => 'required|numeric|min:1',
        ]);

        \Log::info('STEP 1');

        $serviceToken = $this->getServiceToken();

        \Log::info('STEP 2');

        $orderRes = Http::withToken($serviceToken)->get(
            env('ORDER_SERVICE_URL') .
                '/api/internal/orders/' .
                $data['order_id'] .
                '/total'
        );

        \Log::info('STEP 3', [
            'status' => $orderRes->status(),
            'body' => $orderRes->body(),
        ]);

        if (!$orderRes->ok()) {
            return response()->json([
                'error' => 'Order not found'
            ], 404);
        }

        $order = $orderRes->json();

        \Log::info('STEP 4');

        $payment = Payment::create([
            'order_id' => $data['order_id'],
            'amount'   => $order['total'],
            'status'   => 'pending',
            'provider' => 'fake',
        ]);

        \Log::info('STEP 5');

        $payment->update([
            'status' => 'success',
            'transaction_id' => Str::uuid(),
        ]);

        \Log::info('STEP 6');

        $res = Http::withToken($serviceToken)->post(
            env('ORDER_SERVICE_URL') .
                '/api/internal/orders/' .
                $payment->order_id .
                '/paid'
        );

        \Log::info('STEP 7', [
            'status' => $res->status(),
            'body' => $res->body(),
        ]);

        return response()->json([
            'message' => 'Payment success',
            'payment' => $payment
        ], 201);

    } catch (\Throwable $e) {

        \Log::error('PAYMENT ERROR', [
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ]);

        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}
}
