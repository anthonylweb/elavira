# Elavira Core 0.2.5 — pre-submission remediation

Follow-up: see `PLUGIN_CHECK_0.2.5.md` for the subsequent notice consolidation, cache timing correction, and successful Plugin Check runs. The validation counts below are the earlier remediation snapshot.

Date: 2026-11-04. Decision: **CONDITIONAL — ready for the next validation stage, not yet cleared for WordPress.org submission.**

Implementation is in this isolated `elavira-0.2.5-remediation` directory, based on installed repository commit `dd78f48f9a01a15c963c2e9ace87809869f2cc70`. The installed plugin, original release package, and synced project sources were not modified. The installed repository remained clean when checked after remediation. No final ZIP was created. No dependencies were installed.

## Completed remediation

| Requirement | Result and behavior |
| --- | --- |
| Subdirectory scanner URLs | Build the current request from the WordPress origin, avoiding duplicated home paths. Reject internal endpoints relative to both home and WordPress installation paths, including configured REST prefixes. Plain-permalink REST URLs do not block ordinary `?p=42` pages. Reject ambiguous encoded/traversal paths and targets outside the site's home path. Preserve non-default origin ports. |
| Sensitive query policy | Reject recognized credential, nonce, session, contact and internal parameters, including nested/camel-case/encoded keys and email/Bearer/JWT-like values. Validate before preflight, persistence, and each redirect hop. Preserve other public selectors and filters; do not silently strip parameters and audit a different page. Only the Elavira launch credential is removed from canonical identity. Return actionable feedback. This is a bounded policy, not a guarantee that arbitrary secrets can be detected. Historical records are not rewritten. |
| Extractable rule copy | Move Round 3–6 titles, summaries and descriptions to literal gettext calls at definition sites. Remove obsolete dynamic-string suppressions. Translate code-example labels separately from fixed markup. Preserve rule IDs, English content and historical versions. |
| Plugin URI | Set to `https://elavira.org/`. Release version remains 0.2.5; schema remains 0.8.0. |
| Licensing | Include the complete GPL v2 text with the existing GPL-2.0-or-later grant; add third-party provenance and the W3C 2015 license. Preserve the font's OFL. Add license references to derived vocabulary files. |
| Privacy | Expand readme and settings disclosures; register suggested WordPress privacy-policy text. Cover URLs/query parameters, evidence/excerpts, notes, user attribution/timestamps, statement contacts/revisions, exports, same-site requests, indefinite retention and browser-local preferences. No automatic deletion or historical data rewrite. |
| Denied localStorage | Catch access to the storage property itself, as well as failed operations. Toolbar opens, adjusts preferences and resets in memory when storage is unavailable. |
| Early toolbar initialization | Replace direct head-script output with a head-enqueued readable script and `wp_add_inline_script(..., 'before')` configuration. Apply only enabled, validated saved preferences before body rendering. Keep toolbar interaction in the footer. |
| Migration failure recovery | Catch upgrade exceptions, preserve the last successfully recorded schema state and existing data, expose a bounded administrator notice, and retry on a later request. Block new scans while the update is unavailable; avoid statement database rendering during that request. No raw SQL or exception details are displayed. Existing migrations are unchanged. |
| Statement publishing authorization | Retain Elavira capability and nonce checks. Additionally require `edit_pages` and `publish_pages`, plus `edit_post` for an associated page. Provide a specific authorization notice; unauthorized requests do not call the page-write API. |
| Deactivation statement behavior | Clearly document in the editor and readme that structured statements require the active plugin. Records and the associated page remain, but the public body is unavailable after deactivation/uninstall. Explain copying approved content to a standalone page before deactivation when ongoing availability is required. No automatic publication or content replacement was introduced. |
| Translator comments/plurals | Add placeholder comments to Dashboard/Settings strings. Use `_n()` for configured visitor preferences and available Guided Decisions, with separate singular/plural messages. |

No Freemius, premium licensing, automated accessibility remediation, production dependency, or telemetry was added. Approved branding, CSS and the bundled font bytes are unchanged. Existing data-retention behavior and independent Core functionality remain intact. Automated audit results are not described as proof of WCAG conformance.

## Validation actually completed

Environment: Windows; PHP CLI 8.5.5; Node.js 22.15.0; PHPUnit 9.6.36. Existing development dependencies were reused.

