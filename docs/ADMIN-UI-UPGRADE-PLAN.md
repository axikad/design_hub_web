# DesignHub Admin UI Upgrade Plan

This document is the implementation checklist for upgrading the existing DesignHub admin panel with selected UI and UX ideas from `laravel-react-rbac-starter`.

The starter project is a reference only. DesignHub's current Laravel models, database tables, routes, OTP authentication, permissions, APIs, and business logic must remain the source of truth.

## UI execution rule

- Work through this document from top to bottom, one checkbox at a time.
- Finish and verify every screen in the current section before starting the next section.
- Inventory all related pages before redesigning the first page in a module.
- Reuse the same visual system and shared components instead of styling pages independently.
- Preserve the existing routes, form fields, validation, authorization, and business logic.
- After each section, update this checklist with the real completion status.
- Run targeted checks during each section; run the full regression suite before deployment.

## UI master tracker

- [ ] Phase 1A: Authentication screens
- [ ] Phase 1B: Shared admin layout and navigation
- [ ] Phase 1C: Reusable admin UI components
- [ ] Phase 2: User management
- [ ] Phase 3: Roles and permissions
- [ ] Phase 4: Catalog management
- [ ] Phase 5: Design management
- [ ] Phase 6: Purchases and payments
- [x] Phase 7: Dashboard and settings
- [ ] Phase 8: Quality and accessibility
- [ ] Phase 9: Deployment

## Main goals

- Give the admin panel a consistent, modern interface.
- Make user, design, catalog, purchase, and permission management easier.
- Reuse suitable table, filter, badge, modal, and navigation ideas from the starter.
- Preserve all existing mobile APIs and current admin URLs.
- Keep the interface responsive and usable on desktop, tablet, and mobile.

## Compatibility rules

- Do not copy the starter's migrations, `User` model, controllers, routes, or RBAC service directly.
- Keep DesignHub's existing roles and user assignments, but migrate permission assignments to the starter-style `permission_role` pivot structure before completing the UI upgrade.
- Preserve OTP/mobile fields such as `country_code`, `mobile_number`, and `phone_key`.
- Preserve public IDs used by APIs and admin routes.
- Preserve design ownership, purchases, uploads, and authentication relationships.
- Every destructive action must require confirmation.
- Buttons and navigation entries must follow the current permission system.
- New database fields must be introduced only through reversible migrations.

## Phase 0 — Migrate RBAC to the starter structure

Target permission format:

- Each action is a separate permission, for example `users.read`, `users.create`, `users.update`, and `users.delete`.
- Roles and permissions use a standard many-to-many `permission_role` pivot table.
- `role_permissions` is removed only after its existing assignments have been migrated and verified.
- Super Admin remains protected and receives all permissions.

Implementation checklist:

- [x] Extend `modules` with `slug`, `description`, `is_active`, ordering, and audit-user fields.
- [x] Extend `permissions` with `action`, `description`, `is_active`, and audit-user fields.
- [x] Create the `permission_role` pivot with `role_id`, `permission_id`, `assigned_by`, and timestamps.
- [x] Add separate permission records for the existing admin modules and CRUD actions.
- [x] Convert existing `role_permissions` boolean flags into individual pivot assignments.
- [x] Change `Role` and `Permission` relationships to `belongsToMany`.
- [x] Change permission checks to use one stable permission slug.
- [x] Remove the general `is_admin` permission bypass; keep the Super Admin bypass only.
- [x] Migrate legacy admin accounts to roles and remove the `users.is_admin` column.
- [x] Update middleware, controllers, routes, Inertia props, and sidebar.
- [ ] Hide page-level create, edit, and delete buttons when permission is missing.
- [x] Update the Roles & Permissions screen for individual permission records.
- [x] Add targeted tests for allowed and forbidden access.
- [ ] Verify migrated assignments in development.
- [x] Drop the old `role_permissions` table in a reversible cleanup migration after verification.

Optional starter features:

- [ ] Add permission assignment audit logs.
- [ ] Add cached permission slugs with cache invalidation after role updates.

Completion check:

- [ ] Existing users retain the correct roles and equivalent access.
- [ ] No permission assignment is lost during migration.
- [x] `permission_role` is the only active role-permission assignment table.
- [x] All admin routes are protected by their specific permission slug.

## Phase 1A - Authentication screens

All authentication screens must use the same responsive DesignHub visual language as the new Login page.

