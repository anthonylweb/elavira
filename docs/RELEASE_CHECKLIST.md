# Elavira Phase 1 Release Checklist

Last reviewed: 2026-09-15.

## Release decision

**RELEASE CANDIDATE READY — 0.2.0.** No Blocker, High, or release-critical
Medium defect remains after the final Audit Report UX gate.

- Plugin/header/readme version: `0.2.0`; schema remains `0.7.0`; scanner,
  ruleset, and editorial content-contract versions remain independently
  versioned. — **Passed**
- PHPUnit: 260 tests, 4,456 assertions. JavaScript: 30 tests. Full configured
  PHPCS/WPCS/PHPCompatibility: 156 files. JavaScript syntax, Composer strict
  validation, and `git diff --check` passed. — **Passed**
- Edge checked 93 production-controller fixture pages; Chrome checked 23.
  The Edge run covered all 62 Guided Decision states and all 14 Learn topics at
  320 CSS pixels plus desktop representatives. It found no horizontal overflow,
  unlabeled controls, unsafe/unexplained new-tab links, or fixture server errors.
  — **Passed**
- WordPress 7.1/PHP 8.2.27 returned HTTP 200 for the site and scanner asset. The
  active plugin upgraded its stored release version from 0.1.0 to 0.2.0 with
  schema 0.7.0 unchanged. A deactivation/reactivation cycle succeeded, all 13
  Elavira tables remained present, and row counts were preserved. — **Passed**
- Core is free, intended for WordPress.org, and GPL-2.0-or-later. Premium stays
  separate. Multisite is not officially supported/tested in Phase 1. Data is
  preserved on deactivation/uninstall by default. Browser policy remains current
  Chrome, Firefox, Edge, and Safari on applicable desktop/mobile platforms.
- `readme.txt`, license metadata, changelog, upgrade notice, Stable tag, Tested
  up to, requirements, text domain, and Composer license are synchronized.
- `dist/elavira-0.2.0.zip`: 150 entries, one `elavira/` root, zero forbidden
  development entries, all required runtime files present, optimized Composer
  autoload verified. SHA-256:
  `7625A64328265416D3C1547DD1B7A53D8D091B5A20EE057161C30C81E5029519`.

## Environment-blocked coverage

- Firefox is installed, but its stock binary exited before Playwright could
  establish the required automation protocol. — **BLOCKED BY ENVIRONMENT**
- Safari and current iOS Safari are unavailable on Windows. — **BLOCKED BY
  ENVIRONMENT**
- No NVDA, JAWS, VoiceOver, or other screen-reader surface was available to the
  test harness. DOM/accessibility-tree inspection was not counted as a screen-
  reader test. — **BLOCKED BY ENVIRONMENT**
- The authenticated WordPress UI could not be reached by the isolated in-app
  browser even though host-side HTTP returned 200. End-to-end scan creation,
  authenticated POST/redirect focus, UI download, and full keyboard/true browser
  zoom workflows remain blocked. — **BLOCKED BY ENVIRONMENT**
- No separate clean disposable WordPress installation or supported spreadsheet
  application matrix was available. Clean ZIP installation and Excel,
  LibreOffice, and Google Sheets import remain blocked. — **BLOCKED BY
  ENVIRONMENT**

## Known defects

No known Blocker, High, Medium, or Low product defect remains from this gate.
The PHP 8.2 Local runtime logs a missing optional Imagick extension at startup;
the site and Elavira run without it, so this is an environment-level
informational warning rather than an Elavira defect.

---

## Archived 0.1.0 release-gate snapshot (2026-09-06)

The material below is retained as historical validation evidence. Its blocked
policy decisions and old test counts were superseded by the 0.2.0 gate above.

## Baseline and metadata

- Plugin version `0.1.0`; schema `0.7.0`. — **Passed**
- Scanner `core-browser-1.5.0`; ruleset `core-rules-1.6.0`; editorial
  `core-editorial-1.5.0`. — **Passed**
- Plugin header and README agree on WordPress 6.4 and PHP 7.4 minimums. These
  are declared requirements, not a completed compatibility matrix. — **Passed**
- Local runtime observed: WordPress 7.1, Twenty Twenty-Five, nginx 1.26.1,
  MySQL 8.0.35, site PHP 8.2.27; CLI checks used PHP 8.5.5. — **Passed**
- Browser baseline, tested PHP range, Tested up to policy, and meaningful AT
  support statement are not frozen. — **Blocked**
- No secrets, private keys, test credentials, source maps, OS junk, build cache,
  `.git`, `dist`, or PHPUnit cache are tracked as plugin source. — **Passed**
