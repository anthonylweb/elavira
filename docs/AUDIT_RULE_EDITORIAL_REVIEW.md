# Current Core editorial implementation report — Audit Report UX Prompt 3

This implements the current-rule editorial rewrite within the ongoing 0.2.0 milestone. It does **not** change the plugin version or mark the milestone complete.

## Resumed verification — September 12, 2026

After the reported usage-limit interruption, the attached handoff report and saved
implementation were checked again. The attachment contained this report rather
than additional implementation instructions. The Rule Result wording, scoped
title capitalization, recorded-location casing exceptions and asset cache revisions
were already present; no further code repair was needed.

Fresh validation passed: PHPUnit **224 tests / 3,419 assertions**, JavaScript
**30 tests**, npm lint, PHPCS **149 files**, and `git diff --check`. All eleven
report-controller fixtures were regenerated. The actual controller export was
rerun and its five worksheets parsed successfully as nonempty XML. These are
fresh automated/render/export checks; the browser observations below remain from
the prior review, and no new live WordPress or screen-reader session is claimed.

Plugin version is **0.2.0**. The 0.2.0 milestone is release-candidate ready. Preserve
the tone/title decisions and outstanding integration/content gaps documented below.

## Latest follow-up — relaxed Rule Result copy and capitalized titles

The user approved the improved layout and editorial tone, then requested a calmer,
less technical Rule Result explanation and consistent title capitalization. This
report includes that follow-up for the next model; preserve these choices in later
0.2.0 work.

The displayed messages now read:

- **Automatic Failure** — Elavira has enough objective evidence to confidently identify this as an accessibility failure. The guidance below explains what to change and how to check the result.
- **Potential Issue** — Elavira found something that may cause an accessibility problem. Check it in context using the guidance below before deciding whether it needs a change.
- **Human Review** — this check needs your judgment. Elavira will explain what to look for so you can decide whether there is an accessibility problem and what to do next.

The historical `detected_failure` alias uses the same Automatic Failure message.
The unavailable-result fallback now directs the reader to the saved summary and
recorded evidence. The explanations retain certainty boundaries and apply to the
individual finding. No result type or scanner behavior changed. The tone should
help a developer facing an unfamiliar finding understand their next step without
feeling blamed or overwhelmed by standards terminology.

Scoped CSS applies initial capitals to page/section headings, disclosure titles
and table headings, including finding titles in report tables. It covers Dashboard,
Settings, Reports, Guided Decisions, Learn, Run Audit, Statement admin/preview and
Toolbar settings. Admin CSS now loads on Elavira admin routes; its selectors do
not affect unrelated WordPress pages. The visitor toolbar heading also uses the
same visual treatment. Literal text, translations, exports and stored rule
guidance retain their underlying casing. Body descriptions and code are exempt.
Occurrence headings are also exempt because they contain case-sensitive recorded
filenames, URLs and user text; browser review caught and corrected that side effect.

Six plugin-authored headings in newly rendered accessibility statements use title
case directly because published statements are standalone HTML. Existing published
content is not rewritten or republished. The required qualified conformance heading
retains its exact wording. CSS asset URLs have a `-titles-1` cache revision; the
plugin version is **0.2.0**.

Follow-up validation: **224 PHPUnit tests / 3,419 assertions**, **30 JavaScript
tests**, npm lint, PHPCS (149 files) and `git diff --check` passed. Added a result-copy
regression test covering the three messages, historical alias, unknown fallback
and actual rendering; updated the statement-heading assertion without weakening
its conformance-language check. Browser fixtures confirmed the three messages,
320-pixel reflow, all seven admin CSS scopes, the visitor heading, unaffected
outside headings/body text/code and preserved filename casing. The existing local
WordPress integration limitation below still applies.

Additional changed files: `assets/css/admin.css`, `assets/css/toolbar.css`,
`src/Admin/Modules/AdminModule.php`, `src/Toolbar/Admin/ToolbarPage.php`,
`src/Toolbar/FrontendToolbar.php`, `src/Statement/StatementRenderer.php`,
`src/Reports/Admin/ReportsPage.php`, `tests/Unit/StatementTest.php`, and further
updates to `tests/Unit/ReportsTest.php`, `tools/render-editorial-fixtures.php` and
the editorial/framework documentation.

