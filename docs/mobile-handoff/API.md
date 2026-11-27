# Connecting DesignHub to your backend

Copy `.env.example` to `.env.local`, set `EXPO_PUBLIC_API_URL=https://your-server.example/api/v1`, then restart Expo and reload the app. A blank URL uses the in-memory demo; a nonempty URL enables Axios for every data flow. No mock fallback occurs on server errors. Public env variables must not contain secrets.

Only the URL needs changing **if your backend implements this contract**. Otherwise update `src/api/endpoints.ts` and the request/response mappings in `src/api/services.ts`. No backend has been supplied or tested against yet.

## Guest browse

The app may browse without creating a guest token. Send no `Authorization` header for guest requests.

These `GET` endpoints are public: `/home`, `/designs`, `/designs/{id}`, `/folders`, `/folders/{id}`, and all browse catalog endpoints under `/catalog`. When a valid Bearer token is included, the same endpoints continue to apply the user's saved machine preferences.

Account actions, purchases, saves, uploads, mutations, downloads, image search, and all seller endpoints remain protected. A guest request to a protected endpoint returns HTTP `401`:

```json
{
  "success": false,
  "message": "Please sign in to continue.",
  "code": "AUTH_REQUIRED"
}
```

## Transport

JSON requests use a compact response envelope. Successful responses always contain `{ "success": true, "message": "...", "data": ... }`. Paginated responses additionally contain `meta` and `links`. Errors contain `{ "success": false, "message": "...", "code": "machine_readable_code" }`; validation errors additionally contain `errors` by field. Optional fields are omitted rather than returned as `null`. Axios uses a 20-second timeout and sends `Authorization: Bearer <accessToken>` after verification. An authenticated 401 signs the user out and clears remote data. Local development uses OTP `1234` when configured in `.env`.

Design and user collection endpoints accept `page` and `per_page` (default 20, maximum 100). Their `meta` contains `current_page`, `per_page`, `total`, `last_page`, `from`, and `to`; `links` contains `first`, `last`, `prev`, and `next`.

## Endpoints

