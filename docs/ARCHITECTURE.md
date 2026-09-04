# Elavira Foundation Architecture

This document describes implementation layout; `TECH_SPEC.md` and
`ENGINEERING_GUIDELINES.md` remain authoritative.

## Current directories

- `src/Core`: application bootstrap and cross-cutting foundation services.
- `src/Core/Contracts`: contracts shared by independently registered modules.
- `src/Core/Capabilities`: stable authorization identifiers and installation.
- `src/Core/Features`: the single Core/Premium feature-availability boundary.
- `src/Core/Lifecycle`: activation, deactivation, and update coordination.
- `src/Core/Migrations`: versioned, idempotent migration contracts and runner.
- `src/Core/Version`: persisted plugin and schema version management.
- `tests/Unit`: tests that do not require a booted WordPress installation.

## Future module placement

Core feature work should add cohesive top-level modules under `src` for Audit,
Scanner, Findings, Reports, Guided Decisions, Statement, Toolbar, Learn,
Settings, Admin HTTP, and API handling. The Core scanner boundary is browser-side
JavaScript operating on one rendered canonical public page and submitting
bounded structured evidence to an authenticated and authorized WordPress
endpoint. WordPress remains authoritative for rule metadata, findings, guidance,
history, and reports. The current `src/Scanner` module implements this Core
single-page boundary; reports and the wider improvement workflow remain separate.

Site-wide and selected/multi-page audits, URL inventory, persistent queues, and
remote browser workers belong to a later Premium implementation. Core contains
stable feature identifiers for that boundary but no workers, schedulers, queue
tables, leases, remote clients, or browser-runtime dependencies.

Domain contracts belong with the domain that owns them. WordPress database
implementations and repositories should be kept behind those contracts rather
than placed in the plugin entry file. A future Audit model may support multiple
scope identifiers, but Core execution remains single-page and is not queue
driven.

Assets should live under `assets` and load only in their relevant admin or
front-end contexts. WordPress integration tests should live separately from
fast unit tests. These locations are documented now without creating empty or
speculative feature implementations.

## Distribution

Composer provides PSR-4 loading for `Elavira\\`. Development installs include
quality tools. A production build must run `composer install --no-dev
--classmap-authoritative` and package the generated `vendor` directory, so an
installed plugin never depends on a site administrator running Composer.

## Core audit persistence

Schema versions `0.2.0` and `0.3.0` introduce seven WordPress-prefixed custom
tables. Five retain audit/report history and two coordinate short-lived browser
scan sessions:

- `elavira_audits` stores the Audit aggregate, scope, WCAG target, lifecycle,
  configuration snapshot, owner, and scanner/ruleset/editorial versions.
- `elavira_audit_urls` stores canonical pages belonging to an Audit. Core
  normally creates one row, while the one-to-many relationship avoids a future
  destructive redesign for Premium scopes. It contains no queue or retry data.
- `elavira_findings` stores grouped issues with rule/WCAG snapshots, internal
  priority, a separate human-review state, compact summary snapshot, and counts.
- `elavira_occurrences` stores individual finding-to-page instances with bounded
  structured target, evidence, and element metadata.
- `elavira_finding_status_history` stores every workflow transition, actor,
  bounded reason, timestamp, and optional source Audit used by later verification.
- `elavira_scan_sessions` stores hashed browser credentials, ownership, canonical
  URL and version snapshots, expiry, lifecycle state, and result totals.
- `elavira_scan_batches` stores small accepted-batch receipts with a unique
  session/batch key. It stores no browser payload and is not a queue.

Table names always use the active WordPress database prefix. Relational IDs are
validated in repositories rather than enforced through MySQL foreign keys, in
line with WordPress portability practices. Grouped findings and canonical URLs
have aggregate-local uniqueness keys; common parent/date lookups are indexed.

### Historical integrity

