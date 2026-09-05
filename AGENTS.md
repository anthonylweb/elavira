# Elavira Development Instructions

## Authoritative project sources

Before making code changes, read:

1. `/docs/TECH_SPEC.md`
2. `/docs/ENGINEERING_GUIDELINES.md`
3. `/docs/PHASE1_RULESET.md`
4. `/docs/ARCHITECTURE.md` when the task changes data flow, persistence, scanner boundaries, or Core/Premium extension points

Then inspect the existing implementation relevant to the requested task.

These documents are authoritative:

- `TECH_SPEC.md` defines **what Elavira Phase 1 should do**.
- `ENGINEERING_GUIDELINES.md` defines **how Elavira should be engineered**.
- `PHASE1_RULESET.md` defines the **approved initial production accessibility ruleset, outcome semantics, and review/suppression behavior**.
- `ARCHITECTURE.md` records the **current implementation architecture and extension boundaries**.

## Working rules

Do not:

- Invent product behavior not defined by the specification or the current task.
- Silently change established terminology, workflows, data semantics, or UX behavior.
- Change Core/Premium boundaries without explicit instruction.
- Weaken WCAG 2.2 Level AA requirements.
- Introduce automated or semi-automated remediation in Phase 1.
- Add accessibility scores, percentage-conformance grades, or claims that automated testing establishes complete WCAG conformance.
- Rewrite unrelated architecture merely because another implementation appears cleaner.
- Introduce major dependencies without a clear requirement and justification.
- Modify WordPress core.
- Assume a particular theme, page builder, hosting provider, permalink structure, or optional plugin unless the feature specifically requires it.

If a requested task conflicts with either authoritative source, identify the conflict before making the conflicting change.

## Existing code first

Before implementing a task:

1. Inspect the relevant existing code, tests, schema, hooks, APIs, and UI.
2. Determine what already satisfies the specification.
3. Preserve working behavior unless the task explicitly requires a change.
4. Prefer focused changes over broad rewrites.
5. Account for migrations and backward compatibility when stored data or schemas change.
6. Add or update tests where appropriate.

## Accessibility baseline

All Elavira admin and frontend interfaces must target **WCAG 2.2 Level AA**.

Elavira should demonstrate the accessibility practices it teaches. Do not introduce inaccessible UI in order to provide accessibility functionality.

Prefer semantic HTML and native controls. Use ARIA only when necessary and only when implemented behavior matches the declared semantics.

Keyboard operation, visible focus, logical focus order, accessible names, sufficient contrast, zoom/reflow, and appropriate focus management are required considerations for interactive work.

## Phase 1 accessibility model

Preserve the Phase 1 workflow:

**Detect → Explain → Recommend → User acts → Re-audit → Verify**

Elavira does not automatically modify content, markup, CSS, accessibility attributes, themes, plugins, or configuration in response to findings.

Human judgment remains authoritative. Guided Decisions support judgment; they do not replace it.

Scanner outcomes are not interchangeable with WCAG conformance. Preserve these rule-result meanings:

- **Pass** — this individual Elavira check found no issue; it does not establish that the whole WCAG success criterion or page passed.
- **Automatic Failure** — Elavira has sufficient objective evidence for the mapped accessibility failure.
- **Potential Issue** — Elavira found an objectively suspicious condition but context is required.
- **Human Review** — a person must make the accessibility determination; Elavira provides evidence, testing guidance, and possible remediation.
- **Not Evaluated** — Elavira cannot responsibly evaluate the condition.

Priority is separate from result type. A Human Review may be High priority.

Automated testing is incomplete by definition.

## Conformance language

Do not imply that plugin findings establish complete WCAG conformance.

When presenting plugin-derived conformance status, use:

**Conformance Status — Based on Plugin Findings**

Keep these concepts distinct:

- WCAG Target
- Plugin Findings
- Organization's Stated Conformance

## Core and Premium

Core must remain a genuinely useful accessibility product.

Core provides rendered single-page auditing in the user's browser. Premium extends Elavira with site-wide auditing, remote browser-worker execution, and professional management features for complex sites, teams, clients, diagnostics, permissions, retention, and advanced configuration.

Implement feature gating cleanly. Core must remain stable if Premium is missing, inactive, outdated, or unavailable. Do not introduce a remote browser service, persistent site-wide queue, or site-wide audit workflow into Core unless the specification is explicitly revised again.

## WordPress engineering

Follow current WordPress best practices and the detailed requirements in `ENGINEERING_GUIDELINES.md`, including:

- Capability-based authorization
- Nonces for appropriate state-changing actions
- Input validation and sanitization
- Context-appropriate output escaping
- Safe database queries
- Internationalization
- Scoped frontend assets
- Bounded processing and resource use appropriate to the feature
- Privacy-conscious logging
- Graceful failure handling

## Completion

A task is not complete merely because it renders or passes a happy-path test.

Before finishing, review the change against:

- The requested task
- `TECH_SPEC.md`
- `ENGINEERING_GUIDELINES.md`
- Existing behavior and regression risk
- Security and authorization
- WCAG 2.2 AA requirements
- Data integrity and migrations
- Core/Premium boundaries
- Automated and manual testing needs

Report material assumptions, unresolved conflicts, known limitations, and any follow-up work rather than hiding them.
## Initial production ruleset boundary

The approved initial production ruleset is limited to:

1. Links & Navigation
2. Headings & Document Structure
3. Forms & Controls
4. Keyboard Accessibility
5. Focus
6. Color & Contrast
7. Names, Roles & States
8. ARIA Usage
9. Images & Alternative Text

Do not add unrelated scanner categories merely to increase rule count.

Special requirements:

- Keyboard and focus findings are human-authoritative. The scanner may identify risk patterns, explain likely impact, provide a test procedure, and suggest remediation, but DOM inspection alone must not declare keyboard/focus accessibility.
- Contrast may be an Automatic Failure only when effective rendered colors and applicability can be determined reliably. Indeterminate contrast becomes Human Review; never guess.
- Invalid ARIA is not automatically a WCAG failure. Declare Automatic Failure only when the resulting accessibility condition required by the mapped success criterion can be established.
- Missing or multiple H1 elements receive a strong **Structural Warning / Human Review** treatment. They are not automatic WCAG failures. Explain that Elavira strongly recommends a clear primary document heading as a document-structure best practice without claiming that WCAG or HTML requires exactly one H1.
- Avoid duplicate user-facing findings when one underlying accessibility problem is observed by overlapping Forms, Names/Roles/States, or ARIA rules. Preserve evidence while applying explicit grouping/supersession behavior.
- Images/alternative text are intentionally narrow in this release. Do not generate or approve alternative text automatically.

## Finding review decisions

Preserve historical findings and status history. Add and support **Reviewed — Not an Issue** as a human decision meaning that the reported condition was reviewed in context and determined not to be an accessibility issue in the current implementation.

This is distinct from:

- **Addressed** — remediation work was performed; not proof of resolution.
- **Accepted Limitation** — a known accessibility issue is intentionally retained.
- **Verified** — reserved for subsequent verification; users do not manually assert it.
- **Needs Review** — human evaluation remains outstanding.

A Reviewed — Not an Issue decision may create a persistent reviewed exception so the same materially unchanged condition does not keep appearing as an active issue in later audits. Exceptions must be auditable, reversible, version-aware, and based on more than a fragile CSS selector.

Resetting reviewed exceptions clears future suppression only. It must not delete historical audits, findings, decisions, or status history.
