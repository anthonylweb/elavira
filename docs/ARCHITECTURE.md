# Elavira Architecture

Status: current Core architecture and approved extension boundaries.

## 1. Core execution model

Core performs one rendered public-page audit at a time in the authorized user's browser.

```text
Public canonical WordPress page
        ↓
Elavira browser scanner JavaScript
        ↓
Rendered DOM, including relevant client-side changes
        ↓
Bounded structured evidence
        ↓
Authorized WordPress REST submission
        ↓
Server validation + normalization + rule resolution
        ↓
Grouping / supersession / reviewed-exception matching
        ↓
Persist audit + findings + occurrences + history
        ↓
Reports / recommendations / Guided Decisions / Statement recommendations
```

Core does not require Playwright, Chromium on the WordPress host, a remote scanning service, or a persistent site-wide queue.

## 2. Authority boundaries

### Browser scanner owns

- Rendered-DOM observation.
- Stable rule ID and scanner-contract version emitted with evidence.
- Outcome/category data allowed by the evidence contract.
- Target/element reference needed to explain the observation.
- Minimum bounded evidence required by the rule.

### WordPress/domain layer owns

- Authorization and scan-session integrity.
- Canonical page identity.
- Rule registry and exact historical versions.
- WCAG mappings.
- Priority.
- Classification and editorial meaning.
- Explanations and recommendations.
- WordPress guidance and developer guidance.
- Guided Decision relationships.
- Grouping and supersession.
- Reviewed-exception identity and matching.
- Finding workflow/history.
- Reports, XLSX, and Statement recommendations.

The browser must not author trusted grouping, suppression, WCAG, priority, recommendation, or conformance semantics.

## 3. Current persistence

Current Core data includes:

- audits
- audit_urls
- findings
- occurrences
- finding_status_history
- scan_sessions
- scan_batches
- scan_observations (validated normalized observations staged until explicit page completion)
- Guided Decision completion/history data
- Accessibility Statement data/recommendations/revisions
- bounded Core/Toolbar settings

Round 1 may add the minimum schema needed for reviewed exceptions and any explicit metadata required to represent the expanded result model or deterministic supersession. Migrations must be idempotent and backward compatible.

## 4. Finding lifecycle

Scanner result type and finding workflow are separate.

Rule results:

- Pass
- Automatic Failure
- Potential Issue
- Human Review
- Not Evaluated

Finding workflow:

- Open
- Addressed
- Verified
- Accepted Limitation
- Needs Review
- Reviewed — Not an Issue

Verified remains system/subsequent-audit authoritative; it is not a manual “done” checkbox.

## 5. Reviewed-exception layer

A reviewed exception is a persistent human decision that the same materially unchanged review condition should not continue appearing as an active issue.

It is not deletion, remediation, conformance, or acceptance of a known barrier.

Minimum identity:

```text
rule ID
+ rule version
+ canonical page
+ stable target fingerprint
+ material evidence fingerprint
```

The exact implementation must use trusted normalized persisted evidence and must not store sensitive page content merely to create a fingerprint.

A selector-only key is prohibited.

Matched exceptions are auditable and reversible. A material identity change causes reevaluation.

## 6. Reset behavior

Reset Reviewed Exceptions deletes/deactivates only the future-suppression layer.

It must not delete:

- audits
- audit URLs
- findings
- occurrences
- status history
- Guided Decision history
- reports
- Statement history

Core supports page-scoped and global reset.

## 7. Cross-rule grouping and supersession

The server may resolve overlapping rule observations into one primary user-facing finding plus diagnostic evidence.

The framework must support deterministic precedence without hard-coding every future rule into persistence code.

Round 1 establishes the generic mechanism. Later ruleset rounds define actual precedence relationships as rules are added.

## 8. Premium boundary

Premium later adds:

- selected URL/content-type batch audits
- site-wide audits
- remote browser workers
- persistent/resumable site-wide queue
- retries/backoff/failure isolation/pause/resume/cancel/concurrency controls
- managed URL inventory and advanced URL/content rules
- custom audit names
- retention controls
- developer diagnostics
- permission management

The remote worker remains an execution layer. WordPress owns Elavira product semantics.

No Premium site-wide/remote implementation belongs in Round 1.

## 9. Phase 1 ruleset Round 2

Round 2 adds the production Links & Navigation and Headings & Document Structure
browser checks on the Round 1 result, reviewed-exception, and supersession
foundation. Current browser contracts are represented by ruleset snapshot
`core-rules-1.2.1` and scanner snapshot `core-browser-1.1.1`.

