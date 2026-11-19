# Accessibility Plugin — Phase 1 Product & Technical Specification

**Product objective:** Make accessibility a normal, practical part of building and maintaining WordPress websites by detecting issues, explaining impact, guiding human decisions, and supporting transparent accessibility communication — without automated remediation in Phase 1.

**Primary audience:** WordPress agencies, developers, designers, and web managers responsible for building and maintaining client or organizational websites.

**Phase 1 operating principle:** **Detect → Explain → Recommend → User acts → Re-audit → Verify.** The plugin does not automatically modify content, markup, CSS, accessibility attributes, themes, plugins, or configuration in response to findings.

**Specification date:** August 31, 2026

**Revision note:** Core/Free Phase 1 is intentionally focused on rendered single-page auditing executed in the user's browser. Site-wide auditing and remote browser-worker infrastructure are Premium capabilities reserved for later implementation.

## Contents

1. Product principles and scope
2. Admin information architecture
3. Core single-page audit engine and Premium expansion boundary
4. Dashboard
5. Audits: Run Audit, lifecycle, reports and exports
6. Findings, workflow and Guided Decisions
7. Accessibility Statement
8. Visitor Accessibility Toolbar
9. Learn
10. Settings
11. Core vs. Premium
12. Data model and technical architecture
13. Accessibility, security, privacy and performance requirements
14. Phase 1 acceptance criteria
15. Deferred roadmap / non-goals

# 1. Product Principles and Scope

- Accessibility is not an overlay claim. The toolbar provides visitor-controlled presentation preferences; it does not make an inaccessible site conformant.
- No automated fixes in Phase 1. All remediation is advisory and requires deliberate action by a site administrator, web manager, designer, or developer.
- Rendered experience is the audit target. Audits evaluate the public page as rendered to a visitor, including relevant client-side DOM changes.
- Automated testing is incomplete by definition. The UI must distinguish detected failures, potential issues, and items requiring human judgment.
- No accessibility score. Do not present a percentage that implies overall accessibility or WCAG conformance.
- Conformance language must be qualified. Use **“Conformance Status — Based on Plugin Findings”** when representing what the plugin currently knows.
- Human judgment remains authoritative. Guided Decisions support judgment; they do not replace it.
- Core accessibility capability stays useful through rendered single-page auditing and the full improvement workflow around those scans. Premium adds site-wide auditing through remote browser workers plus agency-scale management, customization, retention, diagnostics, and permissions.

## Primary Phase 1 workflow

**Rendered Website → Audit → Automated Findings + Human Review → Guided Decisions → Recommendations → User Remediation → Re-audit → Verification → Statement Recommendations → Published Accessibility Statement**

# 2. Admin Information Architecture

| Area | Purpose |
|---|---|
| Dashboard | Operational overview: latest audit, open findings, human review, statement recommendations, Guided Decisions, toolbar status, recent audits. |
| Audits | Run rendered single-page audits and browse historical reports. Premium later adds persistent site-wide audit workflows. |
| Statement | Create, maintain, review, version, and publish the accessibility statement. |
| Toolbar | Configure the visitor-facing accessibility preferences toolbar and preview it. |
| Learn | Contextual accessibility education, WCAG explanations, topic guidance, Guided Decision directory, and WCAG reference. |
| Settings | Tabbed global configuration. Core tabs: General, Audit Defaults, Data & Privacy. Premium adds Content & URLs, Developer, Permissions. |

# 3. Core Single-page Audit Engine and Premium Expansion Boundary

## 3.1 Core scan type

- **Rendered single-page scan:** Core/Free scans one canonical public URL at a time using browser-side JavaScript executing against the page as rendered to the user, including relevant client-side DOM changes.
- Single-page scans are available from supported page/post/custom-post-type editing contexts and from the front-end admin bar.
- The scanner must evaluate the public visitor experience, not the WordPress editor DOM.
- A scan launched from an editing context must resolve to the canonical public URL and execute against that public page.
- Because Core scanning executes in the user's browser, the scan requires the scan page/browser session to remain open until that individual page scan completes. The UI must communicate this clearly when relevant.
- Core does not provide multi-page, selected-URL batch, content-type batch, or site-wide auditing in this release.

## 3.2 Browser scanner architecture

