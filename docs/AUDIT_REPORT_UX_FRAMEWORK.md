# Audit Report UX framework implementation report

This implements the initial information architecture request for the Elavira
0.2.0 milestone. The plugin version is unchanged and the overall milestone is
not complete.

## Prompt 3 — complete current-rule editorial rewrite

**Latest follow-up:** Rule Result messages now use calmer plain language, with
clear next steps and unchanged certainty. Scoped title capitalization covers
Elavira admin headings, disclosures, table titles and the visitor toolbar;
newly rendered statement headings use title case. Recorded location headings,
body copy and code retain their casing. No published statement was republished.
The [updated implementation report](AUDIT_RULE_EDITORIAL_REVIEW.md) includes the
exact messages, scope, cache revision and validation: 224 PHP tests / 3,419
assertions, 30 JavaScript tests, lint, PHPCS and whitespace checks passed.

All **46 current rule IDs** received an intentional editorial rewrite and a second
content-quality pass. The registry contains **52 definitions**, including six
historical contracts whose original guidance is retained. Every current rule now
has a specific summary, impact, inspection, remediation, verification, WordPress
and developer guidance. Eighteen rules have 22 teaching examples. Forty-five
finding titles were simplified; all current titles were reviewed explicitly.

Implementation and inventory: [Current-rule editorial review](AUDIT_RULE_EDITORIAL_REVIEW.md).
Future authoring requirements: [Editorial standard](AUDIT_EDITORIAL_STANDARD.md).
The complete pre-edit registry/editorial snapshot is
[AUDIT_RULE_EDITORIAL_BASELINE.json](AUDIT_RULE_EDITORIAL_BASELINE.json).

`CoreRuleGuidance` becomes part of the existing `RuleDefinition` metadata through
exact ID/version refinement during registry assembly. Detection contracts,
historical resolution, snapshots, status history and exports retain their existing
semantics. Compatible reports using current contracts receive the clarified copy;
the model does not preserve a separate immutable prose edition for every edit.

Image-only link guidance now explicitly connects image alternative/name to link
purpose, preserves meaningful words in linked images, allows decorative images
beside useful link text to have empty alt, and permits retaining already-correct
functional alternatives. The captured simple name is never presented as a
guaranteed complete accessibility-tree name.

No Guided Decision compatibility was broadened. The stale obscured-focus hint
was cleared, and hidden-focusable ARIA content now maps to the registered keyboard
Learn topic. Actual report-controller fixtures cover the eleven requested cases,
including narrow-screen and keyboard checks; full results and integration limits
are in the implementation report. The overview count remains dynamic.

No plugin version, scanner outcome, migration, dependency or Core/Premium boundary
changed in Prompt 3. The 0.2.0 milestone remains in progress. The earlier Prompt 1
and Prompt 2 sections below describe their own implementation stages.

## Prompt 2 — occurrence identification

Prompt 2 adds a dedicated `OccurrencePresenter`, primary evidence rows and
identifying occurrence headings. The sections below this Prompt 2 report describe
the earlier Prompt 1 work. The plugin version and rule guidance remain unchanged.

### Evidence audit

The registry actually contains **46 current rule IDs, 52 definitions including
historical versions**, not five rules. The five original representative families
are documented here. All occurrences already have an audited URL through the
audit-URL record, a selector/tag target, and element tag. Optional locator,
element ID, role, input type and name-presence fields exist in the server contract
but are not universally emitted by the scanner. No hidden store of href, src,
nearby prose or HTML existed in the original evidence.

An asterisk below means newly captured data, rather than just newly exposed data.

| Rule | Evidence before this change | Primary evidence now | Still missing / intentionally excluded |
| --- | --- | --- | --- |
| `elavira.image.alt_missing` 1.0.0 | Image tag/selector; `alt_state=missing` | Image description and missing-alt state; filename*, containing link relationship/destination/name/context* where supported | Full source URL/bytes, generated alt, arbitrary surrounding prose; old reports lack filename/link data |
| `elavira.form.accessible_name_missing` 2.0.0 (1.0.0 historical) | Control type and missing-name state (`accessible_name_state` in v1, `name_state` in v2); current input type/name-presence false | Specific field description, recorded missing-name result, field name attribute* | Form values, label text, form HTML. Missing accessible name is not presented as proof that no label exists |
| `elavira.link.purpose_review` 2.0.0 (1.0.0 historical) | Generic label category and destination kind; v1 stores text-state category | Text/image-link description*, text excerpt*, minimized destination*, simple available link name*, immediate paragraph context*, single image filename/alt* | Full computed name, complex referenced names, queries/fragments, non-HTTP destinations, large surrounding containers. Historical categories do not reconstruct original text |
| `elavira.heading.structure_review` 2.0.0 (1.0.0 historical) | v2: page has no visible headings or main region lacks headings; v1: current/previous levels and issue | Page/main-content description and recorded contextual state. Related level-jump/empty-heading rules expose recorded levels and new heading text excerpts* | v2 is a page/region finding, not an individual H3. No heading text is invented for it; no complete outline is collected |
| `elavira.document.language_missing` 2.0.0 (1.0.0 historical) | HTML target and missing/empty language state | Page language description and explicit recorded state | No guessed language or invented language value |

