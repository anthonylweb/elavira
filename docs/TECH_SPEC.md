# Accessibility Plugin — Phase 1 Product & Technical Specification

**Product objective:** Make accessibility a normal, practical part of building and maintaining WordPress websites by detecting issues, explaining impact, guiding human decisions, and supporting transparent accessibility communication — without automated remediation in Phase 1.

**Primary audience:** WordPress agencies, developers, designers, and web managers responsible for building and maintaining client or organizational websites.

**Phase 1 operating principle:** **Detect → Explain → Recommend → User acts → Re-audit → Verify.** The plugin does not automatically modify content, markup, CSS, accessibility attributes, themes, plugins, or configuration in response to findings.

**Specification date:** August 30, 2026

## Contents

1. Product principles and scope
2. Admin information architecture
3. Audit engine and site-wide processing
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
- Core accessibility capability stays useful. Premium primarily adds agency-scale management, customization, retention, diagnostics, and permissions.

## Primary Phase 1 workflow

**Rendered Website → Audit → Automated Findings + Human Review → Guided Decisions → Recommendations → User Remediation → Re-audit → Verification → Statement Recommendations → Published Accessibility Statement**

# 2. Admin Information Architecture

| Area | Purpose |
|---|---|
| Dashboard | Operational overview: latest audit, open findings, human review, statement recommendations, Guided Decisions, toolbar status, recent audits. |
| Audits | Run single-page or site-wide audits; monitor active audit; browse historical reports. |
| Statement | Create, maintain, review, version, and publish the accessibility statement. |
| Toolbar | Configure the visitor-facing accessibility preferences toolbar and preview it. |
| Learn | Contextual accessibility education, WCAG explanations, topic guidance, Guided Decision directory, and WCAG reference. |
| Settings | Tabbed global configuration. Core tabs: General, Audit Defaults, Data & Privacy. Premium adds Content & URLs, Developer, Permissions. |

# 3. Audit Engine and Site-wide Processing

## 3.1 Scan types

- **Single-page scan:** available from page/post/custom-post-type editing contexts and from the front-end admin bar. Scans only the canonical rendered URL for that content.
- **Selected URL/content scan:** user chooses a limited set of public URLs or content types.
- **Site-wide audit:** scans the managed inventory of eligible canonical public URLs. Only one site-wide audit may be active at a time.

## 3.2 Self-contained background queue

The plugin must implement its own purpose-built persistent audit queue. It may borrow proven queue concepts, but Phase 1 must not require a separate scheduler plugin or user-managed dependency.

- Persist audit and page-scan jobs in the WordPress database.
- Process work in small batches to respect PHP execution and memory limits.
- Each URL is an independent job; one failed page must not fail the audit.
- Support queued, processing, completed, failed, retrying, skipped, paused, and cancelled states.
- Support retry/backoff and recovery after request termination or temporary host failure.
- Persist progress incrementally so audits are resumable.
- User-initiated single-page scans receive higher queue priority than background site-wide jobs.
- A paused site-wide audit still counts as the single active site-wide audit.
- Finalization aggregates page findings only after all eligible jobs have reached a terminal state.

## 3.3 URL discovery

- Build a managed inventory from intentionally public WordPress content rather than blindly crawling every discovered link.
- Include published pages, posts, public custom post types, products, homepage, and applicable public archives.
- Use canonical URLs to deduplicate equivalent pages.
- Automatically ignore `wp-admin`, previews, feeds, REST endpoints, XML sitemaps, add-to-cart/action URLs, common tracking parameters, and duplicate canonicals.
- WooCommerce variations that are UI states of one canonical product page do not automatically become separate URLs.
- Core supports basic content-type inclusion/exclusion. Premium provides custom URL patterns, query rules, advanced content rules, and later saved audit scopes.

## 3.4 Large sites

Do not impose an arbitrary hard site-size limit. Show the estimated URL count before starting and provide a large-audit warning with options to continue or adjust scope. Processing remains incremental and resumable.

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

