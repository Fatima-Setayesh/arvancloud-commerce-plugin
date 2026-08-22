=== ArvanCloud Commerce Plugin ===
Contributors: fatima-setayesh
Tags: arvancloud, cloud-server, wallet, billing
Requires at least: 6.4
Requires PHP: 8.2
Stable tag: 1.1.0
License: GPLv2 or later

Standalone Persian RTL Cloud Server reseller product with a secure prepaid backend and deterministic Mock Mode.

== Description ==

The plugin provides an integer-money wallet, immutable ledger, mock top-up payments,
idempotent Cloud Server orders, hourly prepaid billing, low-balance notification,
zero-balance suspension, configurable termination, settlement aggregation, audit logs,
and a versioned REST API.

The presentation layer adds an independent storefront, WordPress authentication entry,
customer portal, catalog-driven Cloud Server configurator, backend estimate, wallet and
Mock payment workflow, responsive service/billing screens, and a complete reseller
operations console. It uses native PHP, vanilla JavaScript and scoped CSS with no page
builder, commerce plugin, frontend framework or runtime CDN dependency.

Mock Mode is the default and never performs an HTTP request. Live Mode uses only the
published ArvanCloud IaaS v3 operations at `https://ecc.[region].arvanapis.ir/v3`.
The published specification does not expose per-server usage or settlement transfer
operations, so Live Mode derives usage from the documented flavor hourly price and
settlement remains an internal accounting record. No external payout is claimed.

== Installation ==

1. Install and activate the plugin in WordPress.
2. Keep API Mode set to `mock` while testing.
3. Grant `manage_arvan_reseller` only to trusted administrators.
4. For Live Mode, create a least-privilege Machine User key in ArvanCloud, enter it
   once in settings, configure the documented region and availability zone, then use
   the connection-test endpoint. The key is authenticated-encrypted and never shown.
5. Ensure WP-Cron is reliable. Because normal WP-Cron depends on site traffic,
   production installations should use a monitored system scheduler to request
   `wp-cron.php` at least every five minutes. Disable visitor-triggered WP-Cron only
   after the replacement scheduler is verified.

== Machine User setup ==

Create a dedicated least-privilege Machine User in the ArvanCloud console and grant
only the Cloud Server permissions needed for catalog reads, server creation/status,
power-off and termination. Copy the key directly into the plugin settings over HTTPS,
save it once, and use the connection-test action. The stored key is never returned by
the settings page or REST API. Rotate by submitting a new key; remove it only through
the explicit delete-key action. Never configure a custom API hostname.

== Mock demo ==

1. Activate the plugin and leave API Mode as `mock`.
2. Configure company/currency, wallet limits, threshold, zero-balance suspension and
   a termination policy.
3. Authenticate as a customer and send the WordPress REST nonce in `X-WP-Nonce`.
4. Create and confirm a Mock payment using one unique `Idempotency-Key`.
5. Read the Mock catalogs, request an estimate, and create a Cloud Server order with a
   new idempotency key. The response contains the local order and mapped Mock server ID.
6. Run the protected admin Cron action (or wait for WP-Cron), then inspect wallet,
   usage, notifications, resources and Cron health.
7. Run settlement/reconciliation through their scheduled jobs. The reproducible
   Docker scenario in `tests/wordpress-e2e.php` executes this lifecycle with every
   application HTTP request blocked and asserts zero network calls.

== Security ==

All state-changing REST calls require an authenticated WordPress cookie and a valid
`X-WP-Nonce`. Customer IDs are derived from the current user; they are not accepted
from customer requests. Admin calls require `manage_arvan_reseller`, are rate-limited,
and record redacted audit events. Live destinations are HTTPS-only, fixed from a
validated region, redirect-free, and allowlisted to `.arvanapis.ir`.

API keys use versioned authenticated encryption (libsodium secretbox or AES-256-GCM),
random nonces and WordPress salts. Saving a blank key preserves the current key;
explicit deletion is available through the admin REST settings contract.

== Data lifecycle ==

Activation performs non-destructive versioned migrations and schedules usage,
reconciliation and settlement jobs. Deactivation removes schedules and locks but
retains customer and financial data. Uninstall also retains data by default. Set
`delete_data_on_uninstall` explicitly before uninstalling to delete plugin tables and
options. This action is irreversible.

== REST API ==

Namespace: `arvan-reseller/v1`. Customer routes cover wallet, ledger history, mock
payments, catalogs and estimates, Cloud Server orders, owned resources, usage windows,
invoices and notifications. Admin routes cover safe settings, connection testing,
customers, wallets, payments/refunds, orders, resources, usage, settlements, audit
logs, cron health and manual billing/reconciliation.
See `docs/backend.md` for the exact contract.

== Storefront and portal ==

Use the protected Setup action to create required pages without duplicates, or place
these shortcodes manually in normal WordPress pages:

`[arvan_reseller_store]`

`[arvan_reseller_portal]`

Logged-out portal visitors authenticate through normal WordPress APIs. Site registration
is offered only when enabled in WordPress. The customer UI works with a minimal default
theme and uses a dedicated mobile navigation at small viewports.

== Known API limitations ==

The official IaaS v3 specification used by this release does not publish a per-server
usage endpoint or a reseller payout/settlement endpoint. Live billing therefore uses
the hourly flavor price snapshotted at provisioning and exact UTC usage windows.
Settlement is explicitly internal accounting with a Mock settlement adapter; this
release does not claim or attempt an external payout. Suspension uses the documented
power-off operation, so it is operational shutdown rather than an undocumented billing
freeze.

== Changelog ==

= 1.1.0 =
* Added integer financial schema, atomic wallet ledger and idempotent usage billing.
* Added dedicated mock payments and deterministic no-network Cloud Server Mock Mode.
* Added documented IaaS v3 Live adapter, authenticated secret storage and SSRF controls.
* Added safe provisioning/reconciliation, reliable cron policy jobs, settlement and REST APIs.
