# DesignHub Seller Payout and Withdrawal Plan

## Current Project Decision

DesignHub will use the **marketplace/agent model**.

- The user who uploads a design or folder is its seller.
- Another user purchases that seller's item through DesignHub.
- DesignHub collects the buyer payment.
- DesignHub keeps a configurable platform commission.
- The remaining seller share is credited to the seller's internal earnings balance.
- The seller can request a withdrawal after the earning hold period.

For the first test-mode implementation, use this default revenue split:

```text
DesignHub platform commission: 25%
Seller share:                  75%
```

The 25% commission is a DesignHub business rule, not a government tax rate. It must be editable from Admin Settings and snapshotted on every purchase so that future commission changes do not alter old sales.

The first version will run in **Razorpay Test Mode** with simulated/manual withdrawals. No real money will be transferred to a seller bank account until live payment activation, marketplace payout-provider approval and tax review are complete.

## Objective

DesignHub users can upload and sell their own designs or folders. When another user purchases a seller's item, the system must:

1. Record the buyer payment.
2. Calculate the seller's earning and all deductions.
3. Hold the earning during the refund/review period.
4. Make the net amount available for withdrawal.
5. Transfer approved withdrawals to the seller's verified payout account.
6. Maintain a complete audit trail for refunds, taxes, fees and failed transfers.

## Important Decision Before Development

DesignHub must confirm whether it operates as:

- A marketplace/agent where the seller supplies the digital design to the buyer; or
- A merchant of record where DesignHub buys/sells in its own name.

The selected product model is the **marketplace/agent model**, but its tax and invoicing treatment must be confirmed with an Indian Chartered Accountant before live seller payouts because it changes:

- Who issues the buyer invoice.
- Who pays GST on the design sale.
- GST TCS applicability and reporting.
- Income-tax TDS responsibility.
- Refund and credit-note responsibility.

Also confirm the GST/SAC classification and applicable GST rate for downloadable embroidery, textile and sketch design files. Do not hard-code a GST rate before written confirmation.

Tax confirmation does not block test-mode development. Build all tax fields and calculation hooks now, but keep unconfirmed statutory rates configurable and disabled or set to zero in the test environment.

## Simple Test-Mode Example

For an item with a test selling value of INR 100:

```text
Buyer test payment:             INR 100.00
DesignHub commission at 25%:  - INR 25.00
Seller earning at 75%:          INR 75.00
```

Store monetary amounts as integer paise:

```text
Sale amount:           10000 paise
Commission rate:       2500 basis points
Commission amount:      2500 paise
Seller gross earning:   7500 paise
```

Use basis points for percentage settings:

```text
100 basis points   = 1%
2500 basis points  = 25%
10000 basis points = 100%
```

Recommended calculation:

```text
commission_paise = round(sale_base_paise * commission_basis_points / 10000)
seller_gross_paise = sale_base_paise - commission_paise
```

In the test MVP, this 75% is the seller share before any future CA-approved statutory deductions or provider fees. Buyer GST, GST on commission, GST TCS, TDS and payment-provider fees must be stored as separate components; never silently include them inside the 25% commission.

If GST is later charged to the buyer, do not treat collected GST as platform or seller revenue. The accountant-approved taxable base must be used for the 25/75 split.

## Pricing Experience for Seller and Buyer

The backend is the only trusted source for prices, commission, taxes, deductions and seller earnings. Mobile and web clients must display the breakdown returned by the backend and must not calculate or override financial values.

### Seller create/edit design or folder

The seller enters only the selling price and sees a server-calculated estimate:

```text
Selling price:                    INR 100.00
DesignHub commission (25%):      -INR 25.00
Estimated seller earning:         INR 75.00
Statutory deductions:             INR 0.00 (Test Mode)
```

The form must:

- Show the active platform commission policy.
- Show estimated seller earning before submission.
- Explain that the final net earning may change when CA-approved GST, TDS, TCS, provider fees, refunds or reversals apply.
- Require acceptance of the current seller terms/commission-policy version.
- Clearly label simulated earnings when the environment is `test`.

### Buyer listing, detail and checkout

The product card and detail page show the customer-facing price. Platform commission is not a buyer-facing charge.

Test-mode checkout example:

```text
Design price:         INR 100.00
GST:                    INR 0.00
Total payable:        INR 100.00
```

