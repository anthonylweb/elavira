# Elavira rule editorial standard

Applies to current Core findings and compatible historical guidance. Product semantics remain governed by TECH_SPEC.md, ENGINEERING_GUIDELINES.md and PHASE1_RULESET.md.

## Purpose

Help a person move from **Found → Understand → Locate → Evaluate → Decide → Fix → Verify**. A diagnostic is incomplete if a reasonable reader still cannot identify the affected content, decide whether it is a problem, choose a change or check the result.

Write for a developer or owner who may be frustrated by unfamiliar findings. Be
objective, calm and approachable. Explain the observation and next step without
blame or diagnostic jargon such as “individual mapped accessibility failure.”
Use direct confidence when evidence supports it; explicitly leave contextual
decisions with the person reviewing the page. The Rule Result messages in
ReportsPage follow this standard, including the historical alias and unavailable
explanation fallback.

Interface titles use initial capitals through scoped CSS. Keep prose and code in
their authored casing. Recorded occurrence headings must preserve filenames,
URLs and captured text exactly. Do not rewrite stored evidence or historical
editorial definitions merely to change how titles look. Newly rendered public
statement section titles use authored title case; changes do not authorize
republishing an existing statement.

## Field responsibilities

| Field | Responsibility |
|---|---|
| title | Short, readable description of the condition; avoid unexplained diagnostic jargon. |
| description | Accurate contract-level description of the observed condition. It must not promise evidence the scanner does not collect. |
| summary | What Elavira found and the certainty of that observation. Introduce unfamiliar terms when needed. |
| why_it_matters | Practical consequence for someone using the page. Avoid universal claims about disability experiences. |
| what_to_check | Concrete inspection and decision process: where to look, what to compare, what makes it acceptable or problematic. |
| recommendation | What to change when needed; separate the decision from implementation. Do not merely restate a success criterion. |
| verification_guidance | Specific checks of the rendered result, interaction, name, structure or measurement. Re-audit supplements human testing. |
| wordpress_guidance | Distinguish content/settings work from code/theme/plugin work when useful. Do not assume the editor controls generated output. |
| developer_guidance | Exact HTML/ARIA/CSS implementation details that support the plain-language advice, including relevant evidence limits. |
| examples | Optional teaching comparisons: instead_of, consider and explanation. Never captured page evidence or universal replacement values. |
| guided_decision | Editorial hint consistent with an actually compatible registered flow. The exact DecisionRegistry lookup remains authoritative. |
| learn_topic | One registered, materially helpful topic; no generic fallback just to display a link. |
| presentation | Existing presentation semantics, such as structural_warning. Copy refinement must not silently change result semantics. |

Do not dynamically concatenate sentence fragments around evidence. Keep translated, authored prose separate from escaped recorded values. Use literal WordPress translation calls in the elavira domain for new editorial prose and examples containing user-readable wording. Keep code examples literal and escape them when rendered; a narrowly documented PHPCS exception may distinguish an escaped example from actual HTML.

## Certainty and human authority

Automatic Failure copy can directly describe the objective condition supported by that exact contract. It must not enlarge the check into a whole-page or whole-criterion conformance claim.

Potential Issue and Human Review copy must explain why the condition was flagged, what a person must assess, when the condition may be acceptable, and what should change if it is a problem. A priority does not change certainty. A retained review flag after re-audit does not prove that the human's decision was wrong.

Preserve the workflow meanings of Addressed, Accepted Limitation, Reviewed — Not an Issue, Needs Review and Verified. Never instruct a user to manually mark a finding Verified. No guidance performs remediation or promises complete WCAG conformance.

## Evidence and location

Start with available occurrence identifiers: element text, image filename, link destination and context, field type/name, heading or language state. Do not fabricate missing excerpts or reconstruct content from fingerprints. Excerpts may be truncated and historical reports may omit newer metadata.

An available simple link-name excerpt is not necessarily the complete browser accessibility-tree name. ARIA naming overrides and references may require inspection on the actual page. Destinations omit query/fragment data. Do not infer full destination equivalence from the displayed shortened form.

