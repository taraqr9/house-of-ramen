# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel 13 (PHP 8.4) HRIS (Human Resource Information System) — a server-rendered Blade admin panel, not an SPA/API backend.

## Commands

```bash
# Initial setup (installs deps, copies .env, migrates, builds assets)
composer setup

# Local dev — runs server + queue worker + log tailer (pail) + vite concurrently
composer dev

# Tests (Pest, configured via phpunit.xml)
composer test
php artisan test
php artisan test --filter=test_name
vendor/bin/pest tests/Feature/SomeTest.php

# Formatting — Pint runs automatically as a pre-commit hook (git hooksPath = .githooks),
# re-adds formatted files, and blocks the commit if it fails. Run manually with:
vendor/bin/pint
```

Queued mail/jobs use `QUEUE_CONNECTION=database` in local dev — `composer dev` starts `queue:listen`, so queued mailables won't send unless that process (or `php artisan queue:work`) is running.

## Architecture

### Request flow: FormRequest → Controller → Filter/Model

Every resource follows the same shape — when adding a new resource, copy this pattern rather than inventing a new one:

- **Controller** (`app/Http/Controllers/XController.php`): constructor calls `$this->authorizeResource(Model::class, 'param')`, which auto-authorizes each CRUD action against `app/Policies/XPolicy.php` (resolved by Laravel's naming convention — no manual registration needed except non-standard ones like `RolePolicy`, which is registered in `AppServiceProvider`).
- **FormRequests** (`app/Http/Requests/`): one per action — `XStoreRequest`, `XUpdateRequest`, `XIndexRequest` (search/filter params for `index`). `authorize()` typically re-checks `auth()->user()->can('resource-action')` in addition to the policy.
- **Filters** (`app/Filters/XIndexFilter.php`): stateless class with a static `applyFilters(Builder $query, Request $request): Builder`, called from `index()` before `->paginate()`. Keeps search/filter query-building out of controllers.
- **Policies** (`app/Policies/`): thin permission checks, e.g. `return $user->can('department-view');`. Permission strings follow `{resource}-{action}` (view/create/edit/delete), backed by `spatie/laravel-permission`. Note some resources use underscores in the resource segment (e.g. `employee_delegation-create`) — match whatever the existing Policy/Blade `@can` calls use for that resource, don't assume the dash-only form.
- **Models**: commonly use the `HasUserStamps` trait (`createdBy`/`updatedBy` belongsTo `User` via `created_by`/`updated_by`), `SoftDeletes`, and cast `is_active`/similar flags to `App\Enums\StatusEnum`. Several also use Spatie `LogsActivity` with a `getActivitylogOptions()` override (`logAll()->logOnlyDirty()`).

### Employee domain is hub-and-spoke

`Employee` is the central model; most HR data lives in dedicated one-to-one/one-to-many child models rather than on `Employee` itself: `EmployeePersonalInfo`, `EmployeeJobInfo`, `EmployeeContact`, `EmployeeFamilyMember`, `EmployeeDocument`, `EmployeeVerification`, `EmployeeBenefit`, `EmployeeContract`, `EmployeeDonorInfo`, `EmployeeSalaryInfo`, `EmployeeDelegation`, `EmployeeHistory`. Each has its own controller with a single `store` action wired as a nested POST route under `hr-operations/employees/{employee}/...` (see `routes/web.php`) rather than a full REST resource — these are saved as sub-panels on the employee edit screen, not standalone CRUD pages.

### Routes (`routes/web.php`)

Single flat file, grouped by middleware stack rather than by feature:
- `guest` — login, forgot/reset password, first-login password setup.
- `auth` — change-password, profile (always accessible even to unverified/temp-password users).
- `auth + force.password.change` — impersonation start/stop, resend-user-email.
- `auth + force.password.change + block.impersonation.actions` — everything else (`settings/*` and `hr-operations/*` resource groups).

Two custom middleware aliases matter:
- `force.password.change` (`EnsurePasswordIsChanged`) — redirects users with an unexpired temp-password flow to the mandatory password-change screen.
- `block.impersonation.actions` (`BlockActionsDuringImpersonation`) — while an admin is impersonating (via `lab404/laravel-impersonate`), blocks all non-GET requests app-wide.

### Mail

Mailables live in `app/Mail/`, implement `ShouldQueue`, and are dispatched with `Mail::to($email)->queue(new SomeMail(...))` (see `ForgotPasswordController`). Each has a matching Blade view in `resources/views/emails/` using inline-styled HTML tables (for email-client compatibility) — copy `forgot-password.blade.php` as the template for new transactional emails rather than starting from scratch.

### Error logging

`bootstrap/app.php` reports real server errors (5xx, non-auth, non-validation exceptions) to a dedicated `custom_error` log channel with request/user context, and renders a generic "Something went wrong" redirect for non-JSON requests. Auth/validation/4xx exceptions are left to Laravel's default handling — don't add try/catch around those in controllers.

### Frontend

Blade views (`resources/views/`) extend `layout.master` (note: `resources/views/layouts/app.blade.php` also exists but is unused legacy scaffolding — always extend `layout.master`, not `layouts.app`). The UI is a purchased Bootstrap admin theme (jQuery, Select2, SweetAlert2, ApexCharts, etc.) loaded as static assets from `public/` via `partials.styles`/`partials.scripts` — **not** through Vite. Page-specific JS goes in each view's `@section('JScript')` block (jQuery style, e.g. `$('.select2').select2()`, `Swal.fire()`). The admin panel does not use Vite for its own views, but Vite + Tailwind + Vue/Inertia power the separate public Phone Kinbo site below.

### Public site (Vue/Inertia) and SSR

`resources/views/app.blade.php` is the Inertia root template for the public, consumer-facing site (`app/Http/Controllers/Public/*`, `resources/js/Pages/Public/*`, `Layouts/PublicLayout.vue`) — completely separate from the Bootstrap admin above. It's server-rendered via Inertia SSR (`resources/js/ssr.js`, built with `npm run build:ssr`, served by `php artisan inertia:start-ssr`) so per-page `<title>`/meta/canonical/OG/JSON-LD (see `App\Services\Seo\SeoMeta` and `Components/Public/SeoHead.vue`) are present in the initial HTML for crawlers and social-card scrapers that don't execute JS — without the SSR process running, Inertia silently falls back to client-only rendering (see `config/inertia.php`'s `ssr.enabled`/`throw_on_error`), so a production deploy must keep the SSR server running alongside `php artisan serve`/`queue:work` (it is **not** part of `composer dev`, which stays client-side-only for fast local iteration — run `npm run build:ssr && php artisan inertia:start-ssr` manually when testing SSR locally).
