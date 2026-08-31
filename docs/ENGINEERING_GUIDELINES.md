# Elavira Engineering Guidelines

These are standing engineering requirements for the Elavira WordPress plugin. They supplement the Phase 1 Product & Technical Specification.

## 1. Source of truth

The Phase 1 Product & Technical Specification is the primary product and architectural source of truth.

Do not invent, remove, reinterpret, or expand product requirements unless explicitly instructed.

If an implementation requirement conflicts with the specification, stop and identify the conflict before proceeding.

Do not silently change:

- Product terminology
- Core vs. Premium boundaries
- Accessibility behavior
- Audit workflow
- Data model semantics
- User permissions
- Statement behavior
- Toolbar behavior
- Deferred/non-goal features

## 2. WordPress engineering standards

All code must follow current WordPress development best practices.

Use:

- WordPress APIs instead of reinventing platform functionality where appropriate.
- WordPress coding standards for PHP, JavaScript, CSS, and HTML.
- Proper hooks, actions, and filters.
- Namespaced or otherwise collision-safe PHP architecture.
- Unique prefixes for global identifiers where namespaces cannot be used.
- WordPress capabilities instead of hard-coded role checks.
- WordPress HTTP, database, filesystem, cron/scheduling, REST, settings, and metadata APIs where appropriate.
- `$wpdb->prepare()` or equivalent safe database methods for dynamic SQL.
- Translation/i18n functions for user-facing strings.
- WordPress-native escaping and sanitization functions appropriate to the context.

Do not modify WordPress core.

Do not assume a specific theme, page builder, hosting provider, database prefix, permalink structure, or JavaScript framework unless the specification requires it.

## 3. Backward compatibility and environment assumptions

Code should work within the plugin's explicitly supported WordPress and PHP versions.

Do not use APIs unavailable in the supported versions.

Avoid unnecessary dependencies.

Do not require:

- A specific theme
- WooCommerce unless WooCommerce-specific functionality is executing
- A separate queue/scheduler plugin for Core single-page scanning
- External accessibility/scanning services for Core single-page scanning
- External font services
- A visitor account
- Third-party analytics

Feature integrations must fail gracefully when the related plugin or platform capability is unavailable.

## 4. WCAG 2.2 AA is the UI baseline

Every interface Elavira creates must target **WCAG 2.2 Level AA**.

This applies to:

- WordPress admin screens
- Dialogs
- Tables
- Forms
- Notifications
- Reports
- Settings
- Audit controls
- Statement interfaces
- Guided Decisions
- Learn
- Visitor-facing toolbar
- Any future frontend components

Elavira should demonstrate the accessibility practices it recommends to users.

Do not create inaccessible interfaces in the name of providing accessibility functionality.

## 5. Semantic HTML first

Prefer native HTML elements and semantics before ARIA.

Use real:

- Buttons for actions
- Links for navigation
- Headings for document structure
- Labels for form controls
- Fieldsets and legends when grouping related controls
- Lists for actual lists
- Tables for tabular data

Do not create clickable `<div>` or `<span>` elements when a native control exists.

ARIA must only be used when necessary, and the implemented behavior must match the declared ARIA semantics.

**No ARIA is better than incorrect ARIA.**

## 6. Keyboard accessibility

Every interactive function must be fully operable with a keyboard.

Requirements include:

- Logical tab order
- No unnecessary positive `tabindex`
- No keyboard traps
- Visible focus
- Predictable keyboard interaction
- Enter/Space behavior appropriate to the native control
- Escape handling where expected
- Focus restoration after temporary interfaces close

Never remove focus outlines unless replacing them with an equally or more visible focus indicator.

## 7. Focus management

Focus must be deliberately managed when UI state changes.

Examples include:

- Opening dialogs
- Closing dialogs
- Validation errors
- Dynamically revealed controls
- Audit completion states
- Confirmation flows
- Inline editing

Do not move focus merely because content updates.

Where focus is programmatically moved, there must be a clear user benefit and predictable destination.

## 8. Dialogs and overlays

Modal dialogs must follow the expected accessible modal interaction model.

At minimum:

- Trigger remains identifiable
- Focus moves into the dialog on opening
- Dialog has an accessible name
- Background content is not keyboard-interactive while modal is active
- Tab and Shift+Tab stay within the modal
- Escape closes when appropriate
- A visible Close control exists
- Closing restores focus to the invoking control