- [x] Login page (`resources/js/pages/auth/login.tsx`)
- [x] Register page (`resources/js/pages/auth/register.tsx`)
- [x] Forgot Password page (`resources/js/pages/auth/forgot-password.tsx`)
- [x] Reset Password page (`resources/js/pages/auth/reset-password.tsx`)
- [x] Verify Email page (`resources/js/pages/auth/verify-email.tsx`)
- [x] Confirm Password page (`resources/js/pages/auth/confirm-password.tsx`)
- [x] Shared responsive branded auth layout (`resources/js/layouts/auth/admin-login-layout.tsx`)
- [ ] Rename/generalize the shared auth layout if it will be reused by every auth screen.
- [ ] Preserve each screen's existing route, fields, validation errors, status messages, loading state, and redirects.
- [ ] Verify links between Login, Register, Forgot Password, and Reset Password.
- [ ] Verify all auth screens on desktop and mobile widths.
- [ ] Verify light and dark themes on every auth screen.

Completion check:

- [x] No authentication screen is left on the old UI.
- [x] Registration, login, password reset, email verification, and password confirmation still work.
- [x] Targeted authentication tests pass.
- [x] `npm run build` passes.

## Phase 1B - Shared admin layout and navigation

- [x] Define the admin colour, spacing, typography, border, and shadow conventions.
- [x] Improve the main admin layout without changing existing routes.
- [x] Redesign the desktop sidebar.
- [x] Improve the sidebar active state and collapsed behaviour.
- [x] Improve the mobile navigation/drawer behaviour.
- [x] Redesign the top header and page-header area.
- [x] Improve the user/account dropdown and logout action.
- [x] Add permission-aware visibility for sidebar items.
- [ ] Confirm permission-aware navigation with multiple roles.
- [ ] Verify layout responsiveness and dark theme.

Completion check:

- [ ] Every admin page uses the same shared layout.
- [ ] Existing admin pages still open with their original URLs.
- [ ] Unauthorized menu items are hidden and remain protected server-side.
- [ ] Desktop, collapsed-sidebar, and mobile navigation work correctly.

## Phase 1C - Reusable admin UI components

- [ ] Create or improve reusable admin components:
  - [x] Data table container
  - [x] Search input
  - [x] Filter controls
  - [x] Status badge
  - [x] Empty state
  - [x] Loading state
  - [x] Pagination
  - [x] Form modal or form panel
  - [x] Confirmation dialog
  - [x] Flash/error notification
- [ ] Add permission-aware visibility for create, edit, and delete buttons.
- [x] Use the shared components in one pilot module before applying them everywhere.
- [ ] Verify component light/dark styles and mobile responsiveness.

Completion check:

- [ ] Shared components cover the common list, form, status, empty, loading, and confirmation patterns.
- [ ] Unauthorized page actions are hidden and remain protected server-side.
- [ ] `npm run build` passes.

## Phase 2 — User management

- [x] Upgrade the user listing design.
- [x] Add server-side search by name, email, and mobile number.
- [x] Add role filter.
- [x] Show role, mobile number, email, design count, and useful account information.
- [x] Improve create and edit user forms.
- [ ] Keep marketplace-user mobile/OTP requirements.
- [ ] Keep panel-user email/password requirements.
- [x] Protect the Super Admin account from unsafe changes or deletion.
- [x] Add a clear empty state and delete confirmation.
- [x] Preserve pagination filters while moving between pages.

Optional database additions — implement only after approval:

- [x] Add an account `status` database field such as active/inactive.
- [x] Add the `avatar_path` database field.
- [ ] Add the avatar upload flow.
- [x] Add the `last_login_at` database field.
- [ ] Record `last_login_at` after successful panel login.

Completion check:

- [ ] Admin can search and filter users.
- [ ] Admin can create and edit both marketplace and panel users.
- [ ] Existing OTP login and API authentication still work.
- [ ] User permissions are enforced in both UI and backend routes.

## Phase 3 — Roles and permissions UI

- [x] Improve the existing role list and permission matrix UI.
- [x] Group permissions by module.
- [x] Add permission search/filter for large permission lists.
- [x] Clearly display Read, Create, Update, and Delete actions.
- [x] Keep Super Admin permissions locked.
- [x] Improve validation and success/error feedback.
- [ ] Confirm permission changes immediately affect navigation and buttons.

Optional later improvements:

- [ ] Add permission caching using the new starter-style schema.
- [ ] Add audit logs for role and permission changes.

Completion check:

- [ ] Existing permission records and assignments are unchanged unless edited by an authorized admin.
- [ ] Route middleware remains the final authorization check.
- [ ] Tests cover allowed and forbidden actions.

## Phase 4 — Catalog management

Apply the shared UI to:

- [x] Machines
- [x] Machine Types
- [x] Categories
- [x] Design Types
- [x] Design File Types

For each page:

- [x] Add consistent list, search, ordering, status, and action controls where supported.
- [x] Use clear relationship labels, for example Machine → Machine Type → Category.
- [x] Preserve `sort_order`/`index_order` behaviour.
- [x] Prevent unsafe deletion when related records exist.
- [x] Show backend validation errors beside the correct fields.

Completion check:

- [ ] Catalog APIs return the same compatible structure expected by the mobile app.
- [ ] Admin changes are reflected correctly in catalog APIs.

## Phase 5 — Design management

- [x] Upgrade the designs listing with search and useful filters.
- [x] Show design thumbnail, title/display name, owner, machine, machine type, category, file type, price, and status.
- [x] Use `thumbnailUri` for list previews and the original image for detail views.
- [x] Improve the create/edit form layout.
- [ ] Keep the same dependent selection flow:
  - [x] Machine
  - [x] Machine Type
  - [x] Category
  - [x] Design Type
  - [x] Design File Type
- [ ] Show current images and stitch files correctly during editing.
- [ ] Allow existing server file URLs during updates without treating them as new uploads.
- [x] Display the computed design `displayName` where appropriate.
- [x] Keep create and edit validation consistent.

Completion check:

- [ ] Create, edit, view, and delete flows work from the admin panel.
- [ ] Mobile design create/edit APIs remain backward compatible.
- [ ] Existing images, thumbnails, and downloadable files remain accessible.

## Phase 6 — Purchases and payments

- [x] Upgrade purchase listing and filters.
- [x] Show user, design, amount, provider/order reference, and payment status.
- [x] Use clear badges for unpaid, pending, paid, and failed states.
- [x] Add a read-only purchase/payment detail view if needed.
- [x] Do not expose Razorpay secrets or internal provider errors.
- [x] Keep reconciliation and payment state server-controlled.

Completion check:

- [ ] Admin display matches the server-side payment state.
- [ ] No secret key is included in page props, HTML, JavaScript, logs, or API documentation.

## Phase 7 — Dashboard and settings

- [x] Improve dashboard summary cards using real data.
- [x] Add useful counts for users, designs, purchases, and payments.
- [x] Add recent activity sections only when backed by real data.
- [x] Improve site settings form layout and feedback.
- [x] Keep existing branding and settings behaviour.

## Phase 8 — Quality and accessibility

- [ ] Add proper labels and keyboard navigation.
- [ ] Check colour contrast and visible focus states.
- [x] Add loading/disabled states to submitted forms.
- [x] Prevent accidental double submissions.
- [x] Confirm tables remain usable on small screens.
- [x] Remove broken text/encoding characters such as `â€”`.
- [ ] Run formatting, linting, TypeScript/build checks, and Laravel tests.

Commands for local verification:

```bash
npm run format:check
npm run build
php artisan test
```

## Phase 9 — Deployment

- [ ] Review `git diff` and ensure `.env` and secrets are not committed.
- [ ] Commit the completed phase with a focused message.
- [ ] Push to the development branch first.
- [ ] Test on the development site with different roles.
- [ ] Merge into `main` only after approval.
- [ ] Pull the latest commit on the live server.
- [ ] Run migrations only when the phase contains a migration.
- [ ] Clear and rebuild Laravel caches.
- [ ] Build and commit `public/build` when the shared host cannot run npm.

Typical live-server commands after pulling:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

## Recommended implementation order

1. Migrate RBAC to the starter-style permission structure
2. Complete all authentication screens
3. Shared admin layout and navigation
4. Reusable admin UI components and page-action permissions
5. User management
6. Roles and permissions UI
7. Catalog pages
8. Design management
9. Purchases and payments
10. Dashboard and settings
11. Full regression testing and deployment

## Immediate next steps

1. Redesign Register using the shared DesignHub auth layout.
2. Redesign Forgot Password.
3. Redesign Reset Password.
4. Redesign Verify Email.
5. Redesign Confirm Password.
6. Verify the complete authentication flow.
7. Only then begin the shared admin sidebar/header layout.

## Definition of done

The admin UI update is complete when:

- [ ] All admin modules use the same visual system and reusable components.
- [ ] Permissions control both visible actions and backend access.
- [ ] Current mobile APIs and OTP login continue to work.
- [ ] Existing database relationships and public IDs remain compatible.
- [ ] No credentials or sensitive data are exposed.
- [ ] Production frontend assets are built successfully.
- [ ] Automated tests pass.
- [ ] The development-site review is approved before production deployment.
