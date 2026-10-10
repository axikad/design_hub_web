# DesignHub — system flow diagrams

## 1. Mobile app (marketplace) flow

```mermaid
flowchart TD
  subgraph auth [Authentication]
    A[Splash / onboarding] --> B{Signed in?}
    B -->|No| C[Register or Login]
    C --> D[POST /auth/request-otp]
    D --> E[POST /auth/verify-otp]
    E --> F[Bearer accessToken]
    B -->|Yes| G[Home]
    F --> G
  end

  subgraph browse [Browse and filter]
    G --> H[GET /catalog/categories]
    H --> I[GET /designs]
    I --> J[GET /designs/:id]
    G --> K[Saved designs]
    K --> L[GET /users/me/saved-designs]
  end

  subgraph sell [Sell a design]
    G --> M[POST /uploads preview]
    M --> N[POST /uploads stitch]
    N --> O[POST /designs with typeId]
    O --> P[GET /users/me/designs]
  end

  subgraph buy [Buy and download]
    J --> Q[GET /purchases/designs/:id]
    Q -->|unpaid| R[POST /payments/orders]
    R --> S[Razorpay checkout]
    S --> T[POST /payments/verify]
    Q -->|paid| U[POST /designs/:id/download]
    U --> V[Signed stitch file URL]
  end
```

## 2. Admin panel flow

```mermaid
flowchart TD
  L[Email login] --> P{Role + permissions}
  P --> D[Dashboard]
  D --> CAT[Categories CRUD]
  CAT --> TYP[Types CRUD under category]
  TYP --> DES[Designs CRUD linked to type]
  D --> USR[Users + roles]
  D --> PUR[Purchases]
  D --> SET[Site name / favicon / logo]
  D --> ROL[Roles and permissions matrix]
```

## 3. Data model (category → type → design)

```mermaid
erDiagram
  DESIGN_CATEGORY ||--o{ DESIGN_TYPE : contains
  DESIGN_TYPE ||--o{ DESIGN : classifies
  USER ||--o{ DESIGN : sells
  USER ||--o{ PURCHASE : buys
  DESIGN ||--o{ PURCHASE : sold_as

  DESIGN_CATEGORY {
    string public_id
    string name
    boolean is_active
  }

  DESIGN_TYPE {
    string public_id
    string name
    int design_category_id
    boolean is_active
  }

  DESIGN {
    string public_id
    int design_type_id
    int user_id
    int price_paise
  }
```

## 4. Catalog setup order (recommended)

1. Create **categories** (e.g. Hoop size, Color family, Design style).
2. Create **types** under each category (e.g. 6″ hoop under Hoop size).
3. When creating a **design** (admin or mobile API), set **`typeId`** to one type `public_id`.
4. Mobile app loads **`GET /api/v1/catalog/categories`** for filters and pickers.

## 5. API quick reference

| Area | Method | Path |
|------|--------|------|
| Catalog | GET | `/catalog/categories` |
| Design | POST/PATCH | `/designs` (body includes `typeId`) |
| Admin categories | Web | `/admin/categories` |
| Admin types | Web | `/admin/types` |

See also `docs/DesignHub-API.postman_collection.json` and `docs/mobile-handoff/BACKEND_HANDOFF.md`.