## Scope and verified counts

The authoritative registry was inventoried before editing: **46 current rule IDs, 52 total definitions**. Current definitions comprise **10 Automatic Failures, 17 Potential Issues and 19 Human Reviews**. The six older contracts remain registered and unchanged.

| Current guidance field | Definitions revised/populated |
|---|---:|
| Summary/overview | 46 |
| Impact | 46 |
| Remediation | 46 |
| WordPress guidance | 46 |
| Developer guidance | 46 |
| What to check | 46 |
| Verification guidance | 46 |
| Examples | 18 rules, 22 comparisons |
| Finding title | 45 changed; 46 explicitly reviewed |

No current guidance fields are filled by a generic family fallback. Every exact current contract has an intentional entry in `CoreRuleGuidance`. Examples are deliberately omitted for 28 rules.

The full **before-edit inventory**, including every original summary, impact, recommendation, WordPress/developer field, WCAG mapping, evidence field list, optional-field values, Learn ID, editorial hint and actually resolved Guided Decision, is preserved in [AUDIT_RULE_EDITORIAL_BASELINE.json](AUDIT_RULE_EDITORIAL_BASELINE.json). Its `current` flag distinguishes the 46 current entries. Before this rewrite all current `what_to_check`, `verification_guidance` and `examples` fields were empty. This is a historical development snapshot, never a runtime data source.

## Current-rule inventory and second editorial pass

Families: Links & Navigation (3), Headings & Document Structure (5), Document Language (2), Forms & Controls (5), Names/Roles/States (5), ARIA Usage (8), Keyboard Accessibility (5), Focus (4), Color & Contrast (5), Images & Alternative Text (4). Document Language is existing structure-related coverage, not a new scanner category. There are no current media or motion rule definitions; none were invented.

Each row below received the ten-point content-quality review in the request: understandable finding and impact, concrete inspection, acceptable/problematic decision, actionable change, verification, explained terminology, recorded-evidence limits and appropriate certainty. The final column records the particular decision checked in that second pass. Technical details remain available where the captured evidence requires a developer to locate or repair the component.

