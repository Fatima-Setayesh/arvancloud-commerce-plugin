# Backend contract and operations

## Modes and official API boundary

`mock` is the default mode and is deterministic. Its adapter never calls a WordPress
HTTP function. `live` derives its base URL as
`https://ecc.{validated-region}.arvanapis.ir/v3` and implements only operations found
in the official IaaS v3 OpenAPI document:

- `GET /availability-zones`
- `GET /images`
- `GET /flavors`
- `POST /servers`
- `GET /servers/{id}`
- `POST /servers/{id}/power-off`
- `POST /servers/{id}/terminate`

Reference: https://www.arvancloud.ir/api-docs/iaas-3.0.0.yaml

The published document has no Cloud Server usage endpoint. Billing therefore uses
the order's snapshotted `pricePerHour` for exact UTC windows. It also has no settlement
or payout endpoint; settlements are internal, idempotent accounting summaries.

## REST authentication

Base namespace: `/wp-json/arvan-reseller/v1`. Send the normal WordPress authenticated
cookie and `X-WP-Nonce`. State-changing clients must also send a unique
`Idempotency-Key`. Customer identity always comes from the authenticated user.

Customer routes:

| Method | Route | Response purpose |
|---|---|---|
| GET | `/wallet` | Balance, threshold, currency and wallet status |
| GET | `/wallet/transactions` | Safe immutable ledger history |
| GET/POST | `/payments` | List or create a Mock payment intent |
| POST | `/payments/{reference}/confirm` | Atomically confirm and credit wallet |
| GET | `/catalog/regions`, `/catalog/images`, `/catalog/flavors` | Product configuration |
| POST | `/catalog/estimate` | Validate a flavor and return an hourly cost estimate |
| GET | `/orders` | Current customer's orders only |
| POST | `/orders` | Create an idempotent Cloud Server order |
| GET | `/resources`, `/resources/{local-id}` | Only the current customer's resources |
| GET | `/usage` | Current customer's immutable usage windows |
| GET | `/invoices` | Current customer's invoice records, when present |
| GET | `/notifications` | Current customer's notification history with read state |
| POST | `/notifications/{local-id}/read` | Idempotently mark one owned notification read |

The order body contains `region`, `availabilityZone`, `flavorId`, `imageId`, `name`,
and `rootVolumeSizeGigaBytes`; documented optional booleans are accepted.

Write requests use JSON. `/payments` accepts `amount`; `/catalog/estimate` accepts
`region`, `flavor_id`, and decimal-string `usage_hours`; `/orders` accepts the fields
above plus optional `enableBackup`, `enableFailOver`, `enableIpv4`, and `enableIpv6`.
Payment creation and order creation require a unique `Idempotency-Key`
header (the JSON fallback `idempotency_key` remains supported for existing clients).
Payment confirmation is idempotent by its server-generated payment reference.
List routes accept `limit` from 1 to 100 plus either `page` or `offset`. They remain
backward-compatible arrays and return `X-Arvan-Page`, `X-Arvan-Per-Page`, and
`X-Arvan-Has-More` response headers. Customer responses contain formatted money,
stable references/statuses and public lifecycle timestamps, but never encrypted values,
raw remote payloads, SQL errors, stack traces, or filesystem paths.

Admin routes require `manage_arvan_reseller`:

| Method | Route | Purpose |
|---|---|---|
| GET/PUT/PATCH | `/admin/settings` | Read redacted settings or update/rotate/delete key |
| POST | `/admin/connection-test` | Safe adapter connection test |
| POST | `/admin/cron/run` | Run protected usage billing |
| POST | `/admin/reconciliation/run` | Run protected resource reconciliation |
| GET | `/admin/health` | Mode, schema, schedules and last job health |
| GET | `/admin/overview` | Exact aggregate operations totals and sanitized job state |
| GET | `/admin/customers` | Safe customer directory |
| GET | `/admin/wallets` | Wallet balances without ledger mutation data |
| GET | `/admin/payments` | Filtered payment records |
| POST | `/admin/payments/{reference}/refund` | Idempotent Mock payment refund |
| GET | `/admin/orders` | Order records and recovery state |
| GET | `/admin/resources` | Resource mappings and policy state |
| GET | `/admin/usage` | Usage-window billing records |
| GET | `/admin/settlements` | Settlement summaries without metadata |
| GET | `/admin/audit-logs` | Redacted administrative and security events |

`api_key` rotates the stored key only when non-empty. `delete_api_key: true` explicitly
removes it. Responses expose only `api_key_configured`, never key material.

Admin settings accept the documented company fields, logo URL/attachment ID, `mock` or
`live` mode, validated region/zone, three-letter currency, decimal-string wallet/share
values, suspend/termination controls, and the two explicit key actions.
`email_notifications_enabled` controls low-balance email only. The legacy
`notification_enabled` key remains accepted and is migrated/mirrored for compatibility;
in-app financial and service lifecycle records are always retained. Sending credential
rotation and deletion together is rejected.