Audits snapshot scanner, ruleset, editorial, WCAG-target, and configuration
versions. Findings snapshot their rule ID/version, WCAG mapping, editorial
version, and a compact human-readable summary (maximum 4,096 bytes). Historical
records therefore do not silently inherit changed current rule meaning, while
large recommendation, Learn, WordPress-guidance, and developer-guidance content
is not duplicated into every finding.

### Evidence minimization

Occurrence payloads must be arrays encoded as JSON. Limits are 4,096 bytes for
target metadata, 16,384 bytes for scanner evidence, and 8,192 bytes for element
metadata. Audit configuration JSON is limited to 65,535 bytes. The persistence
boundary rejects fields intended for passwords, authentication/cookies/tokens,
form values, HTML blobs, and full request/response bodies. These limits are a
storage safety boundary. The scanner workflow additionally authenticates,
authorizes, validates rule/session identifiers, enforces canonical page binding,
and applies request-size limits before calling repositories.

## Rule Registry and browser evidence contract

Core owns a centralized, transport-independent Rule Registry. Rule identifiers
use the collision-safe form `elavira.<domain>.<semantic_name>`, for example
`elavira.image.alt_missing`. An ID describes durable rule meaning rather than a
selector, WCAG number, translation, or UI label. Exact ID/version pairs are
unique, and historical lookups always request both values.

A rule version changes when detection logic, evaluation certainty, grouping,
evidence shape, WCAG mapping, or historical interpretation changes. Editorial
copy changes retain the rule ID/version and advance the separate editorial
version. Each definition owns structured WCAG mappings, evaluation type,
finding classification, internal priority recommendation, editorial guidance,
optional Learn/Guided Decision references, rule-specific evidence schema, and
server-side grouping strategy.

Evaluation types are `automatic_failure`, `potential_issue`, and
`human_review`. They map respectively to detected failure, potential issue, and
human review classifications. Priority remains High/Medium/Low and never uses
Needs Review as severity. Review rules cannot submit definitive failure
outcomes. Pass outcomes may be consumed transiently but do not create Findings.

The browser contract accepts only an exact rule ID/version, outcome, bounded
target metadata, rule-specific evidence, limited element metadata, and bounded
grouping inputs. It rejects unknown fields, including browser-supplied priority,
WCAG mapping, editorial text, recommendations, or final grouping keys. Evidence
schemas whitelist at most 20 scalar fields, reject unknown fields and HTML-like
strings, and reuse the persistence layer's sensitive-key and encoded-size
protections. Target metadata is diagnostic evidence, not a permanent element
identity; selectors and locators are bounded and no DOM fragments are accepted.

Core generates the final grouped Finding key. Rules either group all of their
occurrences at rule level or hash only whitelisted, schema-validated grouping
inputs. Arbitrary browser grouping keys are never trusted.

The transport-independent flow is:

**browser evidence → PHP structural/privacy validation → exact Rule Registry
lookup → rule-specific evidence validation → server normalization → bounded scan
persistence**

Normalization supplies trusted rule/editorial versions, complete WCAG mapping,
primary persistence mapping, classification, priority, review state, editorial
metadata, grouping key, and bounded occurrence structures. The Core scanner
module supplies authentication, authorization, session validation, transport
limits, persistence orchestration, and browser execution.

## Core single-page browser scan execution

Authorized users launch a Core audit from the Dashboard, Audits history, a
published viewable content editor, or a singular public page's admin bar through
the canonical `elavira-run-audit` admin route. The server-rendered form requires
`elavira_run_audits`, a WordPress nonce, one current-site public URL, and a WCAG
2.2 A or AA target (defaulted from Core Audit Defaults). It then delegates to the
same `StartSinglePageScan` application service used by REST initiation. That
service canonicalizes the URL and atomically creates the Audit, Audit URL, and
short-lived session before redirecting the current tab to the credential-bearing
public page. The existing scoped frontend scanner owns progress, persistence,
failure handling, and the final handoff to the full Audit Report.

