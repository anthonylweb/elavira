<?php
/**
 * Contextual Core audit entry points.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment -- Compact hook adapters are documented at the class boundary.

use Elavira\Core\Capabilities\Capability;

/** Adds credential-free links to the canonical launcher for public content. */
final class ContextualAuditLinks {
	/** Add a frontend admin-bar action. @param object $admin_bar WordPress admin bar. @return void */
	public function admin_bar( $admin_bar ) {
		if ( is_admin() || ! current_user_can( Capability::RUN_AUDITS ) || ! is_singular() ) {
			return;
		}
		$url = $this->public_permalink( get_queried_object_id() );
		if ( $url ) {
			$admin_bar->add_node(
				array(
					'id'    => 'elavira-run-audit',
					'title' => __( 'Run Elavira Audit', 'elavira' ),
					'href'  => $this->launcher_url( $url ),
				)
			);
		}
	}

	/** Add an editor action for the currently edited public item. @param object $post Post. @return void */
	public function editor( $post ) {
		if ( ! current_user_can( Capability::RUN_AUDITS ) ) {
			return;
		}
		$url = $this->public_permalink( isset( $post->ID ) ? $post->ID : 0 );
		if ( $url ) {
			echo '<div class="misc-pub-section"><a href="' . esc_url( $this->launcher_url( $url ) ) . '">' . esc_html__( 'Run Elavira Audit on public page', 'elavira' ) . '</a></div>';
		} else {
			echo '<div class="misc-pub-section"><span>' . esc_html__( 'Publish this content before running an Elavira audit.', 'elavira' ) . '</span></div>';
		}
	}

	/** Resolve only published, publicly viewable content. @param int $post_id Post ID. @return string */
	private function public_permalink( $post_id ) {
		$post_type = $post_id ? get_post_type_object( get_post_type( $post_id ) ) : null;
		if ( ! $post_type || ! is_post_type_viewable( $post_type ) || 'publish' !== get_post_status( $post_id ) ) {
			return '';
		}
		return (string) get_permalink( $post_id );
	}

	/** Create a credential-free launcher URL. @param string $url Public URL. @return string */
	private function launcher_url( $url ) {
		return add_query_arg( 'url', $url, admin_url( 'admin.php?page=elavira-run-audit' ) );
	}
}
