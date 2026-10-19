# Connecting DesignHub to your backend

Copy `.env.example` to `.env.local`, set `EXPO_PUBLIC_API_URL=https://your-server.example/api/v1`, then restart Expo and reload the app. A blank URL uses the in-memory demo; a nonempty URL enables Axios for every data flow. No mock fallback occurs on server errors. Public env variables must not contain secrets.

Only the URL needs changing **if your backend implements this contract**. Otherwise update `src/api/endpoints.ts` and the request/response mappings in `src/api/services.ts`. No backend has been supplied or tested against yet.

## Transport

JSON requests use a compact response envelope. Successful responses always contain `{ "success": true, "message": "...", "data": ... }`. Paginated responses additionally contain `meta` and `links`. Errors contain `{ "success": false, "message": "...", "code": "machine_readable_code" }`; validation errors additionally contain `errors` by field. Optional fields are omitted rather than returned as `null`. Axios uses a 20-second timeout and sends `Authorization: Bearer <accessToken>` after verification. An authenticated 401 signs the user out and clears remote data. Local development uses OTP `1234` when configured in `.env`.

Design and user collection endpoints accept `page` and `per_page` (default 20, maximum 100). Their `meta` contains `current_page`, `per_page`, `total`, `last_page`, `from`, and `to`; `links` contains `first`, `last`, `prev`, and `next`.

## Endpoints

| Method | Path | Request | Response data |
| --- | --- | --- | --- |
| POST | `/auth/request-otp` | `{ flow: "login" or "register", name: string or null, countryCode: "+91", mobileNumber: "9876543210" }` | `{ message: "OTP sent successfully." }` |
| POST | `/auth/verify-otp` | Same fields plus `{ code: "1234" }` | `{ user: User, accessToken: string }` |
| GET | `/users/me` | Bearer token | `User` |
| GET | `/catalog/design-options` | Bearer token | Combined machines, machine types, and compatible categories (backward-compatible) |
| GET | `/catalog/machines` | Bearer token | Active machines |
| GET | `/catalog/machines/:machineId/machine-types` | Bearer token | Active machine types for the selected machine |
| GET | `/catalog/machine-types/:machineTypeId/categories` | Bearer token | Compatible categories for the selected machine type |
| GET | `/catalog/categories/:categoryId/types` | Bearer token | Active design types for the selected category |
| GET | `/designs` | Bearer token | `Design[]`, all community designs, newest first |
| GET | `/users/me/designs` | Bearer token | `Design[]`, only authenticated user's designs |
| GET | `/designs/:id` | Bearer token | `Design` (404 if missing) |
| POST | `/uploads` | Multipart form with one `file` field | `{ url: "https://..." }` |
| POST | `/designs` | `DesignRequest` below | Created `Design` |
| PATCH | `/designs/:id` | `DesignRequest` below | Updated `Design` |
| DELETE | `/designs/:id` | Bearer token | `{ message: "Design deleted successfully." }` |

```ts
type User = {
  id: string;
  name: string | null;
  countryCode: string;
  mobileNumber: string;
};
type DesignRequest = {
  machineTypeId: string | null;
  categoryId: string | null;
  typeId: string;
  showPrice: boolean;
  title: string;
  pricePaise: number; // INR integer paise; 100..100000000
  details: Array<{
    names: string[];
    stitch: string;
    colour: string;
    area: string;
    height: string;
    width: string;
  }>;
  images: Array<{ name: string; uri: string }>;
  files: Array<{ name: string; uri: string }>;
  size: string;
  color: string;
  imageUri: string;
  fileName: string;
  fileUri: string;
};
type Design = DesignRequest & {
  id: string;
  machineId: string | null; // Derived by the backend from machineTypeId
  createdAt: string; // ISO 8601
  ownerKey: string | null; // countryCode + mobileNumber, e.g. +919876543210
};
```

Clients send only `machineTypeId` when creating or updating a design. The backend derives and returns `machineId`, so clients do not need to submit duplicate machine data.

Catalog selection uses two levels. `categoryId` is the main product group and `typeId` is its placement/type:

- `saree`: `pallu`, `border`, `all-over`
- `blouse-top`: `neckline`, `sleeve`, `back`
- `general`: `motif`, `logo`, `patch`

Load compatible categories after selecting a machine type, then load types for the selected category. Legacy requests that used the same category and type ID remain temporarily supported.

The server derives ownership from the token, never from request data, and must enforce it for update/delete and `/users/me/designs`. Null ownerKey is reserved for shared samples. Keep phone formatting consistent with `User`; ownerKey is a compatibility field used by the existing app.

Local photo/file URIs are uploaded before saving. The ordered `images` and `files` arrays contain URLs returned by `/uploads`; the server verifies that every attachment belongs to the authenticated user. Image URLs are public, while stitch file names are visible but their URIs are returned only to the owner or a paid buyer through the protected signed-download flow. The flat attachment and summary fields remain for catalog-card and older-client compatibility.

## App flow

Home cards open `/design/:id` without Edit/Delete controls. Profile contains a three-column grid filtered to the current owner. Only an owned design's details show Edit/Delete. CRUD changes update the shared store immediately after server success; errors retain form values. Pull down on Home/Profile to refresh. No follower/like counts are fabricated.

Implementation references: [Expo environment variables](https://docs.expo.dev/guides/environment-variables/), [Expo Router navigation](https://docs.expo.dev/router/basics/navigation/), [Axios](https://axios-http.com/docs/instance).

## Prices and purchases

New and edited designs require a price in INR. The form converts rupees to integer `pricePaise`; ₹299.99 is 29999. Legacy designs with a null/missing price are not offered for sale. Payment endpoints and server implementation requirements are documented in [RAZORPAY.md](./RAZORPAY.md).
