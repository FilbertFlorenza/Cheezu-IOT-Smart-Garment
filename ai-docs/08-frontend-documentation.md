# 08 — Frontend Documentation

The frontend is **server-rendered Blade** with Tailwind CSS, Alpine.js, and Chart.js. There is no SPA,
no Livewire, no Inertia, and no client-side router.

## Asset pipeline

| Concern | Implementation |
| --- | --- |
| Entry points | `resources/css/app.css`, `resources/js/app.js` (`vite.config.js`) |
| Build | `npm run build` → `public/build` |
| Dev | `npm run dev` (Vite HMR) |
| Templates | `@vite(['resources/css/app.css', 'resources/js/app.js'])` in both layouts |
| CSS | Tailwind 3 via PostCSS; `.panel`, `.data-table`, `.form-input`, `.form-label`, `.chart-frame` |
| JS globals | `window.Alpine`, `window.Chart`, `window.renderChart` |
| HTTP client | Axios configured in `resources/js/bootstrap.js`; unused by pages |

`resources/js/app.js`:

```js
import './bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.color = '#64748b';
Chart.defaults.borderColor = 'rgba(148, 163, 184, 0.18)';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
window.renderChart = (elementId, config) => { /* returns Chart|null */ };

Alpine.start();
```

Note: `@tailwindcss/forms` is declared as a plugin in `tailwind.config.js`; `@tailwindcss/vite` is
installed but not used. See [16](16-known-limitations-and-technical-debt.md).

## Layouts

| Layout | Component | Used by |
| --- | --- | --- |
| `layouts/app.blade.php` | `<x-app-layout title="...">` | All authenticated pages |
| `layouts/guest.blade.php` | `<x-guest-layout title="...">` | Login only |
| `layouts/error.blade.php` | Error shell | Custom error views |
| `layouts/navigation.blade.php` | — | **Unused** Breeze artifact |

`x-app-layout` provides:

- Fixed dark sidebar (`w-64`) with Monitoring, Forecasting, and Administration sections.
- Admin links rendered only when `auth()->user()?->isAdministrator()`.
- Sticky header with page context, forecast shortcut, user menu (profile, sign out).
- Flash message outlet and footer with display timezone/environment.
- Mobile sidebar via Alpine (`sidebarOpen`), with an overlay and `x-cloak`.
- `@stack('scripts')` at the end for page-level chart scripts.

Page titles are passed as the `title` prop and composed as `"{title} — {app.name}"`.

## Reusable components

Located in `resources/views/components/` (anonymous Blade components unless noted):

| Component | Purpose | Used by |
| --- | --- | --- |
| `page-header` | Title, subtitle, `meta` and `actions` slots | Most pages |
| `stat-card` | KPI card with label, value, hint, tone | Dashboard, device detail, settings |
| `status-badge` | Coloured enum badge | Devices, inspections, forecasts |
| `empty-state` | Title/message placeholder | Dashboard, lists |
| `flash-message` | Session `status` banner | App layout |
| `forecast-charts` | Predicted vs actual Chart.js blocks | Forecast index/show |
| `devices/form` | Shared create/edit device fields | Device create/edit |
| `users/form` | Shared create/edit user fields | User create/edit |
| `sidebar-link` | Active-aware sidebar nav item with icon slot | App layout |
| `confirm-form` | Confirmation wrapper | Device, user, and token actions |
| `input-label`, `input-error`, `text-input`, `primary-button` | Breeze form primitives | Auth/profile/forms |
| `auth-session-status` | Session status message | Login |
| `application-logo` | Logo mark | Guest layout |
| `modal`, `dropdown`, `dropdown-link`, `nav-link`, `responsive-nav-link`, `secondary-button`, `danger-button` | Breeze leftovers | Mostly unused (see 16) |

Charts are rendered through the global `renderChart(elementId, config)` helper (`window.renderChart`).
Each page pushes a script block:

```blade
@push('scripts')
<script>
    renderChart('inspectionTrend', { type: 'line', data: {...}, options: {...} });
</script>
@endpush
```

## Page inventory