| Current rule ID | Version | Family | Outcome | WCAG | Rule-specific evidence fields | Second-pass decision checked |
|---|---|---|---|---|---|---|
| elavira.image.alt_missing | 1.0.0 | Images & Alternative Text | automatic failure | 1.1.1 A | alt_state | Choose informative, decorative or functional treatment; missing is distinct from empty. |
| elavira.links.missing_accessible_name | 1.0.0 | Links & Navigation | automatic failure | 2.4.4 A, 4.1.2 A | accessible_name_state | Supply the whole link name; an image filename is not its purpose. |
| elavira.link.purpose_review | 2.0.0 | Links & Navigation | potential issue | 2.4.4 A | label_kind, destination_kind | Evaluate structurally associated context; generic words can be acceptable. |
| elavira.links.identical_name_different_destination | 2.0.0 | Links & Navigation | potential issue | 2.4.4 A | accessible_name_excerpt, name_fingerprint, destination_fingerprint, destination_kind, destination_state | Compare actual purposes and context, not merely different URL strings. |
| elavira.structure.missing_h1 | 1.0.0 | Headings & Document Structure | human review | 1.3.1 A | h1_count | Recommend a primary heading without claiming an exactly-one-H1 requirement. |
| elavira.structure.multiple_h1 | 1.0.0 | Headings & Document Structure | human review | 1.3.1 A | h1_state | Review competing topics versus subsections before changing levels. |
| elavira.structure.heading_level_jump | 1.0.0 | Headings & Document Structure | potential issue | 1.3.1 A | previous_level, current_level | Inspect actual hierarchy; do not mechanically renumber levels. |
| elavira.structure.empty_heading | 1.0.0 | Headings & Document Structure | potential issue | 1.3.1 A | heading_level | Distinguish an empty spacer from a section whose title is missing. |
| elavira.heading.structure_review | 2.0.0 | Headings & Document Structure | human review | 1.3.1 A | issue | Decide whether the main content contains real sections needing headings. |
| elavira.document.language_missing | 2.0.0 | Document Language | automatic failure | 3.1.1 A | language_state | Declare the actual primary language on html; no content-based guessing. |
| elavira.document.invalid_language | 2.0.0 | Document Language | automatic failure | 3.1.1 A | language_state, declared_language, primary_subtag | Correct syntax/primary-language evidence without promising full tag validation. |
| elavira.form.accessible_name_missing | 2.0.0 | Forms & Controls | automatic failure | 4.1.2 A | control_type, name_state | Inspect the announced control name; absence of a supported name is not absence of label markup. |
| elavira.forms.broken_label_reference | 1.0.0 | Forms & Controls | potential issue | 1.3.1 A | reference_state, control_type | Connect the intended label and unique control ID, then test the association. |
| elavira.forms.placeholder_only_label | 1.0.0 | Forms & Controls | potential issue | 3.3.2 A | control_type, placeholder_state | Keep field identity visible while typing; placeholder names are not persistent labels. |
| elavira.forms.required_instruction_review | 1.0.0 | Forms & Controls | human review | 3.3.2 A | required_source, control_type | Check required status and instructions before submission and with assistive technology. |
| elavira.forms.error_identification_review | 1.0.0 | Forms & Controls | human review | 3.3.1 A, 3.3.3 AA | trigger | Exercise errors in a safe test form; identify field, message and useful correction. |
| elavira.semantics.control_missing_name | 1.0.0 | Names, Roles & States | automatic failure | 4.1.2 A | role, name_state | Name the control action and check the resulting announced name. |
| elavira.semantics.invalid_role | 1.0.0 | Names, Roles & States | potential issue | 4.1.2 A | role_state, first_invalid_token, native_role_state | Compare intended behavior with the browser role; syntax alone is not failure. |
| elavira.semantics.required_state_missing | 1.0.0 | Names, Roles & States | automatic failure | 4.1.2 A | role, required_attribute, state_reason | Supply an accurate required state and maintain it during interaction. |
| elavira.semantics.state_consistency_review | 1.0.0 | Names, Roles & States | human review | 4.1.2 A | role, state_attribute | Compare visible, functional and announced state in both directions. |
| elavira.semantics.native_semantics_overridden | 1.0.0 | Names, Roles & States | potential issue | 4.1.2 A | native_role, explicit_role | Use the native element matching the action, or justify a complete custom pattern. |
| elavira.aria.invalid_attribute | 1.0.0 | ARIA Usage | potential issue | No direct mapping | attribute_name, reason | Investigate attribute intent, spelling and vocabulary limits before correcting it. |
| elavira.aria.invalid_value | 1.0.0 | ARIA Usage | potential issue | 4.1.2 A | attribute_name, value_reason | Correct the specific value type and actual state, not a generic true/false value. |
| elavira.aria.prohibited_attribute | 1.0.0 | ARIA Usage | potential issue | No direct mapping | role, attribute_name | Do not assign an inappropriate role just to permit a naming attribute. |
| elavira.aria.required_attribute_missing | 1.0.0 | ARIA Usage | potential issue | 4.1.2 A | role, attribute_name | Check the actual required property and role; use native semantics where suitable. |
| elavira.aria.broken_id_reference | 1.0.0 | ARIA Usage | potential issue | 1.3.1 A | attribute_name, reference_state, token_count | Repair the intended name, description or other relationship, respecting their different purposes. |
| elavira.aria.hidden_focusable_content | 1.0.0 | ARIA Usage | potential issue | No direct mapping | focusable_kind, hidden_source | Test whether hidden descendants receive focus; coordinate visibility and availability. |
| elavira.aria.duplicate_id_reference | 1.0.0 | ARIA Usage | potential issue | 1.3.1 A | attribute_name, duplicate_count | Update unique IDs and all corresponding references across repeated components. |
| elavira.aria.redundant_role | 1.0.0 | ARIA Usage | potential issue | No direct mapping | native_role, explicit_role | Keep an intentional compatibility role only with a tested reason; no mapped WCAG failure. |
| elavira.keyboard.positive_tabindex | 1.0.0 | Keyboard Accessibility | human review | 2.4.3 A | tabindex_category, tabindex_value, focusable_kind | Test forward/backward task order and remove confusing artificial ordering. |
| elavira.keyboard.non_native_interactive | 1.0.0 | Keyboard Accessibility | human review | 2.1.1 A | role, interaction_signal, tabindex_category | Test expected keys for the actual control role rather than one universal recipe. |
| elavira.keyboard.possible_unreachable_control | 1.0.0 | Keyboard Accessibility | human review | 2.1.1 A | role, focusability | Allow managed focus in composite widgets; lack of a separate Tab stop may be correct. |
| elavira.keyboard.possible_trap | 1.0.0 | Keyboard Accessibility | human review | 2.1.2 A | structure, focusable_descendants | Demonstrate entry/use/exit before deciding there is a trap. |
| elavira.keyboard.custom_widget_review | 1.0.0 | Keyboard Accessibility | human review | 2.1.1 A | role, widget_pattern | Apply the actual widget pattern for entry, movement, activation and exit. |
| elavira.focus.possible_hidden_focus | 1.0.0 | Focus | human review | 2.4.7 AA | style_pattern, style_source | Observe a persistent visible indicator; a removed outline can have a valid replacement. |
| elavira.focus.order_review | 1.0.0 | Focus | human review | 2.4.3 A | order_trigger | Judge meaningful task order and dynamic focus movement, not exact visual order. |
| elavira.focus.obscured_review | 1.0.0 | Focus | human review | 2.4.11 AA | position, boundary, coverage | Test overlays at boundaries and zoom; distinguish entire occlusion from the stronger full-visibility goal. |
| elavira.focus.modal_management_review | 1.0.0 | Focus | human review | 2.4.3 A | dialog_pattern, role | Test dialog entry, naming, containment, keyboard dismissal and logical restoration. |
| elavira.contrast.text_insufficient | 1.0.0 | Color & Contrast | automatic failure | 1.4.3 AA | foreground, background, ratio_milli, required_milli, text_size, font_size_milli, font_weight, determination, rendered_state | Measure the recorded pair against 4.5:1; use selector/developer fallback if no text is captured. |
| elavira.contrast.large_text_insufficient | 1.0.0 | Color & Contrast | automatic failure | 1.4.3 AA | foreground, background, ratio_milli, required_milli, text_size, font_size_milli, font_weight, determination, rendered_state | Confirm qualifying size/weight and at least 3:1, including responsive size changes. |
| elavira.contrast.non_text_insufficient | 1.0.0 | Color & Contrast | automatic failure | 1.4.11 AA | foreground, background, ratio_milli, required_milli, visual_category, determination, rendered_state | Identify the essential visual relationship; decorative boundaries are not all subject to the same test. |
| elavira.contrast.cannot_determine | 1.0.0 | Color & Contrast | human review | 1.4.3 AA | candidate, reason, required_milli, rendered_state | Treat indeterminate measurement as unknown; inspect actual layers and applicable thresholds. |
| elavira.color.color_only_review | 1.0.0 | Color & Contrast | human review | 1.4.1 A | meaning, non_color_cue, rendered_state | Test error meaning without color and associated messages; missing reference alone is not proof. |
| elavira.images.alt_quality_review | 1.0.0 | Images & Alternative Text | human review | 1.1.1 A | alt_excerpt, alt_fingerprint, context, review_reason | Check functional purpose, meaningful visible words, decoration and caption equivalence; keep correct existing alternatives. |
| elavira.images.filename_alt | 1.0.0 | Images & Alternative Text | potential issue | 1.1.1 A | alt_excerpt, alt_fingerprint, context, pattern | Decide whether filename-like wording is meaningful before replacing it. |
| elavira.images.long_alt_review | 1.0.0 | Images & Alternative Text | human review | 1.1.1 A | alt_excerpt, alt_fingerprint, context, alt_length, word_count, review_reason | Preserve relevant information, using structured detail when useful; no universal character limit. |

