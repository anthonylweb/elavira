# Elavira Core 0.2.5 — Plugin Check follow-up

Date: 2026-11-04. Updated source: `elavira-0.2.5-remediation`. No commit, tag, package, or release was created. The installed Elavira source and the previous test ZIP were not updated.

## Three reported findings

| Original finding | Resolution and executed verification |
| --- | --- |
| Unexpected root THIRD-PARTY-NOTICES.md | Moved all notice text into `readme.txt`, section `== Third-Party Licenses and Notices ==`, starting at line 156. Only Markdown headings were converted to readme headings. Attribution, copyrights, license names, source URLs, font hash/version and modification notices were preserved. Separate complete GPL, W3C and OFL license files remain. Updated source comments, README and release instructions, then removed the redundant root notice. Plugin Check file-type and readme checks passed. |
| Domain Path points to absent languages directory | Removed only `Domain Path` from `elavira.php`; retained `Text Domain: elavira` at line 12. No languages directory or local catalog was added. Plugin Check header check and unsuppressed WPCS i18n check passed. Gettext functions are unchanged. |
| Unprefixed DONOTCACHEPAGE constant | Preserved its name and redefinition guard. Added a reason and a one-line `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound` suppression at the declaration in `src/Scanner/Frontend/ScanPage.php:123`. This is an intentional interoperability exception, not a new Elavira global. Plugin Check prefixing check passed. No unrelated sniff was suppressed. |

## Caching verification

Execution path: `elavira.php` → `Core\Bootstrap::boot()` → `Core\Application::register()` → `ScannerModule::register()` → `wp_loaded` priority **0** → `ScannerModule::protect_cache_early()` → `protect_cache(false)` → `ScanPage::protect_cache(false)` → canonical URL and session authorization → guarded `DONOTCACHEPAGE` definition and `nocache_headers()`.

Previously the only signal ran at `template_redirect` priority 1, after the main request's `WP::send_headers()`. The new early hook runs after `init` (post types, rewrites and authentication are available), but before the main request, normal response headers and template rendering. This moves the opt-out ahead of later response-cache decisions. The existing `template_redirect` priority 1 hook remains to check the actual 404 result and repeat no-cache headers. It does not inspect `is_404()` prematurely on `wp_loaded`.

The flag is set only for an authenticated owner with a valid, unexpired session in CREATED or ACTIVE state and an exact authorized canonical-page match. Tests confirm normal visits, toolbar-only requests, guests, other users, invalid tokens, wrong pages, finished/failed/cancelled/expired sessions, admin, REST (including requests before REST dispatch), and cron do not set it. The existing scanner enqueue hook, nonce generation/REST validation, result endpoints and collection logic are unchanged. Authorized explicit 404 audits remain supported.

Limitations: `advanced-cache.php` executes before normal plugins; server/CDN caches may respond even before PHP. Some cache plugins make decisions during bootstrap or early hooks. Neither this hook nor the constant can prevent those earlier cache hits. Cache integrations must respect the standard opt-out at their response-write stage and bypass scanner-credential requests where necessary. A pre-existing false DONOTCACHEPAGE value is deliberately not redefined; no-cache headers are still sent. Actual cache-product/browser integration was not exercised, and universal cache prevention is not claimed.

## Executed validation

- **PHPUnit: PASS** — 325 tests, 5,521 assertions; no failures or skipped tests. Added 18 process-isolated cache scenarios.
- **JavaScript: PASS** — all 41 existing tests; no failures or skipped tests.
- **PHPCS/WPCS/PHPCompatibilityWP: PASS** — 175 files; zero errors and warnings using the configured standards.
- **Unsuppressed i18n sniff: PASS** — zero errors/warnings with annotations ignored for the `WordPress.WP.I18n` check.
- **Composer strict validation: PASS** — metadata valid. Composer reports its existing informational root-version fallback because the source copy has no Git metadata.
- **Syntax: PASS** — 184 non-vendor PHP files and seven runtime JavaScript files.
- **WordPress Plugin Check 2.1.0: PASS** — targeted `file_type,plugin_header_fields,plugin_readme,prefixing` and broader default CLI check set both exited 0 and reported `Success: Checks complete. No errors found.` No warnings were emitted. No AI, experimental checks, ignore-codes, ignore-errors or ignore-warnings options were used.
- **Readme: PASS under Plugin Check** — its parser/validator accepted the expanded readme. Every notice paragraph was also compared with the original; only headings differ.
- **Existing test ZIP: inspected, unchanged** — 159 entries, no test/tool/doc/repository/development dependency files; integrity and original SHA-256 verified. It is now stale and still contains the original warnings. No updated production package has been built or validated in this pass, as requested.

Plugin Check ran on the updated source directory with slug `elavira`. The original UI report's settings were not supplied. For release-relevant source checking, it excluded `docs,tests,tools` and `AGENTS.md,phpcs.xml.dist,phpunit.xml.dist,package.json,.gitignore,composer.lock`; Plugin Check also uses its standard vendor exclusions. These are development inputs absent from the earlier production ZIP. No production PHP, JavaScript, license or readme file was excluded. The successful default CLI run is not a claim of manual browser, assistive-technology, or arbitrary cache-product compatibility.

The global PHP CLI lacked mysqli for WordPress, so Plugin Check used the existing Local PHP 8.2.27 runtime with its already-installed extensions and the site's configured MySQL port 10029. No dependencies were installed. Plugin Check's temporary object-cache drop-in was allowed for validation and confirmed absent afterward. Unit/standards checks used PHP 8.5.5 and JavaScript used Node 22.15.0.

Evidence is in the sibling `elavira-0.2.5-validation/plugin-check-followup` directory: `plugin-check-targeted.txt`, `plugin-check-full.txt`, `phpunit.xml`, `phpcs.json`, and `i18n.json`.

## Files changed in this follow-up

- `readme.txt`: all third-party notices, with WordPress.org heading syntax.
- `THIRD-PARTY-NOTICES.md`: removed after notice preservation/reference checks.
- `elavira.php`: remove Domain Path; keep Text Domain.
- `assets/js/aria-vocabulary.js`, `assets/js/language-primary-subtags.js`: attribution references now point to readme.txt; no JavaScript behavior changed.
- `README.md`, `docs/PRE_SUBMISSION_0.2.5.md`: updated notice and packaging references; marked earlier report as historical.
- `src/Scanner/Modules/ScannerModule.php`: early authorized cache hook and retained response-stage check.
- `src/Scanner/Frontend/ScanPage.php`: optional late response check, admin/REST/cron exclusions, documented guarded standard constant.
- `tests/bootstrap.php`: request-state and header/query test doubles.
- `tests/Unit/ScanCacheTest.php`: 18 isolated request-scope/timing/redefinition regressions.
- `docs/PLUGIN_CHECK_0.2.5.md`: this report.

## Readiness

All three reported warnings are resolved in the checked source configuration. No remaining error or warning was emitted by the executed Plugin Check runs. Before submission, build the next production artifact only when requested, check that exact artifact, and complete live installation/upgrade/retention, browser/assistive-technology, supported-version, and actual caching-integration validation. The previous ZIP must not be treated as containing these changes.

References: [wp_loaded](https://developer.wordpress.org/reference/hooks/wp_loaded/), [template_redirect](https://developer.wordpress.org/reference/hooks/template_redirect/), and the installed WordPress `wp-settings.php` / `WP::main()` execution order. The installed Plugin Check prefixing implementation was inspected to select the exact naming diagnostic.