The visitor Accessibility Preferences interface follows this same modal interaction model on desktop and mobile, regardless of visual presentation.

## 9. Color, contrast, and visual communication

Interfaces must meet WCAG 2.2 AA contrast requirements.

Information must never depend solely on:

- Color
- Shape
- Position
- Animation
- Iconography

Statuses such as High, Medium, Low, Review, Open, Addressed, Verified, and Accepted Limitation must have meaningful text labels.

Priority colors may reinforce meaning, but may not be the only means of communicating it.

## 10. Responsive layout, zoom, and reflow

Plugin interfaces must remain usable at:

- Browser zoom up to WCAG-required magnification scenarios
- Narrow viewport widths
- Mobile screen sizes
- Increased text spacing where required

Avoid fixed-height containers that clip content.

Avoid horizontal scrolling except where genuinely necessary for complex tabular information.

Tables should have an intentional responsive/reflow strategy.

## 11. Motion and animation

Avoid unnecessary motion.

Animation must not be required to understand or operate the interface.

Respect relevant reduced-motion preferences when animation is used.

Never introduce:

- Flashing that creates seizure risk
- Distracting continuous movement
- Animations that prevent timely interaction
- Motion-only communication

## 12. Accessible forms

Every form control must have a programmatically associated accessible name.

Validation must:

- Clearly identify the error
- Describe how to correct it
- Not rely on color alone
- Preserve entered information whenever reasonably possible

Required fields must be communicated programmatically.

Instructions should appear before users need them.

Do not use placeholder text as the only label.

## 13. Accessible dynamic updates

Dynamic interfaces must expose meaningful state changes to assistive technologies when appropriate.

Use live regions selectively.

Do not announce every minor interface update.

Prioritize meaningful changes such as:

- Audit started
- Audit paused
- Audit completed
- Audit failed
- Save succeeded
- Save failed
- Validation issue
- Important workflow-state change

Announcements should be concise and avoid repeated or disruptive output.

## 14. Accessibility claims

Never imply that automated scanning establishes complete accessibility or WCAG conformance.

Do not create:

- Accessibility scores
- Percentage-conformant scores
- Automated “site is accessible” claims
- Automated WCAG certification claims

The specified terminology must be preserved:

**Conformance Status — Based on Plugin Findings**

Keep these concepts distinct:

- WCAG Target
- Plugin Findings
- Organization's Stated Conformance

Automated accessibility testing is incomplete by definition.

## 15. Human judgment

Human judgment remains authoritative.

Automated checks may produce:

- Detected failures
- Potential issues
- Needs Review findings

Guided Decisions support human decision-making; they do not replace it.

Completion of a Guided Decision does not automatically mean an issue is resolved.

## 16. No automated remediation in Phase 1

Phase 1 follows:

**Detect → Explain → Recommend → User acts → Re-audit → Verify**

Audit findings must never automatically modify:

- Post/page content
- DOM markup
- CSS
- Theme files
- Plugin files
- ARIA attributes
- WordPress configuration
- Media
- Navigation
- Forms

Recommendations may tell the user how to remediate an issue, but the plugin does not perform the remediation.

## 17. Audit integrity

The audit target is the **rendered public experience**.

Where required, evaluate relevant client-side DOM changes rather than relying solely on stored WordPress content.

Do not treat stored editor markup as equivalent to the final rendered visitor experience.

Audit results must preserve enough rule/version metadata to remain understandable after accessibility rules evolve.

Scanner evidence must remain separate from editable educational/recommendation copy.

## 18. WordPress-specific guidance

When explaining remediation, distinguish between:

**WordPress guidance** — instructions suitable for site owners, editors, designers, and administrators.

**Developer guidance** — implementation-level information for developers.

Do not assume every user writes code.

Do not make the nontechnical guidance unnecessarily technical merely because the plugin is built for WordPress.

## 19. Security

All code must follow WordPress security best practices.

For state-changing actions:

- Verify capabilities.
- Verify nonces where applicable.
- Validate expected request methods.
- Validate and sanitize input.
- Escape output by context.

Never trust `$_GET`, `$_POST`, `$_REQUEST`, REST input, database values, user-generated content, URLs, query parameters, or JavaScript-provided state simply because they originate inside WordPress.

