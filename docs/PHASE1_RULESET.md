# Elavira Phase 1 Initial Production Ruleset

Status: **Approved product direction for implementation**

This document defines the initial production accessibility rule categories and the classification boundaries Elavira must preserve. It is intentionally impact-focused rather than attempting to automate every WCAG 2.2 A/AA success criterion.

## 1. Governing principle

Elavira automates accessibility conclusions only where the rendered page provides sufficient objective evidence.

When context, interaction, perception, or human judgment is required, Elavira identifies the condition, explains the possible accessibility impact, provides testing and remediation guidance, and leaves the accessibility determination to the user.

Being able to detect invalid markup is not, by itself, sufficient to declare a WCAG failure. Elavira must be able to establish the accessibility condition required by the mapped success criterion. Otherwise the result is Potential Issue or Human Review.

## 2. Rule-result vocabulary

| Outcome | Meaning |
|---|---|
| Pass | This individual Elavira check found no issue. It does not mean the complete WCAG success criterion or page passed. |
| Automatic Failure | Elavira has sufficient objective evidence to establish the mapped accessibility failure. |
| Potential Issue | Elavira found an objectively suspicious condition but context is required. |
| Human Review | A person must make the accessibility determination; Elavira supplies evidence, a test procedure, and possible remediation. |
| Not Evaluated | Elavira cannot responsibly evaluate the condition. |

Priority is separate from result type. Human Review may be High priority.

## 3. Approved release categories

1. Links & Navigation
2. Headings & Document Structure
3. Forms & Controls
4. Keyboard Accessibility
5. Focus
6. Color & Contrast
7. Names, Roles & States
8. ARIA Usage
9. Images & Alternative Text

## 4. Proposed production rule matrix

Stable final IDs/versions must follow the existing registry conventions and be validated against current code before implementation. The semantic names below are the approved intent; do not silently change classification or WCAG claims.

### 4.1 Links & Navigation

| Semantic rule | Classification | Priority | Standards intent |
|---|---|---:|---|
| Link has no accessible name | Automatic Failure | High | WCAG 2.4.4 / 4.1.2 where applicable |
| Link purpose may be ambiguous | Potential Issue | Medium | WCAG 2.4.4 contextual review |
| Same accessible link name leads to materially different destinations | Potential Issue | Medium | WCAG 2.4.4 contextual review |

Opening a new browsing context is excluded from the initial A/AA production ruleset.

### 4.2 Headings & Document Structure

| Semantic rule | Classification | Priority | Standards intent |
|---|---|---:|---|
| Missing H1 | Structural Warning / Human Review | Medium, prominent | Strong document-structure best practice; not automatic WCAG failure |
| Multiple H1s | Structural Warning / Human Review | Medium, prominent | Strong document-structure review; not automatic WCAG failure |
| Heading hierarchy skips levels | Potential Issue | Medium | WCAG 1.3.1 contextual structure review |
| Empty semantic heading | Automatic Failure only where mapped consequence is objectively established; otherwise Potential Issue | Medium | WCAG 1.3.1 / 2.4.6 as applicable |
| Page heading structure requires contextual review | Human Review | Medium | WCAG 1.3.1 / 2.4.6 contextual review |
| Document language missing | Automatic Failure | Medium | WCAG 3.1.1 |
| Document language invalid | Automatic Failure when objectively invalid | Medium | WCAG 3.1.1 |

H1 guidance may be strong, but must not claim that WCAG or HTML requires exactly one H1.

### 4.3 Forms & Controls

| Semantic rule | Classification | Priority | Standards intent |
|---|---|---:|---|
| Form control lacks required accessible name | Automatic Failure | High | WCAG 4.1.2; 1.3.1/3.3.2 only where separately established |
| Broken explicit label relationship that causes required relationship/name loss | Automatic Failure | High | WCAG 1.3.1 and/or 4.1.2 as applicable |
| Placeholder appears to be sole visible labeling mechanism | Potential Issue | High | WCAG 3.3.2 contextual review |
| Required-field communication | Human Review | Medium | WCAG 3.3.2 |
| Error identification | Human Review | High | WCAG 3.3.1 |
| Error suggestion/help | Human Review | Medium | WCAG 3.3.3 where applicable |