All rules also retain their recorded target selector/tag and bounded element metadata. Optional Prompt 2 identification includes link kind/destination, image filename/alt state/alt excerpt, simple name excerpt, visible text, immediate paragraph, control type and field name **only when captured**. No current advice assumes these fields exist in historical reports. Link query/fragment data is omitted; excerpts can be incomplete. Hashes do not reconstruct missing content.

## Help mapping decisions

- **No Guided Decision compatibility entries were added, changed or broadened.** The existing exact registry resolves 25 current rules. All seven registered flows were inspected, including the audio/video flow, which has no current scanner rule to attach.
- Removed the stale `keyboard_interaction` editorial hint from `elavira.focus.obscured_review@1.0.0`. Its actual compatible decision was already absent, so this does not remove a previously functional CTA.
- **One Learn mapping changed:** `elavira.aria.hidden_focusable_content@1.0.0` now uses registered `keyboard_accessibility`, which addresses the actual keyboard/focus risk, rather than `forms_labels`.
- Other mappings were retained after checking the actual topic content. The Forms topic explicitly covers accessible names, roles, states, broken references and ARIA basics; it is broader than its short ID suggests. It genuinely supplements those findings, though dedicated material would help.
- The CTA remains **“Not sure? Let's decide”**, with the established supporting explanation. Report rendering uses actual exact compatibility, never merely a matching topic or editorial hint.