| Method | Path                                               | Request                                                                                                 | Response data                                                                     |
| ------ | -------------------------------------------------- | ------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| GET    | `/auth/register/machines`                          | No token required                                                                                       | Full active machine list for registration                                         |
| POST   | `/auth/request-otp`                                | Login fields; registration additionally requires `name` and `machineIds: string[]`                     | `{ message: "OTP sent successfully." }`                                           |
| POST   | `/auth/verify-otp`                                 | Same fields and machine IDs plus `{ code: "1234" }`                                                   | `{ user: User, accessToken: string }`                                             |
| POST   | `/auth/logout`                                     | Bearer token; no request body                                                                              | Current access token revoked                                                      |
| GET    | `/users/me`                                        | Bearer token                                                                                            | `User`                                                                            |
| GET    | `/users/me/machines`                               | Bearer token                                                                                            | All active machines with `selected`, plus selected IDs                            |
| PUT    | `/users/me/machines`                               | `{ machineIds: ["embroidery", "air-jet-loom"] }`                                                    | Saved machine preferences                                                         |
| POST   | `/users/me`                                        | Multipart `name`, optional `image`, optional `removeImage`                                               | Updated `User`                                                                    |
| PATCH  | `/users/me`                                        | JSON `{ name: string }`                                                                                  | Updated `User`                                                                    |
| POST   | `/users/me/contact/request-otp`                    | `{ type: "mobile", countryCode, mobileNumber }` or `{ type: "email", email }`                         | Masked destination and OTP expiry                                                  |
| POST   | `/users/me/contact/verify-otp`                     | Same contact fields plus `{ code: "1234" }`                                                            | Updated `User`                                                                    |
| DELETE | `/users/me`                                        | JSON `{ confirmation: "DELETE" }`                                                                      | Account deleted and current tokens revoked                                         |
| GET    | `/catalog/browse-filters`                         | Public                                                                                                  | Complete machine-type, category, and design-type relationships for browse filters |
| GET    | `/catalog/design-options`                          | Public; optional Bearer token applies saved machine preferences                                         | Combined machines, machine types, and compatible categories (backward-compatible) |
| GET    | `/catalog/design-file-types`                       | Public; optional Bearer token and optional `machineId`                                                   | Active compatible design file types ordered by `sort_order`                       |
| GET    | `/catalog/machines`                                | Public; optional Bearer token applies saved machine preferences                                         | Active machines                                                                   |
| GET    | `/catalog/machines/:machineId/machine-types`       | Public                                                                                                  | Active machine types for the selected machine                                     |
| GET    | `/catalog/machines/:machineId/categories`          | Public                                                                                                  | Active categories available anywhere under the selected machine                   |
| GET    | `/catalog/machines/:machineId/design-file-types`   | Public                                                                                                  | Active file types compatible with the selected machine                            |
| GET    | `/catalog/machine-types/:machineTypeId/categories` | Public                                                                                                  | Compatible categories for the selected machine type                               |
| GET    | `/catalog/categories/:categoryId/types`            | Public                                                                                                  | Active design types for the selected category                                     |
| GET    | `/home`                                            | Public; optional Bearer token applies saved machine preferences                                         | Latest, today, tomorrow and category home sections                                |
| GET    | `/designs`                                         | Public; optional Bearer token applies saved machine preferences                                         | `Design[]`, all community designs, newest first                                   |
| POST   | `/designs/search-by-image`                         | Bearer token; multipart `image` plus optional catalog filters                                           | Search engine, threshold, and matching designs                                    |
| GET    | `/users/me/designs`                                | Bearer token                                                                                            | `Design[]`, only authenticated user's designs                                     |
| GET    | `/designs/:id`                                     | Public                                                                                                  | `Design` (404 if missing)                                                         |
| POST   | `/uploads`                                         | Bearer token; multipart form with one `file` field                                                      | `{ url: "https://..." }`                                                          |
| POST   | `/designs`                                         | `DesignRequest` below                                                                                   | Created `Design`                                                                  |
| POST   | `/designs/:id/images/:index/download`              | Bearer token; owner or paid buyer only                                                                  | Temporary signed clean-image download URL                                         |
| PATCH  | `/designs/:id`                                     | `DesignRequest` below                                                                                   | Updated `Design`                                                                  |
| DELETE | `/designs/:id`                                     | Bearer token                                                                                            | `{ message: "Design deleted successfully." }`                                     |
| GET    | `/folders`                                         | Public; optional `search`, `page`, `per_page`                                                           | Paginated `Folder[]`                                                              |
| POST   | `/folders`                                         | Bearer token; multipart `name`, `pricePaise`, `file` (ZIP or RAR)                                      | Created folder with extracted file metadata                                       |
| GET    | `/folders/:id?page=1&per_page=20`                  | Public                                                                                                  | Folder metadata and one page of ordered extracted files                            |
| POST   | `/payments/orders`                                 | `{ folderId: string }`                                                                                  | Razorpay order using the server-side folder price                                 |
| POST   | `/payments/verify`                                 | `{ folderId, razorpay_order_id, razorpay_payment_id, razorpay_signature }`                              | Folder payment status and purchase ID                                             |
| GET    | `/purchases/folders/:id`                           | Bearer token                                                                                            | `{ folderId, status: "unpaid" or "pending" or "paid" }`                           |
| POST   | `/folders/:id/download`                            | Bearer token; paid buyer only                                                                           | Temporary signed ZIP/RAR download URL                                             |