Core scan initiation is an authorized application service requiring
`elavira_run_audits`. It accepts only HTTP(S) URLs on the exact scheme, host, and
port of `home_url()`, removes the Elavira credential parameter, canonicalizes
query ordering, and rejects WordPress administrative and internal endpoints.
It creates the Audit, Audit URL, and scan session in one database transaction.
The session snapshots scanner `core-browser-1.0.0`, ruleset
`core-rules-1.0.0`, editorial `core-editorial-1.0.0`, WCAG target, and the exact
allowed rule ID/version map. A 256-bit random credential is returned to the
browser; only its SHA-256 hash is stored.

Sessions expire after 15 minutes and use `created → active → completing →
completed`, with explicit `failed` and `expired` terminals. The public scanner
assets load only when the logged-in session owner presents a valid unexpired
credential on the exact canonical Audit URL. Valid credential-bearing pages
receive no-cache headers so personalized runtime configuration cannot enter a
shared page cache. Ordinary frontend requests retain normal caching and load no
scanner assets.

The browser waits for the window load state, then observes DOM mutations until
500 ms of quiet time. Stabilization ends after 3 seconds even on continuously
changing pages, and the entire readiness phase has a 10-second absolute limit.
The MutationObserver, timers, and load listener are always removed. Readiness is
deliberately not defined as permanent network or DOM idleness.

The framework-free scanner evaluates only the five registered representative
rules. It collects no input values, cookies, tokens, HTML snapshots, or
editorial data and never modifies page content for remediation. Its only page
addition is a scoped live status region describing preparation, scanning,
saving, completion, or failure. It does not move or trap focus, rely on color,
or animate.

Authenticated REST `POST` requests use WordPress cookie authentication, the
`wp_rest` nonce, and `elavira_run_audits`. JSON content type and a 256 KiB raw
request limit are enforced before application processing. Batches contain at
most 50 results and one session accepts at most 1,000 total results. Crossing
the total limit fails the scan rather than silently truncating it.

PHP checks ownership, expiry, lifecycle, the snapshotted rule/version map, and
every evidence schema before normalization. It creates or reuses server-grouped
Findings and adds Occurrences through existing repositories. A batch identity
is the server-computed SHA-256 hash of its exact structured results. Findings,
Occurrences, counts, and the unique batch receipt commit in one bounded
transaction. An identical retry returns the prior acceptance without inserting
again; a failed transaction records no receipt and may be retried safely.

Completion is a separate idempotent request. It transitions the Audit URL,
Audit, and session only after accepted batches have committed. Browser silence
never implies success. Safe failure codes cover scanner errors, readiness
timeout, transport or validation failure, result limits, and session expiry.
Raw SQL, paths, request bodies, and stack traces are not stored or returned.
Successful completion redirects to the authenticated report, where grouped
findings and their captured evidence are available to the audit owner.

## Core Guided Decisions

Guided Decisions are server-owned, deterministic decision-support graphs for
findings whose accessibility meaning depends on context or human judgment.
Definitions live under `src/GuidedDecisions`, use stable identifiers in the
form `elavira.decision.<semantic_name>`, and have separate semantic and
editorial versions. The semantic version changes when questions, answer
meaning, branching, rule compatibility, outcomes, or workflow recommendations
change materially. Copy-only changes advance the editorial version.

Every definition declares exact compatible rule ID/version pairs. Historical
findings receive a decision action only when that exact pair is registered;
current decisions are never silently applied to incompatible historical rule
versions. PHP validates the complete graph when it is registered and remains
authoritative for answer validation, branch targets, and outcomes.

Intermediate answers use signed, request-bound state and are replayed through
the trusted graph on every request. They do not update the finding. On explicit
completion, Elavira stores the decision ID/version, validated answers, outcome,
recommendation, actor, and completion time in `elavira_guided_decisions`.
Optional workflow changes reuse the existing finding-status service and history;
Verified remains reserved for subsequent audit evidence.