## 20. Authorization

Use capabilities, not roles, when authorizing actions.

The architecture should support granular capabilities such as:

- Run audits
- View reports
- Manage statement
- Manage toolbar
- Manage settings
- Manage accessibility

Core may map these capabilities to administrators by default.

Do not hard-code role checks when a capability check should be used.

## 21. CSRF protection

Any privileged action that changes state must use appropriate CSRF protection.

For standard WordPress admin flows, use nonces correctly.

A nonce is not authorization.

Always pair nonce validation with the appropriate capability check.

## 22. Input validation, sanitization, and output escaping

Treat these as separate responsibilities.

**Validate** whether a value is allowed.

**Sanitize** data before storage when appropriate.

**Escape** data at the point of output based on its output context.

Examples may include:

- `esc_html()`
- `esc_attr()`
- `esc_url()`
- `wp_kses()`
- `sanitize_text_field()`
- `sanitize_key()`
- `absint()`

Choose functions based on the actual data type and context rather than applying one sanitizer universally.

## 23. SQL safety

Use WordPress database APIs safely.

Parameterize dynamic values.

Never concatenate untrusted values directly into SQL.

Custom tables must use appropriate:

- Indexes
- Data types
- Schema versioning
- Upgrade routines

Schema migrations must be safe to run repeatedly.

## 24. URL security

Core single-page auditing is restricted to canonical public URLs belonging to the current WordPress site.

Validate the requested/canonical URL before authorizing a scan and before accepting persisted scan results. Do not allow a scan session to be repurposed for another origin or unauthorized URL.

Future Premium remote or batch scanning must additionally protect against SSRF and unintended internal-resource access, including redirect, encoded-host, alternative-port, userinfo, DNS, scheme, and private-network bypasses where reasonably preventable.

## 25. Privacy

Minimize stored data.

Do not store visitor-entered sensitive form content as audit evidence.

Visitor toolbar preferences remain in local browser storage and do not require:

- Accounts
- Analytics
- Server profiles
- Tracking identifiers

Core audit data remains within the WordPress installation unless a future specification explicitly introduces an external service.

## 26. Logging

Logs must not contain unnecessary sensitive information.

Avoid recording:

- Passwords
- Authentication tokens
- Full cookies
- Sensitive form contents
- Personal data unless essential
- Full request bodies by default

Premium diagnostics may expose more technical detail, but that does not remove privacy obligations.

## 27. Core single-page scan execution

Core single-page audits execute in the user's browser against the canonical public page.

Core must not require:

- A persistent site-wide audit queue
- A remote browser-worker service
- Chromium/Playwright/browser binaries on the WordPress host
- A separate scheduler/queue plugin
- The administrator's browser to remain open beyond completion of the individual single-page scan

The browser scanner should produce bounded structured evidence and persist results through an authenticated/authorized WordPress endpoint. It must not perform remediation.

## 28. Browser scanner boundary and integrity

Keep the browser execution layer separate from Elavira's product semantics.

The browser scanner may determine rule outcomes and collect the minimum evidence needed for a finding. WordPress/Elavira remains authoritative for:

- Rule metadata/version registry
- WCAG mappings
- Priority
- Explanations
- Recommendations
- WordPress guidance
- Developer guidance
- Guided Decision associations
- Finding workflow/history
- Reports and statement recommendations

Do not represent PHP-fetched source HTML as equivalent to a rendered browser scan when relevant client-side DOM changes have not executed.

Protect result submission against forged, stale, cross-site, or unauthorized requests. Bound runtime and payload size, and avoid collecting sensitive form values or unnecessary page content.

## 29. Premium site-wide and remote execution boundary

Site-wide audits, selected/multi-page batch audits, persistent audit queues, and remote browser-worker execution are Premium capabilities reserved for later implementation.

Core architecture should leave stable extension points for these systems without implementing speculative infrastructure or making Core depend on them.

When Premium site-wide execution is designed, it must address at minimum:

- Persistent/resumable jobs
- Bounded processing and concurrency
- Retry/backoff and failed-page isolation
- Safe leases/locks and stale-job recovery
- One active site-wide audit unless the specification is revised
- Worker authentication and authorization
- Same-site URL policy plus SSRF/network/redirect protections
- Browser isolation
- Data minimization and retention
- Service health and failure reporting
- Privacy disclosures
- Remote service cost/abuse controls

