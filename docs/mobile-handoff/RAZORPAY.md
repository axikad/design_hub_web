# Razorpay checkout setup

## What is implemented

- INR price entry, integer paise in design create/update requests, and formatted prices on cards/details.
- Buy now only on the detail screen for other users' priced designs; your own designs cannot be purchased.
- iOS UPI app-query schemes configured through `app.json` (no hand-edited native folders).
- Android/iOS `react-native-razorpay` checkout, browser Standard Checkout, authenticated Axios order/status/verification requests.
- One checkout at a time, cancellation/error messages, pending verification retries, and purchase status reconciliation before each new checkout. A checkout callback alone never marks a design purchased.
- Demo mode never takes money or fabricates successful payments. The detail screen can open a test-only checkout preview when a public test Key ID is configured.

This repository is the mobile/web client, not a payment backend. The endpoints below must be implemented on your server before payments work. No live transaction or native-device checkout has been tested. `tests/commerce.cjs` uses mocked checkout and HTTP responses.

## Test checkout now (without a backend)

In the ignored `.env.local` file, leave the API URL blank and set your own public test key:

```dotenv
EXPO_PUBLIC_API_URL=
EXPO_PUBLIC_RAZORPAY_TEST_KEY_ID=rzp_test_YOUR_KEY_ID
```

Restart Expo, open another user's/sample design, and tap **Buy now** on its details. This preview opens Razorpay with the design amount and no server order. It accepts only `rzp_test_` keys and never records a purchase, unlocks a file, or reports a verified payment. A valid key from your Razorpay account is required; the placeholder above will not work. Do not use this preview for live transactions.

To switch to real payments, configure the backend below and set `EXPO_PUBLIC_API_URL`. The normal order/verification flow then takes over and uses the key returned by the backend. Simply replacing the preview key with a live key is intentionally rejected.

## App setup

1. Set `EXPO_PUBLIC_API_URL` in `.env.local` to the backend URL. Restart Expo.
2. Install a development build for native checkout. `expo-dev-client`, the Razorpay SDK and `eas.json` profiles are included. Expo Go cannot run this native SDK.
3. Once the project is linked to your Expo account and application identifiers/signing are configured, build with `npx eas-cli@latest build --platform android --profile development` or the corresponding `ios` command. Use `development-simulator` for an iOS Simulator build. Then run `npx expo start --dev-client`.
4. On web, checkout loads Razorpay's official HTTPS script on demand. Allow `https://checkout.razorpay.com` in your web CSP if you set one, and enable backend CORS for your app origin.

Do not put the Razorpay key secret or webhook secret in Expo environment variables or in this app. The backend returns the public `keyId` with the order; there is no Razorpay secret setting on the client.

## Server secrets

Configure these only on the backend:

```dotenv
RAZORPAY_KEY_ID=rzp_test_...
RAZORPAY_KEY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=...
```

Keep test/live order creation, verification and webhook configuration in the same mode. Use test keys for development. Set up captured-payment handling before enabling live mode.

## Required API contract

All endpoints require the existing Bearer token and respond with `{ "data": ... }`. Errors use HTTP 4xx/5xx with `{ "message": "..." }`.

### GET /purchases/designs/:designId

```json
{ "data": { "designId": "d1", "status": "unpaid" } }
```

Status is `unpaid`, `pending` or `paid`; `paid` should include a `purchaseId`. Return `unpaid` rather than 404 when the buyer has no purchase. `pending` means an authorized/processing payment needs reconciliation, not simply that an unpaid order was created. This endpoint must reconcile existing attempts using Razorpay's server API/webhooks, including after the app was closed. Do not return `unpaid` while the previous payment outcome is uncertain.

### POST /payments/orders

Request:

```json
{ "designId": "d1" }
```

Response:

```json
{
  "data": {
    "designId": "d1",
    "orderId": "order_...",
    "keyId": "rzp_test_...",
    "amount": 29900,
    "currency": "INR"
  }
}
```

Server responsibilities:

- Authenticate the buyer. Look up the design, reject buying one's own design, unavailable designs and already-paid purchases.
- Load `pricePaise` from the database. Never accept price, seller, currency or user identity from the client as authoritative.
- Serialize order creation per buyer/design in the database. Reuse an existing unpaid Razorpay order after retries/cancellation; reconcile unknown or pending attempts before allowing another order. An app-side lock cannot prevent duplicates across devices or requests that time out.
- Create the order via Razorpay Orders API using the backend secret, integer amount and INR. Persist order ID, buyer ID, design ID, seller, amount, currency and attempt status before returning checkout data. Use a unique receipt and reconcile uncertain order-creation results.
- Razorpay's checkout displays the server's current price, which may differ from a stale feed card.

### POST /payments/verify

Request:

```json
{
  "designId": "d1",
  "razorpay_order_id": "order_...",
  "razorpay_payment_id": "pay_...",
  "razorpay_signature": "..."
}
```

Response after verification **and capture**:

```json
{ "data": { "designId": "d1", "status": "paid", "purchaseId": "purchase_..." } }
```

Return status `pending` for an authentic payment awaiting capture. Verification must be idempotent because the app retries it when confirmation fails.

1. Load your persisted order for this authenticated buyer/design. Compare its order ID with the callback ID.
2. Compute HMAC-SHA256 of `storedOrderId + "|" + razorpay_payment_id` using the key secret; compare to the signature using constant-time comparison. The stored order ID is authoritative.
3. Fetch the payment from Razorpay's server API. Confirm its order ID, amount and currency match the stored order, and its status is `captured` before returning `paid`.
4. In a database transaction, record payment/purchase with unique constraints for payment ID and buyer/design. Repeated callbacks must return the existing purchase, not deliver twice.
5. Never expose protected stitch-file URLs in the public feed. Return file access only to the owner or a verified buyer through a protected delivery endpoint. This change adds purchase confirmation; protected downloads and seller settlements need backend implementation.

### Webhooks and recovery

Implement `POST /payments/webhook` on the backend. Verify `X-Razorpay-Signature` against the **raw** request bytes using `RAZORPAY_WEBHOOK_SECRET`. Deduplicate event IDs and reconcile `payment.captured` / `order.paid`, failed attempts and refunds in the database. Validate amount/order mapping before granting access. Webhooks must complete purchases even if the user closes the app before its success callback. Pending callback proofs are retained only for the current client session; server reconciliation is required after restarts.

## Verification commands

```sh
node tests/api.cjs
node tests/commerce.cjs
npx expo lint
npx tsc --noEmit
```

Before accepting real payments, test on an installed native development build and web with a test backend: success, cancellation, failed payment, double tap, app closure, lost verification response, webhook replay, wrong signature/order/amount, already purchased and expired auth.

## Official references

- [Razorpay React Native integration](https://razorpay.com/docs/payments/payment-gateway/react-native-integration/standard/integration-steps-android/)
- [Razorpay Standard Checkout and verification](https://razorpay.com/docs/payments/payment-gateway/web-integration/standard/integration-steps/)
- [Expo development builds](https://docs.expo.dev/develop/development-builds/introduction/)