When CA-approved buyer tax is enabled, checkout must show the server-calculated taxable amount, applicable CGST/SGST or IGST breakdown, and final payable amount before Razorpay is opened.

After trusted payment verification, the buyer sees the order/payment reference, paid amount, purchase date, invoice availability and permitted clean-file download action.

### Seller wallet sale detail

Each sale must show:

```text
Buyer paid
Taxable value
Buyer GST
Platform commission
GST on platform commission
TDS
GST TCS
Provider/payout fee
Refund/reversal
Seller net earning
Earning status and available date
```

Only applicable rows should be displayed, but all stored values remain available for audit.

## Tax-Ready Configuration Strategy

Build the tax storage, calculation hooks, breakdown response and reporting fields now. During Test Mode, unconfirmed statutory components remain disabled or use a zero rate.

Before Live Mode, obtain written guidance from an Indian Chartered Accountant covering:

- Correct SAC/HSN classification for downloadable design files.
- Buyer GST rate and whether displayed prices are inclusive or exclusive of GST.
- Who issues the buyer invoice: seller or DesignHub.
- Registered and unregistered seller treatment.
- Intra-state CGST/SGST and inter-state IGST rules.
- GST on DesignHub platform commission.
- GST TCS applicability, rate and calculation base.
- Section 194-O TDS applicability, rate, thresholds and PAN/Aadhaar rules.
- Refund, partial-refund, tax reversal and credit-note responsibility.
- Seller statements, invoices, returns and accounting exports.
- Effective date of every approved rule.

Do not model taxes as one generic percentage. Keep separate configurable components for buyer GST, commission GST, TDS, GST TCS and provider fees.

Recommended purchase-time fields include:

```text
tax_configuration_version_id
taxable_amount_paise
buyer_gst_rate_basis_points
buyer_cgst_paise
buyer_sgst_paise
buyer_igst_paise
buyer_gst_paise
buyer_paid_paise
commission_rate_basis_points
commission_paise
commission_gst_rate_basis_points
commission_gst_paise
tds_rate_basis_points
tds_paise
gst_tcs_rate_basis_points
gst_tcs_paise
provider_fee_paise
seller_net_paise
```

Rates and calculation bases must be applied by versioned backend rules and snapshotted on every purchase. Activating a new rule must never recalculate an old transaction.

## Recommended Money Lifecycle

### 1. Buyer payment

The buyer pays the complete order amount through the payment gateway.

### 2. Payment verification

The backend verifies the payment signature or payment webhook. Financial records must only be created after trusted server-side verification.

### 3. Seller earning creation

Create one immutable earning snapshot for the purchased design or folder. The snapshot must contain the seller, item, prices, commission, tax, deductions and net earning that applied at the time of purchase.

Initial status:

```text
pending
```

### 4. Refund/review hold

Keep the seller earning unavailable for a configurable period, for example 7 to 14 days.

Status:

```text
held
```

### 5. Release earning

If there is no refund, dispute or administrative hold, move the net earning to the seller's available balance.

Status:

```text
available
```

### 6. Withdrawal request

The seller requests an amount from the available balance. The system reserves that amount immediately so it cannot be requested twice.

Status flow:

```text
requested -> approved -> processing -> paid
```

Possible alternative states:

```text
rejected
failed
reversed
cancelled
```

### 7. Provider transfer

After approval, create the Razorpay Route transfer or another approved payout-provider transaction.

- On success, mark the withdrawal as `paid`.
- On failure, return the reserved amount to the available balance.
- Webhook processing must be idempotent.

### Test-mode withdrawal behavior

During the first implementation:

1. Seller submits a withdrawal request from available test earnings.
2. The requested amount moves from `available` to `reserved`.
3. Admin approves or rejects the request.
4. Approval creates a simulated provider reference and marks the request `paid_test` or `paid` with an explicit test-mode flag.
5. Rejection or simulated failure releases the reserved amount back to `available`.

The UI must clearly display **Test Mode - No real money transfer**. Test withdrawals must never be mixed with future live withdrawal records.

## Financial Calculation

Every paid purchase must store a permanent financial breakdown.

```text
Product taxable value
+ Buyer GST
= Buyer paid amount

Seller gross earning
- Platform commission
- GST on platform commission
- Income-tax TDS
- GST TCS
- Allocated payment/transfer fee
- Refund or reversal
= Seller net earning
```