New Guided Decision gaps for later work: obscured focus/overlay testing; contextual ARIA role and state correctness; naming custom controls; repairing ID relationships; contrast applicability and complex background measurement; choosing document language. These deserve their own decisions rather than unrelated form or keyboard flows. The link-purpose flow also deserves a later instructional review: it remains more technical than the rewritten finding and does not branch explicitly through image-only links. No flow content was changed here.

Learn gaps: dedicated native HTML versus ARIA, accessible-name computation and overrides, ID relationships/hidden focus, practical overlay/dialog tests, contrast measurement on complex backgrounds, and functional linked-image examples with meaningful visible words. Existing topics remain supplementary; no rule was redirected to a generic accessibility article.

## Historical editorial behavior

`CoreRuleRegistry` assembles the same definitions in the same order, then applies copy by **exact ID and rule version**. `RuleDefinition::with_editorial()` only accepts existing editorial fields, constructs a newly validated immutable definition, and cannot change the ID, rule/editorial version, certainty, priority, WCAG, evidence/grouping schemas, grouping strategy or supersession.

This is bundled registry metadata, not a new database or parallel persistence store. All six older definitions retain their complete before-edit editorial arrays:

| Retained contract | Reason not to apply current copy |
|---|---|
| elavira.form.accessible_name_missing@1.0.0 | Earlier representative naming/evidence contract; current native-control contract is v2. |
| elavira.link.purpose_review@1.0.0 | Earlier broad human-review scope differs from current bounded ambiguous-name Potential Issue. |
| elavira.heading.structure_review@1.0.0 | Earlier representative heading review differs from current no-heading/main-region conditions. |
| elavira.document.language_missing@1.0.0 | Earlier representative language evidence differs from current missing/empty-state contract. |
| elavira.links.identical_name_different_destination@1.0.0 | Earlier evidence has no required name excerpt; current guidance must not be substituted implicitly. |
| elavira.document.invalid_language@1.0.0 | Earlier language validation differs from current registered-primary-subtag contract. |

**Historical definitions updated: 0. Intentionally retained: 6.** Historical audits using one of the unchanged current contracts receive its compatible clarified guidance. Their persisted summary snapshots, evidence, decisions and history are not rewritten. Rule/editorial version identifiers remain unchanged because no evaluation semantics changed. This model preserves compatible guidance, not immutable historical prose editions for every copy edit. Supporting multiple prose editions for the same exact contract would need a separately designed editorial-version architecture; this task does not introduce it.

`HistoricalRuleResolver` still requires exact stored rule **and editorial** versions. Unavailable versions use the persisted summary and show the existing warning instead of fabricating guidance.

