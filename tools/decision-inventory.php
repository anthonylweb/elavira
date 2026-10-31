<?php
/** CLI-only snapshot of the authoritative decision library. */
if ( 'cli' !== PHP_SAPI ) { exit; }
require dirname( __DIR__ ) . '/tests/bootstrap.php';
$rows = array();
foreach ( \Elavira\GuidedDecisions\CoreDecisionRegistry::create()->all() as $decision ) {
	$row = array();
	foreach ( array( 'id', 'version', 'editorial_version', 'title', 'purpose', 'rules', 'learn_topics', 'start', 'steps', 'outcomes' ) as $key ) {
		$row[ $key ] = $decision->get( $key );
	}
	$rows[] = $row;
}
echo json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