Admin operation collections accept the same bounded pagination parameters plus a
bounded sanitized `search` value where documented by the route. The customer directory
contains commerce participants only and includes configured-currency wallet balance and
service count. `/admin/overview` supplies full-dataset resource/payment/customer counts,
per-currency wallet/billed totals, reconciliation warnings, and sanitized Cron state so
the dashboard does not infer totals from a page of rows.

Successful routes return the safe object/array described above. Errors use WordPress'
standard REST envelope with a stable `code`, localized `message`, and HTTP status only.
Important codes include `arvan_reseller_unauthorized` (401),
`arvan_reseller_forbidden` (403), `arvan_reseller_*_not_found` (404),
`arvan_reseller_rate_limited` (429), validation codes (400), documented adapter status
codes, and `arvan_reseller_provisioning_recovery_required` (remote creation succeeded;
an administrator should run reconciliation). Customer-owned misses deliberately use the
same 404 shape for absent and foreign records to prevent ID enumeration.

## Financial invariants

Money is represented as signed/unsigned integers at scale 10,000. Floats are rejected.
Every wallet mutation locks the wallet in an InnoDB transaction, appends one immutable
ledger row with a unique idempotency key, updates the cached balance and commits as one
unit. Payment confirmation, billing and cursor advancement are idempotent. Usage has
both a billing-reference uniqueness rule and a resource/window uniqueness rule.

Cloud Server ordering is prepaid and backend-authoritative. The server validates the
catalog flavor and reseller share, creates an immutable 24-hour quote, and atomically
debits that amount before calling the cloud adapter. On success the resource billing
cursor begins at `prepaid_until`, so the same first day is not billed again. A known
deterministic create failure is compensated by an idempotent ledger credit. An
ambiguous transport or 5xx outcome retains the debit and records recovery state because
automatic refund plus retry could create an unpaid duplicate remote server.

Resources retain the currency snapshotted at ordering. Hourly billing, notifications,
invoices, and balance policy use that immutable value rather than a later settings
change. The daily UTC job discovers every currency represented in the period and emits
independent idempotent invoices and settlement summaries per currency.

## Jobs and recovery

Usage sync uses an atomic option lock, keyset pagination, persisted retry state,
bounded exponential backoff and a health option. Duplicate billing is a successful
idempotent outcome. Notifications are deduplicated until a recharge crosses the wallet
threshold. A zero balance evaluates every resource owned by only that customer.

Provisioning persists a pending local order before a remote call. If remote creation
succeeds but local mapping fails, or if the remote outcome is ambiguous, the order is
flagged for reconciliation. A known remote ID can be mapped safely without creating
another server. A queued record without a usable remote ID is removed from automatic
retry, marked with stable failure code `manual_review_missing_resource_id`, and surfaced
to both administrator and customer while its debit/evidence remains intact. Processing
continues with later queued records; no remote create, lookup without an ID, or automatic
refund is attempted for that record.

Termination policies are `disabled` (default), `immediate`, or `grace`; repeated runs
are safe because terminated resources are excluded. Suspension uses the documented
power-off operation and pauses this plugin's hourly charging, but it is not a promise
that ArvanCloud stops external infrastructure billing. Daily settlement references are
derived from period and currency, making retries idempotent. Settlement is internal
accounting only and never invokes a payout API.

WP-Cron is traffic-driven. Production sites should arrange a monitored system scheduler
to request `wp-cron.php` at least every five minutes (and disable the built-in visitor
trigger only after that scheduler is verified). The health endpoint reports the next
scheduled events, last success, last failure, and retry state; operations should alert
when an expected run is overdue.

## Mock demonstration

The reproducible WordPress scenario is defined in `tests/docker-compose.yml` and
`tests/wordpress-e2e.php`. It installs WordPress, activates/migrates the plugin, blocks
all WordPress HTTP traffic, configures Mock Mode, tops up a customer wallet, provisions
a Cloud Server, records the remote resource mapping, bills exact usage windows, sends a
threshold warning, applies zero-balance suspension/termination policy and completes a
settlement. It fails if any network request is attempted.

## Testing

Automated scenarios must use Mock Mode. A real key is never required and paid
provisioning must never be invoked. The repository's `tests/run.php` exercises exact
money, wallet rollback/idempotency, migrations, payment lifecycle, authenticated secret
envelopes, no-network Mock Mode, provisioning mapping, billing and settlement.

The Docker-backed activation and full lifecycle commands are intentionally separate
from PHPUnit so CI can run unit/static gates without Docker and run the clean WordPress
scenario as an integration job.
