# Elavira Architecture

Status: current Core architecture and approved extension boundaries.

## Finding activity and integrated remediation workflow (Audit Report UX Prompt 6)

`finding_status_history` is the authoritative finding activity log as well as the
source of workflow-status history. Each row records the resulting status, bounded
note, actor, timestamp, and optional verification audit. The previous status is
derived from the preceding chronological row; no duplicate previous-status column
or parallel comments table is required.

`UpdateFindingStatus` accepts a same-status request only when it contains a
non-empty activity note. It appends history and emits
`elavira_finding_activity_recorded`, but does not emit
`elavira_finding_status_changed`. An unchanged status with an empty note is a safe
no-op represented by return value `0`. Statement recommendations continue to
listen only to the status-change hook, so note-only activity cannot alter
Statement state. Actual status transitions retain existing behavior and emit both
hooks.

Finding detail derives “Finding created,” “Status changed” with previous →
resulting status, and “Note added” with the retained status. Guided Decision
completions remain in their versioned decision-history table and render nearby as
“Guided Decision activity”; detailed answers remain in a disclosure. Completion
alone never changes status. A prior completion is advertised and can be repeated
against the same exact rule/version contract.

The remediation path remains Detect → Explain → Recommend → User acts → Re-audit
→ Verify. Addressed means work was performed and is not proof of resolution;
Verified remains subsequent-audit-owned. “Resolved” is not a stored or displayed
status. Reviewed — Not an Issue remains distinct from Accepted Limitation.

Report/finding/decision and return links are same-tab workflow navigation.
Finding-to-Learn links and approved external resources preserve the active task in
a new tab with `target="_blank"`, `rel="noopener noreferrer"`, and visible new-tab
wording. Audited canonical pages use the same deliberate pattern. Recorded link
destinations remain escaped plain evidence. Historical editorial gaps show stored
snapshots and available evidence without substituting current guidance; exact
decision compatibility remains independent and may still provide a safe CTA.

## Learn Center (Audit Report UX Prompt 5)

`CoreTopicRegistry` is the server-owned source for 14 immutable educational
topics. Stable topic IDs drive Audit Report, Guided Decision, directory, related-
topic, and WCAG-reference links. `TopicDefinition` validates the common content
contract and supports conceptual directory groups plus optional structured code
examples. Examples are product-authored data and are always escaped by
`LearnPage`; developer prose renders as prose rather than executable markup.

The three Start Here topics are ordered separately. The remaining registry topics
are grouped at render time as Content and media, Structure and navigation, Forms
and interaction, Visual accessibility, and Developer semantics while retaining
their searchable categories. Filters are bounded, read-only, and allowlisted.
Unknown topic IDs continue to produce a safe 404, and Learn requires the normal
authenticated WordPress `read` capability.

Learn mappings remain editorial, exact-contract metadata. Prompt 5 changes only
the current semantics/ARIA rule contracts in `CoreRuleGuidance` to the new
`aria_accessible_controls` topic. Hidden focusable ARIA content intentionally
retains `keyboard_accessibility` because actual focus behavior is the core review.
Older unrelated rule contracts retain their established mappings; no fallback or
version-range mapping is introduced. The current Control Roles and States decision
uses the ARIA and Keyboard topics, and historical decision definitions remain
unchanged in `HistoricalDecisionRegistry`.

Internal Learn links remain same-tab. Report/decision links that preserve an active
workflow and all external W3C references use the established accessible new-tab
pattern: `_blank`, `noopener noreferrer`, and visible purpose text. The directory
and article UI is server-rendered, uses semantic headings/lists/figures, wraps long
content, and contains code overflow at narrow widths.

This layer changes no detection, result semantics, persistence, remediation,
schema, or Core/Premium boundary. It makes no conformance claim and does not bump
the plugin version.

## Guided Decision UX (Audit Report UX Prompt 4)

Guided Decisions remain server-owned, finding-bound decision-support graphs.
`DecisionNavigator` replays signed answers through trusted definitions; the browser
never supplies branch targets or outcomes. `DecisionRegistry` now retains the seven
original `1.0.0` definitions for exact historical lookup while exposing revised
`2.0.0` definitions as the current versions. Three focused new definitions begin at
`1.0.0`. `for_rule()` still requires an explicit rule ID and exact rule version;
no range matching or editorial hint creates compatibility.

