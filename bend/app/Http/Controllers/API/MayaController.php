<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MayaController extends Controller
{
    /**
     * Create Pay with Maya (Wallet) one-time payment via PayBy API.
     * Uses your **Pay with Maya** sandbox keys.
     *
     * Endpoint (configurable): POST {MAYA_BASE}{MAYA_PAYBY_CREATE_PATH}
     * Auth: Basic <base64(PUBLIC_KEY:)>
     * Response: { paymentId, redirectUrl, ... }
     */
    public function createPayment(Request $request)
    {
        $request->validate([
            'appointment_id' => 'nullable|exists:appointments,id',
            'patient_visit_id' => 'nullable|exists:patient_visits,id',
        ]);

        $amountDue = $this->computeAmount($request);

        $payment = Payment::create([
            'appointment_id' => $request->appointment_id,
            'patient_visit_id' => $request->patient_visit_id,
            'currency' => 'PHP',
            'amount_due' => $amountDue,
            'amount_paid' => 0,
            'method' => 'maya',
            'status' => 'unpaid',
            'reference_no' => 'PAY-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
            'created_by' => auth()->id(),
        ]);

        // PUBLIC key for create (Basic <base64(publicKey:)>)
        $publicKey = (string) config('services.maya.public');

        // Base PayMaya host
        $baseUrl = rtrim(env('MAYA_BASE', 'https://pg-sandbox.paymaya.com'), '/');
        $createPath = env('MAYA_PAYBY_CREATE_PATH'); // try exact product path first if set

        // ---------- Resolve redirect URLs (must be absolute HTTPS) ----------
        // IMPORTANT: Don't rely on env() for APP/FRONTEND when config is cached.
        // Use config('app.url') as a reliable base and allow MAYA_*_URL env to override.
        $baseHost = rtrim((string) config('app.url'), '/'); 

        // If MAYA_*_URL envs are set, use them; otherwise derive from APP_URL
        $successUrl = env('MAYA_SUCCESS_URL') ?: ($baseHost . '/app/pay/success');
        $failureUrl = env('MAYA_FAILURE_URL') ?: ($baseHost . '/app/pay/failure');
        $cancelUrl = env('MAYA_CANCEL_URL') ?: ($baseHost . '/app/pay/cancel');

        \Log::info('maya.redirects.resolved', [
            'baseHost' => $baseHost,
            'successUrl' => $successUrl,
            'failureUrl' => $failureUrl,
            'cancelUrl' => $cancelUrl,
        ]);

        // Minimal URL validation (must be https, with host)
        foreach (['success' => $successUrl, 'failure' => $failureUrl, 'cancel' => $cancelUrl] as $label => $u) {
            $parts = parse_url($u);
            $ok = $u
                && filter_var($u, FILTER_VALIDATE_URL)
                && isset($parts['scheme'], $parts['host'])
                && strtolower($parts['scheme']) === 'https';
            if (!$ok) {
                return response()->json([
                    'message' => "Maya redirectUrl.{$label} must be a valid absolute https URL.",
                    'resolved' => ['url' => $u, 'baseHost' => $baseHost],
                ], 422);
            }
        }

        // ---------- Build payload (PayBy expects totalAmount.value) ----------
        $payload = [
            'totalAmount' => [
                'value' => number_format((float) $payment->amount_due, 2, '.', ''), // <-- "value", not "amount"
                'currency' => $payment->currency,
            ],
            'requestReferenceNumber' => $payment->reference_no,
            'redirectUrl' => [
                'success' => $successUrl,
                'failure' => $failureUrl,
                'cancel' => $cancelUrl,
            ],
            'buyer' => [
                'firstName' => auth()->user()->name ?? 'Customer',
                'contact' => ['email' => auth()->user()->email ?: 'no-reply@example.test'],
            ],
            'metadata' => [
                'dcms_context' => [
                    'appointment_id' => $payment->appointment_id,
                    'patient_visit_id' => $payment->patient_visit_id,
                ],
            ],
        ];

        \Log::info('maya.payby.payload.preview', [
            'totalAmount' => $payload['totalAmount'],
            'redirectUrl' => $payload['redirectUrl'],
        ]);

        // ---------- Endpoint candidates ----------
        $candidates = $createPath && is_string($createPath) && $createPath !== ''
            ? [$createPath]  // try configured product path first
            : [
                '/payby/v2/paymaya/payments',
                '/payby/v2/payments',
                // Add more if your sandbox product requires it:
                // '/payments/v1/paymaya/payments',
                // '/wallet/v2/payments',
                // '/payments/v2/wallet/payments',
            ];

        $lastResp = null;

        foreach ($candidates as $path) {
            $endpoint = $baseUrl . $path;
            \Log::info('maya.payby.try', ['endpoint' => $endpoint]);

            $resp = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($publicKey . ':'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Idempotency-Key' => $payment->reference_no,
                'X-Request-Id' => (string) Str::uuid(),
            ])->post($endpoint, $payload);

            \Log::info('maya.payby.response', [
                'endpoint' => $endpoint,
                'status' => $resp->status(),
                'body' => $resp->json() ?: $resp->body(),
            ]);

            if ($resp->successful()) {
                $data = $resp->json();

                $payment->update([
                    'status' => 'awaiting_payment',
                    'maya_payment_id' => $data['paymentId'] ?? null,
                    'redirect_url' => $data['redirectUrl'] ?? null,
                ]);

                return response()->json([
                    'payment_id' => $payment->id,
                    'maya_payment_id' => $payment->maya_payment_id,
                    'redirect_url' => $payment->redirect_url,
                ]);
            }

            $lastResp = $resp;

            // If it's not clearly a "wrong endpoint" (401/404/K004/K007), stop retrying
            $body = $resp->json();
            $code = is_array($body) ? ($body['code'] ?? null) : null;
            if (!in_array($resp->status(), [401, 404]) && !in_array($code, ['K004', 'K007'])) {
                break;
            }
        }

        // None matched → mark failed and bubble a readable error (422)
        $payment->update([
            'status' => 'failed',
            'webhook_last_payload' => ['create_error' => $lastResp?->json() ?: $lastResp?->body()],
        ]);

        return response()->json([
            'message' => 'Unable to create Maya wallet payment.',
            'maya' => $lastResp?->json() ?: ['raw' => $lastResp?->body()],
        ], 422);
    }

    /**
     * Optional: Poll wallet payment status (uses SECRET key).
     * Path varies by product → make it configurable.
     */
    public function status(string $paymentId)
    {
        $secretKey = (string) config('services.maya.secret');
        $baseUrl = rtrim(env('MAYA_BASE', 'https://pg-sandbox.paymaya.com'), '/');
        $statusPath = env('MAYA_STATUS_PATH', '/payments/v1/payments/{id}/status');

        $endpoint = $baseUrl . str_replace('{id}', urlencode($paymentId), $statusPath);

        $resp = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($secretKey . ':'),
            'Accept' => 'application/json',
        ])->get($endpoint);

        \Log::info('maya.payby.status.response', [
            'status' => $resp->status(),
            'body' => $resp->json() ?: $resp->body(),
        ]);

        return response()->json($resp->json() ?: ['raw' => $resp->body()], $resp->status());
    }

    /**
     * Webhook receiver (production best-practice).
     * Configure your Pay with Maya wallet webhook to post here.
     */
    public function webhook(Request $request)
    {
        $payload = $request->json()->all();

        $mayaPaymentId = $payload['paymentId'] ?? ($payload['id'] ?? null);
        $status = $payload['status'] ?? null;

        $payment = $mayaPaymentId
            ? Payment::where('maya_payment_id', $mayaPaymentId)->first()
            : null;

        if ($payment) {
            $updates = [
                'webhook_last_payload' => $payload,
                'webhook_first_received_at' => $payment->webhook_first_received_at ?? now(),
            ];

            if (in_array($status, ['PAYMENT_SUCCESS', 'SUCCESS', 'APPROVED'])) {
                $updates['status'] = 'paid';
                $updates['amount_paid'] = $payment->amount_due;
                $updates['paid_at'] = now();
            } elseif (in_array($status, ['PAYMENT_CANCELLED', 'CANCELLED'])) {
                $updates['status'] = 'cancelled';
                $updates['cancelled_at'] = now();
            } elseif (in_array($status, ['PAYMENT_FAILED', 'FAILED', 'DECLINED'])) {
                $updates['status'] = 'failed';
            }

            $payment->update($updates);

            if ($payment->appointment_id && isset($updates['status'])) {
                $payment->appointment()->update([
                    'payment_status' => $updates['status'] === 'paid' ? 'paid' : 'unpaid',
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function computeAmount(Request $request): float
    {
        // TODO: compute from appointment/visit/promos
        return 1500.00;
    }
}