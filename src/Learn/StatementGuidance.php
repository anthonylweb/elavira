<?php
/**
 * Practical guidance for statement authors.
 *
 * @package Elavira
 */

namespace Elavira\Learn;

/** Bundled statement guidance; never copied into saved statement fields. */
final class StatementGuidance {
	/**
	 * Build the Learn article using the existing topic structure.
	 *
	 * @return array
	 */
	public static function topic() {
		return array(
			'id'                 => 'creating_accessibility_statement',
			'version'            => '1.0.0',
			'title'              => __( 'Creating an Accessibility Statement', 'elavira' ),
			'summary'            => __( 'Help visitors understand accessibility on your site, known barriers, and how to get help. Write from evidence and keep the statement current.', 'elavira' ),
			'category'           => __( 'Statements', 'elavira' ),
			'group'              => __( 'Content and media', 'elavira' ),
			'principle'          => 'foundational',
			'wcag'               => array(),
			'sections'           => array(
				__( 'What it is and who it helps', 'elavira' ) => __( 'An accessibility statement is practical information for visitors, especially people who encounter a barrier. Name the organization and the website, services, pages, or documents covered, and explain exclusions such as a separate booking service. Its scope should be clear enough for someone to know whether their task is covered.', 'elavira' ),
				__( 'Why it matters', 'elavira' )       => __( 'A useful statement helps people decide how to complete a task and reach someone who can help. Use everyday language and concrete actions instead of broad promises or technical test results alone.', 'elavira' ),
				__( 'What good looks like: an honest commitment', 'elavira' ) => __( 'Describe the accessibility work your organization actually does and its intended WCAG version and level. A WCAG target is a goal, not demonstrated conformance. Keep the target, Plugin Findings, and Organization’s Stated Conformance separate; make a conformance claim only when a suitable evaluation supports it for the stated scope. If the evidence is incomplete, say so.', 'elavira' ),
				__( 'Known limitations and improvement plans', 'elavira' ) => __( 'For each known barrier, explain where it occurs, the task it affects, and a usable workaround or way to request help. Describe the planned improvement, who is responsible, and a realistic date if one is agreed. If a fix or date is not yet known, say that plainly and explain the next review step.', 'elavira' ),
				__( 'Feedback and contact', 'elavira' ) => __( 'Invite reports of barriers and requests for alternative access. Offer contact methods that someone monitors, such as an email address, accessible contact form, or phone service. Ask for the page and task involved without requiring sensitive information. Set a response expectation the team can meet, distinguishing an initial reply from resolving the issue.', 'elavira' ),
				__( 'Compatibility: describe actual testing', 'elavira' ) => __( 'List the browser, operating system, and assistive technology combinations actually tested, with versions and test dates where recorded. Explain any known problems and untested combinations. Do not turn a list of popular products into a claim of support, or imply that one successful combination proves all visitors can use the site.', 'elavira' ),
				__( 'Assessment approach and limits', 'elavira' ) => __( 'Record when the assessment took place, who carried it out, and whether it was an internal review or external assessment. Describe the pages, documents, and complete tasks examined, the WCAG target, methods and tools used, and what was excluded. Explain sampling and other limitations so a reader can understand how far the evidence applies.', 'elavira' ),
				__( 'Combine automated and manual testing', 'elavira' ) => __( 'Use automated checks to find selected problems, then test manually with the keyboard, zoom and reflow, and relevant assistive technologies. Check complete tasks such as submitting a form, including instructions and errors; involve disabled users where possible. Elavira plugin findings do not establish complete WCAG conformance. No findings in a scan does not mean that a page or site is fully accessible.', 'elavira' ),
				__( 'Additional information and regular reviews', 'elavira' ) => __( 'Explain alternative access arrangements, such as requesting a document in another format, including how to request them and any realistic delivery times. Name the team responsible for the statement and relevant policies or support routes. Record the actual review date, agree the next review, and review again after significant content, design, service, or testing changes.', 'elavira' ),
				__( 'Common mistakes', 'elavira' )      => __( 'Avoid copying an unverified conformance claim, listing tools you have not tested, promising a fix date without an owner, or offering an unmonitored inbox. A statement describes your current situation; publishing it does not fix barriers. Review suggested wording and examples against your own evidence before using them.', 'elavira' ),
				__( 'Short examples to adapt after checking', 'elavira' ) => __( 'These are prompts, not verified claims about your site. Replace every bracketed item with confirmed information and omit any promise you cannot meet.', 'elavira' ),
				__( 'Example: commitment and scope', 'elavira' ) => __( '“We are working toward [WCAG version and level] for [site and services]. We have not yet completed an assessment of [scope]. [Team] is responsible for reviewing accessibility.” Use this only if it accurately describes your work.', 'elavira' ),
				__( 'Example: a barrier and help', 'elavira' ) => __( '“[Document or task] currently has [specific barrier]. You can request [available alternative] through [monitored contact]. We aim to reply within [agreed time]. [Team] plans to [confirmed next step] by [realistic date].” If the plan or date is uncertain, explain that instead.', 'elavira' ),
				__( 'Example: assessment evidence', 'elavira' ) => __( '“On [date], [assessor] checked [pages and tasks] using [automated and manual methods] with [browser, operating system, and assistive technology versions]. The review did not cover [untested areas].” Include only tests that actually happened.', 'elavira' ),
			),
			'wordpress_guidance' => array( __( 'In Elavira → Statement, write and save a draft, preview it, and review every claim before choosing Publish Changes. Saving a draft does not change the published statement. Link the published page from a consistent place such as your site footer, and check that its contact routes work.', 'elavira' ) ),
			'developer_guidance' => array( __( 'Give statement authors dated evidence with tested versions, page and task scope, reproducible barriers, and evaluation limits. Verify fixes on the rendered site before describing a barrier as resolved; keep the public statement readable rather than pasting raw scanner output.', 'elavira' ) ),
			'related_rules'      => array(),
			'related_topics'     => array( 'understanding_conformance', 'understanding_audit_results', 'keyboard_accessibility' ),
			'resources'          => array(
				array(
					'label' => __( 'W3C WAI: Developing an Accessibility Statement', 'elavira' ),
					'url'   => 'https://www.w3.org/WAI/planning/statements/',
				),
			),
			'keywords'           => array( 'statement', 'commitment', 'limitations', 'feedback', 'compatibility', 'assessment', 'conformance' ),
		);
	}
}
