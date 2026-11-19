# Learn Center Inventory

This is the Prompt 5 registry and integration review. The pre-change registry had
13 topics. Every topic used the server-owned `TopicDefinition` contract and was
rendered by `LearnPage`; content normally included five short conceptual sections,
WordPress and developer guidance, related rules/topics, and WCAG metadata.

The Audit Report resolved Learn through the finding's exact historical rule
contract. Guided Decisions declared stable topic IDs and opened Learn in a new tab.
Internal Learn navigation remained same-tab. Before this review the directory was
an ungrouped card grid, developer prose was rendered as code, and the Forms article
carried most semantics/ARIA rules despite not being the best conceptual match.

## Reviewed topic inventory

| Stable topic ID | Purpose and content | Current mapped rules | Current Guided Decisions | WCAG | Review result / important gap |
| --- | --- | --- | --- | --- | --- |
| `understanding_wcag` | Accessibility, affected users, POUR principles, levels | None | None | Orientation only | Retained practical Start Here role; no legal survey added. |
| `understanding_conformance` | Conformance, automation limits, separate product statuses | None | None | Orientation only | Accurate distinction retained; remains intentionally concise. |
| `understanding_audit_results` | Audit, Report, Finding, Learn, decision, fix and verification workflow | None | None | Orientation only | Workflow language retained and aligned with the new Learn role. |
| `images_alternative_text` | Informative, decorative, complex, functional, text and linked images | Four image rules | Alternative Text; Link Purpose | 1.1.1 A | Substantially expanded; linked-image and decorative mixed-link examples added. |
| `forms_labels` | Visible labels, names, instructions, required fields and errors | Five form rules | Form Labels and Instructions | 4.1.2 A; 3.3.2 A | Narrowed to form concepts; ARIA material moved to a dedicated article. |
| `links_navigation` | Link purpose, names, destinations and useful context | Five exact link contracts including historical versions | Link Purpose | 2.4.4 A | Existing guidance was useful; retained with Images cross-link. |
| `headings_structure` | Section names and logical content hierarchy | Six exact heading contracts including historical versions | Heading Structure | 1.3.1 A; 2.4.6 AA | Existing best-practice caveat retained; no exactly-one-H1 claim. |
| `language_content` | Primary language and language changes | Four exact language contracts including historical versions | None | 3.1.1 A; 3.1.2 AA | Existing WordPress guidance retained. |
| `keyboard_accessibility` | Reach, operation, expected keys and traps | Five keyboard rules plus hidden-focusable ARIA | Keyboard Interaction; Control Roles and States | 2.1.1 A; 2.1.2 A | Existing manual testing path retained; focus details live in the related topic. |
| `focus_focus_order` | Visible focus, order, obscuring content and dialog lifecycle | Five focus/order rules | Keyboard Interaction; Focus Visibility and Overlays | 2.4.3 A; 2.4.7 AA; 2.4.11 AA | Substantially expanded for overlays, zoom and human-authoritative testing. |
| `color_contrast` | Color-only meaning and effective text/non-text contrast | Five color/contrast rules | Use of Color; Uncertain Contrast | 1.4.1 A; 1.4.3 AA; 1.4.11 AA | Substantially expanded; kept together for clearer testing workflow. |
| `audio_video` | Captions, transcripts, visual information and controls | None | Audio and Video Alternatives | 1.2.1 A; 1.2.2 A; 1.2.3 A; 1.2.5 AA | Useful manual education; scanner relationship intentionally deferred. |
| `motion_animation` | Pause, autoplay, flashing and reduced motion | None | None | 2.2.2 A; 2.3.1 A | Useful manual education; scanner relationship intentionally deferred. |
| `aria_accessible_controls` | Native HTML, ARIA, names, roles, states, values, ID relationships, hidden content and custom behavior | Twelve current semantics/ARIA contracts | Control Roles and States | 4.1.2 A; 1.3.1 A | New topic closes the largest conceptual and mapping gap. |

All non-Start Here topics now expose a scannable conceptual sequence: What this
is, Why it matters, What good looks like, Common problems, How to check it, and How
Elavira helps. Topic-specific WordPress/developer guidance and examples follow only
where useful. The focused ARIA, Focus, Color/Contrast and Images articles received
the material rewrites; good existing copy in Links, Forms, Headings, Language,
Keyboard, Audio/Video and Motion/Animation was not rewritten merely to create a
diff.

## Mapping decisions

The twelve current semantics/ARIA contracts moved from `forms_labels` to
`aria_accessible_controls`. `elavira.aria.hidden_focusable_content@1.0.0` remains
with `keyboard_accessibility`. The Control Roles and States current decision now
links to ARIA and Keyboard. No other decision mapping changed. All mappings resolve
by exact registered ID/version; historical definitions receive no inferred topic.

## Deferred gaps

Learn intentionally does not provide one article per rule or WCAG criterion. Media
and motion have no current scanner rule, and their education remains manual. A
larger assistive-technology/browser compatibility matrix, legal jurisdiction
guidance, and exhaustive ARIA widget implementation cookbook are outside Phase 1.
The official W3C resources support deeper study without making Learn a link index.
