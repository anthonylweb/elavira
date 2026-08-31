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
