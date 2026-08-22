# UI system

## Direction

The presentation layer is a Persian RTL cloud operations console inspired by compact infrastructure products: cool-gray canvas, white operational surfaces, dark navy navigation, blue interaction states, and restrained orange primary commerce actions. It uses no proprietary ArvanCloud assets and no third-party runtime CDN.

All product selectors are scoped beneath `.arvan-reseller-app`. Theme and WordPress elements outside that root are not styled.

## Tokens

`assets/css/design-system.css` defines semantic custom properties for:

- primary, hover, on-primary, secondary, accent;
- surface, raised surface, background, text, muted text, border;
- success, warning, danger, info, and keyboard focus;
- spacing from 4px to 48px;
- radius, elevation, typography, icon sizes, containers, z-index;
- 160–220ms motion and shared easing.

Components consume these properties instead of carrying product colors independently. A system Persian fallback stack is used because no licensed local font file was available; no font was downloaded.

## Components

The PHP shells and shared JavaScript provide reusable equivalents for app shell, sidebar, topbar, mobile nav, page headers, metric/resource cards, status badges, alerts, toasts, accessible modal/confirm dialog, stepper, tabs, filters, tables/responsive lists, form fields, password/API-key controls, copyable IDs, timeline, SVG charts, loading, skeleton, empty, error, and retry states.

Icons are a single local outline SVG family rendered from `assets/js/ui.js`. No emoji or external icon fonts are used.

## Responsive behavior

- `1440px`: persistent right navigation and dense dashboard grids.
- `1024px`: drawer navigation and single-column primary layouts.
- `768px`: customer mobile bottom navigation appears; tables remain controlled.
- `375px`: two-up compact metrics where useful, one-column forms/cards, bottom-sheet dialogs, and responsive table cards.

The customer portal is composed for mobile rather than being a scaled desktop. Technical IDs use `dir="ltr"` and isolated monospace rendering.

## Accessibility

Implemented foundations include skip links, semantic landmarks/headings, explicit labels, visible `:focus-visible`, 44px primary touch targets, text plus color status labels, live toast/content regions, modal focus trapping, focus restoration, Escape dismissal, reduced motion, responsive table labels, and confirmation for refunds, Cron, reconciliation, and billable operations.

Runtime keyboard and screen-reader validation remains required in a real WordPress browser environment; see `PROJECT_STATUS.md`.
