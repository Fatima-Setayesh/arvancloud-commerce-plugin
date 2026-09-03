# Security policy

## Supported releases

Security fixes are provided for the latest release on the `main` branch. Older
releases may no longer receive fixes; reproduce the issue against the latest
release before reporting when it is safe to do so.

## Reporting a vulnerability

Use GitHub's private vulnerability reporting for this repository when it is
enabled. Include the affected version, impact, minimal reproduction steps, and
any suggested mitigation.

If private reporting is unavailable, contact the maintainer through the private
contact method shown on their GitHub profile. Do not open a public issue that
contains exploit details, credentials, customer information, logs with secrets,
or live infrastructure identifiers.

Never send an ArvanCloud Machine User API key, WordPress authentication cookie,
REST nonce, encryption material, database dump, or other secret in a report.
Use redacted examples. If a real credential may have been exposed, revoke and
rotate it immediately before continuing the investigation.

Reports concerning authorization boundaries, cross-customer data access,
financial-ledger integrity, idempotency, secret storage, remote-host validation,
or unsafe lifecycle actions are especially relevant to this project.

## Project independence

ArvanCloud Commerce is an independent open-source project. It is not an official
ArvanCloud product and does not claim an endorsed or certified partnership with
ArvanCloud.