Guided Decisions assist human judgment and never establish WCAG conformance,
remediate the website, or convert uncertainty into an automatic pass or failure.
The user must explicitly choose whether to keep or change the workflow state.

The Phase 1 registry contains seven semantic-versioned definitions: Alternative
Text, Link Purpose, Heading Structure, Form Labels & Instructions, Use of Color,
Keyboard Interaction, and Audio & Video Alternatives. Alternative Text, Link
Purpose, Heading Structure, and Form Labels & Instructions declare exact current
Rule ID/version compatibility and may run from an owned compatible Finding. Use
of Color, Keyboard Interaction, and Audio & Video Alternatives are registered as
manual educational reviews with no fabricated scanner Rule relationship.

Every definition declares one or more stable Learn topic IDs. This metadata lets
the Learn directory and topic pages describe all registered decisions without a
second list. For rule-bound definitions, Rule metadata remains authoritative for
Finding compatibility and WCAG mappings. Manual definitions derive explanatory
WCAG context from their registered Learn topics. The current execution controller
remains Finding-bound; manual entries are discoverable guidance rather than fake
audits, persisted decisions, or a new manual-audit subsystem.

## Core Accessibility Statement

The Statement module stores structured draft fields separately from the current
published snapshot. A normal WordPress Page supplies the stable public URL and
theme presentation, while a narrowly scoped `the_content` integration renders
only the associated published snapshot. Draft saves, audit completion, finding
status changes, and recommendation review never update public content. Publish
is a separate capability- and nonce-protected action that updates the Page and
creates an immutable, viewable revision snapshot.

Statement fields are bounded plain text except for separately validated email
and HTTP(S) URL values. Public markup is generated by the server with semantic
sections and context-specific escaping; arbitrary stored HTML is never rendered.
WCAG Target, Plugin Findings, and Organization's Stated Conformance remain
separate fields. Plugin-derived information is always labeled **Conformance
Status — Based on Plugin Findings** and includes the automated-testing
limitation.

Audit completion may create advisory recommendations from trusted grouped
Finding identity, status, canonical URL, and an exactly compatible completed
Guided Decision. A SHA-256 fingerprint of those trusted semantics prevents
duplicate prompts while allowing a materially changed status or decision
outcome to create a new review opportunity. Recommendations retain Audit,
Finding, and optional Guided Decision IDs. Accepting editable wording changes
only the structured draft; dismissal retains a bounded reason. Neither action
publishes, remediates, verifies, or asserts conformance.

Schema version `0.5.0` adds `elavira_statements`,
`elavira_statement_recommendations`, and `elavira_statement_revisions`.
Growing recommendation and revision reads are explicitly bounded. The module
uses the existing `elavira_manage_statement` capability and has no Premium,
queue, remote-worker, toolbar, Learn, or Settings dependency.

## Core Learn

Learn is a bundled, version-controlled educational library under `src/Learn`.
Each immutable topic has a stable semantic ID, an independent content version,
summary/category metadata, WCAG mappings, structured educational sections,
separate WordPress and developer guidance, related rule IDs, related topic IDs,
keywords, and authoritative resources. A validated in-memory registry rejects
duplicate IDs and invalid relationships. Static product-authored content uses no
database tables, options, posts, remote downloads, or scanner execution hooks.

Rule definitions remain the source of exact rule/version-to-topic relationships.
A finding links to Learn only when its historical rule and editorial version can
be resolved and the referenced topic exists. Guided Decisions may provide the
same background link while retaining their distinct role: rule guidance explains
the specific finding, Learn teaches the general concept, and Guided Decisions
support a contextual human judgment.

Learn content distinguishes WCAG criterion metadata from Elavira recommendations
and general best practices; no topic or scanner result establishes conformance.
The server-rendered landing and stable topic routes require the standard WordPress
`read` capability, making education available to authenticated users without a
management capability. Search is bounded and operates only over trusted in-memory
metadata. Learn assets load only on Learn screens.