```ts
type User = {
    id: string;
    name: string | null;
    email: string | null;
    avatarUri: string | null;
    countryCode: string;
    mobileNumber: string;
    machineFilteringActive: boolean;
    selectedMachineIds: string[];
    selectedMachines: Array<{ id: string; name: string; imageUri: string | null }>;
};
type DesignRequest = {
    machineId: string; // Always send the selected machine.
    machineTypeId?: string; // Show when machineType is true; require when required.machineType is true.
    categoryId?: string; // Show when category is true; require when required.category is true.
    title?: string; // Optional. Without specifications, the backend uses the first preview filename, then "Untitled design".
    sequenceType?: number; // Used only when enabled; common design value in millimetres (mm).
    designTypes?: Array<{
        typeId?: string; // Catalog type. Send either this or customTypeName.
        customTypeName?: string; // Saved only for this design; not added to the catalog.
        details: {
            image?: { name: string; uri: string }; // One uploaded image for this design type.
            removeImage?: boolean; // Update only: true removes the existing image when image is optional.
            nameOrNumber: string;
            stitchCount: number;
            needle: number;
            area: number; // mm
            height: number; // mm
            width: number; // mm
        };
    }>;
    designFileTypeId?: string; // Show when designFileType is true; require when required.designFileType is true.
    showPrice: boolean;
    publishDate?: string; // YYYY-MM-DD; defaults to today in marketplace timezone
    pricePaise?: number; // Show when pricing is true; require when required.pricing is true.
    images?: Array<{ name: string; uri: string }>;
    files?: Array<{ name: string; uri: string; displayName?: string }>;
};
type DesignFormConfig = {
    machineType: boolean;
    category: boolean;
    designTypes: boolean;
    designSpecifications: boolean;
    sequenceType: boolean;
    designFileType: boolean;
    previewImages: boolean;
    stitchFiles: boolean;
    pricing: boolean;
};
type Design = DesignRequest & {
    id: string;
    typeId: string; // First selected type; legacy compatibility
    title: string; // First selected type's nameOrNumber
    details: Array<object>; // Legacy compatibility
    designFileTypeName: string | null;
    designFileExtension: string | null;
    displayName: string; // Example: "Abcd.dst (.emb)"
    machineId: string | null; // Derived by the backend from machineTypeId
    createdAt: string; // ISO 8601
    ownerKey: string | null; // countryCode + mobileNumber, e.g. +919876543210
    measurementUnit: "mm";
};

type ImageSearchResult = Design & {
    similarityScore: number; // 0..100
    similarityLabel: 'Very close' | 'Similar' | 'Related';
};

type FolderFile = {
    id: string;
    name: string;
    relativePath: string;
    extension: string | null;
    mimeType: string;
    size: number;
    previewUri: string | null; // Temporary signed URL for images only
};
type Folder = {
    id: string;
    name: string;
    pricePaise: number;
    currency: 'INR';
    fileCount: number;
    totalSize: number;
    coverImageUri: string | null;
    zipName?: string; // Detail response only; legacy key containing the ZIP or RAR archive name
    files?: FolderFile[]; // Detail response only; current page in archive path order
    createdAt: string;
    updatedAt: string;
};
```

When a design belongs to a watermark-enabled machine, each `images[]` item returns the protected preview in `uri`, `isWatermarked: true`, and `downloadUri: null` for unpaid users. Owners and paid buyers receive `downloadUri`; call that URL with `POST` and the Bearer token to receive a temporary signed URL for the clean original image. Never use the preview URL as the purchased file.

Folder detail files are paginated. Call `GET /folders/:id?page=1&per_page=20`; `data` contains the folder metadata and current `files[]` page, while top-level `meta` and `links` describe file pagination. Follow `links.next` for infinite scroll or request the next `page` directly. The default page size is 20 and the maximum is 100. Folder image previews use temporary signed URLs and can be displayed directly without a Bearer header. Non-image files intentionally return `previewUri: null`. To buy a folder, send `folderId` instead of `designId` through the existing payment endpoints. The backend always takes `amount` from the folder's stored `pricePaise`; clients must not submit an amount. Once status is `paid`, `POST /folders/:id/download` returns a short-lived signed URL for the original ZIP or RAR archive. Unpaid users receive `403 folder_download_forbidden` and never receive a full-file URL. The existing `zipName` response key is retained for backward compatibility and may now end in `.zip` or `.rar`.

Create folders with `multipart/form-data`. Send `name`, integer `pricePaise` (for example `39900` for ₹399), and one archive in `file`. ZIP and RAR are supported. For compatibility, the API also accepts a rupee value in `price` or `priceRupees`, and accepts the archive field as `zipFile` or `archive`.

## Search by image

Send `POST /designs/search-by-image` as `multipart/form-data` with:

- `image` (required): JPG, PNG, or WebP, maximum 10 MB.
- `machineId`, `machineTypeId`, `categoryId` (optional): reduce the candidate set when known.
- `limit` (optional): 1 to 50, default 20.

The response data is `{ engine, minimumSimilarity, results }`. `engine` is `ai_embedding` when Qdrant AI image matching is configured and available; otherwise it is `local_visual`. Local matching uses the same weighted method as the app prototype: 45% RGB colour histogram, 35% 8x8 grayscale layout, and 20% difference hash. The backend searches the central published catalog rather than a device-only cache.