Do not implement these details before a dedicated Premium site-wide/remote-worker specification exists.
## 30. Performance

Avoid unnecessary work on every WordPress request.

Frontend assets should load only when needed.

Admin assets should load only on relevant plugin screens when practical.

Avoid:

- Large global queries
- Loading entire findings datasets into memory
- Rendering thousands of table rows
- Unbounded loops
- Excessive autoloaded options
- Repeated remote requests
- Large inline scripts/styles

Use pagination and server-side filtering for growing datasets.

## 31. Database design

Use custom tables where the audit/report workload warrants them rather than forcing high-volume relational data into post meta or options.

Respect the entities defined by the specification, including:

- Audit
- Audit URL
- Finding
- Occurrence
- Finding Status
- Guided Decision
- Statement
- Statement Recommendation
- Statement Revision
- Premium Queue Job (reserved for later site-wide execution)

Do not collapse logically separate entities merely to reduce table count if doing so harms performance, history, or data integrity.

## 32. Historical integrity

Historical reports should remain meaningful after:

- Rules change
- Guidance changes
- Priority recommendations evolve
- WCAG mappings are updated

Store relevant version metadata with results.

Do not retroactively rewrite historical scanner evidence when editorial guidance changes.

## 33. Data deletion and uninstall behavior

Follow the configured uninstall behavior.

Do not silently destroy audit history on plugin deactivation, plugin update, or temporary disablement.

Deactivation and uninstall are different operations.

Core reports are retained indefinitely unless the product specification changes.

## 34. Frontend isolation

Toolbar styles and scripts must avoid interfering with the host website.

Use scoped class names and CSS selectors.

Avoid broad selectors such as `button {}`, `a {}`, or `body * {}` unless they are explicitly part of a controlled visitor preference implementation.

Do not unintentionally overwrite theme styles.

## 35. “Default” has a specific product meaning

Whenever Elavira presents a **Default** presentation option:

**Default means the website's native styling and settings.**

It does **not** mean operating-system preference.

For example, Toolbar Appearance is **Default / Light / Dark**. Default preserves the site's normal styling and behavior, including whatever OS-preference handling the theme itself already implements.

## 36. Progressive enhancement

Core workflows should remain understandable and as functional as reasonably possible when JavaScript fails.

Where JavaScript is required for richer interaction:

- Server-side validation remains authoritative.
- Essential data integrity must not depend solely on client-side state.
- UI should fail gracefully.

## 37. JavaScript quality

Avoid unnecessary global state.

Use modular code.

Do not attach large numbers of uncontrolled global event listeners.

Clean up listeners and observers where lifecycle requires it.

Avoid DOM polling when MutationObserver or explicit events provide a better solution.

Do not disable native browser behaviors without a strong usability reason.

## 38. CSS quality

Prefer maintainable scoped CSS.

Avoid excessive specificity and `!important`.

Do not solve theme conflicts by escalating CSS specificity indiscriminately.

Toolbar preference layers should be designed deliberately because they intentionally modify presentation.

Changes must remain reversible through **Reset Preferences**.

## 39. Internationalization

All user-facing plugin strings must be translatable.

Avoid string concatenation that prevents meaningful translation.

Use placeholders appropriately.

Do not assume English sentence order.

Dates and numbers should use WordPress localization functions where appropriate.

## 40. Error handling

Failures should be:

- Recoverable where possible
- Logged appropriately
- Understandable to the user
- Actionable where practical

Do not expose raw PHP errors, stack traces, SQL queries, filesystem paths, or sensitive diagnostics to ordinary users.

Avoid vague messages such as “Something went wrong” when a useful safe explanation can be provided.

## 41. Graceful degradation

Failure of one system should not unnecessarily break another.

Examples:

- One failed audit URL must not fail the audit.
- Failure to load a Learn resource should not prevent viewing the finding.
- Missing statement data should not break reports.
- Disabled toolbar should not enqueue frontend assets unnecessarily.
- Missing optional integrations should not produce fatal errors.

## 42. Core vs. Premium separation

Core must remain a genuinely useful accessibility product.