The Phase 1 library contains three ordered Start Here guides and ten topic-area
guides. The landing route also exposes a directory generated only from registered
Guided Decision definitions and a lightweight WCAG 2.2 A/AA reference generated
from topic and rule metadata. Criterion, topic, rule, Finding, Guided Decision,
Report, and Statement links resolve through their registries or stable Learn IDs;
the directory does not manufacture runnable decision URLs without Finding context.
The reference is explicitly non-normative and links to the centralized W3C WCAG
resource. These additions remain static and do not add persistence or migrations.

`LearnModule` registers menu callbacks without constructing the content registries.
It builds them lazily only when a Learn route renders or enqueues its scoped asset,
so long-form bundled content is not instantiated on unrelated WordPress requests.

## Core Visitor Accessibility Toolbar

The `src/Toolbar` module provides the Phase 1 visitor preference interface and
the minimum capability-protected admin configuration needed to enable it,
choose its corner position, and enable individual optional controls. Configuration
uses the single bounded `elavira_toolbar_settings` WordPress option; it introduces
no custom table or visitor account dependency.

When enabled (or explicitly previewed by an authorized administrator), the module
loads its independent `toolbar.css` and `toolbar.js` assets and renders one modal
dialog at the end of the public page. Opening moves focus into the dialog, Tab is
contained, Escape and the Close button dismiss it, and focus returns to the
trigger. The public Accessibility Statement is linked when a published Statement
exists and otherwise remains unchanged.

Visitor choices are stored only in browser `localStorage` under
`elaviraToolbarPreferences`. The payload contains presentation values, never a
disability, diagnosis, account identifier, or analytics event, and is not sent to
WordPress. A small early inline bootstrap applies validated enabled root states to
reduce flash. The main script revalidates all stored values, ignores disabled or
unknown controls, applies changes immediately, and removes the stored record on
Reset.

Preference effects use namespaced `elavira-pref-*` classes and one text-scale
custom property on the document root. Toolbar component rules are entirely scoped;
page-facing overrides exist only while their corresponding explicit state is
active. **Default** means the theme's native styling and behavior, including its
own operating-system preference handling. The toolbar is a reversible browsing
preference layer—not remediation, an accessibility overlay claim, or evidence of
WCAG conformance—and scanner execution remains independent.

## Core Dashboard and Settings

The Core administration root is `elavira-dashboard`, with ordered destinations
for Dashboard, Audits, Statement, Toolbar, Learn, and Settings. Existing feature
slugs remain stable. Dashboard access uses `elavira_view_reports`; Settings uses
`elavira_manage_settings`, while Statement and Toolbar continue enforcing their
own management capabilities.

The Dashboard performs one bounded five-row Audit query and summarizes grouped
Finding states only for the latest owned Audit. “Open,” “Addressed,” “Accepted
Limitation,” human-review state, and High/Medium/Low priority remain distinct.
It also reads the single site Statement, counts pending recommendations using one
aggregate query, reports the existing Toolbar option, and obtains the seven-item
Guided Decision inventory from its registry. It never calculates a score or
describes historical Finding totals as current site conformance.

`src/Settings` owns the small, non-autoloaded `elavira_core_settings` option.
It contains only the future-audit WCAG target, actionable admin-notice preference,
and admin-bar shortcut visibility. The default target is WCAG 2.2 AA and is read
only when a new scan request omits an explicit target; historical Audit snapshots
are never rewritten. Toolbar configuration remains feature-owned in
`elavira_toolbar_settings` and is linked rather than duplicated.

The server-rendered Settings tabs are General, Audit Defaults, and Data & Privacy.
Core health checks are limited to required database tables, schema currency, and
the local browser-scanner asset, with safe actionable messages. Content/URL rules,
developer diagnostics, permission management, retention controls, queues, and
remote-worker health remain absent because they are Premium boundaries.