| Check | Result |
| --- | --- |
| Full PHPUnit suite | **PASS:** 307 tests, 5,479 assertions; no failures or risky tests. |
| Full JavaScript suite | **PASS:** 41 tests; no failures or skipped tests. |
| PHPCS/WPCS/PHPCompatibilityWP configured suite | **PASS:** 174 files; zero errors and zero warnings. Compatibility configuration targets PHP 7.4 onward. No new suppressions added. |
| Unsuppressed gettext sniff | **PASS:** `WordPress.WP.I18n` over `src`, ignoring annotations; zero errors/warnings. |
| Composer metadata | **PASS:** `composer --no-plugins --no-scripts validate --strict`, exit 0. Composer emitted an informational root-version fallback to 1.0.0 because this isolated copy has no Git metadata; plugin/header/readme version is 0.2.5. |
| PHP syntax | **PASS:** all 183 non-vendor project PHP files. |
| JavaScript syntax | **PASS:** all seven runtime scripts, including the new early-state script. |
| Font provenance | **PASS:** bundled WOFF2 exactly matches the upstream project file (23,196 bytes; SHA-256 `2df4ba17804bc7a36f123127966075d8427bff2df58d0d76820c1130bb1a4150`). Embedded copyright/version/OFL metadata inspected. |
| Branding/assets | **PASS for preservation:** non-JavaScript asset hashes match the baseline, including font and CSS. |

New regression coverage includes subdirectory identity, internal/encoded endpoints, custom REST prefixes, plain permalinks, sensitive/nested query parameters, redirect rejection before a sensitive request, no audit writes on rejection, denied storage access/operations, early valid/invalid preference handling, enqueue timing/configuration, publication permissions, migration failure and retry through schema 0.8.0, front-content fallback, scan blocking during a failed migration, and extractability of every affected round's editorial fields. Existing guidance assertions were adjusted to verify translated labels inside fixed code examples.

PHP tests use the project's WordPress function stubs and in-memory database. JavaScript tests use fixtures. These passing tests do not establish live WordPress, database-engine, browser or WCAG conformance.

Reproduction commands:

```text
php vendor/bin/phpunit --do-not-cache-result
node --test tests/js/*.test.js
php vendor/bin/phpcs --no-colors
php vendor/bin/phpcs -q --no-colors --ignore-annotations --standard=WordPress --sniffs=WordPress.WP.I18n src
composer --no-plugins --no-scripts validate --strict
npm run lint
```

PHP syntax was checked with `php -l` for every PHP file returned by `rg --files -g '*.php' -g '!vendor/**'`.

Machine-readable evidence and the review patch are in the sibling `elavira-0.2.5-validation` directory: `phpunit.xml`, `phpcs.json`, `i18n.json`, `modified-files.json`, and `remediation.patch`.

## Outstanding submission gates

No confirmed high- or medium-priority implementation defect from the requested remediation list remains unresolved. The following checks are **not verified for this candidate**, and remain required before submission:

1. Run current WordPress Plugin Check against a production-only candidate; review every diagnostic rather than suppressing it. This tool was not installed in the available environment.
2. On a disposable WordPress site, test clean activation, upgrade with existing records, deliberate database-permission failure/recovery, deactivation/reactivation and uninstall retention. The unit-test database is not MySQL/MariaDB.
3. Run end-to-end scans at the domain root, in a subdirectory, and with different home/site paths; test plain and pretty permalinks, safe query-dependent pages, denied private queries and redirect chains. Verify actual HTTP/cache behavior and REST authentication.
4. Test publishing with administrator, custom statement-manager and restricted page-editing roles. Confirm drafts remain private and connected pages cannot be overwritten without permission. Verify the documented deactivation behavior on a public statement page.
5. Test the toolbar in real browsers with denied storage, head loading, keyboard-only operation, zoom/reflow, high contrast and representative screen readers against WCAG 2.2 AA. DOM fixtures are not assistive-technology testing. Safari/iOS and real screen-reader coverage are not established here.
6. Test a non-English WordPress locale and standard WordPress POT extraction/import. The static gettext and token-extraction checks passed; the actual WordPress translation toolchain and rendered translations were not run.
7. Validate supported WordPress/PHP versions and the readme's `Tested up to` claim for this candidate. Static PHPCompatibility analysis is not a runtime compatibility matrix.
8. Confirm ownership/authorization for original project branding, the WordPress.org contributor account, and the live Plugin URI/support destinations. Third-party font/vocabulary provenance is documented; a code audit cannot establish original-artwork chain of title.
9. Produce and inspect the production package only after the live validation gates pass. Include the new `src/Core/Privacy` class, `assets/js/toolbar-state.js`, LICENSE, readme.txt (Third-Party Licenses and Notices), licenses/W3C-2015.txt and the existing OFL. Use production Composer autoload files and retain their MIT notice. Exclude development vendor packages, tests, tools, validation outputs, repository metadata and historical/internal documentation. Verify autoload, archive root and package contents, then run Plugin Check and clean-install validation on the exact submission artifact.