Accessible-name checks and sufficient visible labels/instructions are separate concerns.

### 4.4 Keyboard Accessibility

All initial keyboard rules are human-authoritative.

| Semantic rule | Classification | Priority | Standards intent |
|---|---|---:|---|
| Positive tabindex detected | Human Review | High | Possible WCAG 2.1.1 / 2.4.3 risk |
| Non-native element appears interactive | Human Review | High | Possible WCAG 2.1.1 / 4.1.2 risk |
| Element may be unreachable by keyboard | Human Review | High | WCAG 2.1.1 review |
| Pattern may create keyboard trap | Human Review | High | WCAG 2.1.2 review |
| Custom interactive widget detected | Human Review | Medium | Keyboard behavior review |

Every finding must state what was observed, possible impact, how to test it with a keyboard, and possible remediation. DOM inspection alone must never assert complete keyboard inaccessibility or a confirmed keyboard trap.

### 4.5 Focus

All initial focus rules are human-authoritative.

| Semantic rule | Classification | Priority | Standards intent |
|---|---|---:|---|
| Focus indicator may be suppressed | Human Review | High | WCAG 2.4.7 review |
| Focus configuration suggests questionable order | Human Review | High | WCAG 2.4.3 review |
| Sticky/overlay layout may obscure focused content | Human Review | High | WCAG 2.4.11 review |
| Dialog/custom overlay requires focus-management testing | Human Review | High | WCAG 2.1.2 / 2.4.3 / 4.1.2 as applicable |

### 4.6 Color & Contrast

Contrast has a formal confidence boundary.

**Determinate:** Elavira can reliably establish effective rendered foreground/background colors and that the comparison is applicable. It may calculate the ratio and return Pass/Automatic Failure.

**Indeterminate:** gradients, images, transparency, opacity, compositing, overlays, pseudo-elements, state-dependent presentation, uncertain component boundaries, or similar factors prevent a trustworthy conclusion. Return Human Review; never guess.

| Semantic rule | Classification | Priority | Standards intent |
|---|---|---:|---|
| Determinate normal-text contrast insufficient | Automatic Failure | High | WCAG 1.4.3 |
| Determinate qualifying large-text contrast insufficient | Automatic Failure | High | WCAG 1.4.3 |
| Determinate applicable non-text contrast insufficient | Automatic Failure | High | WCAG 1.4.11 |
| Contrast cannot be reliably determined | Human Review | Medium | WCAG 1.4.3 / 1.4.11 as applicable |
| Information may rely on color alone | Human Review | High | WCAG 1.4.1 |

### 4.7 Names, Roles & States

| Semantic rule | Classification | Priority | Standards intent |
|---|---|---:|---|
| Interactive component lacks required accessible name | Automatic Failure | High | WCAG 4.1.2 |
| Required role is not programmatically determinable | Automatic Failure only when objectively established | High | WCAG 4.1.2 |
| Required state/property is not exposed | Automatic Failure only when objectively established | High | WCAG 4.1.2 |
| Exposed semantic state may disagree with actual UI | Human Review | High | WCAG 4.1.2 |
| Explicit ARIA suspiciously overrides native semantics | Potential Issue | Medium | WCAG 4.1.2 consequence review / best practice |

### 4.8 ARIA Usage

Invalid ARIA is not automatically a WCAG failure.