Other existing evidence was inspected for reuse: identical-name links already
record a name excerpt and name/destination fingerprints; image-review rules
record alt excerpts, fingerprints and semantic context; invalid-language findings
record declared language; heading level-jump/empty-heading findings record levels.
Forms/ARIA, keyboard/focus and contrast rules primarily record categorical,
boolean or numeric diagnostics. The common collector enriches only relevant
element types across this existing ruleset and adds no detection rules.

### Presentation and capture contract

`OccurrencePresenter::present()` returns plain text headings, labeled rows and
remaining technical element metadata. `ReportsPage` escapes every value. Small
tag/type/state and field-label maps keep presentation outside detection and the
template. Unknown, empty, malformed or unavailable evidence produces no empty
rows and no inferred failure. Native definition lists hold primary values;
existing native disclosures retain selectors and raw diagnostics. Values already
shown in primary rows are removed from the repeated technical element list.

Headings retain the persisted occurrence ID, adding type, a short identifier and
destination where present. Full captured values remain below shortened headings.
Same-text links can therefore be distinguished by destination. Image links show
their relationship, filename, alt state/excerpt, destination and available simple
name. Available names are not claimed to be complete browser accessibility-tree
names; referenced/complex naming is omitted when not reliably available.

New optional scalar fields use existing `element_json`: `link_kind`,
`link_destination`, `image_filename`, `image_alt_state`, `image_alt_excerpt`,
`link_name_excerpt`, `text_excerpt`, `context_excerpt`, and `field_name`.
`BrowserEvidenceValidator` whitelists and bounds them. No migration, registry
rewrite or parallel store is required. Durable staging, grouping, occurrence
persistence and XLSX continue using existing structures.

Text collection stops at 160 UTF-16 units, 64 visited nodes, or six levels.
Surrounding context is restricted to the immediate paragraph. The walk skips form
controls, scripts/styles, hidden nodes and editable regions; field names are
attributes, never entered values. No HTML is collected. Existing protections
against storing HTML or sensitive value fields remain intact.

URL capture accepts HTTP(S), strips credentials/query/fragment, keeps only a
filename for images, and omits identifiers longer than 512 characters. Server
byte limits allow Unicode within the existing 8KB element limit. Server
validation rejects unminimized destinations and full paths in image filenames.
Destinations are plain text, separately labeled from the audited-page link.
Links differing only by omitted queries/fragments cannot be distinguished by
these URLs; existing diagnostic fingerprints are retained.

The runtime snapshot advances to `core-browser-1.5.1`; scanner asset caching now
uses that snapshot. Plugin, ruleset, rule and editorial versions are unchanged
because the additions are optional identification fields, not changed outcomes.
Older scanner payloads still validate. Historical reports show only their
recorded fields. Reviewed-exception fingerprints already include element data:
new or changed identification can conservatively trigger fresh review, without
rewriting or deleting any prior audit, finding, occurrence or decision.

### Files changed in Prompt 2

- `src/Reports/Admin/OccurrencePresenter.php` (new) and `ReportsPage.php`.
- `assets/css/reports.css` and `assets/js/scanner.js`.
- `src/Rules/Browser/BrowserEvidenceValidator.php`.
- `src/Scanner/Versions.php` and `src/Scanner/Frontend/ScanPage.php`.
- `tests/Unit/OccurrencePresenterTest.php` (new), `ReportsTest.php`,
  `BrowserEvidenceContractTest.php`, and `tests/js/scanner.test.js`.
- This document and `docs/ARCHITECTURE.md`.

