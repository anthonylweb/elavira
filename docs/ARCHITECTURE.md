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
history, and reports. The scanner implementation is intentionally deferred.

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

Schema version `0.2.0` introduces five WordPress-prefixed custom tables:

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
storage safety boundary; the future scanner submission endpoint must still
authenticate, authorize, validate rule/session identifiers, enforce same-site
canonical URLs, and apply request-size limits before calling repositories.

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
lookup → rule-specific evidence validation → server normalization → later scan
persistence**

Normalization supplies trusted rule/editorial versions, complete WCAG mapping,
primary persistence mapping, classification, priority, review state, editorial
metadata, grouping key, and bounded occurrence structures. Authentication,
capability checks, CSRF protection, same-site scan authorization, scan-session
validation, HTTP request limits, persistence orchestration, and browser scanner
execution remain responsibilities of later tasks.