- Scope: Entire public website / Specific content types / Selected URLs.
- Content Included: dynamically discovered Pages, Posts, Products, public custom post types, public archives.
- Estimated URL count and View URL Inventory.
- WCAG target defaults to WCAG 2.2 Level AA; Level A may be selected.
- WCAG Target includes an accessible info button/dialog explaining A, AA, and AAA, that levels are nested rather than grades, and that AAA is not a general whole-site target. Link to Learn.
- Default always means site styling/settings wherever the product uses a Default presentation choice; it never means OS preference.
- Performance preset: Conservative / Balanced (default) / Performance; advanced technical tuning is not required for normal use.
- Premium: optional custom Audit Name. Core uses generated date/time-based labels.
- Premium: advanced custom URL exclusions/rules. Core always applies built-in safe URL hygiene.

## 5.2 Active audit

- Show started time, processed/total URLs, progress, completed, skipped, failed, remaining, findings so far, and current activity.
- Allow Pause and Cancel.
- Leaving the page does not interrupt processing.
- If another site-wide audit is attempted, direct the user to the existing active audit.
- Failed URLs can be retried; user may complete the audit without them. Final report must disclose unscanned failures.

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

## 6.2 Status model

- **Open.**
- **Addressed** — user indicates remediation work has been performed; not proof of resolution.
- **Verified** — subsequent audit confirms the detectable condition is no longer present.
- **Accepted Limitation** — intentionally retained and eligible for Statement workflow.
- **Needs Review** is treated as an evaluation state/category and may be supported by a Guided Decision.

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
| Audit Defaults | Default scope/content types, scanning profile, retry behavior. One-site-wide-audit rule is fixed, not configurable. |
| Data & Privacy | Core storage disclosures, local visitor preferences disclosure, uninstall behavior. Free reports are kept indefinitely. |

## 10.2 Premium tabs

| Tab | Premium capability |
|---|---|
| Content & URLs | Custom path exclusions/inclusions, wildcard rules, query parameter rules, advanced content rules; future saved scopes. |
| Developer | Debug logging, detailed diagnostics, processing queue inspection, failed jobs, advanced audit diagnostics. |
| Permissions | Role/capability management for audits, reports, statement, toolbar, and settings. |

## 10.3 Core system health

- Basic operational health remains Core: background processing, rendered scanning, database availability, and actionable failure messages.
- Premium Developer diagnostics expose forensic detail, not the ability to know whether the plugin is functioning.

## 10.4 Report retention

- **Core:** reports and detailed findings retained indefinitely; no retention controls.
- **Premium:** configurable summary/detail retention periods, storage visibility, and future archive/export-before-deletion options.

# 11. Core vs. Premium

| Capability | Tier |
|---|---|
| Single-page audits | Core |
| Site-wide audits | Core |
| WCAG reporting and recommendations | Core |
| Guided Decisions | Core |
| Historical reports | Core |
| XLSX export | Core |
| Built-in URL hygiene and basic content scope | Core |
| Accessibility Statement and audit recommendations | Core |
| Visitor toolbar | Core |
| Learn / WCAG education | Core |
| Custom audit names | Premium |
| Advanced URL/query/content rules | Premium |
| Report retention controls | Premium |
| Developer diagnostics / queue inspection | Premium |
| Permission management UI | Premium |
| Future saved audit scopes | Premium backlog |
| Future scheduled audits | Premium candidate / later phase |
| Future statement approval/client workflow | Premium backlog |

**Commercial principle:** Core contains what is needed to identify and improve accessibility. Premium improves professional management of complex sites, teams, clients, and long-running programs.

# 12. Data Model and Technical Architecture

## 12.1 Recommended entities

| Entity | Key responsibilities |
|---|---|
| Audit | ID, generated/custom label, scope, WCAG target, status, start/end times, counts, configuration snapshot, owner. |
| Audit URL | Audit ID, canonical URL, content type/object ID, job status, attempts, timing, failure/skip reason. |
| Finding | Audit ID, rule ID, grouped issue key, priority, review state, WCAG mapping, description, recommendation, counts. |
| Occurrence | Finding ID, URL ID, selector/element metadata, evidence needed for report/detail. |
| Finding Status | Workflow status and status history; Addressed is user-declared, Verified is re-audit evidence. |
| Guided Decision | Guide ID/version, finding context, answers, outcome, recommendation, completion metadata. |
| Statement | Structured sections, draft/published state, page association, conformance fields, dates. |
| Statement Recommendation | Finding/group source, suggested limitation text, state, dismissal reason, audit linkage. |
| Statement Revision | Published snapshot and metadata. |
| Queue Job | Type, audit/URL references, priority, state, lock/lease data, attempts, next run, error metadata. |