`RuleDefinition`, repositories, migrations, exports and decision/Learn mappings
needed no further edits. Pre-existing work and RELEASE_CHECKLIST edits remain.

### Prompt 2 validation and follow-up

- PHPUnit: **214 tests, 1,364 assertions passed**. JavaScript: **30 tests passed**.
  Nine new PHP tests and two JS tests cover identification, historical fallback,
  empty/malformed fields, relationships, headings, full captured values,
  escaping, non-clickable destinations, URL safety and bounded collection.
  Existing report navigation/status/history/help tests remain passing.
- JavaScript lint, PHPCS (WordPress/PHP compatibility) and `git diff --check`
  passed. No build script exists. Git emits only configured LF/CRLF notices.
- Five actual report-controller fixtures were verified: vague text link,
  image-only link, missing-alt image, H3 occurrence and page language. Their
  primary identification was readable with Technical Details closed.
- At 320px, all five measured 305px document scroll width. Long URLs, filenames,
  alt/name/text excerpts and expanded selectors wrapped. Enter opened disclosures
  with focus retained and a visible 3px outline. Markup injected into a recorded
  text field displayed literally and created zero image elements. The temporary
  viewport override was reset.
- XLSX creation and worksheet XML validation passed with the already-installed
  ZIP extension enabled for that CLI invocation. Default CLI PHP does not load
  ZIP; system configuration was not changed.
- WordPress still refused connections. Live WordPress styling, authenticated
  scan/status/export flows, actual browser zoom and screen-reader testing remain
  release QA work. Fixture checks are not proof of WCAG conformance.

The next prompt should rewrite guidance using these actual evidence fields and
limits, preserve human authority, and explicitly consider historical editorial
versioning. Old reports cannot acquire identifiers never captured. Broad new-tab
navigation and the complete guidance rewrite remain deferred as requested.

## Files changed

| File | Change |
| --- | --- |
| `src/Reports/Admin/ReportsPage.php` | Overview, finding hierarchy, optional sections, contextual CTAs, occurrence excerpts, disclosures, escaped examples. |
| `src/Reports/Application/HistoricalRuleResolver.php` | Registry-derived current browser rule count. |
| `src/Rules/RuleDefinition.php` | Validated optional inspection, verification, and comparison fields in existing editorial metadata. |
| `assets/css/reports.css` | Scoped disclosures, wrapping, grid minimum sizing, and form width bounds. |
| `tests/Unit/ReportsTest.php` | Seven new rendering, historical compatibility, optional guidance, occurrence, escaping, and mapping tests. |
| `tests/Unit/RuleRegistryTest.php` | Three new model/default/validation and dynamic-count tests. |
| `tests/Support/FakeWpdb.php` | Array-style prepared arguments, report aggregates, and occurrence URL joins for rendering fixtures. |
| `tests/bootstrap.php` | Minimal localization and nonce-rendering stubs for full page tests. |
| `docs/ARCHITECTURE.md` | Guidance contract, authority boundaries, and historical/count semantics. |
| `docs/AUDIT_REPORT_UX_FRAMEWORK.md` | This implementation and QA report. |

The pre-existing changes to `docs/RELEASE_CHECKLIST.md` were left untouched.

## Architecture and content

The existing report controller, registry, historical resolver, repositories,
status service, decision registry, and Learn registry remain in use. No database
migration, new dependency, scanner change, or Core/Premium boundary change was
needed. Exports retain their existing data and columns.

The count comes from distinct IDs in the injected Core rule registry, matching
the scanner's selection of one version per ID. Historical versions and Guided
Decisions do not inflate the count. Browser checks that request human judgment
are included; this is not a count of objectively decidable WCAG failures.
Copy distinguishes current coverage from an older audit's recorded ruleset.

Existing summary, impact, recommendation, WordPress/developer guidance, mappings,
and WCAG metadata remain authoritative. Optional `what_to_check`,
`verification_guidance`, and `examples` support future rule-specific content.
Examples require `instead_of`, `consider`, and a contextual `explanation`.
They are clearly labeled as guidance and escaped separately from audit evidence.
Missing optional guidance produces no empty sections. No production rule's
editorial guidance was rewritten in this request.

Guided Decision actions appear only for exact rule/version compatibility in the
existing decision registry. Learn actions require an available historical
editorial mapping and a registered topic. No mappings were invented.

## Accessibility and validation

- Semantic headings and native details/summary disclosures; no disclosure
  JavaScript or unnecessary ARIA.
