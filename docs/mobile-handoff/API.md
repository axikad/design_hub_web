# Connecting DesignHub to your backend

Copy `.env.example` to `.env.local`, set `EXPO_PUBLIC_API_URL=https://your-server.example/api/v1`, then restart Expo and reload the app. A blank URL uses the in-memory demo; a nonempty URL enables Axios for every data flow. No mock fallback occurs on server errors. Public env variables must not contain secrets.

Only the URL needs changing **if your backend implements this contract**. Otherwise update `src/api/endpoints.ts` and the request/response mappings in `src/api/services.ts`. No backend has been supplied or tested against yet.

## Transport

JSON requests use a compact response envelope. Successful responses always contain `{ "success": true, "message": "...", "data": ... }`. Paginated responses additionally contain `meta` and `links`. Errors contain `{ "success": false, "message": "...", "code": "machine_readable_code" }`; validation errors additionally contain `errors` by field. Optional fields are omitted rather than returned as `null`. Axios uses a 20-second timeout and sends `Authorization: Bearer <accessToken>` after verification. An authenticated 401 signs the user out and clears remote data. Local development uses OTP `1234` when configured in `.env`.

Design and user collection endpoints accept `page` and `per_page` (default 20, maximum 100). Their `meta` contains `current_page`, `per_page`, `total`, `last_page`, `from`, and `to`; `links` contains `first`, `last`, `prev`, and `next`.

## Endpoints

| Method | Path                                               | Request                                                                                                 | Response data                                                                     |
| ------ | -------------------------------------------------- | ------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| POST   | `/auth/request-otp`                                | `{ flow: "login" or "register", name: string or null, countryCode: "+91", mobileNumber: "9876543210" }` | `{ message: "OTP sent successfully." }`                                           |
| POST   | `/auth/verify-otp`                                 | Same fields plus `{ code: "1234" }`                                                                     | `{ user: User, accessToken: string }`                                             |
| GET    | `/users/me`                                        | Bearer token                                                                                            | `User`                                                                            |
| POST   | `/users/me`                                        | Multipart `name`, optional `image`, optional `removeImage`                                               | Updated `User`                                                                    |
| PATCH  | `/users/me`                                        | JSON `{ name: string }`                                                                                  | Updated `User`                                                                    |
| POST   | `/users/me/contact/request-otp`                    | `{ type: "mobile", countryCode, mobileNumber }` or `{ type: "email", email }`                         | Masked destination and OTP expiry                                                  |
| POST   | `/users/me/contact/verify-otp`                     | Same contact fields plus `{ code: "1234" }`                                                            | Updated `User`                                                                    |
| DELETE | `/users/me`                                        | JSON `{ confirmation: "DELETE" }`                                                                      | Account deleted and current tokens revoked                                         |
| GET    | `/catalog/design-options`                          | Bearer token                                                                                            | Combined machines, machine types, and compatible categories (backward-compatible) |
| GET    | `/catalog/design-file-types`                       | Bearer token; optional `machineId`                                                                      | Active compatible design file types ordered by `sort_order`                       |
| GET    | `/catalog/machines`                                | Bearer token                                                                                            | Active machines                                                                   |
| GET    | `/catalog/machines/:machineId/machine-types`       | Bearer token                                                                                            | Active machine types for the selected machine                                     |
| GET    | `/catalog/machines/:machineId/design-file-types`   | Bearer token                                                                                            | Active file types compatible with the selected machine                            |
| GET    | `/catalog/machine-types/:machineTypeId/categories` | Bearer token                                                                                            | Compatible categories for the selected machine type                               |
| GET    | `/catalog/categories/:categoryId/types`            | Bearer token                                                                                            | Active design types for the selected category                                     |
| GET    | `/home`                                            | Bearer token                                                                                            | Latest, today, tomorrow and category home sections                                |
| GET    | `/designs`                                         | Bearer token                                                                                            | `Design[]`, all community designs, newest first                                   |
| POST   | `/designs/search-by-image`                         | Bearer token; multipart `image` plus optional catalog filters                                           | Search engine, threshold, and matching designs                                    |
| GET    | `/users/me/designs`                                | Bearer token                                                                                            | `Design[]`, only authenticated user's designs                                     |
| GET    | `/designs/:id`                                     | Bearer token                                                                                            | `Design` (404 if missing)                                                         |
| POST   | `/uploads`                                         | Multipart form with one `file` field                                                                    | `{ url: "https://..." }`                                                          |
| POST   | `/designs`                                         | `DesignRequest` below                                                                                   | Created `Design`                                                                  |
| PATCH  | `/designs/:id`                                     | `DesignRequest` below                                                                                   | Updated `Design`                                                                  |
| DELETE | `/designs/:id`                                     | Bearer token                                                                                            | `{ message: "Design deleted successfully." }`                                     |
| GET    | `/folders`                                         | Bearer token; optional `search`, `page`, `per_page`                                                     | Paginated `Folder[]`                                                              |
| GET    | `/folders/:id`                                     | Bearer token                                                                                            | Folder metadata and ordered extracted files                                       |
| POST   | `/payments/orders`                                 | `{ folderId: string }`                                                                                  | Razorpay order using the server-side folder price                                 |
| POST   | `/payments/verify`                                 | `{ folderId, razorpay_order_id, razorpay_payment_id, razorpay_signature }`                              | Folder payment status and purchase ID                                             |
| GET    | `/purchases/folders/:id`                           | Bearer token                                                                                            | `{ folderId, status: "unpaid" or "pending" or "paid" }`                           |
| POST   | `/folders/:id/download`                            | Bearer token; paid buyer only                                                                           | Temporary signed ZIP download URL                                                 |

