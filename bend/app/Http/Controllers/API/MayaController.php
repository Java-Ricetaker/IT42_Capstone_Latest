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
     * Uses your **Pay with Maya** sandbox keys (NOT Checkout keys).
     *
     * Endpoint: POST {MAYA_BASE}/payby/v2/paymaya/payments
     * Auth: Basic <base64(SECRET_KEY:)>
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

        $secretKey = (string) config('services.maya.secret'); // server-to-server key
        $baseUrl = rtrim(env('MAYA_BASE', 'https://pg-sandbox.paymaya.com'), '/');

        // Wallet payload: amount as "amount" (not "value")
        $payload = [
            'totalAmount' => [
                'amount' => number_format((float) $payment->amount_due, 2, '.', ''),
                'currency' => $payment->currency,
            ],
            'requestReferenceNumber' => $payment->reference_no,
            'redirectUrl' => [
                'success' => env('MAYA_SUCCESS_URL'),
                'failure' => env('MAYA_FAILURE_URL'),
                'cancel' => env('MAYA_CANCEL_URL'),
            ],
            // optional buyer details (some wallets require at least email)
            'buyer' => [
                'firstName' => auth()->user()->name ?? 'Customer',
                'contact' => ['email' => auth()->user()->email],
            ],
            'metadata' => [
                'dcms_context' => [
                    'appointment_id' => $payment->appointment_id,
                    'patient_visit_id' => $payment->patient_visit_id,
                ],
            ],
        ];

        // Try the likely wallet endpoints in order; stop at first success
        $candidates = [
            '/payby/v2/payments',
            '/payby/v2/paymaya/payments',
            // add more if your sandbox doc specifies a product-specific path
            // '/payments/v1/paymaya/payments',
        ];

        $lastResp = null;

        foreach ($candidates as $path) {
            $endpoint = $baseUrl . $path;
            \Log::info('maya.payby.try', ['endpoint' => $endpoint]);

            $resp = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($secretKey . ':'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->post($endpoint, $payload);

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
            // If clearly an endpoint error (401 K004/K007 or 404), try next; otherwise break
            $body = $resp->json();
            $code = is_array($body) ? ($body['code'] ?? null) : null;
            if (!in_array($resp->status(), [401, 404]) && !in_array($code, ['K004', 'K007'])) {
                break;
            }
        }

        // None matched → record and bubble a readable error (422 to avoid scary 401 in console)
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
     * (Optional) Poll wallet payment status.
     * Depending on product version this may differ; leave as-is for now and
     * adjust path if your sandbox docs specify a different status endpoint.
     */
    public function status(string $paymentId)
    {
        $secretKey = (string) config('services.maya.secret');
        $baseUrl = rtrim(env('MAYA_BASE', 'https://pg-sandbox.paymaya.com'), '/');

        // Some wallet integrations expose a status endpoint under /payments/v1/... or /payby/v2/...
        // Try generic first; if 404/400, check your sandbox docs and change the path accordingly.
        $resp = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($secretKey . ':'),
            'Accept' => 'application/json',
        ])
            ->get($baseUrl . '/payments/v1/payments/' . urlencode($paymentId) . '/status');

        \Log::info('maya.payby.status.response', [
            'status' => $resp->status(),
            'body' => $resp->json() ?: $resp->body(),
        ]);

        return response()->json($resp->json() ?: ['raw' => $resp->body()], $resp->status());
    }

    /**
     * Webhook receiver (best for production)
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