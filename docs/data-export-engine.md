# Outbound data-export engine

Configurable monthly feeds of loan-book data to credit bureaux (SACRRA route)
and partners. Built in response to the loan agreement's promise of monthly
bureau reporting, which the system could not previously keep.

## What Phase 1 delivers (this branch)

| Piece | Detail |
|---|---|
| `reporting_account_snapshots` | Flat per-loan row per `(period, generation)`. Every figure — DPD, arrears, payment profile, status — is computed **as at period end** by `AccountSnapshotService`. Payments net off oldest-first (a near-current partial payer is not reported as deeply delinquent). `reversed` / never-disbursed loans excluded; written-off loans frozen at the write-off date. A rebuild is a **new immutable generation** once an export run has consumed the prior one. |
| `keystone:build-account-snapshots {period?}` | Self-gated on `DATA_EXPORTS_SNAPSHOTS_ENABLED`. Scheduled 27th 23:00. |
| Recipients / profiles / runs | Admin UI under **IT & System → Data Exports** (`role:admin,it_admin`; creating a *recipient* is `role:admin`). A profile picks ordered columns + presentational format from the dataset whitelist — no expressions. `export_runs` freezes the resolved config (`profile_snapshot`), row count, `file_sha256`, transport result. |
| Column safety | `DatasetRegistry` tags each column `pii` / `internal`. Internal columns never reach an external recipient; PII columns only a `kind = bureau` recipient. Enforced at profile save **and** in the Alpine column picker. |
| Transports | `download` (file kept for manual pull) and `email` (refuses bureau recipients, any PII/internal column, any non-allowlisted destination domain — `DATA_EXPORTS_EMAIL_DOMAINS`). |
| Dispute hold | `credit_report_disputes` register (NCA s.72 / NCR Reg 18, 20-business-day `respond_by`). Open disputes set `under_dispute` on the snapshot and are **held back from real runs** (still visible in Preview). |
| `keystone:run-data-exports` | Per-profile day-of-month selection, catch-up safe (runs on/after the day while no successful run exists). Scheduled daily 06:00. Self-gated on `DATA_EXPORTS_ENABLED`. |
| Feature flags | `DATA_EXPORTS_SNAPSHOTS_ENABLED` and `DATA_EXPORTS_ENABLED`, both **off**. Neither may be switched on in production until the snapshot table and the monthly build are in the POPIA records-of-processing and a retention schedule is agreed. |

## Not built (Phase 2 — blocked on choosing a bureau + signed data-supply agreement + legal sign-off)

1. `SacrraFormatter` / `FixedWidthFormatter` — header/detail/trailer records, the
   SACRRA account-type and payment-status **controlled vocabularies**, the real
   24-month profile encoding. Phase 1 stores raw delinquency buckets and a
   `KL:`-prefixed interim `payment_status_code` so nothing is mistaken for the
   bureau codes. **The field list and every code mapping must be reviewed by the
   `chief-loan-officer` agent against NCA/SACRRA rules before shipping.**
2. `SftpTransport` — `composer require league/flysystem-sftp-v3`; per-recipient
   runtime SFTP disk from the recipient's DB columns + the hybrid
   `credential_env_key` (secret named in `.env`, not stored in the DB); static
   egress IP for the bureau allow-list.
3. `ApiTransport` — HTTPS POST + bearer token.
4. Dispute **resubmit-on-correction** loop and bureau rejection-file ingestion
   into `export_runs`.
5. Structured borrower address — `users.address` is a single unstructured blob;
   SACRRA layouts want line/city/province/postal. Snapshot ships `address_raw`
   (tagged internal) for now.
6. `credit_bureau_score` / `credit_bureau_provider` / `ncr_purpose_code` are still
   never populated (no inbound bureau-enquiry feature) — snapshot emits them as
   null.

## Open items for the business

- Owner for getting `reporting_account_snapshots` + the monthly build into the
  POPIA records-of-processing and setting the retention schedule.
- Compliance sign-off owner for the Phase-2 SACRRA field list + code maps.
- Bureau choice (TransUnion / Experian / XDS / Compuscan) and the signed
  data-supply agreement, which unblocks everything in Phase 2.
