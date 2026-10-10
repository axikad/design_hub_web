<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\WebhookEvent;
use App\Services\RazorpayPaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly RazorpayPaymentService $razorpay,
    ) {}

    public function purchaseStatus(Request $request, string $designId)
    {
        $design = Design::query()->listed()->where('public_id', $designId)->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        if (! $this->razorpay->isConfigured()) {
            return ApiResponse::error('Payments are not configured yet.', 'payments_unavailable', 503);
        }

        try {
            $payload = $this->razorpay->purchaseStatus($request->user(), $design);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error('Payment reconciliation failed.', 'payment_reconciliation_failed', 422);
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::error('Payment provider is temporarily unavailable.', 'payment_provider_unavailable', 503);
        }

        return ApiResponse::data($payload, message: 'Purchase status retrieved successfully.');
    }

    public function createOrder(Request $request)
    {
        if (! $this->razorpay->isConfigured()) {
            return ApiResponse::error('Payments are not configured yet.', 'payments_unavailable', 503);
        }

        $validated = $request->validate([
            'designId' => ['required', 'string'],
        ]);

        $design = Design::query()->listed()->where('public_id', $validated['designId'])->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        try {
            $payload = $this->razorpay->createOrder($request->user(), $design);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['designId' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::error('Payment provider is temporarily unavailable.', 'payment_provider_unavailable', 503);
        }

        return ApiResponse::data($payload, message: 'Payment order created successfully.');
    }

    public function verify(Request $request)
    {
        if (! $this->razorpay->isConfigured()) {
            return ApiResponse::error('Payments are not configured yet.', 'payments_unavailable', 503);
        }

        $validated = $request->validate([
            'designId' => ['required', 'string'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $design = Design::query()->listed()->where('public_id', $validated['designId'])->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        try {
            $payload = $this->razorpay->verify(
                $request->user(),
                $design,
                $validated['razorpay_order_id'],
                $validated['razorpay_payment_id'],
                $validated['razorpay_signature'],
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['razorpay_signature' => $exception->getMessage()]);
        } catch (\Exception $exception) {
            return ApiResponse::error('Payment verification failed.', 'payment_verification_failed', 422);
        }

        return ApiResponse::data($payload, message: 'Payment verified successfully.');
    }

    public function webhook(Request $request)
    {
        $secret = config('designhub.razorpay_webhook_secret');
        if (! filled($secret)) {
            return ApiResponse::error('Webhook secret is not configured.', 'webhook_unavailable', 503);
        }

        $signature = $request->header('X-Razorpay-Signature');
        $body = $request->getContent();
        $expected = hash_hmac('sha256', $body, $secret);

        if (! hash_equals($expected, (string) $signature)) {
            return ApiResponse::error('Invalid webhook signature.', 'invalid_webhook_signature', 400);
        }

        $payload = $request->json()->all();
        $payment = $payload['payload']['payment']['entity'] ?? null;
        $eventId = (string) ($payload['event'] ?? '').':'.(string) ($payment['id'] ?? uniqid());

        $event = WebhookEvent::query()->firstOrCreate(
            ['provider_event_id' => $eventId],
            [
                'event_type' => $payload['event'] ?? null,
                'payload' => $payload,
                'status' => 'received',
            ]
        );

        if ($event->wasRecentlyCreated && is_array($payment)) {
            try {
                $this->razorpay->reconcileWebhookPayment($payment);
                $event->update(['status' => 'processed']);
            } catch (\Throwable $exception) {
                report($exception);
                $event->update(['status' => 'failed']);

                return ApiResponse::error('Webhook processing failed.', 'webhook_processing_failed', 500);
            }
        }

        return ApiResponse::data(['received' => true], message: 'Webhook received successfully.');
    }
}