The current library contains ten flows. The seven established flows were reviewed
and rewritten in plain language with inspection help, a genuine uncertainty route,
actionable conclusions, and verification guidance. Link Purpose now branches among
text, image-only, and mixed-content links. A single occurrence with a reliable
recorded `link_kind` may pre-answer only that first branch; grouped findings,
historical records, and mixed/unknown content do not. Recorded simple link names
remain excerpts rather than claimed browser accessibility-tree names.

The three new flows are:

- Focus Visibility and Overlays for
  `elavira.focus.possible_hidden_focus@1.0.0` and
  `elavira.focus.obscured_review@1.0.0`.
- Control Roles and States for
  `elavira.semantics.state_consistency_review@1.0.0`,
  `elavira.semantics.native_semantics_overridden@1.0.0`, and
  `elavira.aria.prohibited_attribute@1.0.0`.
- Uncertain Contrast for
  `elavira.contrast.cannot_determine@1.0.0`.

`DecisionPage` loads at most three occurrence records after capability, finding,
audit ownership, and exact compatibility checks. `DecisionContext` presents only
bounded persisted facts through the existing `OccurrencePresenter` plus a small
allowlist of rule evidence. It does not infer a result from missing evidence.
Recorded evidence is labeled separately from examples and is escaped at output.

Completed finding-bound reviews reuse `DecisionRepository`: the stored record
contains decision ID/version, ordered question/answer labels, outcome, combined
interpretation/recommendation/verification copy, actor, timestamp, optional note,
and the user's explicit workflow choice. Saving a decision leaves status unchanged
by default. An authorized user may explicitly choose Open, Addressed, Accepted
Limitation, or Needs Review. Verified remains audit-owned, and Reviewed — Not an
Issue remains the finding workflow that creates a reviewed exception when eligible.
Standalone educational media guidance cannot save or change a finding.

Finding-launched flows provide same-tab links back to the finding and its report,
including their IDs. Deeper Learn links open in a new tab with
`noopener noreferrer` and visible “opens in a new tab” text. Internal workflow
navigation remains same-tab. The page uses native fieldset, legend, radio, button,
label, textarea, heading, navigation, and definition-list semantics. Scoped wrapping
and width rules keep long evidence usable at 320 CSS pixels.

No database migration, scanner-result change, automatic remediation, Premium
boundary change, or plugin-version bump is part of this work.

## Current-rule editorial refinements (Audit Report UX Prompt 3)

`CoreRuleGuidance` supplies intentionally authored copy keyed by exact Core rule
ID and version. `CoreRuleRegistry` assembles the same contract definitions in the
same order, then refines their existing editorial metadata using the validated,
immutable `RuleDefinition::with_editorial()` method. No additional persistence
store, database migration, scanner behavior or Core/Premium boundary is involved.
Non-editorial fields cannot be changed by that method. All current contracts have
explicit entries; the six earlier contracts have no entry and retain their copy.

Compatible same-contract clarifications retain rule/editorial version identifiers,
so old reports using those exact contracts display clarified guidance without
rewriting stored snapshots or history. This is not immutable prose editioning.
`HistoricalRuleResolver` still requires exact rule/editorial versions; incompatible
or unavailable versions never borrow newer guidance. See
`AUDIT_EDITORIAL_STANDARD.md` for authoring/compatibility policy and
`AUDIT_RULE_EDITORIAL_REVIEW.md` for the inventory, mapping decisions and QA.

The existing dynamic overview remains accurate for old audits. Guided Decision
resolution remains exact; the obscured-focus rule's stale editorial hint was
cleared without broadening any flow. Hidden-focusable ARIA content uses the
registered keyboard Learn topic. No plugin version bump is part of this work.

## Occurrence identification (Audit Report UX Prompt 2)

`Reports/Admin/OccurrencePresenter` maps recorded target/element/evidence facts
to primary labeled values and concise occurrence headings, with no detection or
workflow authority. `ReportsPage` escapes its output and displays the audited URL
separately from plain-text destinations. Native disclosures retain technical
data. Missing historical fields are omitted, and heading shortening does not
shorten the complete captured values shown below.

