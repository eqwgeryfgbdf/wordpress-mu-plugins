<?php
/**
 * Plugin Name: Site Footer
 * Description: Beautifies the site footer without changing Puock theme files.
 * Version: 1.0.0
 *
 * @package SiteFooter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SITE_FOOTER_COPY_VERSION = 1;

/**
 * Add an about-page link into the footer bio once.
 */
function siteFooterMaybeEnhanceCopy(): void {
	if ( (int) get_option( 'site_footer_copy_version', 0 ) === SITE_FOOTER_COPY_VERSION ) {
		return;
	}
	if ( ! defined( 'PUOCK_OPT' ) ) {
		error_log( 'Site footer: Puock options are unavailable; footer copy was not enhanced.' );
		return;
	}

	$options = get_option( PUOCK_OPT, array() );
	if ( ! is_array( $options ) ) {
		error_log( 'Site footer: puock_options is not an array; footer copy was not enhanced.' );
		return;
	}

	$aboutPage = get_page_by_path( 'about' );
	$aboutUrl  = ( $aboutPage instanceof WP_Post ) ? get_permalink( $aboutPage ) : '';
	if ( ! is_string( $aboutUrl ) || $aboutUrl === '' ) {
		error_log( 'Site footer: about page permalink is empty; footer copy was not enhanced.' );
		return;
	}

	$currentAbout = isset( $options['footer_about_me'] ) ? (string) $options['footer_about_me'] : '';
	if ( $currentAbout !== '' && strpos( $currentAbout, 'site-footer__more' ) === false ) {
		$options['footer_about_me'] = rtrim( $currentAbout ) . ' <a class="site-footer__more" href="' . esc_url( $aboutUrl ) . '">閱讀介紹</a>';
	}

	$updated = update_option( PUOCK_OPT, $options, false );
	if ( false === $updated && get_option( PUOCK_OPT ) !== $options ) {
		error_log( 'Site footer: failed to save enhanced footer copy.' );
		return;
	}

	$storedVersion = update_option( 'site_footer_copy_version', SITE_FOOTER_COPY_VERSION, false );
	if ( false === $storedVersion && (int) get_option( 'site_footer_copy_version', 0 ) !== SITE_FOOTER_COPY_VERSION ) {
		error_log( 'Site footer: failed to store footer copy version.' );
	}
}

add_action( 'init', 'siteFooterMaybeEnhanceCopy', 20 );

/**
 * Topic and about links shown under the footer cards.
 *
 * @return array<int, array{title:string,url:string}>
 */
function siteFooterNavItems(): array {
	$items = array();
	$seen  = array();

	if ( function_exists( 'homepageIntroTopicCards' ) ) {
		foreach ( homepageIntroTopicCards() as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			$title = isset( $card['title'] ) ? (string) $card['title'] : '';
			$url   = isset( $card['url'] ) ? (string) $card['url'] : '';
			if ( $title === '' || $url === '' || isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			$items[]      = array(
				'title' => $title,
				'url'   => $url,
			);
		}
	}

	$aboutPage = get_page_by_path( 'about' );
	if ( $aboutPage instanceof WP_Post ) {
		$aboutUrl = get_permalink( $aboutPage );
		if ( is_string( $aboutUrl ) && $aboutUrl !== '' && ! isset( $seen[ $aboutUrl ] ) ) {
			$items[] = array(
				'title' => '關於',
				'url'   => $aboutUrl,
			);
		}
	}

	return $items;
}

/**
 * Prepend topic chips and wrap the copyright block.
 *
 * @param string $content Existing footer info HTML from Puock.
 */
function siteFooterBeautifyInfo( string $content ): string {
	$items = siteFooterNavItems();
	$nav   = '';
	if ( $items !== array() ) {
		$links = array();
		foreach ( $items as $item ) {
			$links[] = '<a class="site-footer__chip" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a>';
		}
		$nav = '<nav class="site-footer__nav" aria-label="主題入口">' . implode( '', $links ) . '</nav>';
	}

	return $nav . '<div class="site-footer__legal">' . $content . '</div>';
}

add_filter( 'pk_footer_info', 'siteFooterBeautifyInfo', 20 );

/**
 * Attach footer styles to the Puock stylesheet.
 */
function siteFooterEnqueueAssets(): void {
	if ( is_admin() ) {
		return;
	}
	if ( ! wp_style_is( 'puock', 'enqueued' ) && ! wp_style_is( 'puock', 'registered' ) ) {
		error_log( 'Site footer: Puock stylesheet is not available; footer CSS was not attached.' );
		return;
	}

	$css = <<<'CSS'
#footer{position:relative;margin-top:28px;padding:28px 0 0;background:var(--pk-bg-box);border-top:1px solid var(--pk-c-border)}
#footer::before{content:"";position:absolute;inset:0 auto auto 0;width:100%;height:3px;background:linear-gradient(90deg,transparent,var(--pk-c-primary),transparent);opacity:.7}
#footer>.container{position:relative}
#footer>.container>.row{display:grid;grid-template-columns:1fr;gap:12px;margin:0}
#footer>.container>.row>.col-md-6{width:100%;max-width:none;padding:18px 18px 16px;border-radius:12px;background:var(--pk-bg-box-content);border:1px solid transparent}
#footer>.container>.row>.col-md-6>div:first-child span{display:inline-flex;align-items:center;gap:10px;margin:0;padding:0;border:0!important;font-size:14px;font-weight:600;letter-spacing:.06em;color:var(--pk-c-font)}
#footer>.container>.row>.col-md-6>div:first-child i{width:32px;height:32px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:rgba(28,96,243,.12);color:var(--pk-c-primary);font-size:13px}
#footer .fa-bell{font-family:"Font Awesome 6 Free";font-weight:900}
#footer .fa-bell:before{content:"\f007"}
#footer>.container>.row>.col-md-6>p{margin:12px 0 0;line-height:1.8;color:var(--pk-c-sub)}
#footer .site-footer__more{display:inline-flex;align-items:center;margin-left:.35em;color:var(--pk-c-primary);font-weight:600;text-decoration:none}
#footer .site-footer__more:hover{text-decoration:underline}
#footer>.mt20.text-center{margin:18px 0 0;padding:16px 16px 20px;border-top:1px solid var(--pk-c-border);background:var(--pk-bg-box-content)}
#footer .info{display:flex;flex-direction:column;align-items:center;gap:12px}
#footer .site-footer__nav{display:flex;flex-wrap:wrap;justify-content:center;gap:8px}
#footer .site-footer__chip{display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;background:var(--pk-bg-box);color:var(--pk-c-font);font-size:13px;line-height:1.4;text-decoration:none}
#footer .site-footer__chip:hover{color:var(--pk-c-primary);background:rgba(28,96,243,.1)}
#footer .site-footer__legal{color:var(--pk-c-sub);font-size:13px;line-height:1.7}
#footer .site-footer__legal a{color:inherit}
#footer .site-footer__legal .fs12{margin-top:6px}
@media (min-width:768px){
#footer{padding:32px 0 0}
#footer>.container>.row{grid-template-columns:1fr 1fr;gap:16px}
}
@media (prefers-reduced-motion:reduce){
#footer .site-footer__chip,#footer>.container>.row>.col-md-6{transition:none}
}
CSS;
	wp_add_inline_style( 'puock', $css );
}

add_action( 'wp_enqueue_scripts', 'siteFooterEnqueueAssets', 22 );
