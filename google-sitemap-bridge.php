<?php
/**
 * Plugin Name: Google Sitemap Bridge
 * Description: Publishes root-level Rank Math sitemaps Google Search Console can fetch on this PATHINFO install.
 * Version: 1.0.0
 *
 * @package GoogleSitemapBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowed static sitemap file names written to the web root.
 */
const GOOGLE_SITEMAP_BRIDGE_FILE_PATTERN = '/^(sitemap_index|sitemap-index|[a-z0-9_-]+-sitemap[0-9]*)\.xml$/';

/**
 * Remove X-Robots-Tag noindex so Search Console can fetch the sitemap XML.
 *
 * @param array $headers HTTP headers Rank Math would send.
 * @param bool  $isXsl   Whether the response is the XSL stylesheet.
 * @return array
 */
function googleSitemapBridgeFilterHeaders( $headers, $isXsl ): array {
	if ( ! is_array( $headers ) ) {
		error_log( 'Google Sitemap Bridge: Rank Math sitemap headers were not an array.' );
		return array();
	}

	unset( $headers['X-Robots-Tag'] );
	$headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
	$headers['Pragma']        = 'no-cache';

	return $headers;
}

/**
 * Serve sitemap files from the web root instead of /index.php/.
 *
 * @param string $base Rank Math sitemap base path.
 * @return string
 */
function googleSitemapBridgeUseRootBase( $base ): string {
	if ( ! is_string( $base ) ) {
		error_log( 'Google Sitemap Bridge: sitemap base was not a string.' );
		return '';
	}

	return '';
}

/**
 * Point sitemap loc entries at root-level XML files.
 *
 * @param string $xml Sitemap XML.
 * @return string
 */
function googleSitemapBridgeRewriteSitemapLocs( string $xml ): string {
	$homeUrl = rtrim( (string) home_url(), '/' );
	if ( $homeUrl === '' ) {
		error_log( 'Google Sitemap Bridge: home_url() returned an empty value.' );
		return $xml;
	}

	$rewritten = preg_replace(
		'#' . preg_quote( $homeUrl, '#' ) . '/index\.php/((?:[a-z0-9_-]+-)?sitemap(?:_index)?(?:[0-9]*)\.xml)#',
		$homeUrl . '/$1',
		$xml
	);

	if ( ! is_string( $rewritten ) ) {
		error_log( 'Google Sitemap Bridge: failed to rewrite sitemap loc URLs.' );
		return $xml;
	}

	return $rewritten;
}

/**
 * Strip the browser stylesheet so Search Console receives plain sitemap XML.
 *
 * @param string $xml Sitemap XML.
 * @return string
 */
function googleSitemapBridgeStripStylesheet( string $xml ): string {
	$stripped = preg_replace( '/<\?xml-stylesheet\b[^>]*\?>\s*/', '', $xml );
	if ( ! is_string( $stripped ) ) {
		error_log( 'Google Sitemap Bridge: failed to strip xml-stylesheet from sitemap XML.' );
		return $xml;
	}

	return $stripped;
}

/**
 * Write one sitemap file into the WordPress root.
 *
 * @param string $fileName File name only, for example sitemap_index.xml.
 * @param string $xml      Sitemap XML.
 * @return bool
 */
function googleSitemapBridgeWriteRootFile( string $fileName, string $xml ): bool {
	if ( preg_match( GOOGLE_SITEMAP_BRIDGE_FILE_PATTERN, $fileName ) !== 1 ) {
		error_log( 'Google Sitemap Bridge: refused to write unexpected sitemap file name: ' . $fileName );
		return false;
	}

	if ( $xml === '' ) {
		error_log( 'Google Sitemap Bridge: refused to write empty sitemap: ' . $fileName );
		return false;
	}

	$targetPath = ABSPATH . $fileName;
	$written    = file_put_contents( $targetPath, $xml, LOCK_EX );
	if ( false === $written ) {
		error_log( 'Google Sitemap Bridge: failed to write ' . $targetPath );
		return false;
	}

	return true;
}

/**
 * Generate Rank Math sitemaps and publish them as static root files.
 */