Core browser scanning must be designed as a defined scanner execution boundary rather than coupling the audit domain directly to frontend implementation details.

- Browser JavaScript evaluates the rendered DOM and produces structured scanner evidence.
- Scanner evidence should include stable rule ID/version, outcome/category, target/selector or equivalent element reference, and the minimum evidence required to explain the result.
- WordPress persists the audit, evidence, findings, workflow state, and report history.
- WCAG mappings, priority, explanations, recommendations, WordPress guidance, developer guidance, and Guided Decision associations remain Elavira domain/editorial data rather than being embedded as the browser worker's product logic.
- The browser scanner must not perform automated remediation.
- Scanner requests/results must be authorized and protected against forged or unauthorized persistence.
- Do not claim that PHP-only retrieval of server HTML is equivalent to rendered browser auditing.

## 3.3 Premium site-wide expansion — later implementation

Premium is the intended home for persistent site-wide auditing and remote browser-worker execution. These capabilities are architecturally anticipated but are **not part of the current Core implementation**.

Later Premium implementation may add:

- Managed inventory of intentionally public WordPress URLs.
- Selected-URL/content-type batch scopes and site-wide scopes.
- Canonical deduplication and advanced URL/content rules.
- Persistent audit/page jobs and resumable background processing.
- Retry/backoff, failure isolation, pause/resume/cancel, and concurrency controls.
- A controlled remote browser-worker service using a real browser execution environment for rendered pages.
- One active site-wide audit at a time unless a future specification explicitly changes that rule.
- Optional future self-hosted or agency runner implementations behind the same scanner contract.

Core architecture must leave clean extension points for these capabilities, but Core must not require a remote service, browser binary on the WordPress host, separate scheduler plugin, or persistent site-wide queue.

## 3.4 Premium remote worker principles — reserved

When implemented, a remote worker should be an execution environment rather than the owner of Elavira's product semantics. It should render and inspect a page, return structured evidence, and minimize retained page content. WordPress/Elavira remains authoritative for findings, rule metadata, guidance, history, Guided Decisions, reports, and statement recommendations.

Remote-worker security, authentication, privacy, abuse prevention, URL/network restrictions, scaling, retention, and service economics require a separate Premium implementation specification before production development begins.
# 4. Dashboard

- Latest audit date and scope.
- Pages/URLs scanned.
- Open findings.
- Needs Review count.
- Priority summary: High, Medium, Low; Review is shown separately as human review, not severity.
- Latest report and Run New Audit actions.
- Accessibility Statement status and pending recommendations.
- Guided Decisions available/incomplete.
- Visitor Toolbar enabled/disabled and configuration link.
- Recent audits.
- First-use empty state with Run Your First Audit, Create Statement, and Configure Toolbar actions.

Do not display an overall accessibility percentage or conformance score.

# 5. Audits: UI, Lifecycle, Reports and Exports

## 5.1 Run Audit

- Core scope is **Current Page / Single Page** only.
- A scan may be initiated from supported editing contexts or the front-end admin bar and must execute against the canonical public page.
- WCAG target defaults to WCAG 2.2 Level AA; Level A may be selected.
- WCAG Target includes an accessible info button/dialog explaining A, AA, and AAA, that levels are nested rather than grades, and that AAA is not a general whole-site target. Link to Learn.
- Default always means site styling/settings wherever the product uses a Default presentation choice; it never means OS preference.
- Core uses generated date/time-based audit labels.
- Premium may later add custom Audit Name, selected/multi-page scopes, site-wide scopes, advanced URL/content rules, and remote execution controls.

## 5.2 Active single-page scan

- Show clear scan state and current activity without implying a long-running background audit.
- Tell the user when the scan page/browser session must remain open until completion.
- Allow safe cancellation when technically applicable.
- A completed scan persists its audit metadata, evidence, findings, and report history in WordPress.
- Failed scans must produce an actionable error without creating false completed results.
- Core does not promise that a browser-executed single-page scan continues after the scan page/browser session closes.

## 5.3 Reports

- Historical report list with audit label/name, scope, URL count, issue count, date, and status.
- Report detail includes audit metadata, priority explanation, filters, search, findings table, and XLSX export.
- Group findings by issue/common cause rather than producing one report row for every DOM occurrence.
- Show affected page count and total occurrences; drill down to specific URLs/elements.
- Finding table columns: Status, Priority, Issue, Affected Pages, WCAG. Recommendation detail is opened rather than crammed into the table.

