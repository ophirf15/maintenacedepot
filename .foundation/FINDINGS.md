# Foundation findings — maintenance-depot

This file is written by Foundation after `validate_project`. It is the briefing for this repository's Cursor agent.

Generated: 2026-08-06T08:26:46.259Z
Run: `run.validate-1786004806060`
Run status: **manual_verification_required**
Open items: 11 (7 agent-actionable, 4 human-gated)

## How to use this (Cursor)

Ask: **"review Foundation findings and propose fixes"** or **"work the open Foundation findings"**.

1. Read this file and `.foundation/FLAGS.md` (high/critical interrupt list).
2. Prefer `fix_in_repo` and `add_tests_or_evidence` items first.
3. Propose concrete patches; do not invent secrets; do not commit unless asked.
4. Skip or summarize `needs_human` items — do not fake host access or legal sign-off.
5. After fixes, ask the operator to re-run `/foundation-validate` (or Foundation MCP `validate_project`).

## Remediation order

| Priority | Finding | Action | Where |
| --- | --- | --- | --- |
| high | `find.deploy.backup.blocked` | Confirm backup step before apply/pull on the target host |  |
| high | `find.privacy.compliance.manual` | Human review retention, PII inventory, and legal_regulatory flags; technical findings ≠ legal conclusions |  |
| high | `find.privacy.logging.sensitive` | Confirm audit events for auth failures and admin actions; attest scrubbing in the production log pipeline |  |
| medium | `find.a11y.wcag.manual` | Complete accessibility checklist against design.default and record attestation |  |
| medium | `find.a11y.wcag.static-subset` | Run keyboard, focus, contrast, and screen-reader manual checks; do not claim automated a11y complete |  |
| medium | `find.data.index-pagination.manual` | Review hot-path queries for missing indexes and unbounded list endpoints |  |
| medium | `find.deploy.rollback.manual` | Document and rehearse rollback for the active deployment profile; attach attestation evidence |  |

## Add tests or evidence

### HIGH · `find.data.migrations.existence-not-pass`

- Status: `not_tested`
- Agent: `foundation.data`
- Cursor action: **Add or run tests / record evidence, then re-validate**

**What was found.** Schema/migration artifacts present (database/migrations) but migrate/rollback was not executed

**Suggested fix.** Execute migrate on a disposable DB and document rollback; file presence is not a pass

### MEDIUM · `find.module.deploy-github-updater.val-deploy-github-updater-pack-excludes`

- Status: `not_tested`
- Agent: `foundation.deployment`
- Module: `deploy.github-updater`
- Cursor action: **Add or run tests / record evidence, then re-validate**

**What was found.** Module validation val.deploy.github-updater.pack-excludes listed but not executed in this run

**Suggested fix.** Execute module validation definition when environment allows

### MEDIUM · `find.module.deploy-github-updater.val-deploy-github-updater-preserve-env`

- Status: `not_tested`
- Agent: `foundation.deployment`
- Module: `deploy.github-updater`
- Cursor action: **Add or run tests / record evidence, then re-validate**

**What was found.** Module validation val.deploy.github-updater.preserve-env listed but not executed in this run

**Suggested fix.** Execute module validation definition when environment allows

### MEDIUM · `find.module.deploy-github-updater.val-deploy-shared-hosting-smoke`

- Status: `not_tested`
- Agent: `foundation.deployment`
- Module: `deploy.github-updater`
- Cursor action: **Add or run tests / record evidence, then re-validate**

**What was found.** Module validation val.deploy.shared-hosting.smoke listed but not executed in this run

**Suggested fix.** Execute module validation definition when environment allows

## Review then attest

### HIGH · `find.privacy.logging.sensitive`

- Status: `manual_verification_required`
- Agent: `foundation.privacy`
- Standard: `std.logging.no-sensitive-fields`
- Cursor action: **Do the reviewable work, then attest in foundation.project.yaml**

**What was found.** Static scan of 80 file(s) found no password/token logging; audit-event coverage still needs human review

**Why it matters.** Credentials written to logs spread to log shippers, backups, and support tickets, where they are rarely rotated.

**Suggested fix.** Confirm audit events for auth failures and admin actions; attest scrubbing in the production log pipeline

**Scope.** Line-level scan of up to 80 repository source files; log pipeline configuration and runtime output were not inspected.

### MEDIUM · `find.data.index-pagination.manual`

- Status: `manual_verification_required`
- Agent: `foundation.data`
- Cursor action: **Do the reviewable work, then attest in foundation.project.yaml**

**What was found.** Index and pagination review requires query/plan inspection

**Suggested fix.** Review hot-path queries for missing indexes and unbounded list endpoints

### MEDIUM · `find.deploy.rollback.manual`

- Status: `manual_verification_required`
- Agent: `foundation.deployment`
- Standard: `std.deploy.rehearsed-rollback`
- Cursor action: **Do the reviewable work, then attest in foundation.project.yaml**

**What was found.** Deploy signals found (.github/workflows); rollback rehearsal not verified in this run

**Suggested fix.** Document and rehearse rollback for the active deployment profile; attach attestation evidence

## Needs a human

### HIGH · `find.deploy.backup.blocked`

- Status: `manual_verification_required`
- Agent: `foundation.deployment`
- Cursor action: **Needs a human (host access, legal judgment, or full WCAG review)**

**What was found.** Pre-update backup verification not executed (blocked without host access)

**Suggested fix.** Confirm backup step before apply/pull on the target host

### HIGH · `find.privacy.compliance.manual`

- Status: `manual_verification_required`
- Agent: `foundation.privacy`
- Cursor action: **Needs a human (host access, legal judgment, or full WCAG review)**

**What was found.** No privacy policy doc found in common paths; Foundation does not provide legal advice

**Suggested fix.** Human review retention, PII inventory, and legal_regulatory flags; technical findings ≠ legal conclusions

### MEDIUM · `find.a11y.wcag.manual`

- Status: `manual_verification_required`
- Agent: `foundation.accessibility`
- Cursor action: **Needs a human (host access, legal judgment, or full WCAG review)**

**What was found.** Full WCAG 2.2 AA review requires human judgment (focus order, ARIA, contrast, forms)

**Suggested fix.** Complete accessibility checklist against design.default and record attestation

### MEDIUM · `find.a11y.wcag.static-subset`

- Status: `manual_verification_required`
- Agent: `foundation.accessibility`
- Cursor action: **Needs a human (host access, legal judgment, or full WCAG review)**

**What was found.** Static img-alt scan clean; WCAG 2.2 AA is NOT fully assured by automation

**Suggested fix.** Run keyboard, focus, contrast, and screen-reader manual checks; do not claim automated a11y complete

---

Machine-readable twin: `.foundation/findings.yaml`. Critical interrupts also land in `.foundation/FLAGS.md`.
