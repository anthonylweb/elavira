<?php
/**
 * Public Statement integration.
 *
 * @package Elavira
 */

namespace Elavira\Statement;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment -- Concise integration comments are intentional.

/** Replaces only the associated published WordPress Page content. */
final class FrontendStatement {
	/** Repository. @var StatementRepository */
	private $repository;
	/** Renderer. @var StatementRenderer */
	private $renderer;

	/** Create integration. @param StatementRepository $repository Repository. @param StatementRenderer $renderer Renderer. */
	public function __construct( StatementRepository $repository, StatementRenderer $renderer ) {
		$this->repository = $repository;
		$this->renderer   = $renderer;
	}

	/** Render only inside the main singular loop. @param string $content Existing content. @return string */
	public function filter_content( $content ) {
		if ( \Elavira\Core\Lifecycle\UpgradeManager::has_failed() || is_admin() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$statement = $this->repository->find_published_by_post( get_the_ID() );
		if ( ! $statement ) {
			return $content;
		}
		$statement['updated_at'] = $statement['published_at'];
		return $this->renderer->render( StatementRepository::published_fields( $statement ), $statement );
	}
}
