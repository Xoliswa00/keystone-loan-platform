# SACRRA Integration — Requirements List

*Plain-language explanation — for partners, investors, consultants, and compliance review. No system/technical knowledge required.*

## Where we stand today

We do **not** have a working SACRRA integration. What exists right now:

- Our loan agreement already tells every client: *"Your repayment behaviour will be reported monthly to registered credit bureaux."* That's a promise we've made but can't yet keep — nothing in the system sends that data anywhere.
- We have two empty fields on file for each client (a credit score and a bureau name), but nothing ever fills them in. No live bureau check, no reporting feed.
- Our only related output is a **quarterly** file we already build for the NCR (the regulator) — but that's a different report, for a different purpose, on a different schedule than what SACRRA needs.

So this is a from-scratch build, not a tweak to something existing. Below is everything we need to line up before it can go live.

## 1. Business / legal prerequisites (needed before any code work is useful)

- [ ] **Confirm our NCR registration is active and in good standing.** SACRRA reporting assumes you're a registered credit provider already.
- [ ] **Decide which credit bureau we submit through.** In practice, lenders don't send data straight to SACRRA — SACRRA aggregates data that flows through a bureau (TransUnion, Experian, XDS, or Compuscan). We need a signed data-supply agreement with one of them before we can get the exact file spec.
- [ ] **Get the current file-format spec from that bureau.** The format changes over time and each bureau administers it slightly differently — we cannot build against an assumed format.
- [ ] **Confirm submission frequency and deadlines** (industry practice is monthly, but the bureau agreement will state exact cut-off dates and penalties for late/missed submissions).
- [ ] **Legal/compliance sign-off on data-sharing** — the lawyer needs to confirm our POPIA basis for reporting client data covers this specific use, not just the general consent wording already in the agreement.
- [ ] **Dispute-handling process defined** — the NCA requires a documented way for clients to dispute inaccurate information we've reported, get it corrected, and have the correction resubmitted. This needs to be a real business process, not just a technical resubmission button.

## 2. Data we need to be able to produce, per client, per loan

Once the format is confirmed, expect it to require (this list is the industry-standard shape, to be confirmed against the actual bureau spec):

- [ ] Client identifying details (ID number, name — we already hold these)
- [ ] Loan/account number
- [ ] Account open date and NCA credit type (we already capture this)
- [ ] Original loan amount and term
- [ ] Current outstanding balance
- [ ] Arrears amount, if any, and number of months in arrears
- [ ] Payment/instalment history for the period (a standard behaviour code, not free text)
- [ ] Account status: open, settled, written off, handed over for collection
- [ ] A flag for any account currently under formal dispute

Some of this (NCA credit type, balances) we already store. Arrears history and a standard behaviour code are the pieces most likely to need new tracking.

## 3. Technical / system requirements

- [ ] A **monthly extract job** (similar in spirit to our existing quarterly NCR export, but a new build — different data, different cadence, different format)
- [ ] A **file builder** that outputs the bureau's exact required format (fixed-width or CSV, whichever they specify) with field-level validation before anything is sent
- [ ] A **secure transmission method** to the bureau — typically SFTP or a submission portal, using credentials issued under the data-supply agreement
- [ ] A **submission log** — proof of what was sent, when, and confirmation it was accepted, for our own compliance evidence
- [ ] A **rejection-handling step** — bureaus validate submissions and reject bad records; we need a way to see what failed and why, fix it, and resubmit
- [ ] A decision on whether we also want a **live bureau enquiry** integration (checking a client's credit score/report during the application process) — this is a separate, optional feature from reporting outbound, and would be the thing that actually populates the credit-score fields we already have sitting empty

## 4. Process / ownership

- [ ] Someone named as responsible for the monthly submission actually happening (a person, not just a scheduled job — job failures need a human backstop)
- [ ] A review step before submission for the first few cycles, to catch format/mapping problems before they become bureau penalties
- [ ] An internal audit trail so the lawyer/investors can be shown, on request, that reporting has been happening as promised in the agreement

## Why this matters for SACRRA / investors / the lawyer

- Right now there's a gap between what our loan agreement promises clients and what the system actually does — that's worth flagging as a priority, not just a nice-to-have feature.
- The technical build (extract job, file format, transmission) is the easy part once the business prerequisites in Section 1 are settled. Those prerequisites — bureau agreement, confirmed format, legal sign-off, dispute process — are the actual blockers and are not something engineering can resolve alone.
- Nothing here should be read as legal or regulatory advice — Section 1's sign-offs need the lawyer, and the bureau relationship needs whoever owns that commercial negotiation.

*This document describes what the system does and doesn't do today, and what's needed to close the gap. It is not a legal or regulatory compliance certification.*
