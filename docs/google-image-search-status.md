# Google Image Search — Implementation Status

**Project:** Textile Design Hub  
**Google Cloud project ID:** `textile-design-hub`  
**Status updated:** 27 November 2026 (Asia/Kolkata)  

This document records what has already been completed, the Google-side blocker we confirmed, and the remaining work required before image similarity search can be enabled in production.

## Status summary

- Completed setup/implementation milestones: **13 items**
- Confirmed technical conclusions: **5 items**
- Total checked items: **18**
- Blocked by Google: **Google Cloud Vision Product Search onboarding**
- Pending checklist items: **11**
- Next focus: **architecture decision and replacement integration**
- Current production recommendation: keep the old Google Product Search integration disabled.

## Completed work

- [x] Created the Google Cloud project `textile-design-hub`.
- [x] Connected a billing account to the project.
- [x] Enabled the Cloud Vision and Cloud Storage APIs used during the original setup.
- [x] Created the private Cloud Storage bucket `textile-design-hub-image-search-2026`.
- [x] Selected the `asia-east1` region and Standard storage class.
- [x] Kept Uniform bucket-level access and Public Access Prevention enabled.
- [x] Created/downloaded a service-account credential file.
- [x] Stored the credential outside the public web root at `/home/u998495687/private/google-cloud/designhub-image-search.json`.
- [x] Verified that the credential belongs to project `textile-design-hub` without exposing its private key.
- [x] Added the Google image-search environment settings to Laravel and cleared the configuration cache.
- [x] Implemented the original Google Product Search integration, setup/index commands, migration, fallback handling, and tests.
- [x] Passed the backend test suite after the integration: **230 tests and 1,897 assertions**.
- [x] Ran the setup command on the live server and captured Google's authoritative rejection response.

Implementation commit already pushed to the backend repository:

```text
a438f16 Add Google product image search integration
```

## Confirmed blocker

The live server command was:

```bash
php artisan designs:setup-google-image-search
```

Google returned:

```json
{
  "error": {
    "code": 400,
    "message": "Product Search doesn't onboard new projects. For image search, please use Vision Warehouse: https://cloud.google.com/vision-ai/docs/warehouse-overview ",
    "status": "INVALID_ARGUMENT"
  }
}
```

### What this proves

- [x] Laravel successfully reached Google's API.
- [x] The Google credential and project identification were accepted far enough for the API to process the request.
- [x] The failure is **not** caused by the bucket name, Laravel cache, or an invalid JSON path.
- [x] Google Cloud Vision Product Search is refusing new-project onboarding.
- [x] The existing Product Search implementation cannot be activated for this new project.

> [!IMPORTANT]
> This is a Google product-availability restriction, not a DesignHub application bug. Retrying the same Product Set request or recreating the bucket will not resolve it.

## Independent Google Cloud Shell verification

The team can reproduce the same response directly from Google Cloud Shell, without Laravel:

```bash
gcloud config set project textile-design-hub

ACCESS_TOKEN="$(gcloud auth print-access-token)"

curl -sS -X POST \
  -H "Authorization: Bearer ${ACCESS_TOKEN}" \
  -H "x-goog-user-project: textile-design-hub" \
  -H "Content-Type: application/json; charset=utf-8" \
  -d '{"displayName":"Product Search onboarding verification"}' \
  "https://vision.googleapis.com/v1/projects/textile-design-hub/locations/asia-east1/productSets?productSetId=onboarding-check-20261127"
```

- [ ] Run this independent Cloud Shell verification and save a screenshot for the team, if required.

## Current safe production configuration

Until the replacement architecture is implemented, keep the obsolete Google Product Search provider disabled:

```dotenv
DESIGNHUB_GOOGLE_IMAGE_SEARCH_ENABLED=false
```

Then refresh Laravel configuration:

```bash
php artisan optimize:clear
php artisan config:cache
```

