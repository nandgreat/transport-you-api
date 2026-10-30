<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes one structured line per API request to the rotating daily "api" log
 * channel (storage/logs/api-Y-m-d.log). Sensitive fields are redacted so
 * credentials and payment secrets never hit disk.
 */
class LogApiRequests
{
    /**
     * Request keys whose values must never be logged.
     */
    protected array $redact = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'secret_key',
        'authorization',
        'otp',
        'pin',
        'cvv',
        'card_number',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = round((microtime(true) - $start) * 1000, 2);

        Log::channel('api')->info('API request', [
            'method'      => $request->method(),
            'path'        => '/' . ltrim($request->path(), '/'),
            'status'      => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'ip'          => $request->ip(),
            'user_id'     => optional($request->user())->id,
            'user_agent'  => Str::limit((string) $request->userAgent(), 255, ''),
            'query'       => $this->sanitize($request->query()),
            'body'        => $this->sanitize($request->except($this->redact)),
        ]);

        return $response;
    }

    /**
     * Recursively replace sensitive values with "[redacted]".
     */
    protected function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            } elseif (in_array(strtolower((string) $key), $this->redact, true)) {
                $data[$key] = '[redacted]';
            }
        }

        return $data;
    }
}
