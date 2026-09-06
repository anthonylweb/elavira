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
`core-rules-1.2.0` and scanner snapshot `core-browser-1.1.0`.

Three representative IDs remain stable to avoid duplicate historical findings:

- `elavira.link.purpose_review` version `2.0.0` implements the conservative
  ambiguous-link-name check requested as `elavira.links.ambiguous_purpose`.
- `elavira.document.language_missing` version `2.0.0` implements the missing
  document-language check requested as `elavira.document.missing_language`.
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
Rounds 3–6 remain unimplemented.
