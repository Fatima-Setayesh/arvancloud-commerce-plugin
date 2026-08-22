# Submission and packaging checklist

## Product

- [x] Cloud Server-only scope is explicit.
- [x] Admin and customer shells are Persian RTL and theme-independent.
- [x] Storefront and portal shortcodes are registered.
- [x] Setup creates pages idempotently.
- [x] One REST client handles nonce, timeout, safe errors, retries, and idempotency.
- [x] Browser estimate comes from `POST /catalog/estimate`.
- [x] Mock top-up and provisioning are visibly labelled.
- [x] Internal settlement never claims external payout.
- [ ] Live read-only connection verified by a human.
- [ ] Contract gaps accepted or backend serializers extended after review.

## Security

- [x] No custom password storage.
- [x] No API key is preloaded, logged, or stored in browser persistence.
- [x] Admin page creation uses capability and nonce checks.
- [x] Refund/Cron/reconciliation/billable actions require confirmation.
- [x] UI error mapping hides raw backend detail and preserves stable codes.
- [ ] Run a final secret scan immediately before release.

## Validation

- [x] JavaScript syntax checked with `node --check`.
- [x] `git diff --check` passes.
- [ ] PHP syntax checked with PHP 8.2 CLI (CLI unavailable on the current machine).
- [ ] Runtime shortcode/page registration verified in a clean WordPress browser.
- [ ] Mock REST flow exercised through the new UI.
- [ ] Keyboard and screen-reader sanity pass completed.
- [ ] Visual acceptance recorded at 375, 768, 1024, and 1440px.

## Clean package

The release archive must contain only the plugin runtime directory. From repository root, after the release commit:

```powershell
if (Test-Path 'dist\arvan-reseller-1.1.0.zip') { throw 'Release ZIP already exists; obtain approval before overwrite.' }
New-Item -ItemType Directory -Force dist
git archive --format=zip --prefix=arvan-reseller/ -o dist/arvan-reseller-1.1.0.zip HEAD:arvan-reseller
```

Before distribution, inspect the archive and confirm it excludes `.git`, secrets, Docker volumes, editor state, test artifacts, caches, and unrelated repository files while retaining all runtime PHP/CSS/JS/views/readme/uninstall files.

## Deployment

1. Back up database and plugin files.
2. Install ZIP on staging with PHP 8.2+ and a default theme.
3. Activate, run setup, and verify Mock end-to-end.
4. Verify Cron replacement before disabling visitor-triggered Cron.
5. Save a Machine User key directly in WordPress only when Live testing is authorized.
6. Run only the read-only connection test.
7. Obtain separate approval before first billable Live provisioning.