## 12.2 Rule architecture

- Accessibility checks should map **rule → WCAG criterion → level → principle → explanation → recommendation → WordPress guidance → developer guidance → optional Guided Decision**.
- Store rule/version metadata with audit results so historical reports remain interpretable after rules evolve.
- Separate scanner evidence from editorial guidance so copy can improve without corrupting historical scan facts.

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
- Only allow Phase 1 selected-URL auditing of URLs belonging to the current site.
- Protect queue mutations against duplicate workers/races with leases/locks.
- Do not expose sensitive diagnostics to users without appropriate capability.
- Avoid storing sensitive form values or visitor-entered data as scan evidence.

## 13.3 Privacy

- Core audit content remains in the site's WordPress database unless a future feature explicitly requires an external service.
- Visitor toolbar preferences are stored locally in the browser.
- Do not require visitor accounts or analytics/tracking for toolbar operation.
- Verbose/debug logs must avoid sensitive content.

## 13.4 Performance

- Never execute a full site-wide audit inside one synchronous PHP request.
- Bound batch size, runtime, memory usage, retries, and concurrency.
- One active site-wide audit maximum.
- Single-page scans may coexist and should be prioritized.
- Frontend toolbar bootstrap must be small and avoid unnecessary layout shift or flash.
- Audit tables must paginate/filter server-side as data grows; do not render tens of thousands of rows at once.

# 14. Phase 1 Acceptance Criteria

- A user can run a rendered single-page scan and receive persisted findings with explanations, WCAG mapping, recommendations, WordPress guidance, and developer guidance.
- A user can start a site-wide audit, leave the page, return, pause/resume/cancel it, and recover from individual URL failures without losing completed work.
- A second site-wide audit cannot start while one is active.
- A completed audit produces a searchable/filterable grouped report and XLSX export.
- Priority definitions/disclaimer appear in reports and exports.
- Human-review findings can launch applicable Guided Decisions and save their answers/outcomes to audit history.
- The Statement builder can create/connect a statement, maintain drafts, surface audit-derived recommendations, and publish only after user action.
- Plugin-derived conformance language is clearly qualified as based on plugin findings.
- The visitor toolbar exposes all 11 agreed controls, uses correct modal focus behavior, persists explicit choices locally, and treats Default as native site styling/settings.
- Learn provides the agreed Start Here pages, ten topic areas, seven Guided Decisions, contextual links, and WCAG reference.
- Core Settings use tabs; Premium-only settings are separated as specified.
- No audit finding triggers an automated remediation change anywhere in Phase 1.

# 15. Deferred Roadmap / Explicit Non-goals

Phase 1 should resist scope expansion. The following are intentionally deferred or reserved for later evaluation.

- Automated or semi-automated remediation — Phase 2/3 candidate only after the recommendation/audit system is stable.
- Scheduled audits — later feature; likely premium-oriented.
- Saved/reusable audit scopes — premium backlog.
- Advanced statement approval/client workflow — premium backlog.
- Statement PDF export and enhanced branding/templates — premium backlog.
- Restore old statement revisions — later enhancement; Phase 1 keeps viewable published history.
- Advanced server/CLI runners — potential later performance feature for agencies/large sites.
- Arbitrary off-site URL scanning — not Phase 1.
- Claims that automated scanning establishes complete WCAG conformance — prohibited product behavior.
- Accessibility scores or percentage-conformant grades — prohibited Phase 1 behavior.

## Phase 1 source-of-truth principle

**Build the smallest reliable system that makes accessibility discovery, human decision-making, remediation guidance, verification, and communication substantially easier for WordPress teams. Add automation only after those foundations are trustworthy.**