Browser runtime `core-browser-1.5.1` adds optional, bounded identification fields
to the existing element object: link kind/destination/name excerpt, image
filename/alt state/excerpt, element text, immediate paragraph context and field
name. The scanner asset uses the runtime version for cache invalidation. The
shared browser validator enforces field and URL limits. Existing staging and
occurrence JSON persistence handle this data without migrations. Plugin version,
rule/editorial versions, detection outcomes, grouping and help mappings remain
unchanged.

Context collection uses only a small immediate paragraph walk, excluding form
values, hidden/script/style/editable content. HTTP(S) destinations omit
credentials, queries and fragments; images expose only a filename. No HTML or
full-page prose is collected. Simple available link names are explicitly labeled
as excerpts, not computed accessibility-tree names. Historical name/alt excerpts,
heading levels and language states remain usable.

Reviewed-condition identity already includes element metadata. Richer or changed
identification data may conservatively require renewed review while all historical
records remain untouched. No identity weakening or selector-only matching was
introduced. Evidence matrix, field limits and QA results are recorded in
`docs/AUDIT_REPORT_UX_FRAMEWORK.md`.

## Audit Report UX guidance framework (0.2.0 milestone in progress)

Reports continue to use `ReportsPage`, `HistoricalRuleResolver`, and the existing
versioned `RuleDefinition` registry. No database migration, scanner contract change,
plugin version bump, or parallel guidance store is introduced.

`RuleDefinition::editorial()` retains summary, impact, recommendation, WordPress
and developer guidance, and existing Learn/Guided Decision identifiers. It now
also exposes optional `what_to_check` and `verification_guidance` strings, plus
`examples` (at most ten comparisons). Each example requires `instead_of`,
`consider`, and `explanation` strings, each bounded to 4096 bytes. Comparison code
remains literal text and is escaped on output. Examples are editorial content,
never captured evidence or automatically approved replacement values. Authors
must use WordPress translation functions with the `elavira` domain for prose;
code literals may remain literal, with translated contextual explanations.

Missing optional fields default to empty strings/an empty array and produce no
empty guidance sections. Existing guidance is not rewritten in this framework
change. Historical resolution still requires exact rule and editorial versions;
unavailable versions show the persisted summary with an explicit warning. Later
editorial work must account for this exact-version policy rather than silently
substituting new advice into historical reports.

Finding detail presents the summary, captured locations, impact, inspection
guidance, contextual help, remediation, examples, verification, and technical
guidance. Native details/summary disclosures contain occurrence diagnostics and
rule/developer information. Existing bounded name/alternative-text excerpts may
help identify occurrences outside the disclosure. No new page data is collected.
Workflow forms, status history, decision history, and pagination retain their
existing services and routes. XLSX retains its existing columns and guidance;
exporting the new optional editorial fields is not part of this change.

Guided Decision CTAs use `DecisionRegistry::for_rule()` exact rule/version
compatibility; Learn CTAs require the historically resolved `learn_topic` to
exist in `TopicRegistry`. Missing mappings produce no action. The decision
registry remains authoritative for finding launch compatibility, independently
of editorial availability, matching the existing decision route.

`HistoricalRuleResolver::current_rule_count()` counts distinct registered rule
IDs from the injected Core registry, matching scan-start selection of one version
per ID. It includes browser checks whose observations require human review;
Guided Decisions are in a separate registry and are never counted. Retained
historical versions do not inflate the count. The overview explicitly describes
current Core coverage separately from the ruleset recorded by an older audit,
because audits do not persist a historical rule-count snapshot.

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


## Report UX 0.2.3

Needs Correction (`needs_correction`) is an unresolved workflow status, independent
of scanner classification, priority, and review_state. It is stored in existing
status columns without a schema migration. Both grouped activity and independent
occurrence services reject blank correction notes before writing any status or
history. Existing occurrence aggregation remains authoritative; mixed groups keep
the established Needs Review aggregate while occurrence filters and counts retain
the actual individual statuses.

Rejected form drafts use a ten-minute, non-autoloaded WordPress transient scoped
to the signed-in user, finding, and occurrence. The next matching form consumes
it and exposes an associated validation error. Report URLs contain no note text.

WordPress remediation renders only with an explicit `wordpress_guidance_useful`
editorial approval. Omitted prose remains in definitions; no detection contract
or historical evidence is changed. Legacy generated-note presentation requires
an exact matching persisted decision (actor, time, status, prefix, and note).
Unmatched notes are displayed unchanged. New decision activity stores plain note
text; the existing decision record retains ID, version, outcome, and answers.
