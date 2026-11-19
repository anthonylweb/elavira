=== Elavira ===
Contributors: elavira
Tags: accessibility, audit, wcag, reports, toolbar
Requires at least: 6.4
Tested up to: 7.1
Stable tag: 0.2.6
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accessibility auditing, evidence, guidance, and human review workflows for WordPress.

== Description ==

Elavira Core audits one rendered public page at a time in the signed-in user's
browser. It groups plugin findings into reports, explains the evidence, offers
guided review and educational material, and preserves an auditable history of
human decisions.

Elavira follows Detect, Explain, Recommend, User acts, Re-audit, Verify. It does
not automatically change site content, markup, styles, themes, plugins, or
configuration. Automated testing is incomplete. An individual Elavira check
does not establish that a page or website conforms to WCAG.

Phase 1 includes Audit Reports, Guided Decisions, the Learn Center, an
Accessibility Statement workflow, and an optional visitor preference toolbar.
Elavira Premium is a separate extension and is not included with Core.

== Installation ==

1. Upload the Elavira plugin ZIP through Plugins > Add New Plugin > Upload Plugin.
2. Activate Elavira.
3. Open Elavira in the WordPress administration menu.

Elavira preserves its data and settings on deactivation and uninstall by
default. WordPress multisite is not officially supported or tested in Phase 1.

== Frequently Asked Questions ==

= Does Elavira prove that my site conforms to WCAG? =

No. Automated testing is incomplete, and many accessibility determinations
require human judgment and assistive-technology testing.

= Does Elavira automatically fix accessibility issues? =

No. Elavira provides evidence, explanations, recommendations, and review tools.
The site owner decides and performs any remediation.

= Does Core send page scans to a remote service? =

No. Core runs its single-page scanner in the user's browser and stores the audit
records in the WordPress database.

== Changelog ==

= 0.2.6 =

* Designate the pre-submission remediation build as the release candidate.
* Correct subdirectory scan targets and reject recognizable sensitive URL parameters while retaining public page selectors.
* Make rule copy extractable for translation and improve plural forms and translator comments.
* Preserve toolbar operation when browser storage is denied and use WordPress script APIs for early initialization.
* Recover gracefully from database upgrade failures and enforce WordPress page publishing capabilities.
* Expand privacy and retention disclosures, consolidate third-party notices, and set the Plugin URI to https://elavira.org/.
* Remove the unused Domain Path header and signal authorized scanner cache bypass earlier in the request lifecycle.
* Preserve audit data, approved branding, independent Core functionality, and explicit human review.

= 0.2.5 =

* Harden subdirectory scan targets, private query handling, migration recovery and statement publishing permissions.
* Improve translation extraction, privacy disclosures, licensing records and toolbar initialization when storage is denied.
* Document statement availability after deactivation.
* Reduce statement info buttons to a 24px minimum width and 20px minimum height, with surrounding spacing.
* Open hover guidance only from the info button, while keeping the open panel hoverable and preserving focus, tap, and Escape support.
* Position enhanced guidance panels absolutely to avoid form layout shifts, with wrapping constrained to the field width.
* Preserve visible inline guidance when JavaScript is unavailable. Saved statement content is preserved. Publishing now also checks WordPress page capabilities.


= 0.2.4 =

* Add plain-language information controls beside five Accessibility Statement fields, with hover, focus, tap, Escape dismissal, and inline guidance that wraps at narrow widths.
* Add the Creating an Accessibility Statement Learn article, with practical examples and guidance on honest claims, barriers, feedback, testing, and regular reviews.
* Link the article prominently from the statement editor and first-use screen.
* Preserve saved statement content, explicit publishing, and revision history. No database migration is required.


= 0.2.3 =

* Add Needs Correction workflow status with required notes, server/client validation, retained rejected input, active counts, filters, and XLSX support.
* Improve responsive report filters, review columns, activity cards, full-width fields, and repeated reset/export actions.
* Separate accessible new-tab notices from links and emphasize issue titles and human-review guidance.
* Omit generic WordPress advice using explicit editorial quality decisions; label useful advice as potential approaches.
* Expand Learn articles and remove unhelpful introduction and placeholder sections.
* Prefer first names, preserve user attribution and review history, and display generated activity notes without internal prefixes.


= 0.2.2 =

* Show the latest non-empty review note separately for each occurrence across all report sections.
* Keep the latest note visible after status-only updates and leave the review input empty after saving.
* Retrieve notes in one batch for displayed entries, with deterministic timestamp and record-ID ordering.
* Preserve full review history and safely display multiline notes with narrow-screen wrapping.

= 0.2.1 =

* Added independent occurrence review, persisted decisions, and conservative cross-audit matching.
* Separated active, addressed, accepted, and total occurrence counts; accepted findings remain available in a collapsed section.
* Preserved legacy review history with an additive migration and flagged ambiguous grouped decisions for review.
* Displayed escaped element IDs and classes alongside hierarchical selectors.
* Added contextual ARIA Learn guidance and corrected presentation-role and case-sensitive ID-reference handling.
* Rejected missing pages during normal audits and added an explicit Audit 404 page option.
* Improved keyboard navigation, review feedback focus, labels, and status announcements.

= 0.2.0 =

* Made Audit Reports clearer and improved occurrence and element identification.
* Added actionable guidance across the current Core rules while preserving historical definitions.
* Improved Guided Decisions, Learn Center content, and linked-image guidance.
* Added finding activity notes, including useful same-status activity updates.
* Improved report navigation, responsive behavior, and accessibility.

= 0.1.0 =

* Initial development release of the Phase 1 Core workflow.

