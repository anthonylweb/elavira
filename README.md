# Elavira

Elavira is a WordPress accessibility auditing and guidance plugin. Core Phase 1
provides rendered single-page audits, grouped findings and reports, Guided
Decisions, a bundled Learn library, a structured Accessibility Statement, and
an optional visitor preferences toolbar.

Elavira follows **Detect → Explain → Recommend → User acts → Re-audit →
Verify**. It does not automatically modify site content, markup, styles,
accessibility attributes, themes, plugins, or configuration. Automated testing
is incomplete and Elavira does not guarantee accessibility or WCAG conformance.

## Requirements and installation

- WordPress 6.4 or later.
- PHP 7.4 or later.

Install the production plugin ZIP through **Plugins → Add New Plugin → Upload
Plugin**, then activate Elavira. Site administrators receive the Core
capabilities during activation. The release ZIP includes the generated Composer
autoloader; site owners do not need Composer.

The PHP and WordPress minimums above are the current executable package
requirements. Elavira targets the latest stable WordPress release while
retaining those minimums. Phase 1 browser support is Chrome, Firefox, Edge, and
Safari on desktop and mobile where applicable. Individual release-checklist
results distinguish that support policy from browser/platform combinations that
were not available for validation.

Elavira Core is free software intended for distribution through WordPress.org
under GPL-2.0-or-later. Premium is a separate extension and is not bundled with
Core. WordPress multisite is not officially supported or tested in Phase 1.

## Core scanner limitations

Core scans one public URL at a time in the signed-in user's browser. Keep the
scan page open until it finishes. The scanner evaluates the rendered DOM after a
bounded stabilization period, but cannot detect every accessibility issue or
replace manual and assistive-technology testing. Core does not perform
site-wide scans, use a remote browser service, or continue scans as background
jobs after the browser session is abandoned.

## Privacy and stored data

Audit records, findings, evidence, workflow history, Guided Decision history,
and Accessibility Statement data are stored in this WordPress site's database.
Core does not send scans to a remote Elavira browser service. Scanner evidence
is bounded and does not include form values, cookies, authentication tokens, or
full-page HTML snapshots.

When enabled, the visitor toolbar offers reversible presentation preferences;
it is not remediation and does not make an inaccessible site conformant.
Preferences remain in that visitor's browser storage and do not create a
disability or diagnosis profile in WordPress.

Deactivation and uninstall preserve Elavira data and settings by default. This
prevents an uninstall or temporary deactivation from silently destroying audit,
finding, decision, statement, or history records.

## License

Elavira is licensed under GPL-2.0-or-later. See [LICENSE](LICENSE).

## Development

Install development dependencies and run the complete project check:

```sh
composer install
composer check
```

Elavira has no production Composer package dependencies. Release artifacts must
still include Composer's generated `vendor/autoload.php`; WordPress site owners
must not be required to run Composer after installing the plugin.

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the module layout and
current architectural boundaries.