## 5.4 Priority

| Value | Meaning |
|---|---|
| High | Likely to create a significant accessibility barrier and generally should be investigated first. |
| Medium | Meaningful accessibility concern that should be addressed, with less immediate impact than High. |
| Low | Lower-impact accessibility concern or best-practice issue. |
| Review | Human evaluation is required; this is not a severity level. |

Every report and XLSX export must explain that Priority is an internal organizational recommendation, not an official WCAG severity or conformance grading system.

## 5.5 XLSX export

- **Summary sheet:** audit metadata, scope, target, dates, totals, failed/skipped URLs.
- **Issues sheet:** status, priority, issue, description, WCAG criterion/level, occurrences, affected page count, recommendation, WordPress guidance, developer guidance.
- **Affected URLs sheet:** finding-to-URL/element mapping.
- **Priority Guide sheet:** definitions and disclaimer.

# 6. Findings, Workflow and Guided Decisions

## 6.1 Finding detail

- What we found.
- Why this matters.
- Recommendation.
- How to address this in WordPress.
- Developer guidance.
- Affected pages and occurrences.
- Related WCAG criterion and level.
- Contextual Learn links.
- Status management does not modify the website.

## 6.2 Status and review-decision model

Finding workflow and scanner outcome are separate concepts.

### Scanner outcome

Each rule evaluation uses one of these semantic outcomes:

- **Pass** — the individual Elavira check found no issue. It does not establish that the complete WCAG success criterion or page passed.
- **Automatic Failure** — Elavira has sufficient objective evidence to establish the accessibility failure represented by the mapped rule.
- **Potential Issue** — Elavira found an objectively suspicious condition, but context is required before declaring an accessibility failure.
- **Human Review** — a person must evaluate the condition. Elavira supplies evidence, testing guidance, and possible remediation.
- **Not Evaluated** — Elavira cannot responsibly evaluate the condition.

Priority remains separate from outcome. High, Medium, and Low are Elavira organizational priorities; Review is not a severity.

### Finding workflow

- **Open** — unresolved finding.
- **Addressed** — user indicates remediation work has been performed; not proof of resolution.
- **Verified** — a subsequent audit verifies that a detectable condition is no longer present. Users cannot manually set Verified.
- **Accepted Limitation** — a known accessibility issue is intentionally retained and may participate in Accessibility Statement workflow.
- **Needs Review** — human evaluation remains outstanding.
- **Reviewed — Not an Issue** — a human reviewed the reported condition in context and determined that it is acceptable and is not an accessibility issue in the current implementation.

Reviewed — Not an Issue is not equivalent to Accepted Limitation.

### Reviewed exceptions and future audits

When a user marks an eligible Potential Issue or Human Review finding **Reviewed — Not an Issue**, Elavira may persist a reviewed exception so that the same materially unchanged condition is not repeatedly presented as an active issue in future audits.

A reviewed exception must:

- Be an explicit human decision.
- Remain auditable and discoverable.
- Preserve the original finding and status history.
- Be scoped at minimum by stable rule ID, rule version, canonical page, stable target fingerprint, and material evidence fingerprint or an equivalently robust domain identity.
- Never rely only on a CSS selector.
- Stop suppressing when the rule version, page, target, or material evidence changes enough that the prior decision is no longer safely applicable.
- Never suppress a different rule merely because it affects the same element.
- Be applied server-side from trusted normalized evidence, not from a browser-provided suppression key.
- Be reversible.

Normal active-finding views should exclude matched reviewed exceptions by default while providing an auditable way to view reviewed/suppressed decisions.

### Reset Reviewed Exceptions

Core provides a deliberate **Reset Reviewed Exceptions** action with at least:

- **This page**
- **All reviewed exceptions**

Reset removes future suppression decisions only. It does not delete or rewrite historical audits, findings, Guided Decision history, status history, reports, or statement history.

The confirmation must clearly explain that future audits will evaluate those conditions again.

This provides a safe fresh-review workflow after a redesign without destroying historical records.

## 6.3 Guided Decision system

Guided Decisions are rule-driven branching workflows for accessibility questions that automated testing cannot reliably answer. They are decision support, not automated judgments.