- Atkinson Hyperlegible includes the SIL OFL 1.1 text. — **Passed**
- The bundled IANA registry and WAI-ARIA vocabulary identify their authoritative
  sources, but a complete third-party notices policy is not present. — **Blocked**

## Automated regression

- PHPUnit: 195 tests, 1,250 assertions. — **Passed**
- PHPCS/WPCS/PHPCompatibility: 145 files. — **Passed**
- JavaScript: 28 tests. — **Passed**
- JavaScript syntax: all four production scripts. — **Passed**
- PHP syntax: 145 files. — **Passed**
- `composer validate --strict`. — **Passed**
- `git diff --check`. — **Passed**
- No PHPStan, Psalm, browser automation, packaging, or dedicated security scan
  is configured. — **Not Applicable**

## Browser scanner and ruleset

The Local router was found stopped with a stale PID. Its existing nginx router
was restarted, restoring listeners on ports 80 and 443. The site then reached
the isolated browser, but that browser rejected Local's untrusted development
certificate (`ERR_CERT_AUTHORITY_INVALID`). Chrome and Edge connectors were not
available. WordPress/TLS settings were not weakened.

- Canonical HTTPS transport returned the public page, scanner asset, and the
  correct same-host login redirect after the router restart. — **Passed**
- Real authenticated audit workflow in a certificate-trusting browser. —
  **Blocked**
- Redirect, status UI, rendered scan, browser console, mixed content, and
  unsupported-page browser behavior. — **Blocked**
- Multi-batch browser submission. — **Blocked**
- Partial batches cannot create findings; complete-set finalization is required
  and covered by domain tests. — **Passed**
- Duplicate batches and completion retries are idempotent in domain tests. —
  **Passed**
- Finalization rollback prevents count inflation in forced repository-failure
  tests. — **Passed**
- Expiry state transition and bounded stale-staging cleanup are covered by
  automated tests; browser-close behavior was not observed. — **Blocked**
- Representative Links, Headings/Language, Forms, ARIA, Keyboard/Focus,
  Color/Contrast, and Images matrices are covered by JavaScript and PHP contract
  tests. A live report-level smoke matrix was not possible. — **Blocked**
- Cross-batch and image supersession, consequence precedence, diagnostic
  retention, distinct-target preservation, and an acyclic registry graph are
  covered by automated tests. — **Passed**
- Browser-level overlap cases were not manually observed. — **Blocked**

## Reviewed exceptions and product integrations

- Exact reviewed-exception identity includes rule/version, canonical page,
  stable target fingerprint, and material evidence fingerprint; selector-only
  suppression is rejected by tests. — **Passed**
- Page/global reset preserves history in tests. — **Passed**
- Complete live Reviewed — Not an Issue/re-audit/material-change/reset workflow.
  — **Blocked**
- All Guided Decision definitions, exact bindings, state signatures, tamper and
  cross-finding replay rejection, navigation, representative outcomes, and
  prohibition on manual Verified are covered by tests. — **Passed**
- Live keyboard/screen-reader Guided Decision workflow. — **Blocked**
- All 13 Learn definitions, bounded search, categories/metadata, invalid IDs,
  escaped code, historical links, related topics, and WCAG references are
  covered by tests/static review. — **Passed**
- Live Learn deep-link, keyboard, and screen-reader workflow. — **Blocked**
- Statement draft/publish snapshots, immutable revisions, recommendation
  filtering/deduplication, sanitization, and qualified conformance wording are
  covered by tests. — **Passed**
- Live Statement edit/recommendation/preview/two-publish/public-permalink flow.
  — **Blocked**
- Report classifications, structural warnings, historical fallback, status
  rules, unmapped wording, and formula neutralization are covered by tests. —
  **Passed**
- Live report filters/search/pagination/history and generated XLSX content. —
  **Blocked**
- XLSX import in Excel, LibreOffice Calc, and Google Sheets. — **Blocked**
- Toolbar defaults, setting validation, scoped hooks, modal contract, all ten
  optional controls plus Reset, and local-only persistence are covered by tests
  and static review. — **Passed**
- Live toolbar focus trapping, restoration, Reset, combinations, and theme
  interaction. — **Blocked**

## Accessibility verification

- Source review found semantic native controls, labels, headings, polite scan
  status, focus-visible rules, responsive breakpoints, and reduced-motion rules
  for scanner/toolbar motion. — **Passed**
- Exhaustive keyboard operation and focus management across all screens. —
  **Blocked**
