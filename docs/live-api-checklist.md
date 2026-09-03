# Live API checklist

The adapter paths and host rules have been checked against the official IaaS 3.0.0 OpenAPI document, but authenticated Live operation has not been verified. Complete the full Mock workflow first.

## Machine User

1. Sign in to ArvanCloud and open **Settings → IAM → Machine Users**.
2. Create a dedicated Machine User whose name uses lowercase English letters, numbers, and hyphens. The official guide currently states a length of 5–100 characters.
3. Grant least-privilege permissions only for Cloud Server catalog reads, server creation/status, power-off, and termination. Do not add unrelated CDN, Object Storage, or account-administration permissions.
4. Copy the generated API key once and store it in a password manager or equivalent secret store. ArvanCloud documents that it cannot be recovered after closing the display.
5. Never paste it into Codex/chat, source code, Git, logs, browser storage, or screenshots.

Official references:

- https://docs.arvancloud.ir/fa/developer-tools/api/api-key/
- https://www.arvancloud.ir/fa/dev/api
- https://www.arvancloud.ir/api-docs/iaas-3.0.0.yaml

## WordPress configuration

1. Open **ArvanCloud Commerce → Setup/Settings → API connection** over HTTPS.
2. Paste the key directly into the password-style protected field.
3. Save. Confirm only the “configured” indicator is returned; the key must not be preloaded.
4. Keep Mock mode while completing the product demo.
5. Set the documented region and availability zone, then switch to Live only for the connection test.
6. Click **Read-only connection test**. It must not create, power off, terminate, or otherwise mutate a Cloud Server.
7. Record only success or a safe stable error code. Do not record the secret or raw response.

## Live acceptance

- [ ] Dedicated Machine User created
- [ ] Least-privilege policy reviewed
- [ ] Key stored directly in WordPress encrypted storage
- [ ] No secret present in HTML response, REST response, logs, or Git
- [ ] Read-only connection test passed
- [ ] Mode badge visibly shows Live
- [ ] No silent fallback to Mock occurred

Until every acceptance item above is completed by a human with a least-privilege key, release status remains **LIVE UNVERIFIED**. The official specification check does not replace an authenticated connection test.

Before the first potentially billable provisioning, obtain explicit human approval for the region, image, flavor, backend estimate, wallet state, requested operation, and cleanup plan. Never create a paid server automatically.

To rotate, submit a new key. To revoke, use the explicit clear action and confirm the provisioning impact. A blank value preserves the current key.