Three representative IDs remain stable to avoid duplicate historical findings:

- `elavira.link.purpose_review` version `2.0.0` implements the conservative
  ambiguous-link-name check requested as `elavira.links.ambiguous_purpose`.
- `elavira.document.language_missing` version `2.0.0` implements the missing
  document-language check requested as `elavira.document.missing_language`.
- `elavira.document.invalid_language` version `2.0.0` validates the declared
  primary subtag against the bundled IANA Language Subtag Registry snapshot;
  version `1.0.0` remains resolvable for historical syntax-only findings.
- `elavira.links.identical_name_different_destination` version `2.0.0` adds a
  120-character accessible-name excerpt to the hashed, destination-minimized
  occurrence evidence; version `1.0.0` remains historically resolvable.
- `elavira.heading.structure_review` version `2.0.0` implements the bounded
  contextual check requested as `elavira.structure.heading_semantics_review`.

Their version `1.0.0` definitions remain registered for historical report and
Guided Decision resolution. New scan sessions select the later registered exact
version. The Heading Structure and Link Purpose Guided Decisions list each
compatible rule/version explicitly; compatibility remains exact rather than
range-based.

The contextual heading review uses the bounded absence of any visible semantic
heading, or a semantic `main` region with no visible headings while headings exist
elsewhere, as its triggers. It does not fire on every page. When a heading-free
document also produces the more specific Missing H1 Structural Warning, Missing
H1 supersedes the contextual observation and retains it as diagnostic evidence.
Missing and multiple H1 results use the server-owned `structural_warning`
presentation while remaining Human Review results, separate from Automatic
Failure totals.

No persistence migration is required for Round 2. All Potential Issue and Human
Review occurrences use the existing exact reviewed-exception fingerprints.
Rounds 3–6 are documented below.

## 10. Phase 1 ruleset Round 3

Round 3 adds Forms & Controls, Names/Roles/States, and ARIA Usage under ruleset
snapshot `core-rules-1.3.0`, browser snapshot `core-browser-1.2.0`, and editorial
snapshot `core-editorial-1.2.0`. The historical
`elavira.form.accessible_name_missing` version `1.0.0` remains resolvable; version
`2.0.0` is the current contract and distinguishes programmatic naming from
visible-label and instruction review.

The bundled `assets/js/aria-vocabulary.js` is a deliberately bounded WAI-ARIA
1.2 vocabulary based on the W3C Recommendation dated 2023-06-06. It covers the
roles, attributes, deterministic value grammars, ID-reference forms, naming
prohibitions, and required role properties used by the Round 3 rules. It does not
claim to reproduce a browser accessibility tree or test dynamic widget behavior.
Invalid syntax is normally a Potential Issue; only the separate missing-name and
required-state consequence rules are Automatic Failures.

WCAG mappings are optional for explicitly unmapped best-practice observations.
Reports describe these as having no direct WCAG mapping, exports retain blank
WCAG fields, Learn presents them as education, and Statement generation excludes
any observation whose stored WCAG criterion is empty. No artificial conformance
association is created.

Schema `0.7.0` adds `elavira_scan_observations`. Each accepted batch is validated
and normalized before its observations and idempotent batch receipt are stored in
one transaction. Partial batches never create active findings. Explicit completion
locks the session, loads the full staged page result set, applies deterministic
server-owned supersession, matches reviewed exceptions, and persists findings,
occurrences, diagnostics, and terminal state in one transaction. Successful
completion or explicit failure removes staging and batch receipts. Interrupted
expired staging is removed in bounded groups during later batch activity. A
failed finalization rolls back and can be retried without inflating counts.

Primary consequence relationships are registry-declared. The current Forms
missing-name rule supersedes generic semantic missing-name and broken label/ARIA
reference observations on the same cross-rule target. Required-state absence
supersedes missing-required-attribute or invalid-value causes. Diagnostics remain
attached to the primary occurrence and appear in Reports and XLSX exports.

Round 3 evidence is restricted to bounded selectors, element/control/role and
attribute categories, enumerated reasons, booleans/counts, and existing trusted
fingerprints. Scanner evidence does not include current/default form values,
serialized forms, arbitrary page text, event handlers, or complete destinations.

Later rounds are documented below.

## 11. Phase 1 ruleset Round 4

Round 4 adds nine Human Review rules for Keyboard Accessibility and Focus under
ruleset snapshot `core-rules-1.4.0`, browser snapshot `core-browser-1.3.0`, and
editorial snapshot `core-editorial-1.3.0`. Static/rendered-DOM observations never
assert keyboard inoperability, a keyboard trap, an incorrect focus order, an
invisible focus indicator, obscured focus, or incorrect dialog focus management.

