<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class VerifyToken
{
    public function handle($request, Closure $next)
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $token = str_replace('Bearer ', '', $header);

        try {
            $publicKey = file_get_contents('/var/www/storage/oauth-public.key');

            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            $request->merge([
                'auth_user' => [
                    'id' => $decoded->sub ?? null,
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}