function googleSitemapBridgePublishStaticFiles(): void {
	static $isPublishing = false;
	if ( $isPublishing ) {
		return;
	}

	if ( ! class_exists( '\RankMath\Sitemap\Generator' ) ) {
		error_log( 'Google Sitemap Bridge: Rank Math sitemap generator is not available.' );
		return;
	}

	$isPublishing = true;

	try {
		if ( class_exists( '\RankMath\Sitemap\Cache' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
		}

		$generator = new \RankMath\Sitemap\Generator();
		$indexXml  = $generator->get_output( '1', 1 );
		if ( ! is_string( $indexXml ) || $indexXml === '' ) {
			throw new RuntimeException( 'Rank Math returned an empty sitemap index.' );
		}

		$indexXml = googleSitemapBridgeStripStylesheet( googleSitemapBridgeRewriteSitemapLocs( $indexXml ) );
		if ( ! googleSitemapBridgeWriteRootFile( 'sitemap_index.xml', $indexXml ) ) {
			throw new RuntimeException( 'Could not write sitemap_index.xml.' );
		}

		if ( ! googleSitemapBridgeWriteRootFile( 'sitemap-index.xml', $indexXml ) ) {
			throw new RuntimeException( 'Could not write sitemap-index.xml.' );
		}

		preg_match_all( '#<loc>([^<]+)</loc>#', $indexXml, $matches );
		$childUrls = isset( $matches[1] ) && is_array( $matches[1] ) ? $matches[1] : array();
		foreach ( $childUrls as $childUrl ) {
			$path = (string) wp_parse_url( $childUrl, PHP_URL_PATH );
			$fileName = basename( $path );
			if ( preg_match( '/^([a-z0-9_-]+)-sitemap([0-9]*)\.xml$/', $fileName, $parts ) !== 1 ) {
				error_log( 'Google Sitemap Bridge: skipped unexpected child sitemap URL: ' . $childUrl );
				continue;
			}

			$sitemapType = $parts[1];
			$pageNumber  = $parts[2] === '' ? 1 : max( 1, (int) $parts[2] );
			$childXml    = $generator->get_output( $sitemapType, $pageNumber );
			if ( ! is_string( $childXml ) || $childXml === '' ) {
				error_log( 'Google Sitemap Bridge: Rank Math returned an empty child sitemap: ' . $fileName );
				continue;
			}

			$childXml = googleSitemapBridgeStripStylesheet( googleSitemapBridgeRewriteSitemapLocs( $childXml ) );
			googleSitemapBridgeWriteRootFile( $fileName, $childXml );
		}

		$robotsTxt  = "User-agent: *\n";
		$robotsTxt .= "Disallow: /wp-admin/\n";
		$robotsTxt .= "Allow: /wp-admin/admin-ajax.php\n\n";
		$robotsTxt .= 'Sitemap: ' . rtrim( (string) home_url(), '/' ) . "/sitemap-index.xml\n";
		$robotsWritten = file_put_contents( ABSPATH . 'robots.txt', $robotsTxt, LOCK_EX );
		if ( false === $robotsWritten ) {
			error_log( 'Google Sitemap Bridge: failed to write robots.txt' );
		}

		delete_transient( 'google_sitemap_bridge_scheduled' );
	} catch ( Throwable $error ) {
		error_log( 'Google Sitemap Bridge: ' . $error->getMessage() );
	}

	$isPublishing = false;
}

/**
 * Rebuild static sitemaps once at the end of the request.
 */
function googleSitemapBridgeSchedulePublish(): void {
	if ( get_transient( 'google_sitemap_bridge_scheduled' ) ) {
		return;
	}

	$stored = set_transient( 'google_sitemap_bridge_scheduled', '1', MINUTE_IN_SECONDS );
	if ( false === $stored ) {
		error_log( 'Google Sitemap Bridge: failed to store the sitemap rebuild lock.' );
	}

	add_action( 'shutdown', 'googleSitemapBridgePublishStaticFiles', 20 );
}

/**
 * Publish immediately when the root sitemap is missing.
 */
function googleSitemapBridgeMaybePublishOnLoad(): void {
	if ( ! class_exists( '\RankMath\Sitemap\Generator' ) ) {
		return;
	}

	if ( is_readable( ABSPATH . 'sitemap_index.xml' ) ) {
		return;
	}

	googleSitemapBridgePublishStaticFiles();
}

add_filter( 'rank_math/sitemap/http_headers', 'googleSitemapBridgeFilterHeaders', 10, 2 );
add_filter( 'rank_math/sitemap/base_url', 'googleSitemapBridgeUseRootBase' );
add_action( 'init', 'googleSitemapBridgeMaybePublishOnLoad', 20 );
add_action( 'save_post', 'googleSitemapBridgeSchedulePublish', 99 );
add_action( 'deleted_post', 'googleSitemapBridgeSchedulePublish', 99 );
add_action( 'created_term', 'googleSitemapBridgeSchedulePublish', 99 );
add_action( 'edited_term', 'googleSitemapBridgeSchedulePublish', 99 );
add_action( 'delete_term', 'googleSitemapBridgeSchedulePublish', 99 );
