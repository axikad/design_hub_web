# DesignHub Backend (Laravel + Inertia React)

Backend and admin panel for the **DesignHub** embroidery marketplace mobile app. API contracts are defined in `docs/mobile-handoff/`.

## Stack

- Laravel 12, Sanctum (mobile Bearer tokens)
- Inertia.js + React + Tailwind (admin UI; layout inspired by Surya ERP indigo/slate patterns, implemented separately)
- MySQL (configure `DB_*` in `.env`; default database name `surya_erp_new`)
- Razorpay (optional until keys are set)

## Quick start

```bash
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install
npm run dev
php artisan serve
```

- **Admin panel:** http://127.0.0.1:8000/login — `admin@designhub.local` / `password`
- **Mobile API base:** http://127.0.0.1:8000/api/v1

Set the Expo app to `EXPO_PUBLIC_API_URL=http://127.0.0.1:8000/api/v1` (use your LAN IP on a physical device).

## OTP in local development

Local development uses the static OTP `1234` when `DESIGNHUB_OTP_STATIC_CODE=1234`. The code is also written to `storage/logs/laravel.log` when `DESIGNHUB_OTP_LOG=true`. The static code is accepted only when `APP_ENV=local`; remove it and disable OTP logging in production.

## Implemented API (v1)

See `docs/mobile-handoff/BACKEND_HANDOFF.md` for the full contract. Core routes:

- Auth: `POST /auth/request-otp`, `POST /auth/verify-otp`
- User: `GET /users/me`, designs, purchases, saved designs
- Designs CRUD, uploads, download authorization, Razorpay order/verify/webhook

Payments return `503` until `RAZORPAY_*` env vars are configured.

## Postman

Import **`docs/DesignHub-API.postman_collection.json`** into Postman. Set `base_url` to your server (default `http://127.0.0.1:8000/api/v1`).

## Flow diagrams & catalog

See **`docs/SYSTEM-FLOW.md`** for mobile/admin flowcharts and the category → type → design model.

Admin: **Categories** and **Types** under the sidebar; each design requires a **type** (`typeId` in the mobile API).