- [ ] Confirm `DESIGNHUB_GOOGLE_IMAGE_SEARCH_ENABLED=false` on the live server.
- [ ] Do **not** run `php artisan designs:index-images` against the old Google Product Search provider.

The application's existing exact-hash, perceptual matching, and configured fallback behavior can remain available while Google Product Search is disabled.

## Replacement options

### Option A — Vision Warehouse

Google explicitly recommends Vision Warehouse in the API error. It is a managed visual-search/index platform, but its deployed-index pricing can create a significant continuous monthly cost.

- Advantage: Google's official managed replacement path.
- Disadvantage: more infrastructure, operational complexity, and potentially high recurring index-node cost.

### Option B — Vertex AI embeddings + Qdrant (recommended)

Use Vertex AI `multimodalembedding@001` to convert each design image and uploaded search image into a **512-dimension vector**. Store and search those vectors using the Qdrant integration that already exists in DesignHub.

```text
Design image -> Vertex AI embedding -> Qdrant index
Search image -> Vertex AI embedding -> Qdrant similarity search -> matching designs
```

- Advantage: fits the existing DesignHub/Qdrant architecture.
- Advantage: avoids an always-running Vision Warehouse index.
- Advantage: the Google service account remains server-side; no Google credentials are exposed to web or mobile clients.
- Disadvantage: requires replacing the current Product Search adapter with a Vertex embedding adapter.

## Pending work after team approval

- [ ] Approve either Vision Warehouse or **Vertex AI embeddings + Qdrant**.
- [ ] If Vertex is selected, enable the Vertex AI API: `aiplatform.googleapis.com`.
- [ ] Grant the service account only the required Vertex AI role, normally **Vertex AI User**.
- [ ] Replace `GoogleProductSearchIndex` with a Vertex multimodal-embedding provider.
- [ ] Keep the embedding dimension aligned with the Qdrant collection (`512`).
- [ ] Re-index the existing design preview images.
- [ ] Test image upload -> embedding -> Qdrant similarity -> DesignHub results end to end.
- [ ] Add monitoring, request limits, file validation, and a controlled fallback for API failures.

## Existing environment variables

The current code contains these old Product Search settings:

```dotenv
DESIGNHUB_GOOGLE_IMAGE_SEARCH_ENABLED=false
DESIGNHUB_GOOGLE_PROJECT_ID=textile-design-hub
DESIGNHUB_GOOGLE_LOCATION=asia-east1
DESIGNHUB_GOOGLE_STORAGE_BUCKET=textile-design-hub-image-search-2026
DESIGNHUB_GOOGLE_PRODUCT_SET_ID=designhub-designs
DESIGNHUB_GOOGLE_PRODUCT_CATEGORY=general-v1
GOOGLE_APPLICATION_CREDENTIALS=/home/u998495687/private/google-cloud/designhub-image-search.json
```

Do not commit the service-account JSON file, its private key, Qdrant API key, or live `.env` values to Git.

## References

- [Google Product Set create REST API](https://docs.cloud.google.com/vision/product-search/docs/reference/rest/v1/projects.locations.productSets/create)
- [Google Product Search setup guide](https://docs.cloud.google.com/vision/product-search/docs/before-you-begin)
- [Google Vision Warehouse overview](https://cloud.google.com/vision-ai/docs/warehouse-overview)
- [Vertex AI multimodal embeddings](https://docs.cloud.google.com/gemini-enterprise-agent-platform/models/embeddings/get-multimodal-embeddings)
- [Google Vision AI pricing](https://cloud.google.com/products/vision-ai/pricing)

## Final decision record

| Item | Status |
|---|---|
| Google Cloud project and billing | Complete |
| Private bucket and server credential | Complete |
| Original Product Search backend integration | Complete but unusable for a new Google project |
| Google Product Search onboarding | Blocked by Google |
| Production Product Search flag | Must remain disabled |
| Recommended replacement | Vertex AI embeddings + existing Qdrant |
| Replacement implementation | Pending team approval |
