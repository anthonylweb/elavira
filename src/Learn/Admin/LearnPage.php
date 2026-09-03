<?php
/**
 * Learn admin screens.
 *
 * @package Elavira
 */

namespace Elavira\Learn\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.Security.NonceVerification.Recommended -- Concise view helpers and read-only filters are intentional.

use Elavira\Learn\TopicDefinition;
use Elavira\Learn\TopicRegistry;
use Elavira\Learn\WcagReference;
use Elavira\Learn\CoreTopicRegistry;
use Elavira\GuidedDecisions\DecisionRegistry;
use Elavira\Rules\RuleRegistry;

/** Accessible, server-rendered Learn library. */
final class LearnPage {
	/** Topic registry. @var TopicRegistry */
	private $topics;
	/** Other server-owned registries. @var array */
	private $registries;

	/** Create controller. @param TopicRegistry $topics Topics. @param DecisionRegistry $decisions Decisions. @param RuleRegistry $rules Rules. @param WcagReference $wcag WCAG reference. */
	public function __construct( TopicRegistry $topics, DecisionRegistry $decisions, RuleRegistry $rules, WcagReference $wcag ) {
		$this->topics     = $topics;
		$this->registries = array(
			'decisions' => $decisions,
			'rules'     => $rules,
			'wcag'      => $wcag,
		);
	}

	/** Register routes. @return void */
	public function register() {
		add_submenu_page( 'elavira-audits', __( 'Elavira Learn', 'elavira' ), __( 'Learn', 'elavira' ), 'read', 'elavira-learn', array( $this, 'landing' ) );
		add_submenu_page( null, __( 'Elavira Learn Topic', 'elavira' ), __( 'Learn Topic', 'elavira' ), 'read', 'elavira-learn-topic', array( $this, 'detail' ) );
	}