Known design limitations are disclosed, not hidden: sensitive query recognition is bounded; data persists indefinitely; public structured statements require the active plugin; multisite support is not claimed. None is presented as a proven violation merely because it requires documentation or site-owner judgment.

## References

- [WordPress.org Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) — GPL compatibility, developer responsibility, readable source, tracking, external code and completeness.
- [WordPress security handbook](https://developer.wordpress.org/plugins/security/) and [checking user capabilities](https://developer.wordpress.org/plugins/security/checking-user-capabilities/).
- [WordPress internationalization](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) and [script APIs](https://developer.wordpress.org/reference/functions/wp_add_inline_script/).
- [Privacy-policy suggestions](https://developer.wordpress.org/reference/functions/wp_add_privacy_policy_content/).
- [W3C Software and Document License 2015](https://www.w3.org/copyright/software-license-2015/) and [IANA licensing terms](https://www.iana.org/help/licensing-terms).
- [Atkinson Hyperlegible upstream](https://github.com/googlefonts/atkinson-hyperlegible).

## Modified and added files

The following list is relative to this remediation directory. No baseline source files were deleted. The development vendor tree was copied for testing and was not edited; it is not a submission payload.

- Modified: `LICENSE`.
- Modified: `README.md`.
- Updated: `readme.txt` (third-party notices consolidated during the Plugin Check follow-up).
- Modified: `assets/js/aria-vocabulary.js`.
- Modified: `assets/js/language-primary-subtags.js`.
- Added: `assets/js/toolbar-state.js`.
- Modified: `assets/js/toolbar.js`.
- Modified: `elavira.php`.
- Added: `licenses/W3C-2015.txt`.
- Modified: `package.json`.
- Modified: `readme.txt`.
- Modified: `src/Admin/DashboardPage.php`.
- Modified: `src/Core/Lifecycle/UpgradeManager.php`.
- Modified: `src/Core/Modules/CoreModule.php`.
- Added: `src/Core/Privacy/PrivacyPolicy.php`.
- Modified: `src/Rules/CoreRuleGuidance.php`.
- Modified: `src/Rules/RoundFiveRuleDefinitions.php`.
- Modified: `src/Rules/RoundFourRuleDefinitions.php`.
- Modified: `src/Rules/RoundSixRuleDefinitions.php`.
- Modified: `src/Rules/RoundThreeRuleDefinitions.php`.
- Modified: `src/Scanner/Admin/RunAuditPage.php`.
- Modified: `src/Scanner/Application/StartSinglePageScan.php`.
- Modified: `src/Scanner/Frontend/ScanPage.php`.
- Modified: `src/Scanner/Security/CanonicalUrl.php`.
- Modified: `src/Scanner/Security/PageResponse.php`.
- Modified: `src/Settings/Admin/SettingsPage.php`.
- Modified: `src/Statement/Admin/StatementPage.php`.
- Modified: `src/Statement/FrontendStatement.php`.
- Modified: `src/Toolbar/FrontendToolbar.php`.
- Modified: `src/Toolbar/Modules/ToolbarModule.php`.
- Modified: `tests/Unit/CoreRuleGuidanceTest.php`.
- Added: `tests/Unit/RuleTranslationExtractionTest.php`.
- Modified: `tests/Unit/SinglePageScanTest.php`.
- Added: `tests/Unit/SubmissionRemediationTest.php`.
- Modified: `tests/Unit/ToolbarTest.php`.
- Modified: `tests/bootstrap.php`.
- Added: `tests/js/toolbar-storage.test.js`.
- Added: `docs/PRE_SUBMISSION_0.2.5.md` (this report).

**Recommendation:** proceed with the isolated 0.2.5 candidate to live WordPress and accessibility validation. Do not submit the old package or treat this automated gate as final directory approval.
