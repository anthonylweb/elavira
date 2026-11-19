<?php
/**
 * Privacy policy suggestions for site administrators.
 *
 * @package Elavira
 */

namespace Elavira\Core\Privacy;

/** Describes local storage without making legal compliance claims. */
final class PrivacyPolicy {
	/**
	 * Register suggested text in WordPress's privacy policy guide.
	 *
	 * @return void
	 */
	public function register() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( 'Elavira', wpautop( esc_html( self::text() ) ) );
		}
	}

	/**
	 * Return editable policy guidance; administrators must adapt it to their site.
	 *
	 * @return string
	 */
	public static function text() {
		return __( 'Elavira stores accessibility audit URLs (including permitted query parameters), bounded page excerpts and element evidence, findings, review notes, user IDs and timestamps attributing audit and review actions, Guided Decision history, and Accessibility Statement contact details and revisions in this site’s WordPress database. Public page text can contain personal information. Administrators should audit public URLs only and avoid entering confidential information into review notes or statement drafts. Recognizable credential and contact query parameters are rejected for new scans; other public query parameters are retained to distinguish pages. This is not a guarantee that every sensitive value can be detected. Existing records are not automatically rewritten. Authorized users can download audit reports; those exports may contain the retained information and should be shared carefully. Core sends no scans or telemetry to an Elavira service. A scan performs a same-site HTTP preflight and authenticated browser requests to this site. Records and settings are retained indefinitely, including after deactivation or uninstall. Core does not provide automatic retention deletion or personal-data erasure; contact the site administrator to review access, export, or an explicit removal request, including backups and downloaded reports. The optional visitor toolbar stores presentation preferences in the visitor’s browser under elaviraToolbarPreferences, not in a server-side visitor profile. Reset Preferences clears that stored choice. When browser storage is unavailable, preferences last only for the current page. These preferences do not establish a disability diagnosis or WCAG conformance.', 'elavira' );
	}
}