The exact calculation base for commission, GST, TDS and TCS must be approved by the accountant.

Never recalculate an old transaction using current settings. Store all applied values and rates as a purchase-time snapshot.

## Tax and Fee Components

### Customer GST

- Included in or added to the displayed product price according to platform policy.
- Store taxable value, GST rate, CGST, SGST and IGST separately.
- Determine intra-state or inter-state treatment using the legally relevant supplier and buyer locations.

### Platform commission

- Percentage commission, optional fixed fee, or both.
- Can be configured globally and later overridden by seller or item if needed.
- The applied commission must be saved on every earning record.

### GST on platform commission

The platform may need to issue a service-fee invoice to the seller and charge GST on its commission. Confirm the applicable rate and place-of-supply treatment with the accountant.

### GST TCS

Section 52 of the CGST Act can require an e-commerce operator that collects buyer consideration for other suppliers to collect GST TCS on net taxable supplies and report it through GSTR-8.

Reference: [CBIC FAQ on TCS](https://cbic-gst.gov.in/pdf/FAQs-TCS-30-11-2018.pdf)

The current applicable rate and exact applicability must remain configurable and must be confirmed before production use.

### Income-tax TDS

Section 194-O currently states a 0.1% deduction on the gross amount of sales or services facilitated for an e-commerce participant. PAN, seller type, thresholds and reporting requirements must be confirmed with the accountant.

Reference: [Income Tax Department - Section 194-O](https://www.incometaxindia.gov.in/w/section-194-o-5)

### Payment gateway and transfer fee

Decide whether DesignHub absorbs the provider cost or deducts it from the seller. This policy must be explicit and snapshotted on the transaction.

## Seller KYC and Payout Profile

Withdrawal should remain disabled until the required seller verification is complete.

Store:

- Legal name.
- Seller type: individual, proprietorship, company, partnership, etc.
- PAN and PAN verification status.
- GST registration status.
- GSTIN and GST verification status, if applicable.
- Registered address and state.
- Contact email and mobile number.
- Bank account holder name.
- Bank account number in encrypted form.
- Masked account number for display.
- IFSC code.
- Optional verified UPI ID.
- Provider linked-account ID.
- KYC status and rejection reason.
- Seller agreement version and acceptance timestamp.

Sensitive bank and tax information must not be returned unnecessarily through APIs or written into normal application logs.

## Recommended Database Structure

### `seller_profiles`

Seller identity, address, PAN, GSTIN and KYC status.

### `seller_payout_accounts`

Verified bank/UPI information and payment-provider linked-account identifiers.

### `sale_earnings`

One financial snapshot for each paid seller-owned design or folder purchase.

Suggested fields:

```text
id
public_id
purchase_id
seller_id
buyer_id
sellable_type
sellable_id
currency
taxable_amount_paise
buyer_gst_paise
buyer_paid_paise
seller_gross_paise
commission_rate
commission_paise
commission_gst_rate
commission_gst_paise
tds_rate
tds_paise
gst_tcs_rate
gst_tcs_paise
provider_fee_paise
refund_paise
seller_net_paise
status
available_at
calculation_snapshot_json
created_at
updated_at
```

### `seller_ledger_entries`

Immutable credits and debits for:

- Sale earning.
- Commission.
- Tax deduction.
- Refund.
- Withdrawal reservation.
- Paid withdrawal.
- Failed withdrawal release.
- Manual admin adjustment.

Do not use a manually editable balance as the only source of truth. Balance should be derived from ledger entries or maintained as a safely reconciled cache.

### `withdrawals`

Suggested fields:

```text
id
public_id
seller_id
payout_account_id
amount_paise
fee_paise
net_transfer_paise
currency
status
requested_at
approved_by
approved_at
processed_at
provider
provider_transfer_id
provider_status
failure_code
failure_message
rejection_reason
idempotency_key
created_at
updated_at
```

### `tax_documents`

Store generated seller invoices, platform commission invoices, credit notes and TDS/TCS statements.

### `provider_webhook_events`

Store provider event ID, event type, raw payload, signature verification result and processing status. The provider event ID must be unique to prevent duplicate processing.

### `payment_gateway_connections`

Store versioned Razorpay connections so an authorized Super Admin can rotate credentials or move to another approved Razorpay account without editing application code.

Suggested fields:

```text
id
public_id
provider
account_label
environment
key_id
encrypted_key_secret
encrypted_webhook_secret
status
is_active_for_new_orders
activated_at
deactivated_at
created_by
updated_by
created_at
updated_at
```

Orders, payments, purchases, earnings, withdrawals and webhook events must reference the applicable gateway connection or provider account snapshot. An old connection must be archived, not deleted, because refunds, webhooks, reconciliation and audits can still relate to it.

## Admin-Managed Settings

Add a Seller Payments section in Site Settings with versioned financial rules:

- Seller payouts enabled.
- Platform commission percentage, default `25%` (`2500` basis points) for the test MVP.
- Optional fixed commission.
- GST rate on platform commission.
- GST TCS enabled and rate.
- Income-tax TDS enabled and rate.
- Refund hold days.
- Minimum withdrawal amount.
- Maximum withdrawal amount.
- Withdrawal fee.
- Payment provider fee policy.
- Manual or automatic withdrawal approval.
- Allowed withdrawal days/frequency.
- GST-inclusive or GST-exclusive product prices.
- Effective date for each configuration version.
- Payment environment: `test` or `live`.
- Withdrawal processing mode: `simulated`, `manual` or `provider`.

Changing settings must only affect future purchases.

## Test Mode and Live Mode Separation

The server selects the payment environment. Mobile and web clients must never submit or override `test` or `live` mode.

Use a deployment-level environment value as the safety boundary:

```env
PAYMENT_MODE=test
```

Every financial record must also snapshot:

```text
payment_environment = test | live
gateway_connection_id
```

Rules:

- Test orders can only use a Test Mode gateway connection.
- Live orders can only use an active Live Mode gateway connection.
- Test payments cannot create live earnings or live withdrawals.
- Test balances must never be converted into live balances.
- Test and live webhook secrets and events remain separate.
- All Test Mode screens display `No real money will be charged or transferred`.
- Switching the deployment to Live Mode affects only newly created transactions.
- Enabling Live Mode requires an explicit production-readiness gate; it must not be a casual normal-admin toggle.

Example wallet metadata:

```json
{
  "environment": "test",
  "isRealMoney": false,
  "currency": "INR"
}
```

## Secure Razorpay Account and Credential Management

For the first safe deployment, `.env` or a managed secret store may provide bootstrap/fallback credentials. To support future account rotation without server-file editing, add a permission-protected Payment Gateway page for Super Admin.

The page may allow:

- Add a separate Test or Live Razorpay connection.
- Enter an account label, Key ID, Key Secret and webhook secret.
- Test the connection server-side before activation.
- Activate one eligible connection for new orders in each environment.
- Deactivate/archive a connection without deleting its history.
- Record who created, tested, activated or deactivated the connection.

Security requirements:

- Encrypt Key Secret and webhook secret at rest using Laravel encryption or a dedicated secret manager.
- Never store secrets in plain site settings.
- Never return secrets through mobile APIs or normal Admin responses.
- After save, show masked values only; do not reveal the secret again.
- Never write credentials to logs, exceptions, exports or source control.
- Restrict management to `payout-settings.manage` or a stricter Super Admin permission.
- Require HTTPS, audit logs and ideally recent authentication/2FA for credential changes.
- Preserve and back up Laravel `APP_KEY`; changing it without a planned re-encryption process makes encrypted credentials unreadable.
- Verify provider authentication before allowing activation.
- Block activation when the connection environment does not match `PAYMENT_MODE`.

Changing Razorpay accounts can also require seller Route linked accounts to be onboarded again under the new marketplace account. Confirm provider requirements and migrate linked accounts before activating real payouts on the new connection.

## Withdrawal Rules

Recommended first version:

- Configurable 7-14 day earning hold.
- Configurable minimum withdrawal, for example INR 500.
- Seller must complete KYC and bank verification.
- Only one open withdrawal request per seller.
- Requested amount cannot exceed available balance.
- Withdrawal request reserves the amount atomically inside a database transaction.
- Admin approval is required initially.
- Failed/rejected withdrawal releases the reserved amount.
- Negative seller balance blocks withdrawal.
- Refunds can use available balance and may create a recoverable negative balance.
- Every admin action requires actor, timestamp and note.
- A seller cannot purchase their own design or folder to generate artificial earnings.
- Only verified and successfully captured purchases can create seller earnings.
- One purchase can create only one sale-earning record.

## Razorpay Direction

[Razorpay Route](https://razorpay.com/route/) is designed for marketplace use cases and supports:

- Linked seller accounts.
- Payment transfers/splits.
- Settlement holds.
- Scheduled settlements.
- Transfer reversals.
- Transfer and settlement webhooks.

DesignHub still needs its own ledger as the financial source of truth. Provider records should be reconciled against internal records rather than replacing them.

Provider activation, linked-account KYC and live-mode approval are external prerequisites.

## Seller APIs

Suggested authenticated APIs:

```text
GET    /api/v1/seller/payout-profile
PUT    /api/v1/seller/payout-profile
POST   /api/v1/seller/payout-accounts
GET    /api/v1/seller/wallet
GET    /api/v1/seller/earnings
GET    /api/v1/seller/ledger
GET    /api/v1/seller/withdrawals
POST   /api/v1/seller/withdrawals
GET    /api/v1/seller/withdrawals/{withdrawalId}
POST   /api/v1/seller/withdrawals/{withdrawalId}/cancel
```

Example wallet response:

```json
{
  "data": {
    "currency": "INR",
    "pendingPaise": 20000,
    "availablePaise": 50000,
    "reservedPaise": 10000,
    "paidPaise": 150000,
    "negativePaise": 0,
    "canWithdraw": true,
    "minimumWithdrawalPaise": 50000
  }
}
```

## Admin Features

- Seller KYC review.
- Seller payout-account review.
- Earnings and ledger view.
- Withdrawal request listing.
- Approve/reject withdrawal.
- Retry failed transfer.
- Place/remove seller payout hold.
- Manual adjustment with mandatory reason.
- Provider reconciliation.
- Tax and payout report export.
- Seller-wise and date-wise settlement reports.

## Refund and Dispute Handling

- Refund before earning release: cancel or reduce pending earning.
- Refund after earning release: debit available balance.
- Refund after withdrawal: create negative seller balance and recover from future sales.
- Provider transfer reversal: record a separate ledger entry; never delete the original transfer.
- Partial refunds must proportionally reverse commission and tax according to accountant-approved rules.
- Every operation must be idempotent.

## Security and Reliability Requirements

- Store all monetary values as integer paise.
- Use database transactions and row locking for withdrawal reservation.
- Never trust amount, commission or seller ID received from the mobile app.
- Verify all payment and payout webhook signatures.
- Add unique idempotency keys for payment, earning, webhook and withdrawal processing.
- Encrypt sensitive payout data.
- Do not expose full bank account or PAN through normal APIs.
- Log financial state changes without logging secrets.
- Maintain complete admin audit records.
- Reconcile provider transfers and internal ledger regularly.

## Recommended Implementation Phases

### Phase 0: Agreed MVP configuration

Use these initial test-mode decisions:

```text
Business model:                Marketplace/agent
Payment environment:           Razorpay Test Mode
Platform commission:           25%
Seller share:                  75%
Refund/earning hold:           7 days
Minimum withdrawal:            INR 500
Withdrawal approval:           Manual admin approval
Withdrawal transfer:           Simulated
Automatic bank payout:         Disabled
Unconfirmed statutory taxes:   Configurable, disabled/zero in test
```

### Phase 1: Compliance and configuration

1. Confirm marketplace model and taxes with CA.
2. Add versioned commission/tax/payout settings.
3. Add seller KYC and payout-account structure.
4. Prepare Razorpay Route linked-account onboarding.

### Phase 2: Earnings and ledger

1. Add sale earning snapshots for design and folder purchases.
2. Add immutable seller ledger entries.
3. Add pending, held and available states.
4. Handle refunds and negative balances.
5. Add admin earning and reconciliation screens.

### Phase 3: Manual withdrawals

1. Add seller wallet and transaction APIs.
2. Add withdrawal request API.
3. Add admin approve/reject workflow.
4. In test mode, allow admin to record a simulated transfer only.
5. After legal/provider readiness, allow admin to record a verified real manual bank transfer with proof/reference.

### Phase 4: Automated provider payouts

1. Create Razorpay linked accounts.
2. Create transfers with idempotency protection.
3. Process signed webhooks.
4. Handle failures and reversals.
5. Add automated reconciliation.

### Phase 5: Tax documents and reporting

1. Seller invoice/statement generation.
2. Platform commission invoice.
3. Credit notes and refund statements.
4. TDS/TCS seller reports.
5. Accounting and GST exports.

## Common Backend Architecture

Buying and selling primarily happens from the mobile application, but financial business rules must not be implemented inside the mobile app or duplicated inside Admin controllers.

Use this structure:

```text
Mobile buyer/seller APIs ----\
                              > Common financial services -> Ledger/database
Seller web panel -----------/
Admin panel ----------------/
```

Recommended common services:

```text
CommissionCalculator
SaleEarningService
SellerLedgerService
SellerWalletService
WithdrawalService
PayoutService
RefundService
```

Responsibilities:

- Controllers validate and authorize the request, then call a common service.
- Services calculate commission, create ledger entries and change financial states.
- Mobile and web clients only display server-calculated amounts and allowed actions.
- Admin actions use the same withdrawal and ledger services as seller APIs.
- No client may submit a trusted seller ID, commission amount, wallet balance or payout status.

## Authorization and Data Isolation

Permissions control which feature a user may access. Ownership rules control which records that user may read or change. Both checks are required even when a menu or button is hidden in the UI.

Seller permissions:

```text
wallet.view-own
earnings.view-own
ledger.view-own
withdrawals.view-own
withdrawals.create-own
withdrawals.cancel-own
```

Admin/finance permissions:

```text
wallets.view-any
earnings.view-any
withdrawals.view-any
withdrawals.manage
payouts.process
payout-settings.manage
```

Security rules:

- Seller APIs identify the seller from the authenticated user; do not accept `sellerId` from the client.
- Every seller query must scope records by `seller_id = authenticated user ID`.
- A seller cannot open another seller's earning, ledger entry or withdrawal by changing a URL ID.
- Unauthorized roles receive `403 Forbidden` even if they manually call the endpoint.
- Only users with explicit admin/finance permissions can view marketplace-wide financial records.
- Policies and automated tests must enforce ownership independently of UI visibility.

## Step-by-Step Implementation Board

Complete and test each group before moving to the next group. Mark a checkbox only after its code, automated tests and relevant UI/API behavior are verified.

### Step 1: Audit and protect the existing payment flow

- [ ] Document the current design order, verification and purchase-status flow.
- [ ] Document the current folder order, verification and purchase-status flow.
- [ ] Confirm every sellable design and folder has an owner/seller ID.
- [x] Prevent a seller from purchasing their own design or folder.
- [x] Confirm Razorpay amount is always calculated by the backend.
- [x] Confirm payment signatures and relevant webhooks are verified server-side.
- [x] Add an idempotency constraint so one purchase cannot create duplicate financial records.
- [x] Run regression tests proving the existing buyer purchase/download flow still works.

### Step 2: Add configurable marketplace settings

- [ ] Add versioned seller-payment settings.
- [x] Set test platform commission to `2500` basis points (25%).
- [x] Derive seller share as 75% for the current test configuration.
- [x] Add the 7-day earning hold setting.
- [x] Add the INR 500 minimum withdrawal setting.
- [ ] Add `test`/`live` environment and `simulated`/`manual`/`provider` payout modes.
- [x] Build permission-protected Admin settings UI.
- [x] Ensure setting changes affect only future purchases.
- [x] Add separate disabled/zero Test Mode settings for buyer GST, commission GST, TDS and GST TCS.
- [ ] Add versioned tax configuration and effective dates.
- [x] Return a server-calculated commission-only pricing preview for seller create/edit forms; Admin Design/Folder forms are connected and mobile can consume the same API.
- [x] Return a server-calculated buyer checkout breakdown for designs and folders. Tax and buyer-fee rows currently remain disabled and zero; clients must display the backend total and must not calculate it locally.

### Step 3: Build the common financial domain

- [ ] Add seller profile and payout-account tables.
- [x] Add `sale_earnings` purchase-time snapshot table.
- [x] Add immutable `seller_ledger_entries` table.
- [x] Add `withdrawals` table with status history and idempotency key.
- [ ] Add webhook event/audit storage.
- [x] Implement `CommissionCalculator` using integer paise and basis points.
- [ ] Implement common earning, ledger, wallet, withdrawal and refund services. Earning, ledger, wallet, seller request/cancel, and Admin approve/reject/test-payout processing are complete; refund and live-provider payout processing remain.
- [x] Use database transactions and row locks for withdrawal balance-changing operations.
- [ ] Add unit tests for rounding, INR 100 -> INR 25/75 split and boundary values.
- [ ] Add the versioned payment-gateway connection structure.
- [ ] Encrypt provider secrets and add masked Admin serialization.
- [ ] Snapshot gateway connection and payment environment on all financial records.

### Step 4: Connect successful buyer payments to seller earnings

- [x] After trusted payment verification, resolve the item's seller on the backend.
- [x] Create exactly one sale earning for a paid design purchase.
- [x] Create exactly one sale earning for a paid folder purchase.
- [x] Snapshot the applied commission rate and all calculated amounts.
- [x] Credit the seller's pending ledger balance.
- [x] Set `available_at` using the configured hold period.
- [x] Never create earnings for failed, unpaid or unverified purchases.
- [ ] Test duplicate verify calls and duplicate webhooks.

### Step 5: Release held earnings

- [x] Add a scheduled command/job to release eligible held earnings.
- [ ] Skip refunded, disputed, administratively held or reversed earnings.
- [x] Move released money from pending/held to available through ledger entries.
- [x] Make the release operation idempotent.
- [ ] Add an Admin action for an audited manual hold/release when permitted.

### Step 6: Build seller mobile APIs

- [x] Add authenticated seller wallet summary API.
- [x] Add paginated seller earnings API.
- [x] Add paginated seller ledger/transactions API.
- [x] Add paginated seller withdrawal-history API.
- [x] Add withdrawal detail API with ownership checks.
- [x] Add create-withdrawal API.
- [x] Add cancel-withdrawal API for allowed statuses.
- [x] Return action flags such as `canWithdraw` and server-calculated limits.
- [x] Add `403`, validation, insufficient-balance and idempotency response tests.
- [x] Update the Postman collection and mobile handoff documentation.

### Step 7: Build the seller mobile experience

- [ ] Add My Wallet entry visible only to an eligible seller role/permission.
- [ ] Show pending, available, reserved and paid balances from the API.
- [ ] Show earning and transaction history with pagination.
- [ ] Show withdrawal history and status.
- [ ] Add request-withdrawal form using server limits.
- [ ] Display validation and API errors clearly.
- [ ] Clearly label all test balances and withdrawals as simulated.
- [ ] Do not calculate or override commission and balances in the mobile app.

### Step 8: Build permission-based Admin management

- [x] Register seller payment setting plus own wallet, earning, ledger and withdrawal permissions, including separate Admin view/manage permissions.
- [x] Add Admin navigation only for authorized roles.
- [ ] Add all-seller wallet and earning views.
- [x] Add withdrawal queue with seller and ledger details.
- [ ] Add approve, reject and simulated-failure actions with mandatory notes.
- [x] Record the admin actor, mandatory note and timestamp for every implemented action.
- [x] Add payout settings page restricted to authorized administrators.
- [x] Ensure ordinary admin roles without financial permissions receive `403`.
- [x] Add tests for seller payment settings, withdrawal view/manage permissions, read-only finance roles and forbidden roles.
- [ ] Add Super Admin payment-gateway connection management.
- [ ] Add test-connection, activate, deactivate and audit actions.
- [ ] Prevent deletion of gateway connections referenced by financial records.

### Step 9: Test-mode end-to-end verification

- [ ] Seller creates a design/folder and another user buys it in Razorpay Test Mode.
- [ ] A test INR 100 sale records INR 25 commission and INR 75 seller earning.
- [ ] Seller sees only their own pending earning.
- [ ] Hold release makes the earning available exactly once.
- [ ] Withdrawal atomically moves money from available to reserved.
- [x] Admin approves and completes a simulated payout.
- [x] Rejection restores the reserved amount. Simulated failure remains pending.
- [ ] Refund behavior is correct before and after earning release.
- [ ] Two sellers cannot access each other's financial data.
- [ ] Test and live records cannot be mixed.
- [ ] Test credentials cannot be activated for Live Mode and vice versa.
- [ ] Seller and buyer pricing breakdowns match the stored financial snapshot.
- [ ] Archived gateway connections remain available for old transaction reconciliation.

### Step 10: Production-readiness gate

- [ ] Obtain written CA confirmation for invoices, GST, TDS and TCS treatment.
- [ ] Complete seller KYC and payout-account verification requirements.
- [ ] Obtain Razorpay marketplace/Route approval and live credentials.
- [ ] Verify live webhook signatures, retries and reconciliation.
- [ ] Complete security review and financial reconciliation tests.
- [ ] Enable real payouts only after all production gates are signed off.
- [ ] Enter CA-approved tax rules as a new effective-dated configuration version.
- [ ] Test registered/unregistered seller and intra/inter-state tax scenarios.
- [ ] Test full and partial refund tax reversals and credit-note behavior.
- [ ] Confirm seller Route linked-account compatibility before any Razorpay account switch.

## Where Development Should Start

Start with **Step 1 through Step 4 on the backend**, not with the mobile wallet screen.

The mobile app already initiates design/folder purchases, so preserve that buyer experience. First connect the existing trusted payment-verification result to the new common earning and ledger services. This creates the financial source of truth needed by every later screen.

Recommended first development slice:

```text
Existing mobile purchase
        -> backend verifies payment
        -> backend resolves seller
        -> backend applies 25% / 75% snapshot
        -> backend creates pending earning + ledger entry
        -> seller wallet API returns the result
```

After that slice is tested, build the seller wallet/withdrawal mobile UI and the permission-based Admin withdrawal workflow in parallel against the same backend services.

## Recommended Starting Scope

Start with:

1. Implement the agreed 25% platform / 75% seller test split.
2. Store the applied commission rate and amounts on every purchase.
3. Build an internal immutable ledger.
4. Build pending, held, available and reserved seller balances.
5. Build manual admin-approved simulated withdrawals.
6. Test purchase success, failure, duplicate verification, refund and withdrawal failure scenarios.
7. Obtain CA-approved live tax and invoicing rules.
8. Add Razorpay Route automation only after the internal flow is tested and reconciled.

This approach keeps the first release auditable and prevents provider or tax complexity from corrupting seller balances.

## MVP Acceptance Checklist

The test-mode marketplace MVP is ready when all of these are true:

- [ ] A seller-owned design and folder can be purchased by another user.
- [ ] A seller cannot purchase their own item.
- [ ] Successful payment verification creates exactly one earning snapshot.
- [ ] INR 100 test sale produces INR 25 platform commission and INR 75 seller gross earning.
- [ ] The earning remains unavailable during the configured hold period.
- [ ] The earning automatically or administratively moves to available after the hold.
- [ ] Seller can view pending, available, reserved and paid totals.
- [ ] Seller cannot request more than the available balance.
- [ ] Withdrawal request reserves the amount atomically.
- [ ] Admin can approve, reject and simulate failure.
- [ ] Rejected/failed withdrawal restores the available amount.
- [ ] Duplicate API calls or webhooks do not duplicate earnings or withdrawals.
- [ ] Refunds reverse the correct earning and ledger entries.
- [ ] Every financial record identifies whether it belongs to test or live mode.
- [ ] Seller APIs expose only the authenticated seller's financial data.
- [x] Admin financial pages and actions require explicit permissions.
- [ ] The UI clearly states that test-mode balances and withdrawals are not real money.
- [ ] Mobile and web clients display backend-calculated pricing and never submit trusted tax/commission amounts.
- [ ] Every transaction snapshots its Test/Live environment and gateway connection.
- [ ] Razorpay credentials are encrypted, masked, access-controlled and excluded from logs.
- [ ] Future tax-setting changes and gateway-account changes do not modify old transaction snapshots.

## Monthly wallet settlement cycles

- [x] Super Admin can configure multiple day ranges using From day, To day, and Settlement day (1-31).
- [x] The backend validates that configured cycles cover days 1-31 without gaps or overlaps.
- [x] New earnings snapshot their calculated settlement date; old earnings keep their existing `available_at` date.
- [x] Dates use `DESIGNHUB_MARKETPLACE_TIMEZONE` and day 31 is clamped for shorter months.
- [x] Each seller can use `manual` wallet withdrawal or `automatic` cycle settlement.
- [x] Manual-withdrawal API/UI is disabled for automatic sellers.
- [x] In test mode, an automatic settlement is completed through the internal simulator.
- [x] Seller earnings API returns the saved cycle, scheduled timestamp, timezone and a display label for mobile.
- [ ] In live mode, connect the automatic withdrawal request to the chosen payout provider and its webhook.
- [ ] Add failure/retry/reversal handling after the live payout provider is selected.

