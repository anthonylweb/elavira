<?php
/**
 * Core Learn tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Learn\Admin\LearnPage;
use Elavira\Learn\CoreTopicRegistry;
use Elavira\Learn\TopicDefinition;
use Elavira\Learn\TopicRegistry;
use Elavira\Learn\WcagReference;
use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\Rules\CoreRuleRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LearnTest extends TestCase {
	public function test_initial_registry_covers_every_current_rule_topic() {
		$topics = CoreTopicRegistry::create();
		$this->assertCount( 14, $topics->all() );
		foreach ( CoreRuleRegistry::create()->all() as $rule ) {
			$this->assertNotNull( $topics->find( $rule->editorial()['learn_topic'] ) );
		}
	}

	public function test_duplicate_topic_ids_are_rejected() {
		$registry = new TopicRegistry();
		$topic    = CoreTopicRegistry::create()->all()[0];
		$registry->register( $topic );
		$this->expectException( InvalidArgumentException::class );
		$registry->register( $topic );
	}

	public function test_invalid_and_missing_topics_degrade_safely() {
		$this->assertNull( CoreTopicRegistry::create()->find( 'missing_topic' ) );
		$this->expectException( InvalidArgumentException::class );
		new TopicDefinition( array( 'id' => '<script>' ) );
	}

	public function test_search_is_bounded_and_filters_trusted_metadata() {
		$registry = CoreTopicRegistry::create();
		$this->assertSame( 'forms_labels', $registry->search( 'accessible names' )[0]->get( 'id' ) );
		$this->assertCount( 1, $registry->search( '', 'Forms' ) );
		$this->assertCount( 0, $registry->search( '<script>alert(1)</script>' ) );
		$this->assertSame( $registry->search( str_repeat( 'a', 100 ) ), $registry->search( str_repeat( 'a', 200 ) ) );
	}

	public function test_related_topics_and_wcag_metadata_are_structured() {
		$topic = CoreTopicRegistry::create()->find( 'headings_structure' );
		$this->assertContains( 'links_navigation', $topic->get( 'related_topics' ) );
		$this->assertSame( '1.3.1', $topic->get( 'wcag' )[0]['criterion'] );
		$this->assertSame( 'AA', $topic->get( 'wcag' )[1]['level'] );
	}

	public function test_detail_rendering_escapes_code_and_exposes_metadata() {
		$GLOBALS['elavira_test_capabilities']['read'] = true;
		$_GET['topic']                                = 'forms_labels';
		ob_start();
		$this->page()->detail();
		$html = ob_get_clean();
		unset( $_GET['topic'] );
		$this->assertStringContainsString( '<h1>Forms, labels, and accessible names</h1>', $html );
		$this->assertStringContainsString( 'WCAG 4.1.2 Level A', $html );
		$this->assertStringContainsString( '&lt;label', $html );
		$this->assertStringNotContainsString( '<label', $html );
	}

	public function test_routes_use_read_capability_and_finding_link_is_historical() {
		$page    = file_get_contents( dirname( __DIR__, 2 ) . '/src/Learn/Modules/LearnModule.php' );
		$reports = file_get_contents( dirname( __DIR__, 2 ) . '/src/Reports/Admin/ReportsPage.php' );
		$this->assertStringContainsString( "'read', 'elavira-learn'", $page );
		$this->assertStringContainsString( "\$resolved['available']", $reports );
		$this->assertStringContainsString( "['learn_topic']", $reports );
		$this->assertStringContainsString( "LearnPage::url( 'understanding_audit_results' )", $reports );
	}

	public function test_required_phase_one_topics_and_start_here_are_available() {
		$topics = CoreTopicRegistry::create();
		$ids    = array( 'understanding_wcag', 'understanding_conformance', 'understanding_audit_results', 'images_alternative_text', 'headings_structure', 'links_navigation', 'forms_labels', 'keyboard_accessibility', 'focus_focus_order', 'color_contrast', 'aria_accessible_controls', 'audio_video', 'language_content', 'motion_animation' );
		foreach ( $ids as $id ) {
			$this->assertNotNull( $topics->find( $id ), $id );
		}
		$_GET['topic'] = 'understanding_audit_results';
		ob_start();
		$this->page()->detail();
		$html = ob_get_clean();
		unset( $_GET['topic'] );
		$this->assertStringContainsString( 'Detecting and understanding an issue do not remediate it', $html );
		$this->assertStringNotContainsString( '<h2>Related WCAG</h2>', $html );
	}

	public function test_landing_exposes_all_four_phase_one_paths_and_actual_decisions() {
		$_GET = array();
		ob_start();
		$this->page()->landing();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'New to accessibility? Start here', $html );
		$this->assertStringContainsString( 'Browse Topics', $html );
		$this->assertStringContainsString( 'Guided Decisions', $html );
		$this->assertStringContainsString( 'Lightweight WCAG 2.2 Reference', $html );
		foreach ( CoreDecisionRegistry::create()->all() as $decision ) {
			$this->assertStringContainsString( $decision->get( 'title' ), $html );
		}
		$this->assertStringNotContainsString( 'Alternative Text: decorative', $html );
	}

	public function test_wcag_reference_organizes_criteria_and_cross_links_rules_and_topics() {
		$topics    = CoreTopicRegistry::create();
		$reference = new WcagReference( $topics, CoreRuleRegistry::create() );
		$item      = $reference->find( '2.4.4' );
		$this->assertSame( 'operable', $item->get( 'principle' ) );
		$this->assertSame( 'A', $item->get( 'level' ) );
		$this->assertContains( 'links_navigation', $item->get( 'topics' ) );
		$this->assertContains( 'elavira.link.purpose_review', $item->get( 'rules' ) );
		$this->assertSame( 'understandable', $reference->find( '3.3.2' )->get( 'principle' ) );
		$this->assertNull( $reference->find( '../2.4.4' ) );
		$this->assertCount( 1, $reference->search( 'Link Purpose', 'operable', 'A' ) );
	}

	public function test_decision_to_learn_relationship_uses_registered_rule_metadata() {
		$rules  = CoreRuleRegistry::create();
		$topics = CoreTopicRegistry::create();
		foreach ( CoreDecisionRegistry::create()->all() as $decision ) {
			foreach ( $decision->get( 'learn_topics' ) as $topic_id ) {
				$this->assertNotNull( $topics->find( $topic_id ) );
			}
			foreach ( $decision->get( 'rules' ) as $supported ) {
				$topic_id = $rules->get( $supported['id'], $supported['version'] )->editorial()['learn_topic'];
				$this->assertContains( $topic_id, $decision->get( 'learn_topics' ) );
			}
		}
	}

	public function test_every_topic_has_meaningful_intro_and_renders() {
		foreach ( CoreTopicRegistry::create()->all() as $topic ) {
			$this->assertGreaterThan( 8, strlen( $topic->get( 'title' ) ), $topic->get( 'id' ) );
			$this->assertGreaterThan( 40, strlen( $topic->get( 'summary' ) ), $topic->get( 'id' ) );
			$html = $this->render_topic( $topic->get( 'id' ) );
			$this->assertStringContainsString( '<h1>' . esc_html( $topic->get( 'title' ) ) . '</h1>', $html );
		}
	}

	public function test_expanded_education_and_escaped_examples_render() {
		$aria = $this->render_topic( 'aria_accessible_controls' );
		$this->assertStringContainsString( 'Why native HTML comes first', $aria );
		$this->assertStringContainsString( 'does not create focus, keyboard interaction', $aria );
		$this->assertStringContainsString( 'ID references must resolve to the intended unique elements', $aria );
		$this->assertStringContainsString( 'aria-hidden regions containing controls', $aria );
		$this->assertStringContainsString( '&lt;button type=&quot;button&quot;&gt;Save draft&lt;/button&gt;', $aria );
		$this->assertStringNotContainsString( '<button type="button">Save draft</button>', $aria );

		$focus = $this->render_topic( 'focus_focus_order' );
		$this->assertStringContainsString( 'cookie banners, chat windows', $focus );
		$this->assertStringContainsString( 'Test at zoom and narrow widths', $focus );

		$contrast = $this->render_topic( 'color_contrast' );
		$this->assertStringContainsString( 'Normal text reaches 4.5:1', $contrast );
		$this->assertStringContainsString( 'gradients, images, opacity, overlays', $contrast );

		$images = $this->render_topic( 'images_alternative_text' );
		$this->assertStringContainsString( 'The alternative names the link destination or action', $images );
		$this->assertStringContainsString( 'empty alternative prevents a redundant announcement', $images );
	}

	public function test_current_semantics_rules_use_dedicated_topic_and_historical_copy_stays_exact() {
		$rules = CoreRuleRegistry::create();
		$this->assertSame( 'aria_accessible_controls', $rules->get( 'elavira.aria.broken_id_reference', '1.0.0' )->editorial()['learn_topic'] );
		$this->assertSame( 'keyboard_accessibility', $rules->get( 'elavira.aria.hidden_focusable_content', '1.0.0' )->editorial()['learn_topic'] );
		$this->assertSame( 'links_navigation', $rules->get( 'elavira.link.purpose_review', '1.0.0' )->editorial()['learn_topic'] );
		$this->assertSame( 'links_navigation', $rules->get( 'elavira.link.purpose_review', '2.0.0' )->editorial()['learn_topic'] );
	}

	public function test_internal_and_external_link_behavior_is_intentional() {
		$html  = $this->render_topic( 'aria_accessible_controls' );
		$html .= $this->render_landing();
		$dom   = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( $html );
		libxml_clear_errors();
		foreach ( $dom->getElementsByTagName( 'a' ) as $link ) {
			$text = strtolower( trim( $link->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
			$this->assertNotContains( $text, array( 'click here', 'learn more' ) );
			if ( false !== strpos( $link->getAttribute( 'href' ), 'admin.php?page=elavira-learn' ) ) {
				$this->assertNotSame( '_blank', $link->getAttribute( 'target' ) );
			} elseif ( 0 === strpos( $link->getAttribute( 'href' ), 'https://www.w3.org/' ) ) {
				$this->assertSame( '_blank', $link->getAttribute( 'target' ) );
				$this->assertSame( 'noopener noreferrer', $link->getAttribute( 'rel' ) );
				$this->assertStringContainsString( 'opens in a new tab', $text );
			}
		}
	}

	public function test_directory_groups_topics_and_css_supports_narrow_reflow() {
		$html = $this->render_landing();
		foreach ( array( 'Content and media', 'Structure and navigation', 'Forms and interaction', 'Visual accessibility', 'Developer semantics' ) as $group ) {
			$this->assertStringContainsString( '<h3>' . $group . '</h3>', $html );
		}
		$css = file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/learn.css' );
		$this->assertStringContainsString( 'overflow-wrap: anywhere', $css );
		$this->assertStringContainsString( 'max-width: 100%', $css );
		$this->assertStringContainsString( 'overflow-x: auto', $css );
	}

	/** Render one registered topic.
	 *
	 * @param string $id Topic ID.
	 * @return string
	 */
	private function render_topic( $id ) {
		$_GET['topic'] = $id;
		ob_start();
		$this->page()->detail();
		$html = ob_get_clean();
		unset( $_GET['topic'] );
		return $html;
	}

	/** Render the Learn directory. @return string */
	private function render_landing() {
		$_GET = array();
		ob_start();
		$this->page()->landing();
		return ob_get_clean();
	}

	/** Build the complete registry-backed view controller. @return LearnPage */
	private function page() {
		$GLOBALS['elavira_test_capabilities']['read'] = true;
		$topics                                       = CoreTopicRegistry::create();
		$rules                                        = CoreRuleRegistry::create();
		return new LearnPage( $topics, CoreDecisionRegistry::create(), $rules, new WcagReference( $topics, $rules ) );
	}
}
