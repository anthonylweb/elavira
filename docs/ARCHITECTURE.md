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
Raw SQL, paths, request bodies, and stack traces are not stored or returned. A
temporary authenticated completion page reports only the audit label and
grouped finding count pending the full Reports implementation.