- Existing visible keyboard focus, textual status labels, form labels,
  authorization, nonces, history, and navigation preserved.
- Escaped editorial text, captured markup, selectors, and example code.
- Scoped wrapping for URLs, code and long strings; form controls fit their
  container and evidence grid items can shrink.
- New UI copy uses the `elavira` translation domain, including pluralized counts.

Validation results:

| Check | Result |
| --- | --- |
| `php vendor/bin/phpunit` | Passed: 205 tests, 1,318 assertions. |
| `npm test` | Passed: 28 JavaScript tests. |
| `npm run lint` | Passed. |
| `php vendor/bin/phpcs` | Passed with no errors or warnings; includes WordPress and configured PHP compatibility standards. |
| `git diff --check` | Passed; Git reports only configured LF/CRLF normalization notices. |
| Build | No build script is configured in this repository. |
| Browser fixture | Enter expands and Space collapses the occurrence disclosure; focus stays on the summary with a 3px visible outline. Captured script markup renders as text. |
| Narrow fixture | At a 320px viewport, document scroll width was 305px, with no horizontal overflow. Heading structure inspected through browser accessibility/DOM output. |

## Limits and next prompt

The local WordPress URL refused the browser connection. Browser checks therefore
used isolated HTML rendered by the actual report controller against test data,
with the report stylesheet and a minimal surrounding style. They do not establish
live WordPress integration, actual 400% browser zoom, or screen-reader conformance.
Repeat live report/finding, keyboard, zoom, screen-reader, status/history, and
export smoke tests when the local site is running.

The next content prompt should populate rule-specific inspection and verification
guidance and useful examples, and review exact Guided Decision/Learn mappings.
Keep existing historical editorial resolution in mind: new editorial content
must not silently replace advice for an unavailable historical version. The
current model supports one editorial definition per exact rule version; any
broader editorial-version retention change requires a focused design decision.
Optional fields intentionally remain absent in production rule guidance until
that editorial work is approved.

## Prompt 4 — Guided Decision UX and coverage

This milestone applies the Audit Report editorial standard to Guided Decisions
without redesigning Reports, changing scanner outcomes, or bumping the plugin to
`0.2.0`. The before-edit inventory is preserved in
`GUIDED_DECISION_BASELINE.json`. It records all seven original definitions,
including IDs, titles, exact bindings, questions, branches, outcomes, Learn topics,
and result copy. The existing finding route, signed state, persistence schema,
history, ownership checks, and Learn registry were inspected before new flows were
added.

### Content and question standard

A Guided Decision must identify the contextual decision, tell the user what to
inspect, ask one primary question at a time, explain unfamiliar terms where they
are needed, and branch only when the answer changes the guidance. Answer choices
must be things a site owner or developer can reasonably determine from the rendered
page. “I'm not sure” is available throughout current flows and leads to inspection
or testing guidance with a Needs Review conclusion; it never manufactures a
failure.

Outcomes contain three separate parts:

1. An interpretation limited to what the answers support.
2. A practical next action, including whether a change appears necessary.
3. Verification guidance for the rendered result or remaining manual review.

The wording remains factual, calm, plain-language, specific, and concise enough to
scan. It does not substitute a Learn article for basic instructions, claim that a
clean audit proves conformance, or treat Guided Decision completion as verification.

### Current library and exact compatibility

Before this prompt, 25 of 46 current Core rules resolved to one of seven current
flows. After this prompt, 30 of 46 resolve to one of ten current flows. Historical
`1.0.0` versions of the original seven decision graphs remain registered for exact
history lookup. Their revised current graphs use decision/editorial version `2.0.0`.
The three new graphs begin at `1.0.0`.

Current exact rule bindings are:

| Decision | Exact compatible rule contracts |
| --- | --- |
| Link Purpose `2.0.0` | `elavira.link.purpose_review@1.0.0`, `@2.0.0`; `elavira.links.identical_name_different_destination@1.0.0`, `@2.0.0` |
| Alternative Text `2.0.0` | `elavira.image.alt_missing@1.0.0`; `elavira.images.alt_quality_review@1.0.0`; `elavira.images.filename_alt@1.0.0`; `elavira.images.long_alt_review@1.0.0` |
| Heading Structure `2.0.0` | `elavira.heading.structure_review@1.0.0`, `@2.0.0`; `elavira.structure.missing_h1@1.0.0`; `elavira.structure.multiple_h1@1.0.0`; `elavira.structure.heading_level_jump@1.0.0`; `elavira.structure.empty_heading@1.0.0` |
| Form Labels and Instructions `2.0.0` | `elavira.form.accessible_name_missing@1.0.0`, `@2.0.0`; `elavira.forms.broken_label_reference@1.0.0`; `elavira.forms.placeholder_only_label@1.0.0`; `elavira.forms.required_instruction_review@1.0.0`; `elavira.forms.error_identification_review@1.0.0` |
| Use of Color `2.0.0` | `elavira.color.color_only_review@1.0.0` |
| Keyboard Interaction `2.0.0` | `elavira.keyboard.positive_tabindex@1.0.0`; `elavira.keyboard.non_native_interactive@1.0.0`; `elavira.keyboard.possible_unreachable_control@1.0.0`; `elavira.keyboard.possible_trap@1.0.0`; `elavira.keyboard.custom_widget_review@1.0.0`; `elavira.focus.order_review@1.0.0`; `elavira.focus.modal_management_review@1.0.0` |
| Audio and Video Alternatives `2.0.0` | No scanner contract; educational Learn entry only |
| Focus Visibility and Overlays `1.0.0` | `elavira.focus.possible_hidden_focus@1.0.0`; `elavira.focus.obscured_review@1.0.0` |
| Control Roles and States `1.0.0` | `elavira.semantics.state_consistency_review@1.0.0`; `elavira.semantics.native_semantics_overridden@1.0.0`; `elavira.aria.prohibited_attribute@1.0.0` |
| Uncertain Contrast `1.0.0` | `elavira.contrast.cannot_determine@1.0.0` |

The hidden-focus binding moved from the broad Keyboard Interaction flow to the
new Focus Visibility flow. The other added contracts previously had no compatible
decision. Determinate contrast failures remain unbound because their remediation is
straightforward and a decision tree would repeat the finding. Unsupported rule
versions never receive a decision CTA.

### Link Purpose flow

The first decision is what supplies meaningful content inside the link: text, an
image without useful text, text plus an image, or uncertainty. For one recorded
occurrence only, a reliable `text` or `image_only` value can pre-answer this branch
and the answer is visibly labeled as coming from recorded evidence. Multiple
occurrences and mixed/unknown evidence require the user to answer.

The text path compares the full announced name with the destination or action, then
allows purpose to come from programmatically associated sentence, paragraph, list,
or table context. “Learn more” is therefore not automatically treated as a failure.
When neither name nor context communicates purpose, the outcome recommends more
descriptive wording that matches the actual destination.

The image-only path asks whether the image contains meaningful visible words,
whether the full announced link name contains those words, and whether the name or
valid associated context communicates the action. It explains that “Envelope icon”
may describe appearance without explaining a Contact-page link, while wording such
as “Contact us” is suitable only when it matches the real destination. Linked images
containing words such as “View Fall Collection” preserve those meaningful words and
purpose in the accessible alternative.

The mixed path distinguishes an informative image from a decorative image. Useful
text such as “View pricing” can supply the purpose while an arrow correctly uses an
empty alternative. The flow does not recommend redundant `alt="arrow"` or duplicate
the existing link text. If the image adds necessary information, the combined name
must include it without needless repetition.

Every name-related path states that recorded simple names are excerpts rather than
a guaranteed computed accessibility-tree name. `aria-label`, `aria-labelledby`,
and other naming sources may require browser accessibility-panel or screen-reader
inspection.

### Finding context, persistence, and return

Finding-bound decisions display a small Recorded Evidence section near the start.
After authorization and audit-ownership checks, the controller loads no more than
three persisted occurrences and presents only helpful bounded fields: audited page,
element/link/image/control identification, visible/name/alternative excerpts,
destination, selector, and an allowlist of applicable role/state/contrast evidence.
Captured values are escaped. Missing historical fields are omitted; absence of
evidence is explicitly not treated as failure. Grouped findings tell users to review
the full occurrence list before changing a group status.

Completion reuses the existing decision table. A history record includes the user,
time, exact decision ID/version, ordered answers, conclusion and recommendation,
verification guidance, optional review note, previous status, and any explicit
workflow choice. Saving defaults to keeping the current status. Open, Addressed,
Accepted Limitation, and Needs Review are optional explicit choices. Verified is
never selectable. A no-change conclusion does not automatically create Reviewed —
Not an Issue; the eligible finding workflow remains the authoritative place to make
that auditable reviewed-exception decision.

