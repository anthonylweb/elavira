# Core Phase 1 Release Checklist

This checklist records the final release-hardening pass. `TECH_SPEC.md`,
`ENGINEERING_GUIDELINES.md`, and `ARCHITECTURE.md` remain authoritative.

Status values are **Passed**, **Failed and fixed**, **Blocked by tooling**, and
**Not applicable**. Items remain **Pending** until evidence is collected.

## Installation and lifecycle

- Passed — Production-only Composer install generated an optimized autoloader;
  isolated fresh activation and populated-site reactivation both succeeded.
- Passed — Isolated fresh activation against real MySQL created all 11 expected
  Core tables, initialized plugin `0.1.0` and schema `0.5.0`, installed all six
  administrator capabilities, and left Toolbar disabled by default. Fresh live
  Dashboard and Settings defaults were also verified.
- Passed — Migration ordering and idempotency tests pass; two real installer
  reruns preserved live row counts. A focused regression test now proves a
  thrown migration does not advance the schema version.
- Passed — Two installer reruns plus a real deactivate/reactivate cycle preserved
  Audit, Audit URL, Finding, occurrence, status history, Guided Decision,
  Statement, recommendation, revision, scan-session, and scan-batch rows, along
  with Toolbar settings and computed Core defaults.
- Passed — Real deactivation left every populated table and option intact;
  reactivation completed without a WordPress error and the data remained usable.
- Blocked by tooling — No uninstall implementation or intentionally selected
  removal/retention policy exists. Deactivation is explicitly non-destructive;
  hardening did not invent destructive uninstall behavior.
- Blocked by tooling — Tables/options are site-prefix-local and capabilities are
  role-local, but neither the authoritative specification nor implementation
  defines network activation or a formal multisite support position.

## Core feature regression

- Passed — Fresh live browser pass confirmed the Dashboard empty state and
  Settings screen, named native controls, expected headings, zero positive
  `tabindex`, feature-scoped assets, no console warnings/errors, and no
  Elavira-caused horizontal overflow at a 320 CSS-pixel viewport.
- Passed — Fresh live Run Audit launched the controlled same-site public URL,
  completed browser scanning, persisted Audit 16, and handed off to its full
  report with two grouped findings and two occurrences.
- Passed — Audit history, report filters/semantics, Finding detail/history, and
  the export action passed live. Structural XLSX verification passed separately;
  desktop/cloud application interoperability is classified below.
- Blocked by tooling — Alternative Text passed live for Start, forward navigation, Back,
  Restart, qualified outcome, explicit workflow choice, history persistence,
  no automatic Verified transition, and its Finding-to-Learn link. Link Purpose,
  Heading Structure, and Form Labels lacked controlled live Finding fixtures.
- Passed — Learn landing, bounded search, 7-decision directory, long topic,
  related-topic navigation, and A/AA WCAG reference passed live.
- Passed — Fresh live Statement creation, audit recommendation acceptance,
  explicit publication, revision history, stable logged-out HTTP 200 permalink,
  theme-native rendering, required qualified conformance language, and draft-only
  sentinel isolation all passed.
- Passed — Fresh live Toolbar enablement, frontend rendering, namespaced combined
  preferences, Reset, Statement link, dialog focus containment, Escape/Close focus
  restoration, and 320 CSS-pixel dialog reflow passed.

## Security, privacy, and integrity

- Passed — Fresh live role matrix: administrator has all six Core capabilities;
  the auditor has only Run Audits/View Reports; subscriber has only `read`.
  Screen/application code also enforces ownership where required.
- Passed — Source and automated-test review confirms every state-changing admin
  action has matching nonce generation/verification; REST additionally requires
  the `wp_rest` nonce, authentication, capability, content type, and body bound.
- Passed — Focused validation tests cover URL origin/private paths, WCAG/status
  enums, scalar/array shapes, bounded text/evidence, unauthorized markup/fields,
  Unicode, and signed Guided Decision state.
- Passed — Contextual escaping review and tests pass. The production XLSX writer
  preserved Unicode/long evidence and serialized `=`, `+`, and `@` prefixes as
  apostrophe-prefixed inline strings; independent import found no formula errors.
- Passed — Focused scanner tests and code review cover owner binding, expiry,
  invalid/stale credentials, canonical URL binding, terminal states, and hashed
  credential storage; live scan URLs were no-cache and no token was stored.
- Passed — Configured/tested limits are 256 KiB raw/structured requests, 50
  results per batch, 1,000 results per page session, bounded stabilization and
  readiness time, and bounded target/evidence/element fields.
- Passed — Real MySQL concurrency test submitted one identical batch from two
  simultaneous PHP workers: one was accepted, one identified as duplicate, and
  final state contained exactly one Finding, one occurrence, one receipt, and
  `total_results = 1`.
- Blocked by tooling — Migration failure non-advancement now has a regression
  test and transactional scan paths are tested, but a live forced MySQL write
  failure was not injected into the populated site.
