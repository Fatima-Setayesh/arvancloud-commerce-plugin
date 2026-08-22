# ArvanCloud Commerce Plugin

A standalone WordPress reseller product for **Cloud Server only**. It combines the validated prepaid backend with a Persian RTL operations console, customer storefront, wallet, catalog-driven server configurator, authoritative estimates, Mock provisioning, billing, health, audit, and internal settlement reporting.

It does not require WooCommerce, ACF, Elementor, a page builder, or a specific theme. It does not claim an official ArvanCloud partnership.

## Product surfaces

- Reseller console: setup wizard, dashboard, customers, payments/refunds, orders, resources, usage, internal settlements, health, audit, and protected API-key controls.
- Customer product: storefront, WordPress authentication, wallet and Mock top-up, Cloud Server configurator, backend estimate, orders, Resource IDs, usage, invoices, transactions, and notifications.
- Responsive UI: persistent desktop navigation, tablet drawer, and dedicated mobile bottom navigation.
- Backend contract: `/wp-json/arvan-reseller/v1`; see [`docs/backend.md`](docs/backend.md).

## Installation

1. Package or copy `arvan-reseller/` into `wp-content/plugins/`.
2. Activate **Arvan Reseller** in WordPress.
3. Open **Arvan Reseller → Setup** and remain in Mock mode.
4. Complete the setup wizard and use **Create/check pages**.
5. Open the generated storefront and portal pages.

The setup action is idempotent and creates pages containing:

```text
[arvan_reseller_store]
[arvan_reseller_portal]
```

## Safe first run

Mock mode is deterministic and does not contact ArvanCloud. Create a WordPress customer, sign in through the portal, create and confirm a Mock payment, request a catalog estimate, provision a Mock Cloud Server, and inspect its Resource ID. Run protected Cron and reconciliation only from the health screen.

Live mode requires a dedicated least-privilege Machine User, an encrypted API key saved directly in WordPress, and a successful read-only connection test. Never paste the key into source, Git, logs, or a support conversation. See [`docs/live-api-checklist.md`](docs/live-api-checklist.md).

## Important limitations

- Cloud Server is the only supported product.
- The browser never calculates an authoritative charge.
- The published IaaS v3 contract has no per-server usage endpoint; backend billing uses stored hourly pricing and exact UTC windows.
- Settlement is simulated/internal accounting, not an external payout.
- Power-off is an operational action and is not described as a guaranteed billing freeze.
- Some requested presentation fields are not in the current safe REST serializers; the UI labels those gaps and never invents values. See [`docs/frontend-architecture.md`](docs/frontend-architecture.md#contract-boundaries).

## Documentation

- [`docs/setup-guide.md`](docs/setup-guide.md)
- [`docs/ui-system.md`](docs/ui-system.md)
- [`docs/frontend-architecture.md`](docs/frontend-architecture.md)
- [`docs/live-api-checklist.md`](docs/live-api-checklist.md)
- [`docs/demo-script.md`](docs/demo-script.md)
- [`docs/submission-checklist.md`](docs/submission-checklist.md)
- [`PROJECT_STATUS.md`](PROJECT_STATUS.md)

## Packaging

From the repository root, after committing the intended release contents:

```powershell
New-Item -ItemType Directory -Force dist
git archive --format=zip --prefix=arvan-reseller/ -o dist/arvan-reseller-1.1.0.zip HEAD:arvan-reseller
```

This archives only the runtime plugin directory and excludes repository metadata, tests outside the plugin, local secrets, editor state, and temporary files.