## Before and after examples

| Rule | Before | After |
|---|---|---|
| Form accessible name v2 | “Correct the rendered semantic consequence, then re-audit.” | Identify the recorded field, listen to its name, prefer a connected visible native label, and verify its name and persistent visible label while typing. |
| Link purpose v2 | “Review each link with its programmatically associated context and clarify the accessible name where its purpose remains unclear.” | Read the text and immediate paragraph; check associated list/table context on the page; retain acceptable generic wording or name the destination when unclear. |
| Image alternative quality | “Review this text alternative in context.” followed by image categories | Compare the image-only link name with its destination; retain appropriate alternatives, preserve meaningful image words, and avoid redundant decoration announcements. |
| Hidden focusable ARIA content | Generic component/assistive-technology review | Test Tab, Shift+Tab and script focus into the hidden ancestor; expose usable content or make inactive content unavailable, then test each visibility state. |

## Final image-only link treatment

The guidance explicitly follows **image → alternative/name → link purpose**. An image-only contact link with `alt="Envelope icon"` identifies a picture but may not identify the action. The escaped example considers `alt="Contact us"` **only for that actual destination**, never as a universal replacement.

A linked banner visibly saying “View Fall Collection” needs an alternative preserving those meaningful words and purpose, rather than only “Autumn leaves.” A pricing link already containing useful “View pricing” text can use `alt=""` for a purely decorative arrow. Missing alt and intentional empty alt remain distinct. Informative and captioned images are evaluated for relevant information and redundancy; button images need the button action.

The user compares the recorded destination, filename, alt state/excerpt, available simple name and context **when present**, then checks the actual rendered link name. Complex ARIA/reference relationships and abbreviated evidence require browser/assistive-technology inspection. A simple scanner excerpt is not advertised as the full accessibility-tree name. Correct destination-oriented alt may still trigger contextual image review: the summary and verification explicitly permit retaining it after review.

This treatment is consistent with W3C's [functional images guidance](https://www.w3.org/WAI/tutorials/images/functional/) and [link purpose in context explanation](https://www.w3.org/WAI/WCAG22/Understanding/link-purpose-in-context.html). These explanatory resources describe approaches; they do not turn every short link label into an automatic failure.

## Report overview

Reviewed and retained the existing dynamic wording: “Elavira Core currently provides [calculated count] automated accessibility rules.” It explains which kinds of findings the report contains, the need for human review, and that older audits may use another ruleset. This is more accurate for historical reports than asserting that every audit used today's rules. Tests inject one and two unique IDs, including duplicate historical versions. No user-facing count is hard-coded.

## Actual report-controller fixture review

`tools/render-editorial-fixtures.php` renders real `ReportsPage` output with isolated fake repositories and validates each fixture's rule-specific evidence. It never writes to the site's database. Review was performed in the in-app browser on the generated HTML, not a hand-built mockup.

| Fixture | Could a non-WCAG expert reasonably understand the next step? |
|---|---|
| Missing image alt | Yes: identify image purpose and add appropriate or intentionally empty alt. |
| Decorative arrow inside useful text link | Yes: compare the useful text with the arrow and remove redundant alt if purely decorative. |
| Image-only Envelope icon link | Yes: compare announced name with contact destination, then choose functional wording. |
| Image-only Contact us link | Yes: retain an appropriate existing name after checking the real destination. |
| Learn more text link | Yes: read the services paragraph before deciding whether wording needs changing. |
| Form accessible name | Yes: locate the email field and check its announced name/label connection. |
| Heading structure | Yes: compare the H2/H4 relationship and choose the actual subsection level. |
| Missing document language | Yes: choose the actual primary language and correct root HTML output/settings. |
| ARIA value | Yes: give the recorded attribute to the component author, correct its actual value and test state changes. |
| Keyboard/focus | Yes: Tab to the named control and observe whether a visible indicator follows focus. |
| Contrast | Yes, with a developer fallback when needed: locate text by the recorded selector, measure its color pair, and choose colors meeting 4.5:1. |

