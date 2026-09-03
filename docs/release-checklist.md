# Release checklist

Release is currently blocked. The implementation and documentation may be reviewed,
but no tag, package, deployment, or Live provisioning should be created until the
following evidence is recorded against the exact release commit.

## Automated gates

1. Push the branch or open a pull request so `.github/workflows/ci.yml` runs.
2. Require the Composer install, PHP syntax, PHPUnit, and PHPStan steps to pass.
3. Review failures rather than bypassing the workflow.
4. If PHPCS cleanliness is a release requirement, scope and approve that separate
   cleanup; PHPCS is intentionally not part of the minimal CI workflow.

## Manual WordPress acceptance

1. Install the exact plugin directory on a clean WordPress 6.4+ / PHP 8.2+ staging site.
2. Activate **ArvanCloud Commerce** twice across a deactivate/reactivate cycle and
   confirm migrations, roles, schedules, and generated pages remain idempotent.
3. In Mock Mode, complete top-up, estimate, order, provisioning, resource view, usage,
   invoice, notification, suspension/recovery, and internal settlement flows.
4. Verify admin search and next/previous pagination with more than one page of records;
   confirm dashboard aggregates do not change when a list page changes.
5. Verify manual shortcode embedding leaves theme chrome, admin bar, document language,
   and document direction unchanged. Verify recorded generated pages use standalone mode.
6. Keyboard-check modal and account-drawer entry, Tab/Shift+Tab trapping, Escape close,
   background inertness, scroll restoration, and focus return.
7. Check screen-reader names/status announcements and reduced motion, then visually
   inspect Persian and English at 375, 768, 1024, and 1440 pixels.
8. Confirm disabling low-balance email does not suppress in-app financial/service events.
9. Exercise a recovery record with no Resource ID and confirm it becomes manual review,
   retains payment evidence, and does not block a later recoverable order.

## Live gate

Complete [`live-api-checklist.md`](live-api-checklist.md) with a dedicated
least-privilege Machine User. The read-only authenticated connection must be verified by
an authorized human. Obtain separate approval before any potentially billable server
creation. Payments remain Mock-only and settlement remains internal accounting only.

## Release actions after approval

1. Review the final diff and changelog on the exact commit that passed all gates.
2. Confirm `Version` and `Stable tag` remain aligned at `1.1.0`, or approve a deliberate
   version change before packaging.
3. Create and inspect the archive using the non-overwriting command in
   [`submission-checklist.md`](submission-checklist.md).
4. Confirm the repository contains the root `LICENSE`; confirm the archive contains the
   plugin runtime and WordPress `readme.txt` while excluding repository/test/development
   artifacts.
5. Only then approve merge, tag, GitHub release, and deployment as separate actions.
