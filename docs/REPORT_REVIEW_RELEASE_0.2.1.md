# Elavira 0.2.1 — validation and next testing round

## Delivered changes

- Reports show safely escaped actual element IDs and CSS classes alongside hierarchical selectors. Classes are explicitly described as potentially shared.
- ARIA findings link to contextual Learn guidance and authoritative WAI-ARIA references. Presentation/none conflict resolution, native checkbox semantics, and case-sensitive ID references have regression coverage.
- Each occurrence has independent review state and activity history. Addressed entries follow active entries with text and a non-color visual cue; they do not claim verification. Accepted entries appear in a collapsed section at the bottom.
- Summaries separately count active, addressed, accepted, verified, and total detected occurrences. Rule-group counts are labeled separately.
- Re-audits carry forward only conservatively matched decisions using page, rule, element, and evidence identity. Ambiguous matches require review. Reset exceptions restores applicable accepted occurrences; absent historical occurrences are not inserted into current reports.
- Schema 0.8.0 adds occurrence status, previous-occurrence links, and occurrence IDs in review history. Existing history is retained; ambiguous legacy grouped decisions are flagged rather than guessed.
- Normal scans reject HTTP/WordPress missing pages. Intentional 404 scanning requires the explicit option. Redirects remain subject to URL/security validation; blocked, failed, and server responses remain distinct from missing pages.

Release metadata: plugin 0.2.1; schema 0.8.0; scanner core-browser-1.5.2. Existing ruleset and editorial contract versions remain unchanged.

## Executed validation

- `composer check`: 286 PHP tests, 4,567 assertions; WordPress coding standards and configured PHP compatibility checks.
- `npm test`: 34 JavaScript tests.
- `npm run lint`: syntax validation for scanner, toolbar, vocabulary data, and report feedback script.
- Headless Microsoft Edge report fixture: independent status saves; active/addressed/accepted ordering; collapsed accepted counts; reconsideration; keyboard disclosure and tab order; feedback focus and status role; accessible control labels; unique IDs; 320px reflow.
- PHP tests cover staged scan/review/re-audit/reset behavior, conservative matching, additive migration, safe output, ARIA guidance, missing pages, explicit 404 intent, redirects, and transport failures.

Browser evidence is in `dist/review-qa/browser-results.json` and `dist/review-qa/report-320.png`. The browser fixture uses production report controllers/views and review services with a test database/session adapter.

## Remaining validation boundaries

This run did not install or upgrade a live WordPress database, exercise the complete live WordPress HTTP/admin route stack, or use an actual screen reader. Database migration and HTTP behavior were tested with controlled adapters. Automated checks do not establish WCAG conformance. Identity matching intentionally declines ambiguous cases; bounded element metadata can omit oversized IDs and truncate very long class lists.

The installed plugin was not modified. This workspace copy was based on the existing development tree, including its earlier uncommitted work. The ZIP is ready for staging validation.

## Practical staging checklist

1. Back up the staging database and plugin. Install the ZIP on a fresh site and upgrade a copy containing legacy grouped decisions. Confirm activation, schema version 0.8.0, retained audit/history data, and migration retry behavior.
2. Audit a page with several occurrences of one rule, distinct IDs/classes, shared classes, and special characters. Verify the technical details and escaping.
3. Address one occurrence and accept another. Confirm the remaining occurrence is unchanged, ordering is correct, accepted entries start collapsed, all counts reconcile, and each history is independent.
4. Reorder the page DOM and re-audit. Confirm stable matches retain eligible decisions, duplicate/ambiguous targets require review, and removed targets do not appear as current findings.
5. Reset exceptions and refresh the report, then re-audit. Confirm applicable accepted occurrences return to review and histories remain available. Reconsider an individual accepted occurrence without changing another.
6. Test valid decorative presentation/none roles, focusable presentation roles, global ARIA conflicts, and mixed-case ID references. Follow contextual Learn links and verify guidance against the rendered element.
7. Submit a missing URL normally, then explicitly audit the 404 page. Test same-origin redirects, disallowed destinations, redirect loops, 401/403, 500, and timeout responses. Confirm no failed request creates a successful report.
8. Use keyboard only and NVDA/Firefox or VoiceOver/Safari. Verify labels, disclosure state, focus after saves, announced feedback, visible focus, and 200–400% zoom/reflow. Repeat the core flow in Chrome, Firefox, and Safari.
9. Export a report and compare occurrence statuses/counts to the screen. Confirm permission/nonce checks using an unauthorized account and an expired form.