- 320 CSS px reflow across all representative screens. — **Blocked**
- True 200% browser zoom across all representative screens. — **Blocked**
- WCAG text-spacing overrides. — **Blocked**
- Numerical text, non-text, and focus-indicator contrast measurements. —
  **Blocked**
- Screen-reader smoke test; no AT/browser combination was available. — **Blocked**
- Runtime reduced-motion verification. — **Blocked**

## Compatibility

- Current local theme: Twenty Twenty-Five. Public HTTP output and production
  scanner assets responded, but authenticated visual workflow was unavailable.
  — **Blocked**
- Twenty Twenty-Four and Twenty Twenty-Three are installed but were not activated
  because the only useful database was not used for stateful theme testing
  without a disposable fixture. — **Blocked**
- Builder-generated markup. — **Blocked**
- Named full-page caching plugin and credential-cache isolation. No caching
  plugin is installed. — **Blocked**
- WordPress 7.1/PHP 8.2.27 live site availability and PHP 8.5.5 automated suite.
  — **Passed**
- WordPress 6.4 minimum and PHP 7.4 minimum runtime execution. — **Blocked**
- Multisite behavior and policy. — **Blocked**

## Security and data integrity

- Privileged admin actions pair capabilities and nonces; REST mutations require
  authentication, `elavira_run_audits`, a REST nonce, JSON, and bounded bodies.
  — **Passed**
- Session credentials use 32 random bytes, are stored hashed, and are bound to
  owner, expiry, state, canonical page, and exact origin. — **Passed**
- Offsite, private, admin, login, REST, XML-RPC, and cron targets are rejected in
  policy/tests. — **Passed**
- Batch/result/body/evidence bounds, sensitive-field rejection, server-owned
  rule semantics, contrast recalculation, contextual escaping, prepared queries,
  and XLSX formula neutralization are covered by source review and tests. —
  **Passed**
- Direct authenticated REST abuse tests against the live site. — **Blocked**
- Live database schema is `0.7.0`; all 13 expected Elavira tables and staging
  uniqueness indexes are present. The database contained zero audit, finding,
  history, exception, session, batch, or observation rows during inspection. —
  **Passed**
- Migration ordering, idempotency, verification-before-version-advance, clean
  schema creation, capability idempotency, and rollback are covered by tests. —
  **Passed**
- Fresh disposable WordPress activation and earlier-schema upgrade. — **Blocked**
- Live deactivation/reactivation preservation. Automated lifecycle coverage
  confirms deactivation performs no deletion. — **Blocked**
- Live forced DB failure was not run against the only useful local database;
  repository failure/rollback is covered by tests. — **Passed**
- Uninstall: there is no `uninstall.php`; the current documented behavior is to
  preserve data because destructive uninstall policy is undefined. Final policy
  requires owner direction. — **Blocked**

## Public wording and distribution

- Public source search found no guarantee, score, percentage-accessibility, or
  complete-coverage claims. Reports and dashboard explicitly qualify automated
  testing and conformance. One Learn search keyword is `compliance`; it is not a
  public claim. — **Passed**
- Plugin header has name, URI, description, version, WordPress/PHP minimums,
  author, text domain, and domain path. It has no license fields or Tested up to
  field. — **Blocked**
- Composer declares `proprietary`; distribution channel/project-license decision
  is unresolved. — **Blocked**
- `readme.txt`, Stable tag, Tested up to, changelog, installation text, and
  WordPress.org FAQ are absent. — **Blocked**
- The obsolete ZIP has the correct `elavira/` root and an autoloader but lacks
  `readme.txt` and predates required rules/assets. It must not be reused. —
  **Failed**
- New release ZIP, fresh-install ZIP test, and SHA-256 checksum were not produced
  because release blockers remain. — **Not Applicable**

## Decisions required before another RC pass

1. Choose the distribution channel and project license, including compatibility
   with WordPress.org if that channel is intended.
2. Choose an explicit Phase 1 multisite position.
3. Choose an explicit uninstall/data-retention policy.
4. Freeze the supported/tested WordPress, PHP, browser, and AT policy.
5. Provide a browser environment that trusts the Local certificate (or another
   disposable HTTPS WordPress environment), then complete the blocked manual
   matrix with at least a second theme and a named cache plugin.
6. Validate a representative XLSX in the supported spreadsheet applications.
7. After those blockers are resolved, create consistent `readme.txt` and plugin
   metadata, build a clean current ZIP with a production autoloader, install it
   in a fresh disposable WordPress site, smoke test it, and publish its SHA-256.