- Reusable schema: ID, title, applicable audit rules, questions, answer options, branching logic, outcomes, explanation, recommendation, WordPress guidance, developer guidance, references.
- When launched from a finding, save answers, outcome, date, and recommendation to audit history.
- When launched from Learn, operate as education and do not alter audit data.
- Outcome language: **“Based on your answers, the recommended approach is...”**
- Completion does not mark an issue fixed.

## 6.4 Phase 1 Guided Decisions

- Alternative Text: decorative, informative, functional, complex, image of text, further review.
- Link Purpose.
- Heading Structure.
- Form Labels & Instructions.
- Use of Color.
- Keyboard Interaction.
- Audio & Video Alternatives.

# 7. Accessibility Statement

## 7.1 Principle

The plugin recommends; the organization decides. Audit findings may generate statement recommendations, but nothing is added, removed, or published automatically.

## 7.2 Statement overview

- Published/draft status, last updated, public statement URL, View/Edit actions.
- Statement Health communicates pending attention without a score.
- Show sections and recommendations: Commitment, Conformance, Known Limitations, Feedback & Contact, Compatibility, Assessment Approach, Statement Information.
- Show latest audit linkage.

## 7.3 Guided creation

- First-use options: Create Statement or Connect Existing Statement.
- Guided steps: Organization, Commitment, Conformance, Known Limitations, Feedback, Assessment, Review.
- Structured fields generate editable statement copy; user reviews before publication.
- Plugin can create a normal WordPress Accessibility page (for example `/accessibility/`) rather than requiring only a shortcode.

## 7.4 Conformance terminology

- Use heading/label: **Conformance Status — Based on Plugin Findings** when presenting plugin-derived status.
- Persistent explanation: plugin findings do not constitute a complete determination of WCAG conformance; some criteria require manual evaluation.
- Keep three concepts distinct: WCAG Target, Plugin Findings, Organization's Stated Conformance.
- If user selects a strong conformance claim while unresolved applicable findings exist, warn and link to findings; do not block the user's choice.

## 7.5 Audit-to-statement recommendations

- After audits, identify findings that may represent known limitations.
- Show why the limitation is being suggested, affected pages/occurrences, suggested language, and link to the audit finding.
- Actions: Add to Statement, Edit & Add, Dismiss.
- Dismissal records a reason such as not a limitation, already addressed, already covered, do not include, or other.
- Guided Decisions may be required before an ambiguous finding becomes a statement recommendation.

## 7.6 Publishing and history

- Support Save Draft, Preview Draft, Publish Changes.
- Show front-end preview before publishing.
- Track actual modification dates and user-specified review date.
- Keep basic published revision history in Phase 1; viewing history is required, restoring older versions may be deferred.

# 8. Visitor Accessibility Toolbar

## 8.1 Purpose and semantics

- Visitor-controlled presentation preferences only; no conformance or remediation claim.
- Persistent accessible trigger with visible/focusable identification: **Accessibility Preferences**.
- Desktop presentation resembles a side panel; mobile may use a near/full-screen layout.
- Semantically both use one modal dialog interaction model.
- On open: remember trigger, move focus into dialog, contain Tab/Shift+Tab, Escape closes, Close button closes, restore focus to trigger.
- Changes apply immediately to the page behind the modal; no Apply button.

## 8.2 Phase 1 controls

| Control | Behavior |
|---|---|
| Text Size | 100%–150% stepped increase; decrease never goes below 100%. |
| Text Spacing | Default / Increased tested preset. |
| Line Height | Default / Increased tested preset. |
| High Contrast | Controlled presentation layer; avoid destructive whole-page filtering. |
| Appearance | Default / Light / Dark. Default always preserves the site's own styling and behavior, including any theme-level OS preference handling. |
| Grayscale | Default / Grayscale; explicit visitor choice. |
| Highlight Links | Adds strong non-color differentiation while respecting obvious button/navigation affordances. |
| Enhanced Focus | Strengthens focus visibility without removing existing focus styles. |
| Reading Guide | Reading aid with pointer support and a keyboard-compatible interaction model. |
| Dyslexia-Friendly Font | Optional locally bundled alternative font; do not market as a cure or universal dyslexia mode. |
| Reset Preferences | Immediately returns all plugin preferences to Default. |

