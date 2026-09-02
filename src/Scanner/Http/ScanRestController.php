<?php
/**
 * Core scan REST transport.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Http;

use Elavira\Core\Capabilities\Capability;
use Elavira\Scanner\Application\CompleteScan;
use Elavira\Scanner\Application\StartSinglePageScan;
use Elavira\Scanner\Application\SubmitScanBatch;
use Elavira\Scanner\ScanLimits;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** Thin HTTP adapter; application services own lifecycle and persistence. */
final class ScanRestController {
	/** Initiation service.
	 *
	 * @var StartSinglePageScan
	 */
	private $start;
	/** Batch service.
	 *
	 * @var SubmitScanBatch
	 */
	private $submit;
	/** Completion service.
	 *
	 * @var CompleteScan
	 */
	private $complete;

	/**
	 * Construct controller.
	 *
	 * @param StartSinglePageScan $start    Initiation service.
	 * @param SubmitScanBatch     $submit   Batch service.
	 * @param CompleteScan        $complete Completion service.
	 */
	public function __construct( StartSinglePageScan $start, SubmitScanBatch $submit, CompleteScan $complete ) {
		$this->start    = $start;
		$this->submit   = $submit;
		$this->complete = $complete;
	}

	/** Register authenticated state-changing routes. @return void */
	public function register_routes() {
		$common = array(
			'methods'             => 'POST',
			'permission_callback' => array( $this, 'permission' ),
		);
		register_rest_route( 'elavira/v1', '/scans', array_merge( $common, array( 'callback' => array( $this, 'start' ) ) ) );
		register_rest_route( 'elavira/v1', '/scans/batches', array_merge( $common, array( 'callback' => array( $this, 'submit' ) ) ) );
		register_rest_route( 'elavira/v1', '/scans/complete', array_merge( $common, array( 'callback' => array( $this, 'complete' ) ) ) );
		register_rest_route( 'elavira/v1', '/scans/fail', array_merge( $common, array( 'callback' => array( $this, 'fail' ) ) ) );
	}

	/**
	 * Enforce authentication, capability, and REST CSRF nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function permission( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_user_logged_in() || ! current_user_can( Capability::RUN_AUDITS ) || ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'elavira_forbidden', __( 'You are not authorized to run this audit.', 'elavira' ), array( 'status' => 403 ) );
		}
		if ( false === stripos( (string) $request->get_header( 'content-type' ), 'application/json' ) || strlen( $request->get_body() ) > ScanLimits::MAX_REQUEST_BYTES ) {
			return new WP_Error( 'elavira_invalid_request', __( 'The scan request format or size is not supported.', 'elavira' ), array( 'status' => 413 ) );
		}
		return true;
	}

	/**
	 * Start route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function start( WP_REST_Request $request ) {
		return $this->respond(
			function () use ( $request ) {
				$target = $request->get_param( 'wcag_target' );
				return $this->start->start( $request->get_param( 'url' ), $target ? $target : '2.2_AA' );
			}
		);
	}

	/**
	 * Batch route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit( WP_REST_Request $request ) {
		return $this->respond(
			function () use ( $request ) {
				return $this->submit->submit( $request->get_param( 'session' ), get_current_user_id(), (array) $request->get_param( 'results' ) );
			}
		);
	}

	/**
	 * Completion route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function complete( WP_REST_Request $request ) {
		return $this->respond(
			function () use ( $request ) {
				$result                = $this->complete->complete( $request->get_param( 'session' ), get_current_user_id() );
				$result['destination'] = admin_url( 'admin.php?page=elavira-report&audit=' . $result['audit_id'] );
				return $result;
			}
		);
	}

	/**
	 * Failure route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function fail( WP_REST_Request $request ) {
		return $this->respond(
			function () use ( $request ) {
				$this->complete->fail( $request->get_param( 'session' ), get_current_user_id(), sanitize_key( $request->get_param( 'code' ) ) );
				return array( 'failed' => true );
			}
		);
	}

	/**
	 * Convert domain failures to safe REST errors.
	 *
	 * @param callable $callback Operation.
	 * @return WP_REST_Response|WP_Error
	 */
	private function respond( $callback ) {
		try {
			return new WP_REST_Response( $callback(), 200 );
		} catch ( Throwable $error ) {
			return new WP_Error( 'elavira_scan_rejected', $error->getMessage(), array( 'status' => 400 ) ); }
	}
}