Finding-launched flows show same-tab links back to the same finding and report at
the top and result. A standalone educational flow returns to the Guided Decisions
directory and cannot persist or alter a finding.

### Learn and new-tab policy

Each flow uses only IDs already present in `CoreTopicRegistry`. Enough help remains
inside each question to answer it; Learn supplements deeper education. Decision
Learn links and report Learn links open in a new tab, use
`rel="noopener noreferrer"`, and include visible “(opens in a new tab)” text.
Finding, report, decision, status, and Elavira admin navigation remain same-tab.

At Prompt 4 completion, no new Learn topics had been added and dedicated ARIA,
obscured-focus, and complex-background education remained gaps. Prompt 5, below,
closes those documented gaps while preserving the Prompt 4 decision contracts.

### Deliberate new-flow choices and deferred gaps

Focus visibility/obscuring, contextual ARIA behavior, and indeterminate contrast
were added because each has multiple legitimate outcomes, requires rendered or
interactive context, and benefits from a small question sequence. The ARIA flow
groups role overrides, state consistency, and prohibited attributes so it can
compare actual behavior with exposed semantics without implying that invalid ARIA
alone is a WCAG failure.

Custom-control naming, broken ID relationships, and document language remain
deferred. Their current findings already identify a concrete missing name,
relationship repair, or language declaration; a new decision would mostly repeat
the report. Revisit them only if user testing shows a genuinely branching contextual
decision. Audio/video remains educational because the current production scanner
has no media contract to bind.

### Accessibility and QA

- Native `fieldset`/`legend` groups, radio controls, labels, buttons, headings,
  navigation, definition lists, and textarea semantics are retained.
- Every question has concise inspection help and an uncertainty answer. Normal
  server navigation avoids unexpected scripted focus movement. Required choice
  validation remains native and server-authoritative.
- Scoped focus styling is visible, status is text, and long questions, answer
  labels, URLs, selectors, code terms, evidence, and results wrap without truncating
  the underlying value.
- A browser fixture at a 320 CSS-pixel viewport measured a 305px document client
  and scroll width. Long destination, filename, and alt-text values each had equal
  client and scroll widths, so no document-level horizontal scrolling was present.
  The accessibility tree exposed the question, three radio options, Continue/Back/
  Restart controls, and return links. Selecting a radio and pressing Tab moved focus
  to Continue. The temporary viewport override was reset after testing.

Automated results for this prompt:

| Check | Result |
| --- | --- |
| `composer test` | Passed: 245 tests, 4,201 assertions. |
| `npm test` | Passed: 30 tests. Scanner outcomes remain unchanged. |
| `npm run lint` | Passed. |
| `composer phpcs` | Passed with no errors or warnings. |
| `git diff --check` | Passed; Git reports only configured LF/CRLF notices. |
| Build | No build script is configured. |

Unit coverage renders every current flow; walks every branch to a terminating
outcome; checks uncertainty semantics, historical graph preservation, exact rule
versions, Link Purpose scenarios, escaped/missing evidence, conservative automatic
routing, actionable outcome fields, valid Learn mappings, external/internal link
behavior, signed/nonce-protected persistence, actor and note history, explicit
status authority, and standalone educational isolation. Existing Reports and
scanner tests remain in the passing suite.

The local WordPress site refused the browser connection, so authenticated live
WordPress smoke testing remains release QA. The browser check used the actual
production controller rendered through `tools/guided-decision-browser-fixture.php`
and the production report stylesheet. Repeat live WordPress keyboard, zoom,
screen-reader, persistence/history, status, and return-navigation checks when the
site is running. These results are evidence, not a WCAG conformance claim.

## Prompt 5 — Learn Center integration and education

Learn is the reusable education layer in the workflow: Audit detects and supplies
finding-specific evidence; Learn explains the underlying concept across the site;
a compatible Guided Decision supports a contextual judgment; a person makes the
content, design, theme, or plugin change; re-audit and human testing verify it.
Learn neither duplicates each Finding nor changes the site.

### Editorial standard and organization

All 13 pre-existing topics were reviewed. Topic pages now normally answer what the
concept is, why it matters, what good looks like, common problems, how to check it,
how Elavira helps, WordPress guidance, developer guidance, examples where useful,
related tools, and WCAG references. Content is plain-language, practical, concise,
and explicit about the limits of automation. Techniques and Elavira recommendations
are not presented as normative requirements.