| Route | View | Auth | Notable data |
| --- | --- | --- | --- |
| `GET /login` | `auth/login.blade.php` | guest | Email/password/remember; no register link |
| `GET /dashboard` | `dashboard.blade.php` | any staff | Summary, trend, distribution, recent, devices, forecasts, warnings |
| `GET /inspections` | `inspections/index.blade.php` | staff | Filter form, paginated table (15/page) |
| `GET /inspections/{uuid}` | `inspections/show.blade.php` | staff | Metadata, detections with overlays, image |
| `GET /devices` | `devices/index.blade.php` | staff | Filters, paginated table (10/page) |
| `GET /devices/create` | `devices/create.blade.php` | admin | `x-devices.form` |
| `GET /devices/{id}` | `devices/show.blade.php` | staff | Stats, recent inspections, activate/deactivate |
| `GET /devices/{id}/edit` | `devices/edit.blade.php` | admin | `x-devices.form` |
| `GET /forecasts/cloud` | `forecasts/index.blade.php` | staff | Active forecast, charts, warnings, history |
| `GET /forecasts/network` | `forecasts/index.blade.php` | staff | Same view, network catalogue |
| `GET /forecasts/{id}` | `forecasts/show.blade.php` | staff | One forecast's series and warnings |
| `GET /users` | `users/index.blade.php` | admin | Filters, staff-only paginated table |
| `GET /users/create` | `users/create.blade.php` | admin | `x-users.form` |
| `GET /users/{id}/edit` | `users/edit.blade.php` | admin | `x-users.form`, status toggle |
| `GET /settings` | `settings/index.blade.php` | admin | System facts, counts, service tokens |
| `GET /profile` | `profile/edit.blade.php` | any staff | Account info + password forms |
| `GET /` | — | — | Redirects to dashboard or login |

Unrouted views: `auth/register`, `auth/forgot-password`, `auth/reset-password`,
`auth/verify-email`, `auth/confirm-password`, `welcome`, `layouts/navigation`.

## Interaction patterns

- **Forms** use standard `POST` with `@csrf` and `@method('put'|'patch'|'delete')`.
- **Validation errors** are displayed with `@error` near fields; API validation uses JSON instead.
- **Flash messages** are set with `->with('status', '...')` and rendered by `x-flash-message`.
- **Confirmation** uses `x-confirm-form` for destructive/state-changing actions.
- **Alpine** is limited to the sidebar toggle, header dropdown, and modal helper.
- **No client-side data fetching**: all data is embedded at render time.
- **No pagination component**: views render Laravel's paginator links (`$items->links()`).

## Conventions for new pages

1. Create a controller action in `app/Http/Controllers/Web/` and authorise it.
2. Add a named route in `routes/web.php` inside the `auth`+`active` group; put admin pages inside the
   `role:ADMINISTRATOR` group.
3. Create `resources/views/<area>/<page>.blade.php` starting with
   `<x-app-layout title="...">` and `<x-page-header>`.
4. Reuse `.panel`, `.data-table`, `.form-input`, `.form-label`, `x-stat-card`, `x-status-badge`,
   `x-empty-state`.
5. Use `display_datetime()`/`display_date()` for timestamps; never format UTC directly.
6. Add chart scripts via `@push('scripts')` and `window.renderChart`.
7. Escape all user data with `{{ }}`; only trusted markup uses `{!! !!}`.
8. Keep role-dependent navigation inside the existing `@if (auth()->user()?->isAdministrator())`.

## Accessibility and UX notes

- Sidebar toggle and overlay have `aria-label`s.
- Tables use semantic `thead`/`tbody` and `.data-table` classes.
- Forms pair `<label for>` with inputs and show inline errors.
- `x-cloak` prevents Alpine-controlled elements flashing before initialisation.
- Empty states are provided for dashboards and lists.
- No dark mode, i18n switching, or RTL support is implemented.

## Related documents

- Routes and backend actions: [06-backend-architecture.md](06-backend-architecture.md)
- Chart/metric catalogues: [09-business-logic-and-workflows.md](09-business-logic-and-workflows.md)
- Unused views and debt: [16-known-limitations-and-technical-debt.md](16-known-limitations-and-technical-debt.md)
