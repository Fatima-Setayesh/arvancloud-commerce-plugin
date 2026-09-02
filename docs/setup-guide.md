# Setup guide

## Requirements

- WordPress 6.4 or newer
- PHP 8.2 or newer
- HTTPS for production
- A trusted administrator with `manage_arvan_reseller`

No commerce plugin, page builder, or JavaScript package installation is required.

## Install and activate

1. Copy the packaged `arvan-reseller` directory into `wp-content/plugins`.
2. Activate **Arvan Reseller**.
3. Activation performs versioned custom-table migrations, creates/checks Store and Portal pages idempotently, and schedules usage, reconciliation, and internal settlement jobs.
4. Open **Arvan Reseller → Setup**.

## Setup wizard

Complete the wizard in order:

1. Review the Cloud Server-only scope.
2. Enter organization name/about.
3. Add a licensed/self-owned logo URL and contact information.
4. Keep mode on **Mock** and retain the deterministic Mock region/zone for the first demo.
5. Leave the API key blank in Mock. The field never preloads a stored key.
6. Set reseller share between 0% and the backend maximum of 20%.
7. Confirm the three-letter currency code and wallet thresholds/limits. The current default contract uses `IRR` at four-decimal internal precision.
8. Choose suspension and termination policies deliberately.
9. Confirm the activation-created pages. Use **Create/check pages** only to repair missing pages; repeating it is safe.
10. Save settings and review readiness.

## Customer setup

Enable WordPress registration only if the site should allow self-registration. Otherwise create customers with normal WordPress user administration. The portal delegates login, registration, password reset, session cookies, and password storage to WordPress.

Generated pages use `[arvan_reseller_store]` and `[arvan_reseller_portal]`. They work with a minimal default theme and can also be placed manually.

## Mock demonstration

1. Sign in as a customer.
2. Open Wallet and create a clearly labelled Mock payment.
3. Confirm the pending Mock payment to credit the wallet atomically.
4. Open Create Server; choose region, image, and flavor returned by REST.
5. Set supported options and server name.
6. Review the backend-authoritative 24-hour estimate.
7. Confirm the order. The backend atomically debits the authoritative first-24-hour quote before building the deterministic Mock server.
8. Copy the Resource ID, then inspect orders, usage, invoices, transactions, and notifications.
9. As administrator, inspect health/audit and run protected Cron or reconciliation with confirmation.

## Cron

WordPress Cron is traffic-driven. A production site should use a monitored system scheduler to request `wp-cron.php` at least every five minutes. Disable visitor-triggered Cron only after the replacement has been verified. The health screen shows next schedules and the last recorded job outcome.

## Uninstall

Deactivation removes schedules/locks but retains customer and financial records. Uninstall always deletes the encrypted API key and transient security state, while domain tables remain by default. Enabling `delete_data_on_uninstall` deletes those tables and remaining plugin options and is irreversible; take a verified backup first.

## Troubleshooting

- `arvan_reseller_unauthorized`: sign in again and reload to receive a fresh REST nonce.
- `arvan_reseller_forbidden`: verify the administrator has `manage_arvan_reseller`.
- `arvan_reseller_rate_limited`: wait before retrying; the UI does not bypass limits.
- Network/timeout: check WordPress REST availability and HTTPS; safe GETs may retry automatically.
- Provisioning recovery required: run admin reconciliation; do not create a duplicate server.
- Live key cannot be saved: enable libsodium or OpenSSL with AES-256-GCM; credential storage fails closed when authenticated encryption is unavailable.
- Provisioned but unresolved order: leave the order in recovery state and run reconciliation; do not submit a second order.