Each result is a normal `Design` plus `similarityScore` and `similarityLabel`. Scores above 97 are `Very close`, above 80 are `Similar`, and the remaining results that meet the configured minimum are `Related`.

## Home feed and View More

`GET /home` returns compact home sections in one request:

```ts
type HomeSection = {
    total: number;
    items: Design[];
    hasMore: boolean;
    viewMoreUrl: string;
};

type HomeFeed = {
    latest: HomeSection;
    today: HomeSection;
    tomorrow: HomeSection;
    categories: Array<HomeSection & { id: string; name: string }>;
};
```

Optional home parameters are `sliderLimit` (maximum 10), `sectionLimit` (maximum 20), and `categoryLimit` (maximum 20). Use each returned `viewMoreUrl`, or call the paginated design list directly:

- `/designs?section=latest`
- `/designs?section=today`
- `/designs?section=tomorrow`
- `/designs?categoryId=saree`
- `/designs?search=floral`
- `/designs?machineTypeId=machine-embroidery`

Tomorrow designs use `publishDate` and are excluded from latest and category listings until their publish date.

The mobile browse filter sheet should load `GET /catalog/browse-filters`. Its `machineTypes`, `categories`, and `designTypes` arrays use stable public IDs and include relationship ID arrays for cascading dropdowns. This endpoint is independent of the current design result page, so an active catalog option remains available even when no matching design is currently listed.

`GET /designs` also accepts these optional filter-sheet parameters while preserving `page` and `per_page` pagination:

- `machine_type_id`: one machine-type public ID.
- `category_ids[]`: one or more category public IDs.
- `design_type_ids[]`: one or more design-type public IDs.
- `stitch_ranges[]`: `0:25000` through `225000:250000` in 25,000 steps. Lower bounds are inclusive, upper bounds are exclusive, except that the final range includes exactly 250,000.
- `needle_values[]`: exact integer values from 0 through 12.
- `area_ranges[]`: `0:100`, `100:200`, `200:300`, `300:400`, `400:500`, or `500:*`. Lower bounds are inclusive and finite upper bounds are exclusive.

Values within one multi-select group use OR; different groups use AND. Design type and specification filters are evaluated against the same `designTypes[]` assignment. Filtering occurs before pagination, so `meta.total` and page links describe the filtered result set. Existing `categoryId`, `machineTypeId`, `section`, and `search` parameters remain supported.

Clients should send `machineId` when creating or updating a design. Older requests can still be resolved from `machineTypeId` when that field is enabled. After selecting a machine, load its compatible formats from `/catalog/machines/:machineId/design-file-types` (or `/catalog/design-file-types?machineId=...`). The backend rejects a `designFileTypeId` that is not connected to the selected machine.

Every machine returned by `/auth/register/machines`, `/users/me/machines`, `/catalog/machines`, and `/catalog/design-options` includes `imageUri` and `designFormConfig`. Build the create/edit form from its top-level visibility booleans. The nested `designFormConfig.required` object contains matching booleans that decide whether each visible field is mandatory. `designFormConfig.specificationFields` separately controls visibility for `image`, `nameOrNumber`, `stitchCount`, `needle`, `area`, `height`, and `width`; its nested `required` object controls which of those visible specification fields are mandatory. Hidden fields must be omitted from the request; backend validation follows the same configuration. Existing machines keep their previous behaviour until an admin changes it. `imageUri` is an absolute public image URL when an admin uploaded a machine image, otherwise `null`.

`machineType`, `designFileType`, and `stitchFiles` are independent switches. When `machineType` is disabled but `category` is enabled, load the combined category list from `/catalog/machines/:machineId/categories` and omit `machineTypeId`. `designTypes` depends on `category`, while `designSpecifications` depends on `designTypes`. A design file type is filtered by `machineId` and can remain enabled even when stitch-file upload is disabled.

Catalog selection uses two levels. `categoryId` is the main product group and every `designTypes[].typeId` is a selected placement/type with its own specifications:

- `saree`: `pallu`, `border`, `all-over`
- `blouse-top`: `neckline`, `sleeve`, `back`
- `general`: `motif`, `logo`, `patch`