Core includes rendered single-page browser auditing plus the findings, education, Guided Decisions, reporting, statement, toolbar, and related workflows defined by the specification.

Premium is the intended home for:

- Site-wide audits
- Selected/multi-page batch auditing
- Remote browser-worker execution
- Persistent site-wide processing/queue infrastructure
- Complex management and agency workflows
- Advanced URL/content controls
- Report retention configuration
- Developer diagnostics
- Permission management
- Future advanced workflows

Do not weaken the quality or truthfulness of Core single-page accessibility evaluation merely to create a Premium distinction. The commercial boundary is primarily **page-level evaluation vs. website-scale management/execution**, not deliberately less accurate accessibility rules for Core.

Code should allow Premium functionality to extend Core without requiring Core logic to become duplicated or forked.
## 43. Feature gating

Premium features should be gated cleanly.

Core code must not fatal or behave unpredictably if Premium is:

- Missing
- Deactivated
- Outdated
- Temporarily unavailable

Avoid scattering license checks throughout unrelated domain logic.

Prefer well-defined feature interfaces/capability services.

## 44. Public APIs and extensibility

Use hooks and filters where there is a clear extensibility case.

Do not make every internal value filterable merely for theoretical extensibility.

Public hooks must:

- Have stable names
- Be documented
- Receive predictable data
- Avoid exposing unsafe mutable internal state

Internal APIs should have clear boundaries.

## 45. Testing expectations

New functionality should include appropriate automated tests where practical.

Test especially:

- Authorization
- Validation
- Audit lifecycle
- Browser scanner authorization and result integrity
- Single-page scan lifecycle
- Status transitions
- Canonical/same-site single-page URL validation
- Statement recommendations
- Persistence
- Premium gating

Accessibility behavior that cannot be fully automated must also have documented manual testing requirements.

## 46. Accessibility testing expectations

Do not rely exclusively on an automated accessibility scanner.

For meaningful UI changes, validate with a combination of:

- Automated accessibility testing
- Keyboard-only testing
- Focus-order testing
- Screen-reader-oriented semantic inspection
- Zoom/reflow testing
- Contrast verification
- Reduced-motion behavior where applicable

Automated accessibility tests are useful evidence, not proof of conformance.

## 47. Regression protection

Before changing established code, inspect:

- Existing architecture
- Existing APIs
- Existing database schema
- Existing tests
- Existing hooks
- Existing UX behavior

Do not rewrite working systems merely because another implementation seems cleaner.

Refactoring should preserve behavior unless a behavior change is explicitly requested.

## 48. No speculative architecture rewrites

Solve the requested task, not the entire plugin.

Do not:

- Replace frameworks unnecessarily
- Rename major entities without instruction
- Rewrite unrelated features
- Introduce major dependencies without justification
- Change public APIs without need

If the existing architecture creates a serious technical issue, describe the problem before performing a broad rewrite.

## 49. Comments and documentation

Comments should explain **why**, not narrate obvious code.

Complex areas should document:

- Architectural decisions
- Single-page browser scanner behavior
- Premium extension boundaries where applicable
- Locking assumptions
- Security boundaries
- Accessibility-specific interaction requirements
- Data migrations
- Public hooks

Avoid stale comments and large commented-out code blocks.

## 50. Definition of done

A feature is not done merely because it renders.

Before considering work complete, verify:

- Product specification is satisfied.
- WordPress coding practices are followed.
- Capability/security checks are present.
- Input is validated/sanitized appropriately.
- Output is escaped appropriately.
- Keyboard behavior works.
- Focus behavior works.
- Semantic HTML is correct.
- WCAG 2.2 AA considerations have been reviewed.
- Responsive/zoom behavior works.
- Error states are handled.
- Data persists correctly.
- Existing functionality has not regressed.
- Core/Premium boundaries remain intact.
- No prohibited automated remediation has been introduced.
- Tests have been added or updated where appropriate.

## Reusable instruction for implementation tasks

> Read the Elavira Engineering Guidelines and Phase 1 Technical Specification before modifying code. Inspect the existing implementation first. Treat both documents as authoritative constraints. Do not invent product behavior. Do not weaken accessibility, security, WordPress best practices, or established Core/Premium boundaries to simplify implementation. If the requested task conflicts with either source of truth, identify the conflict before changing code.