Conservative triggers cover positive tabindex values; non-native interactive
roles; non-native controls lacking an ordinary tab stop; application-role regions
with multiple focusable descendants; bounded composite/specialized ARIA widgets;
author focus rules that remove outlines without a detectable alternative in the
same rule; substantial elevated fixed/sticky viewport-boundary overlays; and open
native or explicitly modal ARIA dialogs. Class names, cursor styling, arbitrary
visual appearance, and the mere presence of sticky/fixed positioning do not
trigger findings.

Server-declared supersession makes positive tabindex primary over its generic
focus-order diagnostic, possible-unreachable primary over generic non-native and
custom-widget observations, and modal-management review primary over generic
trap/non-native/widget observations on the same target. Separate semantic failures
from Round 3 are not collapsed into behavioral reviews. All observations continue
through durable page staging and complete-page finalization before reviewed-
exception matching and persistence. No schema migration is required.

Eight behavior-oriented rules bind by exact version to the existing Keyboard Interaction Guided
Decision and link to the existing Keyboard Accessibility or Focus and Focus Order
Learn topic. The topical-only obscured-focus risk does not bind to that decision because
the decision cannot determine overlap at the observed target. Reports/XLSX use
the existing Human Review classification.

## 12. Phase 1 ruleset Round 5

Round 5 adds five Color and Contrast rules under ruleset snapshot
`core-rules-1.5.0`, browser snapshot `core-browser-1.4.0`, and editorial snapshot
`core-editorial-1.4.0`. Normal and large text failures are emitted only when the
current rendered foreground and bounded ancestor background stack can be reduced
to effective opaque sRGB colors. Supported alpha foregrounds are composited before
submission. Gradients, images, opacity, filters/blending, interfering pseudo-
elements, and unresolved or overly complex layering become Human Review rather
than guessed failures.

The server recomputes WCAG relative luminance from normalized effective colors,
derives the trusted 4.5:1 or 3:1 threshold from the exact rule, and validates the
normal/large classification from bounded font metrics. A deliberately narrow
non-text check covers custom-rendered checkbox/radio boundaries only when their
current-state geometry and colors are determinate; other non-text cases remain
Human Review. Alternate states are not claimed as evaluated.

Color-only communication is a separate Human Review observation for an explicitly
invalid control lacking a referenced description or error message. Its exact rule
version binds to the existing Use of Color Guided Decision; determinate contrast
and indeterminate contrast rules do not. All observations use the existing durable
complete-page staging, supersession, reviewed-exception, Reports/XLSX, Learn, and
Statement paths. No Round 5 schema migration is required. Statement
recommendations remain subject to the existing explicit decision and acceptance
workflow.

## 13. Phase 1 ruleset Round 6

Round 6 completes the production ruleset implementation under ruleset snapshot
`core-rules-1.6.0`, browser snapshot `core-browser-1.5.0`, and editorial snapshot
`core-editorial-1.5.0`. The established `elavira.image.alt_missing` version
`1.0.0` remains unchanged. Three version `1.0.0` review contracts add contextual
alternative review, conservative filename-style detection, and an extended,
multi-sentence alternative heuristic.

Image evidence contains only a 120-character sanitized excerpt, an eight-character
change fingerprint, enumerated semantic context/reason data, and bounded counts
where required. It contains no image bytes, full source URL, surrounding page
content, HTML, form values, or browser-authored product metadata. The server
validates filename patterns, review-context combinations, fingerprints, and long-
alternative bounds before existing durable staging.

Filename and extended-alt findings supersede a same-target general quality review
at complete-page finalization and retain it as diagnostic evidence. Objective
missing-alt and accessible-name findings remain distinct when they establish
different element requirements. Empty `alt=""` is not treated as missing or as a
quality problem. All three review rules use the existing reviewed-exception
identity and exact Alternative Text Guided Decision and Images Learn relationships.
No schema migration is required.

The final consistency review enumerated all 52 registered definitions, including
intentional historical versions; verified allowed outcomes/priorities and mapping
structures; confirmed the supersession graph is acyclic; resolved exact Guided
Decision and Learn relationships; and retained existing Reports, XLSX, reviewed-
exception, Statement, staging, and qualified audit-coverage behavior.

**Phase 1 production ruleset implementation is complete.** This is not a release-
readiness statement; the remaining manual and compatibility work is tracked in
the release checklist.
