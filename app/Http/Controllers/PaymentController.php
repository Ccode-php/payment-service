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

        $orderRes = Http::withToken($serviceToken)->get(
            env('ORDER_SERVICE_URL') .
                '/api/internal/orders/' .
                $data['order_id'] .
                '/total'
        );

        if (!$orderRes->ok()) {
            return response()->json([
                'error' => 'Order not found'
            ], 404);
        }

        $order = $orderRes->json();

        $user = $request->get('auth_user');

        if ($order['user_id'] != $user['id']) {
            return response()->json([
                'error' => 'Forbidden'
            ], 403);
        }

        if ($order['status'] !== 'pending') {
            return response()->json([
                'error' => 'Order already processed'
            ], 400);
        }

        if ((float)$data['amount'] !== (float)$order['total']) {
            return response()->json([
                'error' => 'Payment amount mismatch',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | PAYMENT CREATE
    |--------------------------------------------------------------------------
    */

        $payment = Payment::create([
            'order_id' => $data['order_id'],
            'amount'   => $order['total'],
            'status'   => 'pending',
            'provider' => 'fake',
        ]);

        /*
    |--------------------------------------------------------------------------
    | ORDER PAID + STOCK DECREASE
    |--------------------------------------------------------------------------
    */

        $res = Http::withToken($serviceToken)->post(
            env('ORDER_SERVICE_URL') .
                '/api/internal/orders/' .
                $payment->order_id .
                '/paid'
        );

        /*
    |--------------------------------------------------------------------------
    | FAILED
    |--------------------------------------------------------------------------
    */

        if (!$res->ok()) {

            $payment->update([
                'status' => 'failed'
            ]);

            return response()->json([
                'error' => 'Order paid failed',
                'body' => $res->json(),
            ], 500);
        }

        /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

        $payment->update([
            'status' => 'success',
            'transaction_id' => Str::uuid(),
        ]);

        return response()->json([
            'message' => 'Payment success',
            'payment' => $payment
        ], 201);
    }
}
