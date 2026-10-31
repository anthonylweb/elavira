<?php
/** Development-only registry inventory; run from the repository root. */
if ( PHP_SAPI !== 'cli' ) { exit; }
require dirname( __DIR__ ) . '/tests/bootstrap.php';
$rules = \Elavira\Rules\CoreRuleRegistry::create()->all();
$decisions = \Elavira\GuidedDecisions\CoreDecisionRegistry::create();
$current = array();
foreach ( $rules as $rule ) {
    $current[ $rule->id() ] = $rule->version();
}
$inventory = array();
foreach ( $rules as $rule ) {
    $schema = new ReflectionProperty( $rule->evidence_schema(), 'fields' );
    $decision = $decisions->for_rule( $rule->id(), $rule->version() );
    $inventory[] = array(
        'id' => $rule->id(), 'version' => $rule->version(),
        'current' => $current[ $rule->id() ] === $rule->version(),
        'outcome' => $rule->evaluation_type(), 'editorial_version' => $rule->editorial_version(),
        'wcag' => array_map( static function ( $mapping ) { return $mapping->to_array(); }, $rule->wcag() ),
        'evidence_fields' => array_keys( $schema->getValue( $rule->evidence_schema() ) ),
        'resolved_decision' => $decision ? $decision->get( 'id' ) : null,
        'editorial' => $rule->editorial(),
    );
}
echo json_encode( $inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
