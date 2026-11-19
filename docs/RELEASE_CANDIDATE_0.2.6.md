# Elavira Core 0.2.6 release candidate

Version 0.2.6 is the designated release candidate. It includes the 0.2.5 pre-submission remediation and subsequent Plugin Check fixes. This designation does not assert WordPress.org acceptance or WCAG conformance.

## Release metadata

- Plugin header, ELAVIRA_VERSION and readme Stable tag: 0.2.6.
- Database schema: 0.8.0, unchanged; no new migration.
- Scanner, ruleset and editorial contract versions: unchanged.
- Plugin URI: https://elavira.org/.
- License: GPL-2.0-or-later. Third-party notices are in readme.txt.
- Core remains independent of Premium. No Freemius, telemetry or automated remediation was added.

## Validation

The version designation was verified with the full PHP suite (325 tests, 5,521 assertions), full JavaScript suite (41 tests), configured PHPCS/WPCS/PHPCompatibility checks (175 files, zero findings), JavaScript syntax checks and Composer strict metadata validation. The full default Plugin Check 2.1.0 CLI run on the actual 0.2.6 checkout passed with no errors or warnings, using the development-file exclusions documented in PLUGIN_CHECK_0.2.5.md. PHP syntax checks passed for all 184 non-vendor PHP files.

The preceding source-equivalent remediation also passed the broader default Plugin Check CLI run and targeted file/header/readme/prefix checks; see PLUGIN_CHECK_0.2.5.md for configuration and evidence limits. The only changes after that broader run are release metadata, changelog, documentation and matching test expectations. Composer validation passed without the isolated-copy root-version notice in this Git checkout.

## Remaining validation before submission

Validate the exact future production artifact with Plugin Check and clean-install/upgrade/retention tests. Complete supported-version, real browser, keyboard/screen-reader and cache-product integration checks. Scanner cache signals cannot prevent responses served before WordPress runs. Original artwork ownership and account/support metadata remain owner-verification items.

The previous 0.2.5 test ZIP is stale and does not contain the Plugin Check fixes or this version designation. No 0.2.6 ZIP or release tag was created as part of the version update.
