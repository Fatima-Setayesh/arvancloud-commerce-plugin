# Project status

This status separates implementation scope from verification evidence. The
verification record below applies only to the historical approved baseline at
`b0a0b53419bd6bff4037571d0c95c4cf53b1e888`, not to the current release-polish
branch. No test or manual runtime result has been recorded for the current branch.
The three release-showcase commits between that baseline and the merge into `main`
changed documentation, screenshots, and presentation assets only; this new hardening
branch also changes runtime code and therefore requires fresh validation.

| Capability | Status | Evidence or boundary |
| --- | --- | --- |
| Custom financial tables and migrations | **COMPLETE** | Ten InnoDB domain tables, schema version 1.4.0, clean and repeated activation harnesses pass |
| Wallet and immutable ledger | **COMPLETE** | Integer scale 10,000, transactional balance and ledger mutation, currency isolation, idempotency, reconciliation |
| Payments | **MOCK ONLY** | Mock create, confirm, and refund are atomic and idempotent; no external payment gateway is claimed |
| Cloud Server catalog and estimate | **COMPLETE IN MOCK** | Deterministic catalog and backend-authoritative estimate; Live credential connection remains unverified |
| Cloud Server ordering and provisioning | **COMPLETE IN MOCK** | First-24-hour quote/debit, compensation, ambiguity hold, retry hardening, and duplicate prevention |
| Resource mapping and recovery | **COMPLETE** | Stable Resource ID mapping, customer ownership, recovery marker, and reconciliation paths |
| Hourly billing | **COMPLETE** | Starts after the prepaid cursor; exact UTC windows, partial debit, uncovered tracking, immutable resource currency |
| Invoices | **COMPLETE** | Idempotent per-currency customer invoices for the covered period |
| Notifications | **COMPLETE** | Payment, provisioning, low-balance, suspension, and termination events with customer-owned read state |
| Suspension and termination | **COMPLETE WITH LIMITATION** | Adapter operations exist; power-off is not claimed to stop provider-side billing |
| Settlement | **INTERNAL ONLY** | Per-currency internal accounting summaries; no external payout API is invoked |
| REST API | **COMPLETE FOR CURRENT SCOPE** | Cookie and nonce authentication, capability checks, safe serializers, bounded collections |
| Customer isolation | **VERIFIED** | Server-derived customer identity, anti-IDOR lookup behavior, and cross-customer tests pass |
| Secret lifecycle | **VERIFIED** | Authenticated encryption, fail-closed handling, response redaction, explicit rotation/deletion, uninstall cleanup |
| Customer portal | **COMPLETE** | Storefront, auth, wallet, configurator, orders, resources, billing, notifications, responsive mobile UI |
| Reseller admin portal | **COMPLETE** | Setup, customers, payments, orders, resources, usage, settlement, health, audit, settings |
| Customer internationalization | **FA / EN COMPLETE** | Customer-owned UI text, state labels, preferences, and responsive navigation |
| Mock Mode | **COMPLETE** | Deterministic, no-network customer and administrator flow |
| Live adapter | **LIVE UNVERIFIED** | Code and host/operation allowlists exist; no real Machine User credential has been human-verified |
| Commercial products | **CLOUD SERVER ONLY** | CDN and Object Storage purchase flows are intentionally absent |

## Historical verification record

- Standalone invariant harness: **29 passed, 0 failed**.
- PHPUnit: **11 tests, 51 assertions**.
- PHPStan: **0 errors**.
- PHP syntax: **43/43 files passed**.
- JavaScript syntax: **4/4 files passed**.
- Activation and versioned migration harness: **PASS**.
- Mock top-up → prepaid order → provisioning → mapping → billing → notification →
  suspension/termination → invoice/settlement lifecycle: **PASS**.
- Real disposable WordPress E2E: **PASS**.
- Clean-install test: **PASS**.
- Cross-customer isolation: **PASS**.
- Authenticated-encryption and secret-lifecycle tests: **PASS**.
- Retry verification: insufficient balance cannot produce false success; after Mock
  top-up, one retry creates exactly one stable resource and repeated refresh creates
  no duplicate.

Those results must not be treated as current-branch evidence. The current branch
adds a future CI workflow for PHP syntax, PHPUnit, and PHPStan, but it has not been
executed as part of this release-polish work. PHPCS is outside that CI workflow and
the historical style-debt baseline has not been remeasured. For transparency, that
historical baseline reported **2,252 errors and 217 warnings across 34 files**,
primarily formatting, whitespace, alignment, naming, and CRLF debt; those counts are
not asserted for the current branch.

## Current release gate

Release remains blocked until the checks in
[docs/release-checklist.md](docs/release-checklist.md) are run and reviewed. Live
release additionally remains blocked on an authorized, least-privilege connection
test and separate approval before any billable provisioning.

## Live and operational limitations

1. The Live adapter has not been authenticated against ArvanCloud with a real,
   least-privilege Machine User credential.
2. No real payment gateway or external settlement/payout integration exists.
3. Powering off a Cloud Server does not guarantee that provider-side billing stops.
4. Ambiguous Live create outcomes are held for human resolution rather than retried
   or refunded automatically.
5. Billing uses stored hourly flavor pricing and local UTC windows, not an official
   per-server usage endpoint.
6. Traffic-driven WP-Cron is insufficient for billing SLAs; production deployments
   need a monitored system scheduler.
7. Cloud Server is the only supported commercial product.

Complete [docs/live-api-checklist.md](docs/live-api-checklist.md) before any Live
credential connection or potentially billable operation.