Load compatible categories after selecting a machine type, then load types for the selected category. All selected types must belong to that category. The first selected type supplies the legacy `typeId`, `title`, and `size` response fields. Old single-type requests using `typeId`, `details`, and flat summary/upload fields remain temporarily supported.

The create/edit form uses one global specification-mode switch for all selected design types. Send the selected mode on every `designTypes[]` entry as `specificationMode: design_property` or `specificationMode: technical`. Confirm with the user before changing modes because fields from the previous mode are discarded on save. In `design_property` mode, show `nameOrNumber` and one image picker for every selected type; in `technical` mode, show `nameOrNumber` plus the enabled stitch, needle, and measurement fields. The response preserves the mode on every `designTypes[]` entry, so design details render `design_property` entries as image cards and `technical` entries as specification rows.

When `designFormConfig.specificationFields.image` is true and an entry uses `design_property`, accept any `image/*` selection and do not apply a file-size limit. Upload the selected image through `POST /uploads`, then send the returned URL as `designTypes[index].details.image.uri` with its original name. On edit, an omitted `image` keeps the existing image, a new `image` replaces it, and `removeImage: true` removes it when allowed. The design response returns `details.image` as `{ name, uri, thumbnailUri }` or `null`.

A design type may be shared by multiple categories. Mobile clients do not need a contract change: continue loading `/catalog/categories/:categoryId/types` after category selection and submit the same `categoryId` plus selected `typeId` values.

Seeded compatibility examples:

- Multi Head: Saree, Blouse / Top, General
- Sequin: Saree, Blouse / Top
- Multi Sequin: Saree
- Dobby Air Jet: Saree, General
- Jacquard Air Jet: Saree

The server derives ownership from the token, never from request data, and must enforce it for update/delete and `/users/me/designs`. Null ownerKey is reserved for shared samples. Keep phone formatting consistent with `User`; ownerKey is a compatibility field used by the existing app.

Local photo/file URIs are uploaded before saving. The ordered `images` and `files` arrays contain URLs returned by `/uploads`; the server verifies that every attachment belongs to the authenticated user. Image URLs are public, while stitch file names are visible but their URIs are returned only to the owner or a paid buyer through the protected signed-download flow. The flat attachment and summary fields remain for catalog-card and older-client compatibility.

Update the name and optional photo directly with multipart `POST /users/me`. Send `name` and optional `image` (JPG, PNG, or WebP; maximum 5 MB). If `image` is omitted, the existing photo stays unchanged. Send `removeImage=1` without an image to remove it.

Email and mobile number cannot be changed directly through `/users/me`. First call `POST /users/me/contact/request-otp`, then submit the same contact value and received code to `POST /users/me/contact/verify-otp`. For mobile use `{ type: "mobile", countryCode: "+91", mobileNumber: "9876543210" }`. For email use `{ type: "email", email: "user@example.com" }`. A verified email gets `email_verified_at`; a verified mobile updates `country_code`, `mobile_number`, and normalized `phone_key` together.

Machine preferences are managed with `GET /users/me/machines` and `PUT /users/me/machines`. The update request must contain at least one active machine public ID. Once saved, `/catalog/machines`, `/catalog/design-options`, `/catalog/categories`, `/home`, `/designs`, and image-search results are limited to those machines. Existing users with no saved preference keep the backward-compatible behavior and see all machines until they make a selection.

Registration must first load the full public list from `GET /auth/register/machines`. For `flow: "register"`, send the selected `machineIds` in both OTP calls. The server binds that selection to the OTP request and rejects a different selection during verification. After successful verification the machines are attached to the new account, so the first authenticated home and catalog responses are already filtered. Login requests do not send `machineIds`.

Delete an authenticated account with `DELETE /users/me` and body `{ "confirmation": "DELETE" }`. The confirmation is case-sensitive. The server anonymizes personal contact data, removes the profile image and saved-design collection, revokes every API token and session, and soft-deletes the account while retaining purchase/design records required for marketplace integrity. The Super Admin account cannot use this endpoint.

## App flow

The mobile app opens these public, responsive pages in a WebView; no Bearer token is required:

- Terms and Conditions: `https://app.designshub.org/legal/terms`
- Privacy Policy: `https://app.designshub.org/legal/privacy`
- Refund Policy: `https://app.designshub.org/legal/refund`
- About DesignHub: `https://app.designshub.org/about`

