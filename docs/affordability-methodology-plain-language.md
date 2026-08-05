# How We Calculate Affordability

*Plain-language explanation — for partners, investors, consultants, and compliance review. No system/technical knowledge required.*

## The short version

We work out how much someone can safely afford to repay each month, then we only let them borrow up to that amount. We never approve a loan whose instalment is bigger than what our own numbers say the client can afford.

## Step by step

**1. Add up income**
We take the client's net (after-tax) monthly income, plus any other declared income (e.g. a side income), from what they told us on their profile.

> Total income = net monthly income + other income

**2. Add up expenses**
We take five declared expense categories:
- Housing (rent/bond)
- Transport
- Existing debt repayments
- Insurance
- Living expenses (food, utilities, etc.)

> Total expenses = housing + transport + existing debt + insurance + living

**3. Work out disposable income**
This is what's left over every month after the client covers their basics and existing debt.

> Disposable income = total income − total expenses

**4. Apply our affordability ratio**
We don't lend against 100% of what's left over — we only lend against a portion of it, currently **30%**. This is a safety margin: it means the client keeps 70% of their disposable income free for the unexpected, and it's a setting we can tighten or loosen deliberately (it's a configurable business rule, not a fixed number baked into the system).

> Maximum instalment we'll approve = disposable income × 30%

**5. The pass/fail rule**
- If disposable income is zero or negative, the client doesn't qualify — full stop.
- Otherwise, we compare the instalment a loan would require against that maximum. If it's higher, the loan (or that amount/term combination) is declined or reduced.

## Worked example

| | Amount |
|---|---|
| Net monthly income | R12,000 |
| Other income | R0 |
| **Total income** | **R12,000** |
| Housing | R3,000 |
| Transport | R1,200 |
| Existing debt | R1,500 |
| Insurance | R400 |
| Living expenses | R2,500 |
| **Total expenses** | **R8,600** |
| **Disposable income** | **R3,400** |
| **Maximum instalment we'll approve (30%)** | **R1,020/month** |

If the loan this client is applying for would require a monthly instalment above R1,020, it doesn't get approved as requested — either the amount or the term has to change until it does.

## What has to be on file before we even calculate this

Before a client can apply, our checks require:
- Personal details and a linked bank account
- Declared income and expenses (the numbers above)
- ID document, latest payslip, and a recent bank statement uploaded

For our standard product, those documents need to be *uploaded* before applying; a staff member then verifies them as part of reviewing the application — an application with unverified documents is automatically routed to manual review rather than approved outright.

For our **extended-term** product, two extra rules apply on top of everything above:
- The client must have at least 6 months in their current job.
- The client must have no overdue payments on any existing loan with us.

## Why this matters for SACRRA / investors / the lawyer

- This is a documented, repeatable, rule-based calculation — every application is assessed the same way, and the rule itself (the 30% ratio, the expense categories, the document requirements) is something we control and can show, not a black box.
- Nothing here overrides affordability for a "better" client — the hard gate applies to every applicant.
- The 30% ratio, and the exact document/verification requirements, are the specific numbers worth benchmarking against competitors and against SACRRA/NCA guidance — flagging that as the open item, not something this document resolves on its own.

*This document describes what the system actually does today. It is not a legal or regulatory compliance certification — that assessment needs the lawyer's sign-off.*
