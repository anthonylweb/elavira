# Elavira 0.2.3 — report and Learn UX

Release date: October 31, 2026. Version incremented once from 0.2.2 after the implementation checks passed. Plugin header, runtime constant, Stable tag, lifecycle/version tests, changelog, and upgrade notice agree. Schema, scanner, ruleset, and editorial contract versions are unchanged. No database migration or historical-record rewrite is required.

Installable package: `dist/elavira-0.2.3.zip` (154 runtime files, 339,659 bytes). Production Composer autoload smoke passed. SHA-256: `b7e896c24617cab00bbd6356f49b3f7ae9420e4506bb05bd20865c2fe7430f87`.

## Numbered completion checklist

All 18 items were checked against the four available rendered reference pages (`tmp/pdfs/ux-1.png` through `ux-4.png` in the project workspace).

1. **Complete — separate new-tab notices.** Captured-page and report-to-Learn links contain only the URL/title. Adjacent notice text is outside the anchor; a unique accessible description supplies the notice once.
2. **Complete — centered filters.** Filters wrap as label/control pairs, with consistent alignment and the submit button in the same centered group.
3. **Complete — guidance quality.** Rule editorial definitions explicitly approve useful WordPress remediation. Generic and unreviewed advice is omitted, including the screenshot’s empty-link advice. No source owner or location is inferred.
4. **Complete — full-width fields.** Scoped text/search fields and textareas fill their assigned containers. Textareas remain vertically resizable and bounded.
5. **Complete — activity cards.** Finding and occurrence history use div-based cards with headings and definition-list metadata, in chronological order without numbered markers.
6. **Complete — Learn width.** Article headings, prose, and sections use available plugin content width and wrap at narrow widths.
7. **Complete — actor names.** Nonblank first name, then username, then historical User ID fallback. System actors remain “Elavira audit”; email/display-name fallback is not used. Stored attribution is unchanged.
8. **Complete — Needs Correction.** Supported in workflow persistence, independent occurrence reviews, activity, filters, and XLSX. Client/server require a nonblank note. Rejected input is retained in a short-lived user/finding/occurrence-scoped draft and restored with an associated error. Blank rejection writes no status or history. Review classification is unchanged.
9. **Complete — summary.** Needs Correction is counted in occurrences, explicitly included in active work. Grouped findings are labeled separately. Whole-audit summaries retain existing filter semantics. Dashboard grouped correction counts are separate from human-review classification and priority.
10. **Complete — repeated actions.** Matching top/bottom groups have equal-width responsive buttons. Export uses white on dark blue; resets stay secondary. Capability/ownership/nonces and reset consequences remain intact; repeated groups contain no duplicate IDs.
11. **Complete — issue titles.** Titles are bold in the issue table and current occurrence report rows; descriptions remain normal weight.
12. **Complete — human-review emphasis.** The requested “Human Review — this check needs your judgement.” notice is bold, including the combined structural notice.
13. **Complete — generated notes.** New decision activity stores plain notes, while decision records retain ID/version/outcome/answers. Legacy prefix removal requires exact matching persisted decision provenance; arbitrary or uncertain user text is unchanged.
14. **Complete — combined structural notices.** One notice includes the finding-specific structural concern, bold human-review wording, and the limitation that automation alone does not establish an accessibility failure. Evidence and rule classification remain available.
15. **Complete — useful remediation.** Retained sections use “Potential Ways to Address in WordPress”, bold introductory fix labels, and explicitly describe approaches as conditional on the site implementation.
16. **Complete — occurrence introduction removed.** “Where we found it” retains occurrences, page links, technical details, and controls without the requested boilerplate paragraph.
17. **Complete — useful Learn sections.** “How Elavira helps” is omitted. Article Guided Decisions sections show usable educational reviews only; placeholder-only finding links are omitted. Compatible finding decisions remain functional.
18. **Complete — three review columns.** Status, Review Note, Latest Note share a row when container width supports useful fields, and stack in that document order on narrow screens. Save sits beneath its own note field. Reviews remain occurrence-specific.

## Validation evidence

- `composer check`: **296 PHP tests, 4,585 assertions**, all passed; full configured WordPress coding standards and PHP compatibility scan passed for **170 files**.
- `npm test`: **34 tests passed**. `npm run lint` and `composer validate --strict` passed.
- New automated tests cover grouped/occurrence correction persistence, blank-note rejection without partial writes, unchanged review classification, active counts and filters, actor fallbacks, draft isolation/escaping, guidance approval, generated-note provenance, and plain new decision notes.
- Existing occurrence browser regression passed: latest nonempty notes, note-only saves, status-only retention, independent reviews, addressed ordering, accepted disclosure/reconsideration, focus feedback, labels, and mobile reflow.
- Microsoft Edge rendered production-controller fixture at **1440, 800, and 320 CSS pixels**: layout, long URLs/notes, filter pairs, field widths, labels, unique IDs, keyboard order, visible focus, combined notices, cards, Learn width, and retained input errors passed. Screenshots were visually inspected. The fixture uses the actual report/controller/services with a test database adapter and WordPress API stubs.
- Browser tests bypassed client validation to exercise the real rejected-POST controller path, then saved and reloaded a valid correction note. Both reset scopes were invoked from the repeated action groups and retained activity history.
- The downloaded XLSX was opened as an OOXML archive and its sheets verified: one Needs Correction occurrence, one active, one addressed, one accepted, three total; the new status is present in Affected URLs and Finding Activity. Runtime test PHP was started with its ZIP extension enabled.
- Existing scanner/unit coverage for selectors, ARIA guidance, reviewed exceptions, and explicit 404 auditing remains green. No scanner logic changed.
- Package validation: one `elavira/` root, runtime-only contents, production optimized autoload, source/package byte equality, valid ZIP, and SHA-256 recorded. Previous release packages and release documentation remain intact.
- No Git repository is present in this mirror, so a Git diff check was unavailable. Read-only `sources/` files were not edited. The installed WordPress plugin and live audit data were not modified.

## Limitations and staging checklist

Live WordPress/MySQL upgrade/install, real assistive-technology announcements, physical mobile/tablet browsers, true browser zoom, and spreadsheet application imports were not exercised. The fixture and automated checks do not establish WCAG conformance. Historical generated notes whose provenance cannot be matched exactly remain unchanged deliberately.

Before production rollout:

1. Install the ZIP on staging over 0.2.2. Confirm existing reports, accepted decisions, latest notes, and full history survive unchanged.
2. With keyboard and a screen reader, choose Needs Correction, submit blank/whitespace, correct the error, save, and reload. Check first-name, username, deleted-user, and system attribution.
3. Review long URLs/notes at desktop/tablet/mobile widths, 200%/400% zoom, and enlarged text spacing; verify Status → Review Note → Latest Note order and visible focus.
4. Compare report and XLSX counts, open the workbook in the supported spreadsheet applications, and verify both top/bottom reset and export actions on disposable review data.
5. Re-audit representative reviewed/accepted and addressed occurrences; verify independent decisions, selectors/ARIA guidance, and intentional versus ordinary 404 auditing.

Browser scripts: `tools/ux-browser-qa.cjs`, `tools/occurrence-browser-qa.cjs`. Fixture: `tools/ux-browser-fixture.php`. Evidence: `dist/ux-qa/`.
