# Security audit: System settings (own SMTP server, own OpenAI key) and the switch to OpenAI

- **Date:** 2026-10-04
- **Branch:** `feature/system-settings`
- **Scope:**
  - `GET` and `PATCH /api/v1/customer/{customer_id}/system-settings`
  - `POST /api/v1/customer/{customer_id}/system-settings/smtp-check`
  - The optional `customer_id` query parameter on `GET /api/v1/transcription/token`
  - The `customer_system_settings` table and the `SecretBox` (libsodium) encryption
  - Emails sent through an account's own SMTP server
  - The OpenAI Responses API adapter, which replaces Anthropic, and the removed `anthropic-ai/sdk`
  - The new environment variables `SETTINGS_ENCRYPTION_KEY`, `SMTP_ALLOW_PRIVATE_HOSTS` and `LLM_MODEL`
- **Tools:**
  - `composer audit`: no advisories.
  - A grep of the diff for `sk-…` keys and private keys found nothing.
  - The functional tests in `tests/Functional/Api/Identity/SystemSettingsTest.php`.

## Checklist

| Area | Result |
|---|---|
| **Access and tenancy** | Checked, nothing found. All three routes need a console user (401 without one). Another account's id answers 404 `CUSTOMER_NOT_FOUND` (tested for GET, PATCH and smtp-check). Changing and checking need the admin groups (403 for read-only users, tested). |
| **Secrets at rest** | Checked, nothing found. The SMTP password and the OpenAI key are sealed with XSalsa20-Poly1305, with a random nonce per value and a key derived from `SETTINGS_ENCRYPTION_KEY`. A test reads the raw column and finds no clear text. A tampered value or a different key is refused (unit tests). |
| **Secrets in responses** | Checked, nothing found. The output has only `smtp_password_set`, `openai_api_key_set` and the key's last 4 characters (tested). Validation errors list field names, never values. |
| **Secrets in logs and queues** | Checked, nothing found. The commands that carry clear values (`ChangeSystemSettings`, `CheckSmtpServer`) run on the synchronous command bus and are never serialized to the queue; their fields are `#[\SensitiveParameter]`. A secret that can't be opened is logged with the account id only. OpenAI error messages pass through a filter that masks `sk-…` (unit test). |
| **SSRF through the server check** | Finding F1, fixed. |
| **Abuse of the check as a mail relay** | Checked, nothing found. The test email always goes to the user who pressed Validate, never to an address in the request. |
| **Injection** | Checked, nothing found. The host must match a hostname pattern (no scheme, path or spaces), the port is an integer from 1 to 65535, the encryption is an enum, and the sender must be an email. Every query is a Doctrine query with parameters. The test email's template escapes host and port (Twig autoescape). |
| **Prompt injection** | Not applicable: no prompt content changed. Uploaded files sent to OpenAI are wrapped as `<document>` data, as before. |
| **Dependencies** | Checked, nothing found. One package was removed and none were added; `composer audit` is clean. |
| **Key management** | Finding F2, open. |
| **Transcription token** | Checked, nothing found. `customer_id` is optional and validated against `[A-Za-z0-9]{1,16}`; anything else falls back to the platform key. The endpoint stays public and rate limited, as before: anyone can already get a token on the platform key. Passing an account's id spends that account's key in the same way. |

## Findings

### F1: The SMTP check could reach internal hosts (SSRF). High. Fixed.

**Risk.** The check opens a TCP connection to a host and port the caller names. Without limits, a Customer-Admin
could probe the platform's internal network (`mysql:3306`, the metadata service on `169.254.169.254`) and read
whatever banner came back.

**Fixes:**

- `EsmtpTransports` resolves the host and refuses any private, loopback or reserved address. The failure reason is
  `blocked`. Only dev turns this off, through `SMTP_ALLOW_PRIVATE_HOSTS=1` in `.env.dev`, so Mailpit can be used.
- The answer only says where the check failed: `connection`, `tls`, `authentication`, `blocked` or `refused`. The
  server's own text is never returned (tested with an "internal-banner" message).
- Rate limit: 10 checks per account every 10 minutes.
- A 10-second timeout per connection.

**Residual risk.** DNS rebinding: the host is resolved once for the check and again by the socket. An attacker who
controls a DNS name could switch it between the two. That still only gets one SMTP-shaped connection, never a
reply, under the same rate limit. It's recorded under Known gaps.

### F2: `SETTINGS_ENCRYPTION_KEY` can't be rotated. Medium. Open.

**Risk.** Values sealed with the old key can't be opened after the key changes. The adapters log the failure and
fall back to the platform server and key, so nothing breaks, but every account has to re-enter its secrets.

**Next step.** Add a rotation command that re-seals every value with the new key, keeping both keys during the
change. This is recorded in the README's Known gaps.

## Outcome

There are no open Critical or High findings. F1 is fixed with tests. F2 is documented.
