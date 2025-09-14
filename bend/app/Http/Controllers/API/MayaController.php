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
     * Create Single Payment (Pay with Maya: One-time wallet payment)
     * POST /payby/v2/paymaya/payments
     * Auth: Basic (PUBLIC key)
     * Docs: https://developers.maya.ph/reference/one-time-payment-using-maya-wallet
     */
    public function createPayment(Request $request)
    {
        // You decide WHAT is being paid for (appointment or visit)
        // Only trust amount from server-side (never from client)
        $request->validate([
            'appointment_id' => 'nullable|exists:appointments,id',
            'patient_visit_id' => 'nullable|exists:patient_visits,id',
        ]);

        // Compute/lookup amount due from your own logic
        $amountDue = $this->computeAmount($request); // implement your total logic here

        // 1) Create local Payment row (status = unpaid)
        $payment = Payment::create([
            'appointment_id'      => $request->appointment_id,
            'patient_visit_id'    => $request->patient_visit_id,
            'currency'            => 'PHP',
            'amount_due'          => $amountDue,
            'amount_paid'         => 0,
            'method'              => 'maya',
            'status'              => 'unpaid',
            'reference_no'        => 'PAY-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
            'created_by'          => auth()->id(),
        ]);

        // 2) Call Maya: Create Single Payment (PUBLIC key, Basic)
        $publicKey = config('services.maya.public');
        $baseUrl   = rtrim(env('MAYA_BASE', 'https://pg-sandbox.paymaya.com'), '/');

        $payload = [
            'totalAmount' => [
                'amount'   => (float) $payment->amount_due,
                'currency' => $payment->currency,
            ],
            // RRN you’ll use later to correlate (also stored by Maya)
            'requestReferenceNumber' => $payment->reference_no,
            // Where Maya redirects the user after attempting payment
            'redirectUrl' => [
                'success' => env('MAYA_SUCCESS_URL'),
                'failure' => env('MAYA_FAILURE_URL'),
                'cancel'  => env('MAYA_CANCEL_URL'),
            ],
            // Optional — show info on Maya page / for audit
            'metadata' => [
                'dcms_context' => [
                    'appointment_id'   => $payment->appointment_id,
                    'patient_visit_id' => $payment->patient_visit_id,
                ],
            ],
        ];

        $resp = Http::withHeaders([
                    'Authorization' => 'Basic '.base64_encode($publicKey.':'),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])
                ->post($baseUrl.'/payby/v2/paymaya/payments', $payload);

        if (!$resp->successful()) {
            // Keep payload for audit
            $payment->update([
                'status' => 'failed',
                'webhook_last_payload' => ['create_error' => $resp->json()],
            ]);
            return response()->json([
                'message' => 'Unable to create Maya payment.',
                'maya'    => $resp->json(),
            ], $resp->status());
        }

        $data = $resp->json();
        // Expect: paymentId + redirectUrl (per docs)
        $payment->update([
            'status'           => 'awaiting_payment',
            'maya_payment_id'  => $data['paymentId'] ?? null,
            'redirect_url'     => $data['redirectUrl'] ?? null,
        ]);

        // 3) Return only the redirectUrl to the frontend (React will window.location = redirectUrl)
        return response()->json([
            'payment_id'   => $payment->id,
            'maya_payment_id' => $payment->maya_payment_id,
            'redirect_url' => $payment->redirect_url,
        ]);
    }

    /**
     * Optional: Poll payment status (PUBLIC key)
     * GET /payments/v1/payments/{paymentId}/status
     */
    public function status(string $paymentId)
    {
        $publicKey = config('services.maya.public');
        $baseUrl   = rtrim(env('MAYA_BASE', 'https://pg-sandbox.paymaya.com'), '/');

        $resp = Http::withHeaders([
                    'Authorization' => 'Basic '.base64_encode($publicKey.':'),
                    'Accept'        => 'application/json',
                ])
                ->get($baseUrl.'/payments/v1/payments/'.$paymentId.'/status');

        return response()->json($resp->json(), $resp->status());
    }

    /**
     * Webhook receiver for real-time updates (source of truth)
     * Configure in Maya Business Manager to point here.
     * Verify signature if Maya provides one; store raw JSON for DOH audit.
     */
    public function webhook(Request $request)
    {
        $payload = $request->json()->all();

        // Typical fields include paymentId, status, rrn, amount, etc.
        $mayaPaymentId = $payload['paymentId'] ?? null;
        $status        = $payload['status'] ?? null;

        $payment = Payment::where('maya_payment_id', $mayaPaymentId)->first();

        if ($payment) {
            $updates = [
                'webhook_last_payload'      => $payload,
                'webhook_first_received_at' => $payment->webhook_first_received_at ?? now(),
            ];

            // Map Maya statuses → your enums
            // Example statuses: PAYMENT_SUCCESS / PAYMENT_FAILED / PAYMENT_CANCELLED (naming varies per doc set)
            if ($status === 'PAYMENT_SUCCESS' || $status === 'SUCCESS') {
                $updates['status']      = 'paid';
                $updates['amount_paid'] = $payment->amount_due;
                $updates['paid_at']     = now();
            } elseif (in_array($status, ['PAYMENT_CANCELLED','CANCELLED'])) {
                $updates['status']        = 'cancelled';
                $updates['cancelled_at']  = now();
            } elseif (in_array($status, ['PAYMENT_FAILED','FAILED'])) {
                $updates['status'] = 'failed';
            }

            // Optional extras from webhook
            $updates['rrn']       = $payload['rrn']       ?? $payment->rrn;
            $updates['auth_code'] = $payload['authCode']  ?? $payment->auth_code;

            $payment->update($updates);

            // Reflect to Appointments/Visits payment_status as needed
            if ($payment->appointment_id && $updates['status'] ?? null) {
                $payment->appointment()->update([
                    'payment_status' => $updates['status'] === 'paid' ? 'paid' :
                                       ($updates['status'] === 'cancelled' ? 'unpaid' :
                                       ($updates['status'] === 'failed' ? 'unpaid' : $payment->appointment->payment_status)),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function computeAmount(Request $request): float
    {
        // TODO: compute based on appointment_id / patient_visit_id, promos, bundles, etc.
        // For now return a placeholder:
        return 1500.00;
    }
}