Administrators edit the Markdown content from **Admin → Content pages**. Each page shows its own last-updated date.

Home cards open `/design/:id` without Edit/Delete controls. Profile contains a three-column grid filtered to the current owner. Only an owned design's details show Edit/Delete. CRUD changes update the shared store immediately after server success; errors retain form values. Pull down on Home/Profile to refresh. No follower/like counts are fabricated.

Implementation references: [Expo environment variables](https://docs.expo.dev/guides/environment-variables/), [Expo Router navigation](https://docs.expo.dev/router/basics/navigation/), [Axios](https://axios-http.com/docs/instance).

## Prices and purchases

New and edited designs require a price in INR. The form converts rupees to integer `pricePaise`; ₹299.99 is 29999. Legacy designs with a null/missing price are not offered for sale. Payment endpoints and server implementation requirements are documented in [RAZORPAY.md](./RAZORPAY.md).

Before saving a seller-owned design or folder, request the server-calculated commission preview:

```http
POST /seller/pricing-preview
Authorization: Bearer <token>
Content-Type: application/json

{ "pricePaise": 10000 }
```

For the current commission-only test flow, a ₹100 price at 25% commission returns `buyerPayablePaise: 10000`, `platformCommission.amountPaise: 2500`, and `estimatedSellerEarningPaise: 7500`. Display Selling price, Platform commission and Estimated earning from this response. Do not calculate them in the app. Tax objects remain disabled with zero amounts and must not be rendered. Debounce calls while the seller edits the price; the preview is an estimate and the final commission is snapshotted when payment succeeds.

## Seller wallet and earnings

These Bearer-authenticated endpoints expose only the authenticated seller's records in the server's current payment environment:

- `GET /seller/wallet`: pending, available, reserved and paid balances plus withdrawal action flags.
- `GET /seller/earnings?page=1&per_page=20`: paginated purchase-time earning snapshots. Optional `status` filter.
- `GET /seller/transactions?page=1&per_page=20`: paginated immutable ledger entries. Optional `balanceBucket` filter.
- `GET /seller/withdrawals?page=1&per_page=20`: paginated withdrawal history. Optional `status` filter.
- `POST /seller/withdrawals`: reserve available balance and create a withdrawal request.
- `GET /seller/withdrawals/{withdrawalId}`: withdrawal detail and status history.
- `POST /seller/withdrawals/{withdrawalId}/cancel`: cancel a request that is still in `requested` status and return the reserved amount.

All money values use integer paise. The app must display `paymentEnvironment` and clearly label `isTestMode: true` balances and withdrawals as simulated money. It must not calculate balances, commission or withdrawal eligibility locally. `canWithdraw`, `hasOpenWithdrawal` and `minimumWithdrawalPaise` come from the backend. These endpoints require the applicable own-finance permissions assigned to the marketplace user role.

Each `/seller/earnings` row includes the immutable settlement snapshot captured when the buyer paid. Render `settlement.label` and format `settlement.scheduledAt` in the user's locale. Do not recalculate a date from the current Admin cycles because a later settings change must not alter an old earning.

```json
{
  "status": "pending",
  "sellerNetPaise": 7500,
  "availableAt": "2026-11-12T00:00:00+05:30",
  "settlement": {
    "type": "monthly_cycle",
    "startDay": 5,
    "endDay": 10,
    "settlementDay": 12,
    "holdDays": null,
    "timezone": "Asia/Kolkata",
    "scheduledAt": "2026-11-12T00:00:00+05:30",
    "label": "Sales from day 5 to 10 settle on day 12"
  }
}
```

For old records, `type` is `legacy_hold`, the three cycle-day fields are `null`, and `label` explains the legacy hold. The mobile UI should present that label without treating it as an error.

Create request body:

```json
{
  "amountPaise": 50000,
  "idempotencyKey": "withdraw-1730700000-random"
}
```

Generate one stable, unique `idempotencyKey` when the seller taps submit and reuse the same key only when retrying that exact request. A successful create returns `201`; an idempotent retry returns the same withdrawal with `200`. Only one open withdrawal is allowed per seller and payment environment. Creation moves the amount from `available` to `reserved`; cancellation moves it back. The backend enforces payout enablement, minimum amount and available balance.
