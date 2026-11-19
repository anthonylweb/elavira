<?php
/**
 * Core scanner resource limits.
 *
 * @package Elavira
 */

namespace Elavira\Scanner;

/** Conservative limits suitable for ordinary WordPress hosting. */
final class ScanLimits {
	const SESSION_TTL_SECONDS = 900;
	const MAX_RESULTS_BATCH   = 50;
	const MAX_TOTAL_RESULTS   = 1000;
	const MAX_REQUEST_BYTES   = 262144;
	const QUIET_TIME_MS       = 500;
	const STABILIZE_MAX_MS    = 3000;
	const READY_MAX_MS        = 10000;
}