## 8.3 Persistence and loading

- Store visitor preferences locally in the browser; no account or server tracking required.
- Apply stored root classes/settings as early as practical to reduce flash between native and preferred presentation.
- No external font service dependency for the dyslexia-friendly font.
- If a published Accessibility Statement exists, link to it from the toolbar.

## 8.4 Admin Toolbar page

- Enable/disable toolbar.
- Enable/disable each optional preference; Reset is always available.
- Position: Bottom Right default; Bottom Left, Top Right, Top Left available.
- Preview on Site before enabling/publishing changes.
- The audit engine must not exempt the plugin's own front-end UI from accessibility testing.

# 9. Learn

Learn is a Core knowledge center, not a marketing/blog feed. It provides practical WordPress-specific accessibility education and is linked contextually throughout the plugin.

## 9.1 Information architecture

- **Start Here:** Understanding WCAG, Understanding Conformance, Understanding Audit Results.
- **Topics:** Images & Alternative Text; Headings & Structure; Links & Navigation; Forms & Labels; Keyboard Accessibility; Focus & Focus Order; Color & Contrast; Audio & Video; Language & Content; Motion & Animation.
- Guided Decisions directory.
- Lightweight WCAG Reference with principle/level/search filters and links to official W3C material.

## 9.2 Topic template

Every topic follows:

**What it is → Why it matters → Who it affects → What good looks like → Common mistakes → WordPress guidance → Developer guidance → Guided Decisions → Related WCAG → Authoritative resources**

## 9.3 Contextual help

- Run Audit WCAG info dialog links to Learn.
- Report WCAG/priority/help controls link to relevant Learn entries.
- Statement conformance help links to Learn.
- Toolbar feature help links to relevant topics.
- Guided Decision questions may expose a **Why does this matter?** link.

# 10. Settings

Settings use tabs. Feature-specific configuration remains in its feature area rather than being duplicated globally.

## 10.1 Core tabs

| Tab | Phase 1 contents |
|---|---|
| General | Default WCAG target, admin notifications, admin-bar visibility, basic system status. |
| Audit Defaults | Default WCAG target and single-page scanner defaults that are meaningful for Core. Site-wide scope, queue, and remote-worker settings are not exposed in Core. |
| Data & Privacy | Core storage disclosures, local visitor preferences disclosure, uninstall behavior. Free reports are kept indefinitely. |

## 10.2 Premium tabs

| Tab | Premium capability |
|---|---|
| Content & URLs | Site-wide/selected scope controls, custom path exclusions/inclusions, wildcard rules, query parameter rules, advanced content rules; future saved scopes. |
| Developer | Debug logging and detailed diagnostics; later Premium site-wide implementation may add remote-worker/processing queue inspection and failed jobs. |
| Permissions | Role/capability management for audits, reports, statement, toolbar, and settings. |

## 10.3 Core system health

- Basic operational health remains Core: browser scanner availability, database availability, persistence health, and actionable failure messages.
- Premium Developer diagnostics expose forensic detail. When site-wide/remote execution is implemented, basic user-facing service health must still be understandable without requiring forensic access.

## 10.4 Report retention

- **Core:** reports and detailed findings retained indefinitely; no retention controls.
- **Premium:** configurable summary/detail retention periods, storage visibility, and future archive/export-before-deletion options.

# 11. Core vs. Premium

| Capability | Tier |
|---|---|
| Rendered single-page audits using browser JavaScript | Core |
| Admin-bar / supported editing-context single-page scan launch | Core |
| WCAG reporting and recommendations | Core |
| Guided Decisions | Core |
| Historical reports for completed single-page scans | Core |
| XLSX export | Core |
| Accessibility Statement and audit recommendations | Core |
| Visitor toolbar | Core |
| Learn / WCAG education | Core |
| Site-wide audits | Premium — later implementation |
| Selected-URL/content-type batch audits | Premium — later implementation |
| Remote browser-worker execution | Premium — later implementation |
| Persistent site-wide audit queue and recovery controls | Premium — later implementation |
| Advanced URL/query/content rules | Premium |
| Custom audit names | Premium |
| Report retention controls | Premium |
| Developer diagnostics / future worker and queue inspection | Premium |
| Permission management UI | Premium |
| Future saved audit scopes | Premium backlog |
| Future scheduled audits | Premium candidate / later phase |
| Future statement approval/client workflow | Premium backlog |

