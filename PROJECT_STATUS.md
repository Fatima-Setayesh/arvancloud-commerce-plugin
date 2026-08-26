# Project status

Status reflects `fix/product-hardening` after the finance, API, lifecycle, frontend,
notification, invoice, and multi-currency hardening commits. It separates implemented
code from environment-dependent and human Live verification.

| Requirement | Status | Evidence / limitation |
|---|---|---|
| Custom financial tables and migrations | COMPLETE | Ten InnoDB domain tables; schema version 1.4.0; clean/repeated activation harness passes. |
| Wallet and immutable ledger | COMPLETE | Integer scale 10,000, transactional cached balance + ledger row, currency isolation, idempotency, reconciliation. |
| Payments | MOCK ONLY | Create/confirm/refund are atomic and idempotent; no external payment gateway is claimed. |
| Cloud Server ordering | COMPLETE IN MOCK | Backend-authoritative first-24-hour quote/debit, compensation, ambiguity hold, and idempotent provisioning. |
| Resource mapping/recovery | COMPLETE IN MOCK | Safe local mapping and deterministic recovery; ambiguous remote outcomes require operator review. |
| Hourly billing | COMPLETE | Starts after prepaid cursor, exact UTC windows, partial debit/uncovered tracking, immutable resource currency. |
| Invoices | COMPLETE | One daily customer invoice per currency/period, idempotent, paid or issued from uncovered usage. |
| Settlement | INTERNAL ONLY | Per-currency daily accounting summaries; no payout API exists or is invoked. |
| Notifications | COMPLETE | Deduplicated payment/provisioning/suspension/termination events, low-balance email, owned read state. |
| Suspension/termination | COMPLETE WITH LIMITATION | Documented power-off/terminate calls; suspension is not claimed to stop provider billing. |
| REST isolation | COMPLETE | Cookie + nonce, capability checks, server-derived customer ID, anti-IDOR lookups, safe serializers. |
| Pagination | COMPLETE | Bounded page/offset and has-more headers; total count intentionally omitted. |
| Secret lifecycle | COMPLETE, ENVIRONMENT DEPENDENT | Authenticated encryption fails closed; uninstall always removes credentials. The available LocalWP PHP lacks a supported crypto backend. |
| Activation/deactivation/uninstall | COMPLETE | Pages and schedules are idempotent; deactivation retains data; uninstall retention never retains secrets. |
| Customer product UI | COMPLETE FOR EXPOSED MOCK FLOWS | Storefront, WordPress auth, portal, wallet, configurator, orders, resources, billing, notifications, responsive RTL. |
| Reseller admin UI | COMPLETE FOR EXPOSED OPERATIONS | Settings/setup, customers, finance, orders/resources, settlement, health, reconciliation, audit. |
| Accessibility and visual acceptance | PARTIAL | Semantic/keyboard/responsive foundations exist; final assistive-technology and multi-browser human review remains. |
| Mock runtime | AUTOMATED COMPLETE | Standalone, activation, and full no-network lifecycle harnesses pass. Real LocalWP smoke status is recorded in the final audit. |
| Live adapter specification | VERIFIED AGAINST SPEC | Host and operations match official IaaS 3.0.0 OpenAPI paths. |
| Authenticated Live behavior | LIVE UNVERIFIED | No key requested or used; no paid/mutating Live action performed. |
| Cloud products | CLOUD SERVER ONLY | CDN and Object Storage are intentionally not implemented. |

## Verification record

- Standalone invariant harness: 28 passed, 0 failed, 1 environment skip.
- Environment skip: authenticated-encryption round-trip because the available LocalWP
  PHP exposes neither libsodium secretbox nor OpenSSL AES-256-GCM. Production Live
  credential storage correctly fails closed in that environment.
- Mock lifecycle harness: top-up, prepaid order, provisioning/mapping, post-prepaid
  hourly billing, warning, suspension, termination, invoice, and settlement pass with
  zero application HTTP calls.
- Activation harness: clean activation and repeated idempotent activation pass.
- Live spec reviewed at `https://www.arvancloud.ir/api-docs/iaas-3.0.0.yaml`.

## Release blockers and operator obligations

1. Complete a human keyboard/screen-reader and supported-browser visual pass.
2. Provide a PHP runtime with libsodium or AES-256-GCM before storing any Live key.
3. Configure a monitored system scheduler for `wp-cron.php`; traffic-driven WP-Cron is
   insufficient for billing SLAs.
4. Complete the least-privilege read-only Live checklist. Obtain separate explicit
   approval before the first billable server creation.
5. Integrate a real payment provider and payout process if external commerce requires
   them; current payment and settlement adapters are deliberately Mock/internal.
