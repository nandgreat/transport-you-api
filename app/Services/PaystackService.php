<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the Paystack REST API. All calls are server-to-server
 * using the secret key; the frontend only ever receives the authorization URL.
 */
class PaystackService
{
    private string $secret;
    private string $base;

    public function __construct()
    {
        $this->secret = (string) config('services.paystack.secret_key');
        $this->base   = rtrim((string) config('services.paystack.base_url'), '/');
    }

    private function client()
    {
        return Http::withToken($this->secret)->acceptJson()->timeout(30);
    }

    /**
     * Initialize a transaction. $payload needs at least email + amount (in kobo).
     * Returns Paystack's data block (authorization_url, access_code, reference).
     */
    public function initialize(array $payload): array
    {
        $res = $this->client()->post("{$this->base}/transaction/initialize", $payload);

        abort_unless($res->successful() && $res->json('status') === true, 502,
            'Payment gateway error: ' . ($res->json('message') ?? 'could not initialize transaction.'));

        return $res->json('data');
    }

    /**
     * Verify a transaction by reference. Returns the data block, whose
     * `status` is "success" when the payment went through.
     */
    public function verify(string $reference): array
    {
        $res = $this->client()->get("{$this->base}/transaction/verify/" . rawurlencode($reference));

        abort_unless($res->successful() && $res->json('status') === true, 502,
            'Payment gateway error: ' . ($res->json('message') ?? 'could not verify transaction.'));

        return $res->json('data');
    }
}