The directory retains the three ordered Start Here articles, then groups the 11
specific topics as Content and media; Structure and navigation; Forms and
interaction; Visual accessibility; and Developer semantics. Categories remain
available as filters. Groups are rendered only when they contain topics, and cards
use a logical heading level beneath the group heading. Search results use the
appropriate direct child heading level.

### New and substantially expanded education

`aria_accessible_controls` is the one new stable topic. It explains ARIA in terms
of names, roles, states, values and relationships; why native HTML comes first;
visible labels versus accessible names and descriptions; valid unique ID
references; `aria-hidden` with focusable descendants; synchronized state; complete
keyboard behavior for custom widgets; and why incorrect ARIA can override useful
native information. Escaped examples compare a native button with `role="button"`,
show a disclosure state, and distinguish a label from `aria-describedby`. The
“No ARIA is better than bad ARIA” principle is explained as a reason to add only
accurate, necessary, tested information—not as unexplained doctrine.

`focus_focus_order` now distinguishes the existence of focus from a person's
ability to see it. It covers visible custom indicators, logical order, positive
`tabindex`, sticky headers and footers, cookie banners, chat windows, overlays,
dialog entry/containment/restoration, zoom and narrow-viewport testing, and the
human-authoritative nature of keyboard/focus findings. An overlay is not declared
a failure merely because it exists.

`color_contrast` remains one topic because use of color and effective contrast are
related but distinct decisions that users commonly test together. It explains
normal text 4.5:1, qualifying large text 3:1, applicable essential control/graphic
boundaries 3:1, non-color cues, interactive states, gradients, images, opacity,
overlays, changing backgrounds, applicability, and why indeterminate rendering is
Human Review rather than a guessed ratio.

`images_alternative_text` now explicitly distinguishes informative, decorative,
functional, complex and text-containing images. Escaped linked-image examples show
why an image-only Contact link needs a purpose-based name, and why an arrow beside
useful “View pricing” link text can use an empty alternative to avoid repetition.
No universal alternative is prescribed or generated.

Start Here continues in the practical order Understanding WCAG → Understanding
conformance → Understanding audit results. It explains digital accessibility,
affected users, WCAG principles and levels, automation limits, Human Review,
the distinction between WCAG Target / Plugin Findings / Organization's Stated
Conformance, and the Audit → Learn → Decide → Fix → Re-audit → Verify workflow.

### Mapping and link policies

Rule-to-topic mappings are exact rule/version editorial contracts. Current
semantics and ARIA `1.0.0` contracts now map to `aria_accessible_controls`, except
`elavira.aria.hidden_focusable_content@1.0.0`, which remains mapped to
`keyboard_accessibility` because its finding asks the user to test actual focus
behavior. The five form-specific contracts remain in `forms_labels`. Historical
Link Purpose `1.0.0` and other existing contracts retain their established stable
topic IDs; unsupported versions are never inferred.

The current Control Roles and States Guided Decision now links to ARIA and
accessible controls plus Keyboard accessibility. Its questions remain
self-contained. Other current decision mappings were reviewed and retained.
Finding and decision CTAs use descriptive topic titles. Report/decision-to-Learn
links retain the workflow-preserving new-tab pattern; links among Learn pages stay
in the same tab.

Cross-links are deliberately sparse: Links ↔ Images, Keyboard ↔ Focus, and Forms ↔
ARIA/Accessible Controls. External lists contain only useful authoritative W3C
sources. The normative WCAG standard, explanatory Understanding documents, WAI
tutorial, and ARIA Authoring Practices labels identify their source and purpose.
Every external link opens in a new tab with `noopener noreferrer` and visible
“opens in a new tab” text, including the directory WCAG reference link.

### Accessibility, testing and deferred content

Code examples use semantic figures with labels and escaped markup. Developer prose
is no longer incorrectly presented as a code block. The directory and articles use
semantic headings, descriptive links, visible WordPress focus behavior, wrapping
for long text and URLs, responsive card/filter layouts, and contained overflow for
code. Automated tests render every topic, validate meaningful intros and all rule/
decision mappings, check focused content and escaping, enforce internal/external
link behavior, reject generic link names, preserve historical exact mappings, and
assert the narrow-layout safeguards.

