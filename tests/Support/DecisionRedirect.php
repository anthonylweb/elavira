<?php
/**
 * Allows request tests to inspect redirects without exiting PHPUnit.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Support;

/** Redirect boundary marker. */
final class DecisionRedirect extends \RuntimeException {}
