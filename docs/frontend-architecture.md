# Frontend architecture

## Layers

1. `Arvan_Reseller_Presentation` creates non-secret runtime configuration, enqueues assets only while rendering plugin surfaces, and provides the central view renderer.
2. `Arvan_Reseller_Shortcodes` registers the two independent customer entry points.
3. `Arvan_Reseller_Dashboard` chooses storefront, WordPress authentication, or authenticated portal rendering.
4. `Arvan_Reseller_Admin_Menu` registers the operations console and the capability/nonce-protected page setup action.
5. PHP views render accessible application shells; business data is loaded by the REST applications.
6. `assets/js/rest-client.js` is the only HTTP client. Shared UI behavior lives in `assets/js/ui.js`.

There is no frontend framework or build pipeline. The browser applications are modular vanilla JavaScript and scoped CSS.

## Embedded and standalone modes

The same shortcodes support two explicit presentation modes:

- Activation/setup-created Storefront and Portal page IDs receive a standalone
  body class. Only those pages hide theme chrome and apply full-page canvas/layout
  rules.
- A shortcode inserted manually into any other page is embedded. Its styles and
  language behavior stay within `.arvan-reseller-app`, leaving the host theme,
  WordPress admin bar, document language, and document direction untouched.

The mode is determined from the recorded page ID, not by guessing from page content.

## REST client

The client receives `rest_url()` and a `wp_rest` nonce through an inline JSON runtime object. It sends authenticated same-origin requests with `X-WP-Nonce`, JSON headers, an AbortController timeout, normalized safe errors, session-expiration events, and bounded retries for GET requests or idempotent writes only.

Payment and order creation use an in-memory logical-operation key. A retry of the same operation reuses its key; changing a Cloud Server configuration starts a new logical operation. Keys and secrets are never placed in localStorage or sessionStorage.

## Data presentation

The money adapter preserves backend decimal strings and groups digits without converting authoritative amounts to floating-point calculations. Currency comes from settings/API payloads; the interface displays the code and never guesses Toman. Charts use numeric projections only for visual shape, while exact accessible values remain in tables.

Status labels map only allowlisted backend states. Raw remote payloads, secrets, SQL messages, stack traces, and file paths are never rendered. Unknown error details are replaced with a safe Persian message while the stable code remains visible for support.

## Authentication

The portal does not store passwords. Logged-out customers receive a password-manager-compatible form posted to WordPress `wp-login.php`, a WordPress lost-password link, and the standard registration link only when site registration is enabled.

## Contract boundaries

The presentation consumes only allowlisted REST fields. Admin resources include owner
and order mapping; order responses include safe configuration, quote, prepaid payment,
failure/recovery, and local resource fields; resource responses include validated
name/image/flavor/disk/IP/lifecycle values; notifications expose read state and an
owned idempotent read mutation. Raw remote payloads are still intentionally absent.

Collections accept bounded `page`/`offset` pagination and expose has-more metadata in
response headers. They do not compute a potentially expensive total count, so the UI
uses next/previous navigation instead of inventing totals. No reset/seed endpoint is
shipped: demos use normal protected Mock workflows and test harnesses, avoiding a
production mutation surface whose only purpose would be synthetic data.

Admin operation lists also accept bounded, sanitized server-side search. Customer
lists contain only users with commerce evidence (wallet, payment, order, or resource)
and include their configured-currency wallet balance and service count. Dashboard
money and resource/payment totals come from the aggregate `/admin/overview` response,
not from the currently visible table page.

## Polling and performance

Only pending or provisioning orders are polled. Polling stops on terminal states,
after 15 attempts, while the page is hidden, or when navigation changes. Requests are
bounded and list pages normally request 25 rows. Assets enqueue only when a shortcode
or plugin admin page renders.
