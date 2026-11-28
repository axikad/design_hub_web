# DesignHub Image Search — Gemini Embedding 2 Status

**Google Cloud project:** `textile-design-hub`<br>
**Updated:** 28 November 2026 (Asia/Kolkata)<br>
**Selected architecture:** Gemini Embedding 2 + DesignHub database

## Decision

DesignHub uses Google Vertex AI `gemini-embedding-2` to convert each marketplace image into a numeric vector. The application stores those vectors in `design_image_fingerprints` and performs cosine-similarity search in Laravel.

```text
Design image -> Gemini Embedding 2 -> DesignHub database
Search image -> Gemini Embedding 2 -> Laravel cosine similarity -> matching designs
```

The original Google Cloud Vision Product Search integration has been removed from runtime code because Google does not onboard new projects to that product. The historical migration that originally added its database columns remains in migration history; a later migration removes those columns safely from both existing and fresh databases.

## Completed

- [x] Google Cloud project and billing configured.
- [x] Service-account JSON stored outside the public web root.
- [x] Confirmed Product Search rejects new-project onboarding.
- [x] Selected the GA `gemini-embedding-2` model.
- [x] Selected 512-dimensional embeddings.
- [x] Chose the existing DesignHub database for the initial vector catalog.
- [x] Removed the obsolete Product Search service, setup command, configuration and tests.
- [x] Added a cleanup migration for obsolete Product Search columns.
- [x] Added the Gemini image-embedding provider.
- [x] Removed the Qdrant service, configuration and tests.
- [x] Added database storage and cosine-similarity matching for Gemini vectors.
- [x] Preserved exact-hash and local visual matching as the fallback.

## Required live configuration

Enable the Vertex AI API (`aiplatform.googleapis.com`) and grant the service account the minimum role needed to call the model, normally **Vertex AI User**.

```dotenv
DESIGNHUB_GEMINI_IMAGE_SEARCH_ENABLED=true
DESIGNHUB_GOOGLE_PROJECT_ID=textile-design-hub
DESIGNHUB_GOOGLE_LOCATION=global
DESIGNHUB_GEMINI_EMBEDDING_MODEL=gemini-embedding-2
GOOGLE_APPLICATION_CREDENTIALS=/home/u998495687/private/google-cloud/designhub-image-search.json

DESIGNHUB_IMAGE_EMBEDDING_DIMENSIONS=512
DESIGNHUB_IMAGE_MATCHING_TIMEOUT=30
```

Do not commit the service-account JSON, private key, or live `.env` values.

The previous Cloud Storage bucket is not required when images are sent inline to Gemini. Keep or delete that bucket only after confirming no unrelated process uses it.

## Deployment and indexing

After deploying the code:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan designs:index-images
```

Re-indexing stores fresh Gemini vectors in the application database. Old external-vector identifiers are removed by the cleanup migration.

## Verification checklist

- [ ] Vertex AI API is enabled.
- [ ] Service account has Vertex AI prediction permission.
- [ ] `php artisan designs:index-images` completes successfully.
- [ ] Fingerprint rows show `embedding_status = ready` and a 512-value embedding.
- [ ] Search-by-image returns `engine = gemini_embedding_2`.
- [ ] Filters still limit matching results correctly.
- [ ] With Gemini disabled, search falls back to `local_visual`.
- [ ] Duplicate-image checks work after re-indexing.

## Supported Gemini image formats

JPEG, PNG, WebP, BMP, HEIC, HEIF and AVIF are supported by the Gemini provider. Unsupported formats remain eligible for local visual matching but cannot be placed in the Gemini vector index until converted to a supported image format.

## References

- [Gemini Embedding 2 model](https://docs.cloud.google.com/gemini-enterprise-agent-platform/models/gemini/embedding-2)
- [Multimodal embeddings guide](https://docs.cloud.google.com/gemini-enterprise-agent-platform/models/embeddings/get-multimodal-embeddings)
- [EmbedContent REST method](https://docs.cloud.google.com/gemini-enterprise-agent-platform/reference/rest/v1/projects.locations.publishers.models/embedContent)
