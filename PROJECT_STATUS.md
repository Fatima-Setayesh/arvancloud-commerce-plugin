# Project status

Status reflects the `feat/product-experience` implementation and does not overstate runtime or Live validation.

| Requirement | Status | Evidence / limitation |
|---|---|---|
| Custom financial tables | Complete | Validated backend schema/migrations retained. |
| Wallet | Complete | Backend contract integrated in customer/admin UI. |
| Immutable ledger | Complete | Transactions rendered from safe REST serializer. |
| Payments | Mock only | Create/confirm/refund integrated; provider is Mock. |
| Orders | Complete | Idempotent creation/list/timeline integrated. |
| Provisioning | Mock only | Deterministic Mock UI implemented; Live not executed. |
| Resource ID | Complete | Returned IDs shown LTR and copyable. |
| Billing | Complete | Exact usage fields and accessible table integrated. |
| Reseller share | Complete | Backend estimate/usage values displayed; setup capped at 20%. |
| Notifications | Partial | Delivery records shown; read/unread missing from contract. |
| Suspension | Complete | States/policy represented without billing-freeze claim. |
| Termination | Complete | States/policy represented with confirmation language. |
| Settlement | Mock only | Explicitly simulated/internal accounting. |
| CSS/theme isolation | Complete | Product rules scoped under `.arvan-reseller-app`. |
| Secret security | Complete | Existing encrypted backend retained; UI never preloads key. |
| Admin product | Partial | Major screens complete; resource ownership/order diagnostic fields need serializer additions. |
| Customer product | Partial | Main flow complete; detailed name/image/flavor/read-state fields need serializer additions. |
| Responsive | Partial | 375/768/1024/1440 breakpoints implemented; browser captures pending. |
| Accessibility | Partial | Foundations implemented; runtime keyboard/screen-reader audit pending. |
| Mock | Partial | Full UI flow implemented; local WordPress execution unavailable. |
| Live | Not attempted | API key absent; connection test not run. |
| Documentation | Complete | Product, UI, setup, Live, demo, packaging, and status docs added. |
| ZIP | Partial | Clean command documented; archive awaits final committed HEAD. |
| Demo | Complete | Five-minute script prepared. Deterministic reset/seed route is unavailable. |
| Deployment | Partial | Checklist prepared; staging validation pending. |

## Contract mismatches

1. `/admin/resources` does not return customer ownership.
2. Resource responses omit server name, image, flavor/configuration, IP, and lifecycle timestamps beyond created/sync/billed.
3. Admin order responses omit configuration/quote, safe failure reason, recovery flag, and local mapping ID.
4. Orders expose no payment reference/acceptance relationship for a payment step in the provisioning timeline.
5. Notification responses have no read/unread state or mutation.
6. List endpoints lack offset/cursor/total for true server-side search and pagination.
7. No protected Demo reset/seed endpoint exists.

The UI does not invent these values. The smallest future backend work is to extend safe serializers and list arguments, plus add capability/nonce-protected read-state and Mock reset endpoints without changing financial or secret logic.

## Validation record

- Git branch created from synchronized `backend` and published upstream.
- JavaScript entry files pass `node --check`.
- `git diff --check` passes.
- PHP CLI is not installed locally, so required PHP 8.2 lint is pending.
- No unrelated WordPress installation was altered.
- No dependency was installed and no existing backend suite was rerun.