== Upgrade Notice ==

= 0.2.6 =

Release candidate with security, translation, privacy, licensing and scanner cache-readiness improvements. Existing audit data is retained; no new database migration is required.

= 0.2.5 =

Refines statement info buttons and floating guidance panels. No database migration is required.


= 0.2.4 =

Adds statement-writing help and a Learn article. Existing saved content and publishing behavior are preserved; no database migration is required.


= 0.2.3 =

Adds Needs Correction and report/Learn UX improvements. Existing reviews and history are preserved; no database migration is required.


= 0.2.2 =

Displays existing occurrence review notes in reports. No database migration is required.

= 0.2.1 =

Adds occurrence-level review storage without deleting existing audit history. Back up the database before upgrading; review ambiguous legacy grouped decisions individually.

= 0.2.0 =

Adds the Audit Report UX milestone while retaining existing audit and history data.

== Data, privacy and retention ==

Audit URLs, including permitted query parameters, bounded page excerpts and element evidence, findings, review notes, user IDs and timestamps, Guided Decisions, and statement revisions are stored in this site's WordPress database. Public statements and exports can contain personal information supplied by editors or found on audited pages. Only audit public pages and avoid confidential input. Recognizable credential, nonce, session, email and phone parameters are rejected before new scans; public page selectors and filters are preserved. This safeguard cannot identify all sensitive data and does not rewrite historical records.

Core makes same-site requests for scan preflight and authenticated report submission. It has no telemetry or external auditing service. Toolbar choices use the browser-local elaviraToolbarPreferences key; they are not sent to WordPress or used to infer disability. Reset Preferences removes the stored choices. If storage is denied, choices work for the current page only.

Audit data is retained indefinitely, including after deactivation and uninstall. There is no automatic erasure or retention scheduler. Administrators must arrange any required removal, including backups and exported copies. Review exported reports before sharing. Suggested privacy policy text is available in WordPress Privacy settings and Elavira Data & Privacy.

== Statement availability ==

Elavira renders the public accessibility statement from approved structured records while active. Deactivation or uninstall preserves those records and the associated WordPress page, but the statement body is unavailable. Before deactivating, copy approved content into a standalone WordPress page if the statement must remain public. Reactivation restores rendering when the retained schema is available. Publishing requires Elavira statement management and WordPress page editing/publishing permissions, including access to the connected page.

== Third-Party Licenses and Notices ==

Elavira's original code is GPL-2.0-or-later; see LICENSE for the complete GPL v2
text and the notice permitting later versions. The components below retain their
own compatible licenses. No third-party service is contacted to load these assets
at runtime. Verification date: 2026-11-04.

= Atkinson Hyperlegible =

- File: `assets/fonts/AtkinsonHyperlegible-Regular.woff2` (23,196 bytes).
- Copyright 2020 Braille Institute of America, Inc.
- Version 1.006; ttfautohint v1.8.3, as recorded in the embedded name table.
- License: SIL Open Font License 1.1, included in `assets/fonts/OFL.txt`.
- Source: https://github.com/googlefonts/atkinson-hyperlegible/tree/main/fonts/webfonts
- Exact upstream file verified by SHA-256:
  https://raw.githubusercontent.com/googlefonts/atkinson-hyperlegible/main/fonts/webfonts/AtkinsonHyperlegible-Regular.woff2
- SHA-256: `2df4ba17804bc7a36f123127966075d8427bff2df58d0d76820c1130bb1a4150`.
- The bundled binary is unmodified and matches upstream byte-for-byte. Font
  copyright and OFL declarations were also read from its embedded name table.
  This is a locally served font; the Google Fonts CSS service is not used.

= WAI-ARIA vocabulary =

- File: `assets/js/aria-vocabulary.js`.
- Source: Accessible Rich Internet Applications (WAI-ARIA) 1.2,
  W3C Recommendation, 6 June 2023: https://www.w3.org/TR/2023/REC-wai-aria-1.2-20230606/
- Copyright © 2013–2023 W3C® (MIT, ERCIM, Keio, Beihang).
- License: W3C Software and Document License, 2015 version. Complete notice:
  `licenses/W3C-2015.txt`.
- Modification: Elavira extracts a bounded vocabulary of roles, attributes,
  enumerations and reference semantics into JavaScript data for scanner checks.
  This is not a complete implementation of the specification or W3C endorsement.

= IANA language registry =

- File: `assets/js/language-primary-subtags.js`.
- Source: https://www.iana.org/assignments/language-subtag-registry/language-subtag-registry
- Bundled snapshot identifies registry File-Date 2026-08-08.
- Modification: primary language subtags extracted into a JavaScript array;
  registry descriptions and other record types are not included.
- License: IANA protocol parameter registries are available under CC0 1.0:
  https://www.iana.org/help/licensing-terms
- CC0 legal text: https://creativecommons.org/publicdomain/zero/1.0/legalcode

= Composer autoloader =

- Generated runtime files: `vendor/autoload.php` and `vendor/composer/*` in a
  production build. Composer's generated ClassLoader carries its MIT license;
  retain `vendor/composer/LICENSE` when packaging.
- Source: https://github.com/composer/composer
- There are no production Composer library dependencies. Development dependencies
  in composer.lock are testing and standards tools, not release payloads. Their
  individual notices remain in the development vendor directory; do not ship it.

= Original assets =

Elavira's approved branding and project-authored PHP, JavaScript, CSS and SVG
assets are covered by the project's GPL-2.0-or-later declaration. The branding
files were not modified by this remediation. The project owner remains responsible
for confirming rights to original artwork before submission; a source-code audit
does not establish chain of title.