All eleven pages fit a **320 CSS pixel viewport** (document width 305 pixels including browser scrollbar effects), with verification sections present and no empty example sections. Literal HTML examples remain escaped. The occurrence disclosure opened with Enter, closed with Space, and retained a visible 3-pixel blue focus outline.

The fixture review prompted simpler finding titles, explicit acceptable-image guidance, and a contrast locator fallback. This is an editorial/keyboard/reflow review, not an end-user study or a completed NVDA/JAWS test.

## Tests and validation

Added `CoreRuleGuidanceTest` (six tests) for complete current coverage, all-version baseline integrity, immutable detection metadata, exact help resolution, linked-image policy and literal translation calls. Added three report tests: every current rule's actual rendering (including no-example behavior and CTA compatibility), dynamic counting across historical versions, and export occurrence/history joins including audit filtering. Existing tests were updated for intentional copy changes, a deliberately empty optional-guidance fixture, stronger explicit H1/language assertions, and the corrected stale hint. No outcome assertion was weakened.

- PHPUnit after the tone/title follow-up: **224 tests, 3,419 assertions**, passed.
- `npm test`: **30 tests**, passed.
- `npm run lint`: passed.
- PHPCS: **149 files**, passed with no errors or warnings.
- Actual controller XLSX export: all five expected worksheets parsed as XML; new recommendation, captured URL and status history were present. Export shape is unchanged. ZIP support was enabled for that one CLI invocation only.
- `git diff --check`: passed (only existing LF/CRLF conversion notices).
- No build script exists in this repository; no build/dependency change was needed.

The WordPress admin URL timed out during this run. Production WordPress/browser integration and assistive-technology combinations remain to be exercised when the local site is reachable. Actual report-controller, registry, repository, status and exporter checks ran using isolated fixtures.

## Files in this prompt

- `src/Rules/CoreRuleGuidance.php`: exact-contract editorial definitions.
- `src/Rules/CoreRuleRegistry.php`: assemble compatible current refinements without changing registration order.
- `src/Rules/RuleDefinition.php`: validated editorial-only immutable refinement.
- `src/Reports/Application/HistoricalRuleResolver.php`: comment clarifies exact-compatible resolution; behavior unchanged.
- `tests/Unit/CoreRuleGuidanceTest.php`, `ReportsTest.php`, `BrowserEvidenceContractTest.php`, `Round2RuleRegistryTest.php`, `Round4RuleRegistryTest.php`: coverage/assertions described above.
- `tests/Support/FakeWpdb.php`: support actual occurrence/history export joins in isolated tests.
- `tools/editorial-inventory.php`, `tools/render-editorial-fixtures.php`: CLI-only inventory and reproducible rendered fixtures/export.
- `docs/AUDIT_RULE_EDITORIAL_BASELINE.json`, this report, `AUDIT_EDITORIAL_STANDARD.md`, `AUDIT_REPORT_UX_FRAMEWORK.md`, `ARCHITECTURE.md`: inventory, policy and implementation documentation.

The worktree already contained Prompts 1/2 changes. Those were preserved. The pre-existing `docs/RELEASE_CHECKLIST.md` edit was not changed.

## Remaining limits and next-prompt implications

No current rule retains the former generic remediation fallback. Technical attribute/role names remain where needed to identify the actual repair, with plain-language purpose and behavior explanations. The six historical contracts listed above intentionally retain their original copy, including older generic guidance.

Contrast and some custom components lack human-readable text excerpts. This task provides a selector/developer fallback instead of inventing evidence or changing the scanner. Consider bounded text/role identification for those families in the next evidence-focused prompt.

Prioritize the Guided Decision and Learn gaps above, especially an image-only-link branch and practical ARIA/contrast explanations. Consider how to label compatible copy refinements if immutable past prose becomes a product requirement. A review finding can remain after a correct human decision; do not treat re-audit alone as proof of contextual correctness.

No database migration, remote service, dependency, scanner outcome, grouping/supersession, Core/Premium boundary or plugin version was changed by this editorial prompt. **The 0.2.0 milestone remains in progress.**