When only technical location evidence exists, give an explicit route: use the recorded selector on the audited page or share that selector/URL with the responsible developer. Document evidence gaps rather than hiding them behind “review this element.”

## Examples

Use a small realistic before/consider comparison only when it teaches a useful distinction. The explanation must say when the suggested wording or implementation fits.

- A functional image alternative communicates the action or destination.
- Meaningful visible words in linked images must not disappear into an appearance-only description.
- A decorative image accompanying useful link text may correctly use empty alt.
- Generic text links can be meaningful through associated context; “Learn more” is not inherently a failure.
- Native labels and controls are preferred where suitable, but they are not the only valid naming mechanisms.
- Do not imply a universal alt length limit or an exactly-one-H1 WCAG requirement.

Never supply automatically approved alternative text or suggest adding ARIA solely to silence a warning.

## Technical verification

Match the test to the observation. Keyboard guidance must use role-appropriate keys and allow managed focus within composite widgets. Focus warnings need actual interaction and visual inspection. Contrast advice must distinguish reliable measurements from indeterminate rendering; explain applicability and exact thresholds without asking people merely to “increase contrast.”

Use understandable context when referring to WCAG. Distinguish normative requirements, informative techniques and Elavira best practices. For example, WCAG 2.4.11 at AA addresses focus not being entirely hidden by author-created content; aiming for the entire control to remain visible is a stronger practical goal. See W3C's [focus-not-obscured explanation](https://www.w3.org/WAI/WCAG22/Understanding/focus-not-obscured-minimum.html) and [non-text contrast explanation](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html) when reviewing those families.

## Exact-version policy

Current bundled refinements live in CoreRuleGuidance and become part of the existing RuleDefinition editorial model during CoreRuleRegistry assembly. Entries use exact rule ID/version keys. Original definitions continue to own scanner contracts and the legacy prose retained for historical versions. Do not use prefix/family fallback, highest-version advice for an older finding, or a database migration for copy.

Before adding or changing an entry, compare the exact contract's evidence and semantics. Same-contract compatible clarifications can preserve the existing editorial version, which means stored reports using that exact contract receive the clarified prose while snapshots remain untouched. Document this choice. Do not describe it as immutable audit-time prose.

If semantics differ, author an explicitly compatible entry for that version or retain its original copy. HistoricalRuleResolver continues to require the exact rule and stored editorial versions; unknown versions use the persisted summary with the existing warning. Broad support for multiple editorial editions per contract requires a separate design.

## Mapping policy

Read the existing flow or Learn topic, not just its title.

A Guided Decision must actually support the finding's exact rule/version and help with the needed decision. Do not broaden a flow to a related-sounding condition. Leave the CTA absent and document a gap when no flow fits. Clear stale editorial hints rather than fabricating compatibility.

A Learn topic must exist and help explain the finding. It supplements complete in-finding guidance. Document specialist content gaps even when a broader relevant topic can be retained.

## Review checklist for every current rule

1. Does the title/summary explain what was found in ordinary language?
2. Does the impact describe a practical consequence without overgeneralization?
3. Can the user locate the actual content from recorded information, with an explicit fallback?
4. Is the inspection a real decision process, including acceptable and problematic cases?
5. Does remediation say what to change, where, and why?
6. Does verification check correctness beyond merely rerunning a review rule?
7. Is necessary terminology explained where it appears?
8. Does copy stay within the scanner's actual evidence and uncertainty?
9. Are examples conditional, realistic, translated and escaped, with empty sections omitted?
10. Are WCAG, Guided Decision, Learn and historical compatibility accurate?
11. Are native semantics, keyboard/focus, authorization and Core/Premium boundaries preserved?
12. Have representative real report rendering, old-version fallback, exports and status/history been tested?

Maintain a registry-derived inventory and a second editorial pass. Run the existing PHP/JavaScript tests, lint, PHPCS and whitespace checks; add meaningful regression coverage for the rules and presentation being changed. Do not hard-code a current rule count in product copy.
