<?php
/**
 * Plugin Name: Site Fluid Layout
 * Description: Scales content width by page type so high-resolution screens keep less empty margin.
 * Version: 1.2.0
 *
 * @package SiteFluidLayout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep layout classes available if a future theme header starts calling body_class().
 *
 * @param array<int, string> $classes Body classes.
 * @return array<int, string>
 */
function siteFluidLayoutBodyClass( array $classes ): array {
	if ( is_admin() ) {
		return $classes;
	}

	$layoutKey = siteFluidLayoutKey();
	if ( $layoutKey === '' ) {
		return $classes;
	}

	$classes[] = 'site-fluid-layout';
	$classes[] = 'site-fluid-layout--' . $layoutKey;
	return $classes;
}

add_filter( 'body_class', 'siteFluidLayoutBodyClass', 20 );

/**
 * Layout key used by CSS: home, listing, article, or page.
 */
function siteFluidLayoutKey(): string {
	if ( is_front_page() || is_home() ) {
		return 'home';
	}
	if ( is_singular( 'post' ) ) {
		return 'article';
	}
	if ( is_page() || is_singular() ) {
		return 'page';
	}
	if ( is_archive() || is_search() ) {
		return 'listing';
	}
	return 'page';
}

/**
 * Enqueue viewport-relative container widths.
 *
 * Puock prints a hardcoded body class and never calls body_class(), so rules
 * target page landmarks with :has() instead of WordPress body classes.
 */
function siteFluidLayoutEnqueueStyles(): void {
	if ( is_admin() ) {
		return;
	}
	if ( ! wp_style_is( 'puock', 'enqueued' ) && ! wp_style_is( 'puock', 'registered' ) ) {
		error_log( 'Site fluid layout: Puock stylesheet is not available; layout CSS was not attached.' );
		return;
	}

	$css = <<<'CSS'
body{
--site-layout-gutter:32px;
--site-layout-max:1360px;
}
body:has(#content){--site-layout-max:1560px}
body:has(#page){--site-layout-max:1360px}
body:has(#post){--site-layout-max:1440px}
body:has(#content.homepage-dual-sidebars){
--site-layout-gutter:24px;
--site-layout-max:100%;
}
@media (min-width:1200px){
.container,.container-sm,.container-md,.container-lg,.container-xl,.container-xxl{
max-width:min(var(--site-layout-max),calc(100% - var(--site-layout-gutter)))!important;
width:100%;
}
body:has(#content.homepage-dual-sidebars) .container,
body:has(#content.homepage-dual-sidebars) .container-sm,
body:has(#content.homepage-dual-sidebars) .container-md,
body:has(#content.homepage-dual-sidebars) .container-lg,
body:has(#content.homepage-dual-sidebars) .container-xl,
body:has(#content.homepage-dual-sidebars) .container-xxl{
max-width:calc(100% - 24px)!important;
}
}
@media (min-width:1440px){
body{--site-layout-gutter:40px;--site-layout-max:1480px}
body:has(#content){--site-layout-max:1680px}
body:has(#page){--site-layout-max:1480px}
body:has(#post){--site-layout-max:1560px}
body:has(#content.homepage-dual-sidebars){--site-layout-gutter:24px;--site-layout-max:100%}
}
@media (min-width:1680px){
body{--site-layout-gutter:48px;--site-layout-max:1600px}
body:has(#content){--site-layout-max:1840px}
body:has(#page){--site-layout-max:1600px}
body:has(#post){--site-layout-max:1680px}
body:has(#content.homepage-dual-sidebars){--site-layout-gutter:28px;--site-layout-max:100%}
}
@media (min-width:1920px){
body{--site-layout-gutter:64px;--site-layout-max:1680px}
body:has(#content){--site-layout-max:1960px}
body:has(#page){--site-layout-max:1680px}
body:has(#post){--site-layout-max:1760px}
body:has(#content.homepage-dual-sidebars){--site-layout-gutter:32px;--site-layout-max:100%}
body:has(#content.homepage-dual-sidebars) .container,
body:has(#content.homepage-dual-sidebars) .container-sm,
body:has(#content.homepage-dual-sidebars) .container-md,
body:has(#content.homepage-dual-sidebars) .container-lg,
body:has(#content.homepage-dual-sidebars) .container-xl,
body:has(#content.homepage-dual-sidebars) .container-xxl{
max-width:calc(100% - 32px)!important;
}
}
#post .content-main>article,
#post .content-main article.stock-watch-article{
max-width:100%!important;
width:100%;
margin-left:0!important;
margin-right:0!important;
box-sizing:border-box;
}
#post .content-main img{
max-width:100%;
height:auto;
}
#post .content-main figure,
#post .content-main .wp-caption{
width:100%;
max-width:100%;
}
#post .content-main figure img,
#post .content-main img.alignnone,
#post .content-main img.aligncenter,
#post .content-main img.size-large,
#post .content-main img.size-full{
width:100%;
height:auto;
}
@media (min-width:1600px){
#post .content-main{
font-size:clamp(17px,1.05vw,19px);
line-height:1.85;
}
}
CSS;

	wp_add_inline_style( 'puock', $css );
}

add_action( 'wp_enqueue_scripts', 'siteFluidLayoutEnqueueStyles', 30 );