| Semantic rule | Classification | Priority | Standards intent |
|---|---|---:|---|
| Invalid/unknown ARIA attribute | Potential Issue | Medium | ARIA validity; WCAG consequence must be established separately |
| Invalid ARIA value | Potential Issue or Automatic Failure only when required semantics are consequently unavailable | High | WCAG 4.1.2 where consequence established |
| Prohibited role/property combination | Potential Issue unless consequence is determinable | High | ARIA validity / WCAG consequence review |
| Required ARIA state/property missing | Automatic Failure where required semantics are consequently unavailable | High | WCAG 4.1.2 |
| Broken ARIA ID relationship | Automatic Failure only when required accessible semantics are consequently lost; otherwise Potential Issue | High | WCAG 1.3.1 / 4.1.2 as applicable |
| aria-hidden region contains focusable content | Potential Issue / Human Review | High | Focus/accessibility-tree conflict review |
| Duplicate ID makes ARIA relationship ambiguous | Potential Issue | High | Relationship integrity review |
| Redundant native-equivalent role | Low-priority Potential Issue / best practice | Low | Education/best practice; do not overstate WCAG |

### 4.9 Images & Alternative Text

This category is deliberately narrow.

| Semantic rule | Classification | Priority | Standards intent |
|---|---|---:|---|
| Applicable image missing alt attribute | Automatic Failure | High | WCAG 1.1.1 |
| Alternative-text appropriateness | Human Review | Medium | WCAG 1.1.1 |
| Alt appears to be filename/path | Potential Issue | Medium | WCAG 1.1.1 contextual review |
| Complex-image treatment may require review | Human Review | Medium | WCAG 1.1.1 |

Elavira must not generate, approve, or claim correctness of alternative text automatically.

## 5. Finding workflow

Workflow statuses:

- Open
- Addressed
- Verified
- Accepted Limitation
- Needs Review
- Reviewed — Not an Issue

Verified is reserved for subsequent verification of detectable conditions and is not manually asserted by the user.

Reviewed — Not an Issue means a human reviewed the reported condition in context and determined that it is acceptable and is not an accessibility issue in the current implementation.

## 6. Reviewed exceptions

Eligible Potential Issue/Human Review findings marked Reviewed — Not an Issue may create a persistent reviewed exception.

The purpose is to prevent a site owner from repeatedly reviewing the same materially unchanged acceptable condition on every later audit.

Minimum identity requirements:

- Stable rule ID
- Rule version
- Canonical page
- Stable target fingerprint
- Material evidence fingerprint, or an equivalently robust server-derived identity

A selector alone is insufficient.

The matching identity must be server-authoritative and derived from trusted normalized evidence. A browser-provided suppression/grouping key must not be trusted.

A material change in rule version, page, target, or evidence must cause reevaluation rather than blind suppression.

Suppressed matches remain historically/auditably discoverable and are excluded from normal active-finding views by default.

## 7. Reset Reviewed Exceptions

Core must support:

- Reset for this page
- Reset all reviewed exceptions

Reset removes future suppression only. It does not delete historical audits, findings, status history, Guided Decision history, reports, or Accessibility Statement history.

This supports redesign/rebuild workflows where the owner wants all previously reviewed conditions evaluated again.

## 8. Cross-rule grouping and supersession

Forms, Names/Roles/States, and ARIA rules can observe the same underlying problem.

Elavira should present the most meaningful user-facing finding while retaining lower-level evidence when appropriate.

Example:

broken `aria-labelledby` → button has no accessible name

The missing accessible name may be the primary finding while the broken relationship is retained as diagnostic evidence rather than producing several redundant High-priority findings.

Precedence must be deterministic, server-authoritative, testable, and must not collapse genuinely distinct accessibility problems.

## 9. Implementation rounds

1. Result model + Reviewed — Not an Issue + reviewed-exception persistence/reset + grouping/supersession framework.
2. Links + Headings/Document Structure.
3. Forms + Names/Roles/States + ARIA.
4. Keyboard + Focus.
5. Color + Contrast.
6. Images + cross-rule integration + full regression.

Do not implement later-round rules during Round 1 unless a minimal fixture is necessary to prove the generic framework.
