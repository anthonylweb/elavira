# Elavira Release Checklist

This file records the current release-readiness state. It is not a claim that Core is feature-complete.

## Current status

The most recent hardening run completed with:

- 29 Passed
- 1 Failed and fixed
- 18 Blocked by tooling/policy
- 0 Pending

The failed item was corrected. No Blocker/High implementation defect was confirmed in that run.

**Phase 1 production ruleset implementation is complete.** This does not make Elavira Phase 1 release-ready. The remaining hardening, manual accessibility, compatibility, and release-policy work below must still be completed before a final release candidate.

## Completed foundations

- Core single-page rendered browser scanner/session flow
- audit persistence and reports
- finding detail/workflow/history
- XLSX export
- Guided Decisions
- Accessibility Statement
- Learn
- Visitor Accessibility Toolbar
- Dashboard and Settings
- Run Audit initiation UX
- initial release-hardening passes

## Required ruleset work before final RC

Implementation sequence:

1. Completed — Result model + Reviewed — Not an Issue + reviewed-exception persistence/reset + grouping/supersession framework.
2. Completed — Links + Headings/Document Structure.
3. Completed — Forms + Names/Roles/States + ARIA.
4. Completed — Keyboard + Focus.
5. Completed — Color + Contrast.
6. Completed — Images + cross-rule integration + full regression.

The approved rule semantics are in `PHASE1_RULESET.md`.

## Manual/release work still required after ruleset expansion

- True 200% browser zoom across representative Elavira surfaces
- WCAG text-spacing override across Reports/Learn/Statement/Toolbar/Settings
- exhaustive keyboard-only workflows
- focused screen-reader smoke testing
- numerical contrast verification
- Toolbar preference combinations and Reset
- real dynamic-DOM scan fixture
- active scan browser-close/abandon behavior
- live forced database failure if practical
- second representative theme
- named full-page cache plugin and scan-credential caching safety
- XLSX verification in supported spreadsheet applications
- multisite policy and test
- supported WordPress/PHP/browser/AT policy
- uninstall behavior
- distribution channel and project licensing/notices
- final public wording review
- final packaging and checksum
- live schema reconfirmation
- Round 2–6 live browser smoke tests not already completed

## Previous RC artifact

A prior hardening package was created as `dist/elavira-0.1.0-rc.zip`, but because the production ruleset is incomplete it is not the final release candidate.

Do not represent Elavira as release-ready until the ruleset expansion and remaining release decisions/tests are complete.
