# Elavira Development Instructions

## Authoritative project sources

Before making code changes, read:

1. `/docs/TECH_SPEC.md`
2. `/docs/ENGINEERING_GUIDELINES.md`

Then inspect the existing implementation relevant to the requested task.

These documents are authoritative:

- `TECH_SPEC.md` defines **what Elavira Phase 1 should do**.
- `ENGINEERING_GUIDELINES.md` defines **how Elavira should be engineered**.

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