```ts
type User = {
    id: string;
    name: string | null;
    email: string | null;
    avatarUri: string | null;
    countryCode: string;
    mobileNumber: string;
};
type DesignRequest = {
    machineTypeId: string;
    categoryId: string;
    designTypes: Array<{
        typeId: string;
        details: {
            nameOrNumber: string;
            stitchCount: number;
            needle: number;
            area: number;
            height: number;
            width: number;
        };
    }>;
    designFileTypeId: string; // Compatible ID returned for the selected machine
    showPrice: boolean;
    publishDate?: string; // YYYY-MM-DD; defaults to today in marketplace timezone
    pricePaise: number; // INR integer paise; 100..100000000
    images: Array<{ name: string; uri: string }>;
    files: Array<{ name: string; uri: string; displayName?: string }>;
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
    zipName?: string; // Detail response only
    files?: FolderFile[]; // Detail response only, in ZIP path order
    createdAt: string;
    updatedAt: string;
};
```

Folder image previews use temporary signed URLs and can be displayed directly without a Bearer header. Non-image files intentionally return `previewUri: null`. To buy a folder, send `folderId` instead of `designId` through the existing payment endpoints. The backend always takes `amount` from the folder's stored `pricePaise`; clients must not submit an amount. Once status is `paid`, `POST /folders/:id/download` returns a short-lived signed URL for the original ZIP. Unpaid users receive `403 folder_download_forbidden` and never receive a full-file URL.

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

Clients send only `machineTypeId` when creating or updating a design. The backend derives and returns `machineId`, so clients do not need to submit duplicate machine data. After selecting a machine, load its compatible formats from `/catalog/machines/:machineId/design-file-types` (or `/catalog/design-file-types?machineId=...`). The backend rejects a `designFileTypeId` that is not connected to the selected machine.

Every machine returned by `/catalog/machines` and `/catalog/design-options` includes `imageUri`. It is an absolute public image URL when an admin uploaded a machine image, otherwise `null`. Mobile clients should display the image when present and use their existing machine placeholder/icon when it is null.

Catalog selection uses two levels. `categoryId` is the main product group and every `designTypes[].typeId` is a selected placement/type with its own specifications:

- `saree`: `pallu`, `border`, `all-over`
- `blouse-top`: `neckline`, `sleeve`, `back`
- `general`: `motif`, `logo`, `patch`

Load compatible categories after selecting a machine type, then load types for the selected category. All selected types must belong to that category. The first selected type supplies the legacy `typeId`, `title`, and `size` response fields. Old single-type requests using `typeId`, `details`, and flat summary/upload fields remain temporarily supported.

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
