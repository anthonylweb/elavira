# Elavira 0.2.2 — latest occurrence review notes

Notes were already persisted in occurrence history. Reports now show the latest non-empty note below the occurrence status and above its form in Active, Addressed, Accepted, and Verified sections. A later status-only update preserves that display; new-note inputs stay empty. Complete history remains available in details.

One prepared batch query covers only the paginated, displayed occurrences (at most 80 across four sections). Latest means greatest created_at, then greatest history ID. Blank/whitespace-only notes and legacy grouped records without an occurrence association are excluded. Notes are escaped as text, preserve line breaks, and wrap long words. Existing status, security, exception, and count behavior is unchanged. No schema, scanner, ruleset, or editorial version changes were needed.

## Validation

- composer check: 289 PHP tests, 4,585 assertions; coding standards and configured compatibility checks passed.
- npm test: 34 JavaScript tests passed; npm run lint passed.
- Microsoft Edge production report fixture: save/reload, note-only saves, status-only retention, separate occurrence notes, empty inputs, accepted disclosure, keyboard navigation, focus feedback, labels, and 320px reflow passed.
- PHP coverage checks equal-time ID tie-breaking, non-empty selection, legacy exclusion, full history retention, one query for multiple occurrences, escaping, and rendering across all four statuses.
- Visually reviewed the narrow-screen browser screenshot with a long multiline note.

The browser fixture uses production report code with a test database adapter; this run did not exercise a live WordPress/MySQL installation or an actual screen reader. Prior-audit notes remain in the linked historical occurrence rather than being attributed to a newly captured occurrence. The installed plugin was not modified.

## Next staging checks

1. Upgrade from 0.2.1 with existing occurrence notes and confirm they appear without a new save.
2. Save different notes on two occurrences of the same rule; reload and confirm independence.
3. Add a second note without changing status, then change only status. Confirm the second note remains and complete history is intact.
4. Check Active, Addressed, expanded Accepted, and existing Verified entries; verify no-note entries omit the display.
5. Test multiline text, special characters, and long words at 320px and browser zoom; verify save feedback with keyboard and a screen reader.
6. Confirm notes do not appear across unrelated audits or legacy grouped occurrences, and compare report counts before/after review changes.