No new scanner categories, decision flows, remediation behavior, conformance
claims, persistence, schema, Core/Premium boundary, or plugin version are part of
this work. Media and motion remain scanner-independent education, and a dedicated
article for every individual WCAG criterion is intentionally deferred to avoid
turning Learn into a specification index. Authenticated live-WordPress screen-reader
and browser-matrix review remains release QA.

## Prompt 6 — Integrated remediation and finding activity

### Status and activity semantics

A finding status is its current workflow state. An activity is a durable record
of what a person or audit did or documented. The existing
`finding_status_history` rows already store the resulting status, note, actor,
time, and optional source audit, so Prompt 6 requires no schema migration and no
parallel comments system. Previous status is inferred from the prior chronological
row.

Same-status plus a non-empty bounded note now creates an activity row while
retaining the current status. Multiple notes remain separate records with their
own actor and time. Same-status plus an empty note returns a safe no-op and shows
“No changes to save” instead of creating junk history or reporting a validation
failure. Note-only activity emits a dedicated activity hook but not the
status-change hook consumed by Statement recommendations. Real transitions keep
both hooks and their prior behavior.

The finding form is titled “Record finding activity.” Its selected status remains
the current value, “Activity note (optional)” has associated explanatory text,
and “Save update” accurately covers a status change, a note, or both. The visible
workflow explanation preserves the distinctions among Addressed, Verified,
Reviewed — Not an Issue, and Accepted Limitation.

The timeline uses a semantic ordered list and labeled definition data. It presents
the first event as “Finding created,” transitions as “Status changed” with an
explicit previous → resulting value, and same-status entries as “Note added” with
the current status. Actor, date/time, resulting status, and note remain visible
without relying on color. Empty legacy history receives a useful fallback.

### Decision, Learn, link, and evidence integration

An exactly compatible prior Guided Decision is announced on the finding with a
link to “Guided Decision activity” and a “Run Guided Decision again” action.
Conclusion, recommendation, actor, time, and workflow choice remain visible;
decision contract/version and detailed answers remain in a disclosure. Completing
a decision still does not change status unless the user explicitly selects a
different allowed workflow state.

Exact `DecisionRegistry::for_rule()` compatibility controls decision CTAs. A
historically resolved, registered Learn topic supplies the concept-specific
“Learn about [topic title]” CTA. Missing mappings render nothing—no empty card or
dead heading. Report, finding, decision, and return navigation stays in the same
tab. Finding-to-Learn, audited-page, and approved external links use the visible
new-tab pattern with `noopener noreferrer`. Captured link destinations remain
plain text because recorded evidence is not automatically useful navigation.

The guidance/evidence audit found no unconditional “shown above,” “shown here,”
or “the recorded value” claims in current report guidance. Link Purpose already
qualifies optional paragraph evidence with “when recorded,” and exposes captured
destination, filename, and alternative-text excerpts when available. Historical
records say when exact editorial content is unavailable and never fabricate
missing evidence.

Verification content for current contracts combines re-audit with the appropriate
manual inspection, keyboard, screen-reader/accessibility-tree, visual, or
contextual check. Human Review and Potential Issue language keeps judgment with
the reviewer. The activity form reinforces that Addressed is not proof of
resolution and only a subsequent audit can establish Verified; no UI claims that
disappearance of one finding establishes complete WCAG conformance.

### Edge states and QA

The integrated review covered no decision, no Learn mapping, neither mapping,
unavailable historical editorial content, missing historical evidence, only one
help feature, one or multiple occurrences, long activity, multiple same-status
notes and actors, Reviewed — Not an Issue eligibility, Addressed findings, and
reports with no findings. Existing conditional renderers omit unavailable
sections. “Resolved” in the requested illustrative flow maps to the established
Addressed state; the status model was not changed.

`tools/integrated-report-browser-fixture.php` uses real repositories and the
production report, decision, and Learn controllers for five scenarios: automatic
failure, potential Link Purpose review, focus/overlay Human Review, historical
fallback, and three-event multi-user activity. It supplies long URLs, selectors,
names, notes, guidance, multiple occurrences, and decision completion. At its 320
CSS-pixel content width, browser measurements were 320px client width and 320px
scroll width, with no horizontal overflow. Native controls, logical headings,
associated instructions, disclosures, and text event labels appeared in the
accessibility tree.

The actual local WordPress URL refused the connection during this pass. Therefore
authenticated persistence, post-redirect focus behavior, screen-reader/browser
matrix testing, and live XLSX download remain release QA. Controller/repository
fixtures and automated suites are not a WCAG conformance claim.