	/** Load Learn styles only on Learn routes. @return void */
	public function enqueue() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only route selection.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( in_array( $page, array( 'elavira-learn', 'elavira-learn-topic' ), true ) ) {
			wp_enqueue_style( 'elavira-learn', ELAVIRA_URL . 'assets/css/learn.css', array(), ELAVIRA_VERSION );
		}
	}

	/** Render topic discovery. @return void */
	public function landing() {
		$this->authorize();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only bounded filters.
		$search   = isset( $_GET['s'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['s'] ) ), 0, 100 ) : '';
		$category = isset( $_GET['category'] ) ? sanitize_key( wp_unslash( $_GET['category'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$categories = array();
		foreach ( $this->topics->all() as $topic ) {
			if ( 'Start Here' !== $topic->get( 'category' ) ) {
				$categories[ sanitize_key( $topic->get( 'category' ) ) ] = $topic->get( 'category' );
			}
		}
		if ( '' !== $category && ! isset( $categories[ $category ] ) ) {
			$category = '';
		}
		$items = $this->topics->search( $search, '' === $category ? '' : $categories[ $category ] );
		if ( '' === $search && '' === $category ) {
			$items = array_values(
				array_filter(
					$items,
					function ( TopicDefinition $topic ) {
						return 'Start Here' !== $topic->get( 'category' );
					}
				)
			);
		}
		?>
		<div class="wrap elavira-learn"><h1><?php echo esc_html__( 'Learn about accessibility', 'elavira' ); ?></h1>
		<p class="elavira-learn-intro"><?php echo esc_html__( 'Practical guidance for understanding accessibility and improving WordPress websites. Learn explains concepts; it does not certify conformance or change your site.', 'elavira' ); ?></p>
		<nav class="elavira-learn-nav" aria-label="<?php echo esc_attr__( 'Learn sections', 'elavira' ); ?>"><ul><li><a href="#start-here"><?php echo esc_html__( 'Start Here', 'elavira' ); ?></a></li><li><a href="#topics"><?php echo esc_html__( 'Browse Topics', 'elavira' ); ?></a></li><li><a href="#guided-decisions"><?php echo esc_html__( 'Guided Decisions', 'elavira' ); ?></a></li><li><a href="#wcag-reference"><?php echo esc_html__( 'WCAG Reference', 'elavira' ); ?></a></li></ul></nav>
		<section id="start-here" class="elavira-start-here"><h2><?php echo esc_html__( 'New to accessibility? Start here', 'elavira' ); ?></h2><p><?php echo esc_html__( 'Build a practical foundation before interpreting audit findings or making conformance statements.', 'elavira' ); ?></p><ol>
		<?php
		foreach ( array( 'understanding_wcag', 'understanding_conformance', 'understanding_audit_results' ) as $start_id ) :
			$start = $this->topics->find( $start_id );
			?>
			<li><a href="<?php echo esc_url( self::url( $start_id ) ); ?>"><?php echo esc_html( $start->get( 'title' ) ); ?></a> — <?php echo esc_html( $start->get( 'summary' ) ); ?></li><?php endforeach; ?></ol></section>
		<section id="topics"><h2><?php echo esc_html__( 'Browse Topics', 'elavira' ); ?></h2>
		<form method="get" class="elavira-learn-filters"><input type="hidden" name="page" value="elavira-learn"><label for="elavira-learn-search"><?php echo esc_html__( 'Search Learn topics', 'elavira' ); ?></label><input id="elavira-learn-search" type="search" name="s" maxlength="100" value="<?php echo esc_attr( $search ); ?>"><label for="elavira-learn-category"><?php echo esc_html__( 'Category', 'elavira' ); ?></label><select id="elavira-learn-category" name="category"><option value=""><?php echo esc_html__( 'All categories', 'elavira' ); ?></option>
		<?php
		foreach ( $categories as $key => $label ) :
			?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $category, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><button class="button" type="submit"><?php echo esc_html__( 'Search topics', 'elavira' ); ?></button></form>
		<?php
		if ( ! $items ) :
			?>
			<div class="notice notice-info inline"><p><?php echo esc_html__( 'No Learn topics match your search. Try a broader term or all categories.', 'elavira' ); ?></p></div>
			<?php
else :
	?>
			<ul class="elavira-topic-grid"><?php foreach ( $items as $topic ) : ?>
		<li><article><p class="elavira-topic-category"><?php echo esc_html( $topic->get( 'category' ) ); ?></p><h2><a href="<?php echo esc_url( self::url( $topic->get( 'id' ) ) ); ?>"><?php echo esc_html( $topic->get( 'title' ) ); ?></a></h2><p><?php echo esc_html( $topic->get( 'summary' ) ); ?></p><p><strong><?php echo esc_html__( 'Related WCAG:', 'elavira' ); ?></strong> <?php echo esc_html( $this->wcag_text( $topic ) ); ?></p></article></li>
		<?php endforeach; ?></ul><?php endif; ?></section>
		<?php $this->decision_directory(); ?>
		<?php $this->wcag_reference(); ?>
		</div>
		<?php
	}

	/** Render a topic. @return void */
	public function detail() {
		$this->authorize();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only stable identifier.
		$id = isset( $_GET['topic'] ) ? sanitize_key( wp_unslash( $_GET['topic'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$topic = $this->topics->find( $id );
		if ( ! $topic ) {
			wp_die( esc_html__( 'This Learn topic is not available.', 'elavira' ), esc_html__( 'Learn topic unavailable', 'elavira' ), array( 'response' => 404 ) );
			return;
		}
		?>
		<div class="wrap elavira-learn elavira-learn-detail"><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-learn' ) ); ?>">&larr; <?php echo esc_html__( 'All Learn topics', 'elavira' ); ?></a></p><article><p class="elavira-topic-category"><?php echo esc_html( $topic->get( 'category' ) ); ?></p><h1><?php echo esc_html( $topic->get( 'title' ) ); ?></h1><p class="elavira-learn-summary"><?php echo esc_html( $topic->get( 'summary' ) ); ?></p>
		<?php
		foreach ( $topic->get( 'sections' ) as $heading => $content ) :
			$this->section( $heading, array( $content ) );
endforeach;
		?>
		<?php $this->section( __( 'WordPress guidance', 'elavira' ), $topic->get( 'wordpress_guidance' ) ); ?>
		<?php $this->section( __( 'Developer guidance', 'elavira' ), $topic->get( 'developer_guidance' ), true ); ?>
		<?php
		if ( $topic->get( 'wcag' ) ) :
			?>
			<section><h2><?php echo esc_html__( 'Related WCAG', 'elavira' ); ?></h2><ul>
			<?php
			foreach ( $topic->get( 'wcag' ) as $mapping ) :
						$criterion = $this->registries['wcag']->find( $mapping['criterion'] );
				?>
	<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-learn&wcag_s=' . rawurlencode( $mapping['criterion'] ) . '#wcag-reference' ) ); ?>"><?php echo esc_html( sprintf( 'WCAG %s Level %s — %s', $mapping['criterion'], $mapping['level'], $criterion ? $criterion->get( 'title' ) : '' ) ); ?></a></li><?php endforeach; ?></ul><p><?php echo esc_html__( 'WCAG requirements are broader than any one automated check; recommendations and best practices are identified in the guidance above.', 'elavira' ); ?></p></section><?php endif; ?>
		<?php $this->topic_decisions( $topic ); ?>
		<?php $this->related( $topic ); ?>
		<section><h2><?php echo esc_html__( 'Authoritative resources', 'elavira' ); ?></h2><ul>
		<?php
		foreach ( $topic->get( 'resources' ) as $resource ) :
			?>
			<li><a href="<?php echo esc_url( $resource['url'] ); ?>"><?php echo esc_html( $resource['label'] ); ?></a></li><?php endforeach; ?></ul></section></article></div>
		<?php
	}

	/** Render only actually registered Guided Decisions. */
	private function decision_directory() {
		echo '<section id="guided-decisions"><h2>' . esc_html__( 'Guided Decisions', 'elavira' ) . '</h2><p>' . esc_html__( 'Guided Decisions support contextual human evaluation. Finding-compatible decisions become available from Findings; manual decisions are currently provided here as discoverable educational guidance.', 'elavira' ) . '</p><ul class="elavira-directory">';
		foreach ( $this->registries['decisions']->all() as $decision ) {
			$context = $this->decision_context( $decision );
			echo '<li><article><h3>' . esc_html( $decision->get( 'title' ) ) . '</h3><p>' . esc_html( $decision->get( 'purpose' ) ) . '</p>';
			foreach ( $context['topics'] as $topic ) {
				/* translators: %s: Learn topic title. */
				echo '<p><a href="' . esc_url( self::url( $topic->get( 'id' ) ) ) . '">' . esc_html( sprintf( __( 'Learn about %s', 'elavira' ), $topic->get( 'title' ) ) ) . '</a></p>'; }
			echo '<p><strong>' . esc_html__( 'Related WCAG:', 'elavira' ) . '</strong> ' . esc_html( $context['wcag'] ) . '</p>';
			echo $decision->get( 'rules' ) ? '<p>' . esc_html__( 'Open this decision from an applicable Finding so Elavira can preserve the correct rule and audit context.', 'elavira' ) . '</p>' : '<p>' . esc_html__( 'This manual review has no current scanner Finding relationship. Use the related Learn guidance when performing the review; Elavira does not fabricate an audit result.', 'elavira' ) . '</p>';
			echo '</article></li>';
		}
		echo '</ul></section>';
	}

	/** Resolve directory metadata through actual rule/topic registries. @return array */
	private function decision_context( $decision ) {
		$topics = array();
		$wcag   = array();
		foreach ( $decision->get( 'learn_topics' ) as $topic_id ) {
			$topic = $this->topics->find( $topic_id );
			if ( $topic ) {
				$topics[ $topic_id ] = $topic;
				foreach ( $topic->get( 'wcag' ) as $mapping ) {
					$wcag[] = sprintf( 'WCAG %s Level %s', $mapping['criterion'], $mapping['level'] ); }
			}
		}
		foreach ( $decision->get( 'rules' ) as $supported ) {
			try {
				$rule      = $this->registries['rules']->get( $supported['id'], $supported['version'] );
				$editorial = $rule->editorial();
				$topic     = $this->topics->find( $editorial['learn_topic'] ?? '' );
				if ( $topic ) {
					$topics[ $topic->get( 'id' ) ] = $topic; }
				foreach ( $rule->wcag() as $mapping ) {
					$wcag[] = sprintf( 'WCAG %s Level %s', $mapping->criterion(), $mapping->level() ); }
			} catch ( \InvalidArgumentException $error ) {
				continue; }
		}
		return array(
			'topics' => array_values( $topics ),
			'wcag'   => implode( ', ', array_unique( $wcag ) ),
		);
	}

	/** Render decisions related to one topic without dead links. */
	private function topic_decisions( TopicDefinition $topic ) {
		$items = array();
		foreach ( $this->registries['decisions']->all() as $decision ) {
			$context = $this->decision_context( $decision );
			foreach ( $context['topics'] as $related_topic ) {
				if ( $topic->get( 'id' ) === $related_topic->get( 'id' ) ) {
					$items[] = $decision;
					break; }
			}
		}
		if ( ! $items ) {
			return; }
		echo '<section><h2>' . esc_html__( 'Guided Decisions', 'elavira' ) . '</h2><ul>';
		foreach ( $items as $decision ) {
			$availability = $decision->get( 'rules' ) ? __( 'Available from a compatible Finding.', 'elavira' ) : __( 'Manual review guidance; no current scanner Finding relationship.', 'elavira' );
			echo '<li><strong>' . esc_html( $decision->get( 'title' ) ) . '</strong> — ' . esc_html( $availability ) . '</li>'; }
		echo '</ul></section>';
	}

	/** Render filtered lightweight WCAG orientation grouped by principle. */
	private function wcag_reference() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only bounded reference filters.
		$term      = isset( $_GET['wcag_s'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['wcag_s'] ) ), 0, 100 ) : '';
		$principle = isset( $_GET['wcag_principle'] ) ? sanitize_key( wp_unslash( $_GET['wcag_principle'] ) ) : '';
		$level     = isset( $_GET['wcag_level'] ) ? sanitize_text_field( wp_unslash( $_GET['wcag_level'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $principle, array( '', 'perceivable', 'operable', 'understandable', 'robust' ), true ) ) {
			$principle = ''; }
		if ( ! in_array( $level, array( '', 'A', 'AA' ), true ) ) {
			$level = ''; }
		$items = $this->registries['wcag']->search( $term, $principle, $level );
		?>
		<section id="wcag-reference"><h2><?php echo esc_html__( 'Lightweight WCAG 2.2 Reference', 'elavira' ); ?></h2><p><?php echo esc_html__( 'This reference covers A and AA criteria relevant to Elavira’s current Phase 1 education and rules. Omitted criteria remain important. This plain-language orientation is not normative and does not replace WCAG.', 'elavira' ); ?> <a href="<?php echo esc_url( CoreTopicRegistry::wcag_url() ); ?>"><?php echo esc_html__( 'Read the official WCAG 2.2 standard from W3C', 'elavira' ); ?></a>.</p>
		<form method="get" class="elavira-learn-filters"><input type="hidden" name="page" value="elavira-learn"><label for="elavira-wcag-search"><?php echo esc_html__( 'Search WCAG reference', 'elavira' ); ?></label><input id="elavira-wcag-search" type="search" maxlength="100" name="wcag_s" value="<?php echo esc_attr( $term ); ?>"><label for="elavira-wcag-principle"><?php echo esc_html__( 'WCAG principle', 'elavira' ); ?></label><select id="elavira-wcag-principle" name="wcag_principle"><option value=""><?php echo esc_html__( 'All principles', 'elavira' ); ?></option>
		<?php
		foreach ( array( 'perceivable', 'operable', 'understandable', 'robust' ) as $value ) :
			?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $principle, $value ); ?>><?php echo esc_html( ucfirst( $value ) ); ?></option><?php endforeach; ?></select><label for="elavira-wcag-level"><?php echo esc_html__( 'WCAG level', 'elavira' ); ?></label><select id="elavira-wcag-level" name="wcag_level"><option value=""><?php echo esc_html__( 'Levels A and AA', 'elavira' ); ?></option><option value="A" <?php selected( $level, 'A' ); ?>><?php echo esc_html__( 'Level A', 'elavira' ); ?></option><option value="AA" <?php selected( $level, 'AA' ); ?>><?php echo esc_html__( 'Level AA', 'elavira' ); ?></option></select><button class="button" type="submit"><?php echo esc_html__( 'Filter WCAG reference', 'elavira' ); ?></button></form>
		<?php
		if ( ! $items ) :
			?>
			<p><?php echo esc_html__( 'No reference entries match these filters.', 'elavira' ); ?></p>
			<?php
else :
	foreach ( array( 'perceivable', 'operable', 'understandable', 'robust' ) as $group ) :
		$group_items = array_filter(
			$items,
			function ( $item ) use ( $group ) {
					return $group === $item->get( 'principle' );
			}
		);
		if ( ! $group_items ) {
					continue; }
		?>
		<section><h3><?php echo esc_html( ucfirst( $group ) ); ?></h3><ul class="elavira-wcag-list">
		<?php
		foreach ( $group_items as $item ) :
			?>
		<li><article><h4><?php echo esc_html( sprintf( '%s %s — %s', $item->get( 'criterion' ), $item->get( 'level' ), $item->get( 'title' ) ) ); ?></h4><p><?php echo esc_html( $item->get( 'summary' ) ); ?></p><?php $this->criterion_topics( $item ); ?>
			<?php
			if ( $item->get( 'rules' ) ) :
				?>
	<p><strong><?php echo esc_html__( 'Related Elavira rules:', 'elavira' ); ?></strong> <code><?php echo esc_html( implode( ', ', $item->get( 'rules' ) ) ); ?></code></p><?php endif; ?></article></li><?php endforeach; ?></ul></section>
		<?php
endforeach;
endif;
?>
</section>
		<?php
	}

	/** Render criterion-to-topic navigation. */
	private function criterion_topics( $criterion ) {
		echo '<p><strong>' . esc_html__( 'Related Learn topics:', 'elavira' ) . '</strong> ';
		$links = array();
		foreach ( $criterion->get( 'topics' ) as $id ) {
			$topic = $this->topics->find( $id );
			if ( $topic ) {
				$links[] = '<a href="' . esc_url( self::url( $id ) ) . '">' . esc_html( $topic->get( 'title' ) ) . '</a>'; }
		}
		echo implode( ', ', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every link component is escaped above.
	}

	/** Render nonempty paragraphs; code-looking fragments remain escaped text. */
	private function section( $heading, array $paragraphs, $technical = false ) {
		if ( ! $paragraphs ) {
			return; }
		echo '<section><h2>' . esc_html( $heading ) . '</h2>';
		foreach ( $paragraphs as $paragraph ) {
			echo $technical ? '<pre><code>' . esc_html( $paragraph ) . '</code></pre>' : '<p>' . esc_html( $paragraph ) . '</p>';
		}
		echo '</section>';
	}

	/** Render genuine related-topic links. */
	private function related( TopicDefinition $topic ) {
		$related = array_filter( array_map( array( $this->topics, 'find' ), $topic->get( 'related_topics' ) ) );
		if ( ! $related ) {
			return; }
		echo '<nav aria-labelledby="elavira-related-topics"><h2 id="elavira-related-topics">' . esc_html__( 'Related Learn topics', 'elavira' ) . '</h2><ul>';
		foreach ( $related as $item ) {
			echo '<li><a href="' . esc_url( self::url( $item->get( 'id' ) ) ) . '">' . esc_html( $item->get( 'title' ) ) . '</a></li>'; }
		echo '</ul></nav>';
	}

	/** Format WCAG mappings. @return string */
	private function wcag_text( TopicDefinition $topic ) {
		return implode(
			', ',
			array_map(
				function ( $item ) {
					return sprintf( 'WCAG %s Level %s', $item['criterion'], $item['level'] );
				},
				$topic->get( 'wcag' )
			)
		);
	}

	/** Require normal authenticated WordPress access. */
	private function authorize() {
		if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'You are not allowed to view Learn.', 'elavira' ), '', array( 'response' => 403 ) ); }
	}

	/** Stable topic URL. @param string $id Topic ID. @return string */
	public static function url( $id ) {
		return admin_url( 'admin.php?page=elavira-learn-topic&topic=' . sanitize_key( $id ) );
	}
}