**Commercial principle:** Core contains what is needed to evaluate and improve one rendered page at a time, including education, reporting, human review, and communication workflows. Premium extends Elavira from page-level improvement into website-scale accessibility management and professional operations.
# 12. Data Model and Technical Architecture

## 12.1 Recommended entities

| Entity | Key responsibilities |
|---|---|
| Audit | ID, generated/custom label, scope, WCAG target, status, start/end times, counts, configuration snapshot, owner. |
| Audit URL | Audit ID, canonical URL, content type/object ID, scan status/timing, and failure reason for the scanned page. Structure should remain extensible to future multi-page Premium audits. |
| Finding | Audit ID, rule ID, grouped issue key, priority, review state, WCAG mapping, description, recommendation, counts. |
| Occurrence | Finding ID, URL ID, selector/element metadata, evidence needed for report/detail. |
| Finding Status | Workflow status and status history; Addressed is user-declared, Verified is re-audit evidence. |
| Guided Decision | Guide ID/version, finding context, answers, outcome, recommendation, completion metadata. |
| Statement | Structured sections, draft/published state, page association, conformance fields, dates. |
| Statement Recommendation | Finding/group source, suggested limitation text, state, dismissal reason, audit linkage. |
| Statement Revision | Published snapshot and metadata. |
| Premium Queue Job (reserved) | Future Premium site-wide entity for audit/URL references, priority, state, lock/lease data, attempts, next run, and error metadata. Do not require this entity for Core single-page scans. |

## 12.2 Rule architecture

- Stable rule IDs and explicit rule/scanner-contract versions are required.
- Browser JavaScript returns bounded structured evidence; WordPress owns WCAG mappings, priority, classification, explanations, recommendations, WordPress guidance, developer guidance, and Guided Decision associations.
- Historical rule/editorial versions must remain resolvable where required to explain historical findings accurately.
- Rule grouping and cross-rule supersession are server-authoritative.
- Browser-provided grouping or suppression identities are not trusted.
- The approved initial production rule inventory and classification requirements are defined in `/docs/PHASE1_RULESET.md`.
- Do not equate invalid markup with a WCAG failure unless the rule can establish the accessibility condition required by the mapped success criterion.
- Do not equate an individual rule Pass with complete success-criterion or page conformance.

## 12.3 Permissions architecture

Define granular WordPress capabilities in the underlying architecture from the beginning (for example manage accessibility, run audits, view reports, manage statement, manage toolbar, manage settings). Core may map them to administrators by default; Premium exposes role/capability management.

# 13. Accessibility, Security, Privacy and Performance Requirements

## 13.1 WCAG and admin accessibility

- All plugin admin and front-end UI must target WCAG 2.2 AA.
- Full keyboard operability, visible focus, logical focus order, semantic controls, accessible names, sufficient contrast, and zoom/reflow support.
- ARIA only when needed and only when behavior matches the declared semantics.
- Accessible modal behavior for toolbar and other dialogs; no accidental keyboard traps.
- Tables require proper headers and responsive/reflow strategy.
- Status/priority must not rely on color alone.
- Motion must respect accessibility best practices and avoid unnecessary animation.

## 13.2 Security

- Use WordPress capability checks and nonces for state-changing admin actions.
- Sanitize/validate stored configuration and user-entered URLs; escape output by context.
- Only allow Core single-page auditing of canonical public URLs belonging to the current site.
- Protect scan-result persistence against unauthorized or forged requests. Future Premium remote/site-wide execution requires a separate threat model for worker authentication, SSRF/network restrictions, redirects, and queue concurrency.
- Do not expose sensitive diagnostics to users without appropriate capability.
- Avoid storing sensitive form values or visitor-entered data as scan evidence.

## 13.3 Privacy

- Core single-page audit content remains in the site's WordPress database and Core does not require an external scanning service. Future Premium remote browser-worker execution requires explicit privacy disclosures and a separate data-minimization/retention specification.
- Visitor toolbar preferences are stored locally in the browser.
- Do not require visitor accounts or analytics/tracking for toolbar operation.
- Verbose/debug logs must avoid sensitive content.

## 13.4 Performance

