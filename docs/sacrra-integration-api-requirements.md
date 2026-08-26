# SACRRA Integration — API Connection Requirements

*Technical addendum to `sacrra-integration-requirements.md`. Audience: engineering. Assumes the business prerequisites in that document (bureau agreement, confirmed format) are in progress or done — this document is what we need answered/provisioned specifically to build and operate the connection.*

## Important framing

We don't connect to "SACRRA" as an API. SACRRA is a data-aggregation body; lenders submit through a bureau (TransUnion, Experian, XDS, or Compuscan), and that bureau's integration surface is what we actually build against. Every item below is really "what we need from the chosen bureau," not a fixed spec — none of it can be finalized until Section 1 of the requirements doc (which bureau, signed agreement) is settled. Treat this as the checklist to work through with the bureau's integration/onboarding team once that's chosen.

## 1. Connection method — needs confirming per bureau

Bureaus vary here and some support more than one; we need to know which applies to us:

- [ ] **Batch file transfer (most common for SACRRA reporting)** — SFTP/managed file transfer of a fixed-format or CSV file on a schedule, rather than a live API call. If this is the method, "API connection" mostly becomes SFTP credential/host management, not request/response design.
- [ ] **REST/SOAP API** — used more for real-time bureau *enquiries* (pulling a score/report) than for monthly reporting submission, but some bureaus now offer an API endpoint for batch upload too. Need to confirm if this exists for reporting, or only for enquiries.
- [ ] If both exist, confirm which one is contractually required for SACRRA reporting vs. which is optional (e.g., enquiry API is a separate paid product from reporting).

## 2. Environments & credentials

- [ ] Sandbox/UAT environment access, separate from production, with its own credentials and endpoint/host
- [ ] Test data or a test-file acceptance process — confirm whether the bureau validates test submissions and gives pass/fail feedback, or just accepts anything in UAT
- [ ] Production credentials issuance process and lead time (these are often manually provisioned by the bureau after the data-supply agreement is signed — build in lead time here, it's not self-service)
- [ ] Credential type: API key, OAuth2 client credentials, mutual TLS (client certificate), or SFTP key pair/username-password — confirm which, since it changes how we store secrets
- [ ] Credential rotation policy and expiry — who owns rotating these, and does it require re-provisioning through the bureau or is it self-service on our side

## 3. Network / transport requirements

- [ ] Static outbound IP(s) to whitelist on our side, if the bureau requires IP allowlisting (common for SFTP and for financial-sector APIs) — confirm our infra can offer a stable egress IP
- [ ] TLS version requirements (most now require TLS 1.2+; confirm minimum)
- [ ] Whether client-certificate (mutual TLS) is required, and if so, certificate issuance/renewal process and expiry monitoring
- [ ] Firewall/VPN requirements — some bureau connections require a dedicated line or VPN tunnel rather than public internet

## 4. Request/response contract (if a true API, not file transfer)

- [ ] Full endpoint list: submission endpoint, status/acknowledgement endpoint, and enquiry endpoint if used
- [ ] Request schema per record (field names, types, required vs optional, max lengths) — this should map directly to the data fields listed in the requirements doc (Section 2)
- [ ] Response schema: synchronous accept/reject per record, or async batch processing (submit now, poll or get a callback later for results)
- [ ] Idempotency handling — what happens if we submit the same record twice (retry after a timeout, e.g.); does the bureau dedupe or will it create a duplicate/error
- [ ] Error code reference — what each rejection code means, so we can build meaningful handling instead of guessing
- [ ] Rate limits / throttling — max requests per minute/hour, and whether batch size per call is capped
- [ ] API versioning approach — how the bureau signals breaking changes, and our deprecation notice window

## 5. If it's file-based (SFTP), the file-transfer equivalent of the above

- [ ] Exact file naming convention required
- [ ] Delivery confirmation — does the bureau move/rename the file once picked up, or return a receipt file
- [ ] Result/rejection file format and where it's delivered (a return file dropped in the same SFTP, an email, a portal)
- [ ] Cut-off time and timezone for the file to count toward that submission cycle
- [ ] Retry/resend process if a scheduled drop fails (network issue, file build failure) — is a late file accepted, and until when

## 6. Operational requirements on our side

- [ ] Secrets management — API keys/certificates/SFTP keys go through `.env` + `config/services.php`, the pattern this repo already uses for other third-party credentials (Mailgun, Postmark, AWS), not hardcoded or committed. Note: NuPay is a manual file import/reconciliation process today, not a live API client, so it isn't a precedent for credential handling — `config/services.php` is the actual existing pattern to extend.
- [ ] Logging of every submission attempt (request/file sent, response/result received, timestamp) — this doubles as the compliance audit trail called for in the main requirements doc
- [ ] Alerting on submission failure or missed schedule — a silent failure here becomes a missed regulatory deadline, not just a bug
- [ ] Monitoring for credential/certificate expiry before it causes an outage
- [ ] A documented manual fallback (who to call, what to upload where) if the automated path fails close to a deadline

## Open questions to raise with the bureau's integration team once chosen

1. Is reporting submission via file transfer or API, and is there a sandbox for either?
2. What's the credential provisioning lead time after the data-supply agreement is signed?
3. What are the exact rejection/error codes and where are they documented?
4. Is there a bureau-side status dashboard, or do we need to build our own from response/result files?
5. Who is our named technical contact for integration issues (as opposed to the commercial/account contact)?

*This document will need a second pass once a bureau is chosen — right now it's the list of unknowns to resolve, not a finished spec.*