- Passed — Static/runtime review found no remote scanner transport or credential,
  cookie, form-value, full-page HTML, or large-content logging. Toolbar choices
  remain browser-local; no WordPress debug log was created during live workflows.

## Accessibility matrix

- Passed — Semantic browser review covered every named admin/frontend area;
  headings, labels, groups, link/button names, status text, table scopes, and
  zero positive `tabindex` were verified. This was not a screen-reader test.
- Passed — 320 CSS-pixel browser measurements found no Elavira-caused page-level
  overflow on primary admin screens, report, long Learn content, Statement, or
  the open Toolbar dialog; controls remained available.
- Blocked by tooling — The in-app browser exposes viewport sizing but not a
  trustworthy true browser zoom control, so 200% zoom is not claimed.
- Blocked by tooling — Toolbar spacing/line-height combinations passed, but a
  global WCAG text-spacing override across every named screen was not available
  through the read-only browser evaluation surface.
- Blocked by tooling — Decision Back/Restart and Toolbar keyboard behavior passed,
  but a full keyboard-only walkthrough with reliable visual focus measurement
  across every admin screen was not completed.
- Passed — Toolbar focus entered the dialog, Shift+Tab wrapped to its final link,
  Escape/Close dismissed it, and focus returned to the trigger.
- Blocked by tooling — Semantic inspection was completed; no screen-reader
  application was available, so no screen-reader smoke test is claimed.
- Blocked by tooling — No dedicated contrast-measurement integration was
  available; custom color combinations were visually/structurally exercised but
  numerical contrast compliance is not claimed.
- Passed — Scanner and Toolbar styles contain explicit `prefers-reduced-motion:
  reduce` overrides and motion is not required to understand status.
- Blocked by tooling — A destructive combined Light/High Contrast/Grayscale/text
  spacing/line height/focus/font/reading-guide state and Reset passed at 320px;
  the entire requested pairwise matrix was not exhaustively measured.

## Compatibility, performance, and assets

- Blocked by tooling — The prepared dynamic-DOM fixture was sanitized into text,
  so the fresh live run did not prove post-load DOM capture. Browser-abandon
  behavior remains covered by bounded session design/tests, not a completed live run.
- Blocked by tooling — Twenty Twenty-Five passed and disabled Toolbar assets were
  absent, but a second representative theme was not exercised.
- Blocked by tooling — No full-page cache plugin is installed in this site, so a
  named cache configuration could not be tested.
- Blocked by tooling — No authoritative supported-browser matrix exists.
- Passed — WordPress runs with `WP_DEBUG` enabled and representative workflows
  created no debug log. CLI emitted only Local's pre-WordPress missing Imagick
  extension warning, unrelated to Elavira.
- Blocked by tooling — Admin, Toolbar, Statement, and ordinary frontend loads were
  console-clean. Scan launch logged one unattributed theme/navigation
  `AbortError: Transition was skipped`; no Elavira transition API exists.
- Passed — Source/live asset review confirms bounded report/dashboard/session
  queries, lazy Learn registries, non-autoloaded options, Toolbar-only frontend
  assets when enabled, and scan assets only for authorized credential sessions.
- Blocked by tooling — Browser export succeeded and the exact writer produced a
  five-sheet workbook that independently imported with all sheets, Unicode, long
  text, and neutralized formula prefixes intact. Excel, LibreOffice, and Google
  Sheets were unavailable, so application interoperability is not claimed.

## Distribution and policy

- Blocked by tooling — Executable declarations consistently require PHP 7.4+
  and WordPress 6.4+, but browser support remains undefined and the source docs
  do not formally freeze a compatibility-test matrix.
- Passed — Plugin header/version constant are `0.1.0`; schema constant and live
  schema are `0.5.0`. No unsupported final-version bump was made.
- Blocked by tooling — The production Composer autoloader contains no runtime
  packages and retains Composer's license; Atkinson Hyperlegible retains OFL.txt.
  The project's proprietary license/distribution channel and required project
  notices are not authoritatively defined.
- Failed and fixed — README no longer describes Core as foundation-only and now
  covers installation, current executable minimums, privacy, scanner limits,
  Toolbar scope, data retention, and truthful non-conformance language. Whether
  a WordPress.org `readme.txt` is required depends on the unresolved channel.
- Passed — Production Composer build completed with `--no-dev` and optimized
  classmaps; site owners do not need Composer.
- Passed — `dist/elavira-0.1.0-rc.zip` contains 132 production entries and the
  generated autoloader, and excludes development dependencies, tests, Git data,
  repository, secret, fixture, log, database, and temporary artifacts.
- Passed — Final fresh hardening run: PHPUnit 143 tests/433 assertions;
  PHPCS/PHPCompatibility 126 files; PHP syntax 126 files; JavaScript 5 tests and
  both production scripts syntax-checked; Composer validation; and
  `git diff --check`. No PHPStan or Psalm configuration exists, so no additional
  configured static-analysis command applies.