- Core single-page scanning must not require a full site-wide crawl, persistent background queue, remote browser service, or browser binary on the WordPress host.
- Browser scanner JavaScript must be scoped to authorized scan sessions and should avoid materially degrading the page being evaluated.
- Bound scan runtime and evidence payload size; avoid collecting unnecessary DOM/page content.
- Frontend toolbar bootstrap must be small and avoid unnecessary layout shift or flash.
- Audit tables must paginate/filter server-side as data grows; do not render unbounded report history or occurrence rows at once.
- Future Premium site-wide processing must be incremental, resumable, bounded, and designed separately before implementation.
# 14. Phase 1 Acceptance Criteria

- A user can run a rendered single-page scan against the canonical public page using browser-side JavaScript and receive persisted findings with explanations, WCAG mapping, recommendations, WordPress guidance, and developer guidance.
- A single-page scan evaluates the rendered DOM, including relevant client-side changes, rather than claiming server-fetched HTML is the rendered visitor experience.
- The scan workflow clearly communicates that the scan page/browser session must remain open until that individual Core scan completes when required by the execution flow.
- Completed scans produce searchable/filterable grouped reports and XLSX export.
- Priority definitions/disclaimer appear in reports and exports.
- Human-review findings can launch applicable Guided Decisions and save their answers/outcomes to audit history.
- The Statement builder can create/connect a statement, maintain drafts, surface audit-derived recommendations, and publish only after user action.
- Plugin-derived conformance language is clearly qualified as based on plugin findings.
- The visitor toolbar exposes all 11 agreed controls, uses correct modal focus behavior, persists explicit choices locally, and treats Default as native site styling/settings.
- Learn provides the agreed Start Here pages, ten topic areas, seven Guided Decisions, contextual links, and WCAG reference.
- Core Settings use tabs; Premium-only settings are separated as specified.
- Core remains fully functional without Premium, remote browser workers, a persistent site-wide queue, or external scanning services.
- No audit finding triggers an automated remediation change anywhere in Phase 1.
- Site-wide auditing and remote browser-worker execution are not required for Core Phase 1 acceptance and must not be silently implemented as Core dependencies.

Additional ruleset/review acceptance criteria:

- The initial production rule categories and semantics match `/docs/PHASE1_RULESET.md`.
- Keyboard and focus findings remain human-authoritative.
- Contrast failures are automatic only when the effective rendered comparison is determinate and applicable.
- Missing/multiple H1 findings are prominent Structural Warnings/Human Review, not automatic WCAG failures.
- `Reviewed — Not an Issue` is distinct from `Accepted Limitation`.
- Eligible reviewed decisions can suppress the same materially unchanged condition on future audits without deleting history.
- Reviewed-exception matching is server-authoritative, version-aware, page-aware, target-aware, and evidence-aware; selector-only suppression is prohibited.
- Users can reset reviewed exceptions for a page or globally without deleting audit/report/history data.
- Cross-rule grouping/supersession prevents avoidable duplicate active findings while retaining diagnostic evidence.

# 15. Deferred Roadmap / Explicit Non-goals

Phase 1 should resist scope expansion. The following are intentionally deferred or reserved for later evaluation.

- Automated or semi-automated remediation — Phase 2/3 candidate only after the recommendation/audit system is stable.
- Site-wide auditing, selected/multi-page batch auditing, persistent site-wide queue, and remote browser-worker execution — Premium later implementation; architecture may anticipate them but Core does not depend on them.
- Scheduled audits — later Premium feature.
- Saved/reusable audit scopes — premium backlog.
- Advanced statement approval/client workflow — premium backlog.
- Statement PDF export and enhanced branding/templates — premium backlog.
- Restore old statement revisions — later enhancement; Phase 1 keeps viewable published history.
- Self-hosted browser workers, advanced server/CLI runners, and agency runners — potential later Premium features.
- Arbitrary off-site URL scanning — not Phase 1.
- Claims that automated scanning establishes complete WCAG conformance — prohibited product behavior.
- Accessibility scores or percentage-conformant grades — prohibited Phase 1 behavior.

## Phase 1 source-of-truth principle

**Build the smallest reliable system that makes accessibility discovery, human decision-making, remediation guidance, verification, and communication substantially easier for WordPress teams. Add automation only after those foundations are trustworthy.**
