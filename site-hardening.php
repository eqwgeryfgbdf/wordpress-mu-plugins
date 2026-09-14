<?php
/**
 * Plugin Name: Site Hardening
 * Description: Local hardening for this Synology WordPress install. Must-use; not a public plugin.
 * Version: 1.0.0
 *
 * @package SiteHardening
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace WP Fastest Cache's admin-username cookie rule with a generic logged-in check.
 */
function siteHardeningStripWpfcAdminNamesFromHtaccess() {
	$htaccessPath = ABSPATH . '.htaccess';
	if ( ! is_readable( $htaccessPath ) || ! is_writable( $htaccessPath ) ) {
		return;
	}

	$htaccess = file_get_contents( $htaccessPath );
	if ( false === $htaccess ) {
		return;
	}

	$updated = preg_replace(
		'/(# Start_WPFC_Exclude_Admin_Cookie\s*\n)RewriteCond %\{HTTP:Cookie\} !wordpress_logged_in_[^\n]+(\n# End_WPFC_Exclude_Admin_Cookie)/',
		'$1RewriteCond %{HTTP:Cookie} !wordpress_logged_in_$2',
		$htaccess,
		1,
		$count
	);

	if ( ! is_string( $updated ) || 1 !== $count || $updated === $htaccess ) {
		return;
	}

	$written = file_put_contents( $htaccessPath, $updated, LOCK_EX );
	if ( false === $written ) {
		error_log( 'Site hardening: failed to write .htaccess after stripping WPFC admin cookie names.' );
	}
}

add_action( 'init', 'siteHardeningStripWpfcAdminNamesFromHtaccess', 99 );
add_action( 'user_register', 'siteHardeningStripWpfcAdminNamesFromHtaccess', 99 );
add_action( 'profile_update', 'siteHardeningStripWpfcAdminNamesFromHtaccess', 99 );
add_action( 'deleted_user', 'siteHardeningStripWpfcAdminNamesFromHtaccess', 99 );
add_action( 'set_user_role', 'siteHardeningStripWpfcAdminNamesFromHtaccess', 99 );
