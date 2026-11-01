=== Elavira ===
Contributors: elavira
Tags: accessibility, audit, wcag, reports, toolbar
Requires at least: 6.4
Tested up to: 7.1
Stable tag: 0.2.5
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

= 0.2.5 =

* Reduce statement info buttons to a 24px minimum width and 20px minimum height, with surrounding spacing.
* Open hover guidance only from the info button, while keeping the open panel hoverable and preserving focus, tap, and Escape support.
* Position enhanced guidance panels absolutely to avoid form layout shifts, with wrapping constrained to the field width.
* Preserve visible inline guidance when JavaScript is unavailable. Saved content and publishing behavior are unchanged.


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
