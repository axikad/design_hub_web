<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\CatalogPaymentOrder;
use App\Models\Design;
use App\Models\Folder;
use App\Models\FolderPaymentOrder;
use App\Models\WebhookEvent;
use App\Services\BuyerCheckoutPricingService;
use App\Services\RazorpayCatalogPaymentService;
use App\Services\RazorpayFolderPaymentService;
use App\Services\RazorpayPaymentService;
use App\Support\ApiResponse;
use App\Support\IndiaGstState;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly RazorpayPaymentService $razorpay,
        private readonly RazorpayFolderPaymentService $folderRazorpay,
        private readonly RazorpayCatalogPaymentService $catalogRazorpay,
        private readonly BuyerCheckoutPricingService $checkoutPricing,
    ) {}

    public function checkoutPreview(Request $request)
    {
        $this->ensureSinglePurchasable($request);

        $validated = $request->validate([
            'designId' => ['required_without_all:folderId,catalogId', 'string'],
            'folderId' => ['required_without_all:designId,catalogId', 'string'],
            'catalogId' => ['required_without_all:designId,folderId', 'string'],
            'buyerStateCode' => ['nullable', 'string', Rule::in(IndiaGstState::codes())],
        ]);

        if (isset($validated['catalogId'])) {
            $catalog = Catalog::query()->where('public_id', $validated['catalogId'])->first();

            if ($catalog === null) {
                return ApiResponse::error('Catalog not found.', 'catalog_not_found', 404);
            }

            if ($catalog->price_paise < 100) {
                throw ValidationException::withMessages(['catalogId' => 'This catalog is not available for purchase.']);
            }

            if ($catalog->user_id === $request->user()->id) {
                throw ValidationException::withMessages(['catalogId' => 'You cannot purchase your own catalog.']);
            }

            try {
                $breakdown = $this->checkoutPricing->preview(
                    'catalog', $catalog->public_id, $catalog->name, (int) $catalog->price_paise,
                    $catalog->owner, $validated['buyerStateCode'] ?? null,
                );
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['buyerStateCode' => $exception->getMessage()]);
            }

            return ApiResponse::data($breakdown, message: 'Buyer checkout breakdown calculated successfully.');
        }

        if (isset($validated['folderId'])) {
            $folder = Folder::query()->where('public_id', $validated['folderId'])->first();

            if ($folder === null) {
                return ApiResponse::error('Folder not found.', 'folder_not_found', 404);
            }

            if ($folder->price_paise < 100) {
                throw ValidationException::withMessages(['folderId' => 'This folder is not available for purchase.']);
            }

            if ($folder->user_id === $request->user()->id) {
                throw ValidationException::withMessages(['folderId' => 'You cannot purchase your own folder.']);
            }

            try {
                $breakdown = $this->checkoutPricing->preview(
                    'folder', $folder->public_id, $folder->name, (int) $folder->price_paise,
                    $folder->owner, $validated['buyerStateCode'] ?? null,
                );
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['buyerStateCode' => $exception->getMessage()]);
            }

            return ApiResponse::data(
                $breakdown,
                message: 'Buyer checkout breakdown calculated successfully.',
            );
        }

        $design = Design::query()->listed()->where('public_id', $validated['designId'])->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        if ($design->price_paise === null) {
            throw ValidationException::withMessages(['designId' => 'This design is not available for purchase.']);
        }

        if ($design->user_id === $request->user()->id) {
            throw ValidationException::withMessages(['designId' => 'You cannot purchase your own design.']);
        }

        try {
            $breakdown = $this->checkoutPricing->preview(
                'design', $design->public_id, $design->title, (int) $design->price_paise,
                $design->owner, $validated['buyerStateCode'] ?? null,
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['buyerStateCode' => $exception->getMessage()]);
        }

        return ApiResponse::data(
            $breakdown,
            message: 'Buyer checkout breakdown calculated successfully.',
        );
    }

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
        $this->ensureSinglePurchasable($request);

        $validated = $request->validate([
            'designId' => ['required_without_all:folderId,catalogId', 'string'],
            'folderId' => ['required_without_all:designId,catalogId', 'string'],
            'catalogId' => ['required_without_all:designId,folderId', 'string'],
            'buyerStateCode' => ['nullable', 'string', Rule::in(IndiaGstState::codes())],
        ]);

        if (isset($validated['catalogId'])) {
            return $this->createCatalogOrder($request, $validated['catalogId'], $validated['buyerStateCode'] ?? null);
        }

        if (isset($validated['folderId'])) {
            return $this->createFolderOrder($request, $validated['folderId'], $validated['buyerStateCode'] ?? null);
        }

        if (! $this->razorpay->isConfigured()) {
            return ApiResponse::error('Payments are not configured yet.', 'payments_unavailable', 503);
        }

        $design = Design::query()->listed()->where('public_id', $validated['designId'])->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        try {
            $payload = $this->razorpay->createOrder($request->user(), $design, $validated['buyerStateCode'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                str_contains($exception->getMessage(), 'state') ? 'buyerStateCode' : 'designId' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::error('Payment provider is temporarily unavailable.', 'payment_provider_unavailable', 503);
        }

        $payload['checkoutBreakdown'] = $this->checkoutPricing->preview(
            'design',
            $design->public_id,
            $design->title,
            (int) $design->price_paise,
            $design->owner,
            $payload['buyerStateCode'] ?? ($validated['buyerStateCode'] ?? null),
        );

        return ApiResponse::data($payload, message: 'Payment order created successfully.');
    }

    public function verify(Request $request)
    {
        $this->ensureSinglePurchasable($request);

        $validated = $request->validate([
            'designId' => ['required_without_all:folderId,catalogId', 'string'],
            'folderId' => ['required_without_all:designId,catalogId', 'string'],
            'catalogId' => ['required_without_all:designId,folderId', 'string'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        if (isset($validated['catalogId'])) {
            return $this->verifyCatalogPayment($request, $validated);
        }

        if (isset($validated['folderId'])) {
            return $this->verifyFolderPayment($request, $validated);
        }

        if (! $this->razorpay->isConfigured()) {
            return ApiResponse::error('Payments are not configured yet.', 'payments_unavailable', 503);
        }

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
                if (CatalogPaymentOrder::query()->where('razorpay_order_id', $payment['order_id'] ?? null)->exists()) {
                    $this->catalogRazorpay->reconcileWebhookPayment($payment);
                } elseif (FolderPaymentOrder::query()->where('razorpay_order_id', $payment['order_id'] ?? null)->exists()) {
                    $this->folderRazorpay->reconcileWebhookPayment($payment);
                } else {
                    $this->razorpay->reconcileWebhookPayment($payment);
                }
                $event->update(['status' => 'processed']);
            } catch (\Throwable $exception) {
                report($exception);
                $event->update(['status' => 'failed']);

                return ApiResponse::error('Webhook processing failed.', 'webhook_processing_failed', 500);
            }
        }

        return ApiResponse::data(['received' => true], message: 'Webhook received successfully.');
    }

    public function folderPurchaseStatus(Request $request, string $folderId)
    {
        $folder = Folder::query()->where('public_id', $folderId)->first();

        if ($folder === null) {
            return ApiResponse::error('Folder not found.', 'folder_not_found', 404);
        }

        if (! $this->folderRazorpay->isConfigured()) {
            return ApiResponse::error('Payments are not configured yet.', 'payments_unavailable', 503);
        }

        try {
            $payload = $this->folderRazorpay->purchaseStatus($request->user(), $folder);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error('Payment reconciliation failed.', 'payment_reconciliation_failed', 422);
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::error('Payment provider is temporarily unavailable.', 'payment_provider_unavailable', 503);
        }

        return ApiResponse::data($payload, message: 'Purchase status retrieved successfully.');
    }

    public function catalogPurchaseStatus(Request $request, string $catalogId)
    {
        $catalog = Catalog::query()->where('public_id', $catalogId)->first();

        if ($catalog === null) {
            return ApiResponse::error('Catalog not found.', 'catalog_not_found', 404);
        }

        if (! $this->catalogRazorpay->isConfigured()) {
            return ApiResponse::error('Payments are not configured yet.', 'payments_unavailable', 503);
        }

        try {
            $payload = $this->catalogRazorpay->purchaseStatus($request->user(), $catalog);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error('Payment reconciliation failed.', 'payment_reconciliation_failed', 422);
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::error('Payment provider is temporarily unavailable.', 'payment_provider_unavailable', 503);
        }

        return ApiResponse::data($payload, message: 'Purchase status retrieved successfully.');
    }

    private function createFolderOrder(Request $request, string $folderId, ?string $buyerStateCode)
    {
        if (! $this->folderRazorpay->isConfigured()) {
            return ApiResponse::error('Payments are not configured yet.', 'payments_unavailable', 503);
        }

        $folder = Folder::query()->where('public_id', $folderId)->first();
        if ($folder === null) {
            return ApiResponse::error('Folder not found.', 'folder_not_found', 404);
        }

        try {
            $payload = $this->folderRazorpay->createOrder($request->user(), $folder, $buyerStateCode);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                str_contains($exception->getMessage(), 'state') ? 'buyerStateCode' : 'folderId' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::error('Payment provider is temporarily unavailable.', 'payment_provider_unavailable', 503);
        }

        $payload['checkoutBreakdown'] = $this->checkoutPricing->preview(
            'folder',
            $folder->public_id,
            $folder->name,
            (int) $folder->price_paise,
            $folder->owner,
            $payload['buyerStateCode'] ?? $buyerStateCode,
        );

        return ApiResponse::data($payload, message: 'Payment order created successfully.');
    }

    private function createCatalogOrder(Request $request, string $catalogId, ?string $buyerStateCode)
    {
        if (! $this->catalogRazorpay->isConfigured()) {
            return ApiResponse::error('Payments are not configured yet.', 'payments_unavailable', 503);
        }

        $catalog = Catalog::query()->where('public_id', $catalogId)->first();
        if ($catalog === null) {
            return ApiResponse::error('Catalog not found.', 'catalog_not_found', 404);
        }

        try {
            $payload = $this->catalogRazorpay->createOrder($request->user(), $catalog, $buyerStateCode);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                str_contains($exception->getMessage(), 'state') ? 'buyerStateCode' : 'catalogId' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::error('Payment provider is temporarily unavailable.', 'payment_provider_unavailable', 503);
        }

        $payload['checkoutBreakdown'] = $this->checkoutPricing->preview(
            'catalog', $catalog->public_id, $catalog->name, (int) $catalog->price_paise,
            $catalog->owner, $payload['buyerStateCode'] ?? $buyerStateCode,
        );

        return ApiResponse::data($payload, message: 'Payment order created successfully.');
    }

    /** @param array<string, string> $validated */
    private function verifyFolderPayment(Request $request, array $validated)
    {
        if (! $this->folderRazorpay->isConfigured()) {
            return ApiResponse::error('Payments are not configured yet.', 'payments_unavailable', 503);
        }

        $folder = Folder::query()->where('public_id', $validated['folderId'])->first();
        if ($folder === null) {
            return ApiResponse::error('Folder not found.', 'folder_not_found', 404);
        }

        try {
            $payload = $this->folderRazorpay->verify(
                $request->user(),
                $folder,
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

    /** @param array<string, string> $validated */
    private function verifyCatalogPayment(Request $request, array $validated)
    {
        if (! $this->catalogRazorpay->isConfigured()) {
            return ApiResponse::error('Payments are not configured yet.', 'payments_unavailable', 503);
        }

        $catalog = Catalog::query()->where('public_id', $validated['catalogId'])->first();
        if ($catalog === null) {
            return ApiResponse::error('Catalog not found.', 'catalog_not_found', 404);
        }

        try {
            $payload = $this->catalogRazorpay->verify(
                $request->user(), $catalog, $validated['razorpay_order_id'],
                $validated['razorpay_payment_id'], $validated['razorpay_signature'],
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['razorpay_signature' => $exception->getMessage()]);
        } catch (\Exception $exception) {
            return ApiResponse::error('Payment verification failed.', 'payment_verification_failed', 422);
        }

        return ApiResponse::data($payload, message: 'Payment verified successfully.');
    }

    private function ensureSinglePurchasable(Request $request): void
    {
        $identifiers = collect(['designId', 'folderId', 'catalogId'])
            ->filter(fn (string $key) => $request->filled($key));

        if ($identifiers->count() > 1) {
            throw ValidationException::withMessages([
                $identifiers->last() => 'Send only one of designId, folderId, or catalogId.',
            ]);
        }
    }
}
