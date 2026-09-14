<?php
/**
 * Plugin Name: Homepage Sidebars
 * Description: Adds left and right sidebars on the blog homepage without changing the Puock theme files.
 * Version: 1.0.0
 *
 * @package HomepageSidebars
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HOMEPAGE_SIDEBARS_LEFT_ID  = 'sidebar_home_left';
const HOMEPAGE_SIDEBARS_RIGHT_ID = 'sidebar_home';
const HOMEPAGE_SIDEBARS_SEEDED   = 'homepage_sidebars_initialized';

/**
 * Whether the dual homepage sidebars should render on this request.
 */
function homepageSidebarsShouldRender(): bool {
	if ( is_admin() ) {
		return false;
	}
	if ( ! function_exists( 'pk_get_option' ) ) {
		return false;
	}
	if ( pk_get_option( 'index_mode' ) === 'company' ) {
		return false;
	}
	return is_home();
}

/**
 * Register the extra left homepage sidebar.
 */
function homepageSidebarsRegister(): void {
	if ( ! function_exists( 'pk_common_sidebar_register' ) ) {
		register_sidebar(
			array(
				'name'          => '首頁 - 左側欄',
				'id'            => HOMEPAGE_SIDEBARS_LEFT_ID,
				'description'   => '首頁主欄左側，僅在桌面寬度顯示。',
				'before_widget' => '<div class="widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<h3 class="widget-title">',
				'after_title'   => '</h3>',
			)
		);
		return;
	}
	pk_common_sidebar_register(
		HOMEPAGE_SIDEBARS_LEFT_ID,
		'首頁 - 左側欄',
		'首頁主欄左側，僅在桌面寬度顯示。'
	);
}

add_action( 'widgets_init', 'homepageSidebarsRegister', 11 );

/**
 * Seed default widgets once, so the new columns are not empty.
 */
function homepageSidebarsMaybeSeedWidgets(): void {
	if ( get_option( HOMEPAGE_SIDEBARS_SEEDED ) === '1' ) {
		return;
	}
	if ( ! class_exists( 'puockAboutAuthor' ) ) {
		error_log( 'Homepage sidebars: Puock widgets are unavailable; default widgets were not seeded.' );
		return;
	}

	$sidebarsWidgets = get_option( 'sidebars_widgets' );
	if ( ! is_array( $sidebarsWidgets ) ) {
		error_log( 'Homepage sidebars: sidebars_widgets option is missing or invalid.' );
		return;
	}

	$leftWidgets  = homepageSidebarsWidgetIds( $sidebarsWidgets, HOMEPAGE_SIDEBARS_LEFT_ID );
	$rightWidgets = homepageSidebarsWidgetIds( $sidebarsWidgets, HOMEPAGE_SIDEBARS_RIGHT_ID );
	if ( $leftWidgets !== array() && count( $rightWidgets ) > 1 ) {
		update_option( HOMEPAGE_SIDEBARS_SEEDED, '1', false );
		return;
	}

	$errors = array();

	$authorWidgetId = homepageSidebarsTakeExistingWidget( $rightWidgets, 'puockaboutauthor' );
	if ( $authorWidgetId === '' ) {
		$authorWidgetId = homepageSidebarsCreateWidget(
			'puockaboutauthor',
			'widget_puockaboutauthor',
			array(
				'title'         => '關於博主',
				'name'          => 'Roy',
				'email'         => (string) get_option( 'admin_email' ),
				'des'           => '<p>嗨，我是 Roy。<br>研究 AI、Computer Vision 與 Edge Computing，也在這裡寫歌曲推薦與自架服務筆記。</p>',
				'cover'         => function_exists( 'pk_get_static_url' ) ? pk_get_static_url() . '/assets/img/show/head-cover.jpg' : '',
				'show_views'    => 'on',
				'show_comments' => 'on',
				'show_posts'    => 'on',
				'show_users'    => 0,
				'hide_title'    => 0,
				'icon'          => 'fa fa-user',
				'classes'       => '',
			),
			$errors
		);
	}

	$categoryWidgetId = homepageSidebarsCreateWidget(
		'puockcategory',
		'widget_puockcategory',
		array(
			'title'      => '分類探索',
			'categories' => '',
			'hide_title' => 0,
			'icon'       => 'fa fa-layer-group',
			'classes'    => '',
		),
		$errors
	);
	$searchWidgetId   = homepageSidebarsCreateWidget(
		'puocksearch',
		'widget_puocksearch',
		array(
			'title'      => '搜尋文章',
			'pl'         => '輸入關鍵字搜尋',
			'hide_title' => 0,
		),
		$errors
	);
	$newPostWidgetId  = homepageSidebarsCreateWidget(
		'puocknewpost',
		'widget_puocknewpost',
		array(
			'title'      => '最新文章',
			'nums'       => '5',
			'days'       => '3650',
			'simple'     => 0,
			'categories' => '',
			'hide_title' => 0,
			'icon'       => 'fa fa-clock',
			'classes'    => '',
		),
		$errors
	);
	$tagCloudWidgetId = homepageSidebarsCreateWidget(
		'puocktagcloud',
		'widget_puocktagcloud',
		array(
			'title'       => '標籤雲',
			'max_count'   => '18',
			'random_sort' => 0,
			'hide_title'  => 0,
			'icon'        => 'fa fa-tags',
			'classes'     => '',
		),
		$errors
	);
	$randomWidgetId   = homepageSidebarsCreateWidget(
		'puockrandompost',
		'widget_puockrandompost',
		array(
			'title'      => '隨機看看',
			'nums'       => '5',
			'days'       => '3650',
			'simple'     => 'on',
			'categories' => '',
			'hide_title' => 0,
			'icon'       => 'fa fa-shuffle',
			'classes'    => '',
		),
		$errors
	);

	$createdIds = array(
		$authorWidgetId,
		$categoryWidgetId,
		$searchWidgetId,
		$newPostWidgetId,
		$tagCloudWidgetId,
		$randomWidgetId,
	);
	foreach ( $createdIds as $widgetId ) {
		if ( $widgetId === '' ) {
			$errors[] = 'One or more homepage sidebar widgets could not be created.';
			break;
		}
	}
	if ( $errors !== array() ) {
		foreach ( $errors as $error ) {
			error_log( 'Homepage sidebars: ' . $error );
		}
		return;
	}

	$remainingRight = array();
	foreach ( $rightWidgets as $widgetId ) {
		if ( $widgetId === $authorWidgetId ) {
			continue;
		}
		$remainingRight[] = $widgetId;
	}

	$sidebarsWidgets[ HOMEPAGE_SIDEBARS_LEFT_ID ]  = array_values(
		array_filter(
			array( $authorWidgetId, $categoryWidgetId, $searchWidgetId )
		)
	);
	$sidebarsWidgets[ HOMEPAGE_SIDEBARS_RIGHT_ID ] = array_values(
		array_merge(
			array( $newPostWidgetId, $tagCloudWidgetId, $randomWidgetId ),
			$remainingRight
		)
	);

	if ( ! update_option( 'sidebars_widgets', $sidebarsWidgets ) && homepageSidebarsWidgetIds( get_option( 'sidebars_widgets' ), HOMEPAGE_SIDEBARS_LEFT_ID ) === array() ) {
		error_log( 'Homepage sidebars: failed to write sidebars_widgets after seeding.' );
		return;
	}

	update_option( HOMEPAGE_SIDEBARS_SEEDED, '1', false );
}

add_action( 'widgets_init', 'homepageSidebarsMaybeSeedWidgets', 20 );

/**
 * Widget ids currently assigned to a sidebar.
 *
 * @param array<string, mixed> $sidebarsWidgets Sidebars widgets option.
 * @return array<int, string>
 */
function homepageSidebarsWidgetIds( array $sidebarsWidgets, string $sidebarId ): array {
	$assigned = $sidebarsWidgets[ $sidebarId ] ?? array();
	if ( ! is_array( $assigned ) ) {
		return array();
	}
	$ids = array();
	foreach ( $assigned as $widgetId ) {
		if ( is_string( $widgetId ) && $widgetId !== '' ) {
			$ids[] = $widgetId;
		}
	}
	return $ids;
}

/**
 * Return the first widget id in the list that matches an id base.
 *
 * @param array<int, string> $widgetIds Assigned widget ids.
 */
function homepageSidebarsTakeExistingWidget( array $widgetIds, string $idBase ): string {
	$prefix = $idBase . '-';
	foreach ( $widgetIds as $widgetId ) {
		if ( strpos( $widgetId, $prefix ) === 0 ) {
			return $widgetId;
		}
	}
	return '';
}

/**
 * Create a widget instance and return its id, or an empty string on failure.
 *
 * @param array<string, mixed> $instance Widget settings.
 * @param array<int, string>   $errors   Error collector.
 */
function homepageSidebarsCreateWidget( string $idBase, string $optionName, array $instance, array &$errors ): string {
	$widgets = get_option( $optionName, array() );
	if ( ! is_array( $widgets ) ) {
		$widgets = array();
	}
	$nextId = 2;
	foreach ( $widgets as $key => $_value ) {
		if ( $key === '_multiwidget' || ! is_numeric( $key ) ) {
			continue;
		}
		$nextId = max( $nextId, ( (int) $key ) + 1 );
	}
	$widgets[ $nextId ]     = $instance;
	$widgets['_multiwidget'] = 1;
	update_option( $optionName, $widgets );
	$saved = get_option( $optionName );
	if ( ! is_array( $saved ) || ! isset( $saved[ $nextId ] ) ) {
		$errors[] = 'Failed to save widget option ' . $optionName . '.';
		return '';
	}
	return $idBase . '-' . $nextId;
}

/**
 * Swap in the dual-sidebar homepage template.
 *
 * @param string $template Resolved template path.
 */
function homepageSidebarsMaybeUseTemplate( string $template ): string {
	if ( ! homepageSidebarsShouldRender() ) {
		return $template;
	}
	$customTemplate = __DIR__ . '/homepage-sidebars/template.php';
	if ( ! is_readable( $customTemplate ) ) {
		error_log( 'Homepage sidebars: template file is missing or unreadable: ' . $customTemplate );
		return $template;
	}
	return $customTemplate;
}

add_filter( 'template_include', 'homepageSidebarsMaybeUseTemplate', 20 );

/**
 * Mark the homepage so CSS can apply the dual-sidebar grid.
 *
 * @param array<int, string> $classes Body classes.
 * @return array<int, string>
 */
function homepageSidebarsBodyClass( array $classes ): array {
	if ( ! homepageSidebarsShouldRender() ) {
		return $classes;
	}
	$classes[] = 'homepage-has-dual-sidebars';
	return $classes;
}

add_filter( 'body_class', 'homepageSidebarsBodyClass' );

/**
 * Enqueue homepage dual-sidebar layout styles.
 */
function homepageSidebarsEnqueueStyles(): void {
	if ( ! homepageSidebarsShouldRender() ) {
		return;
	}
	if ( ! wp_style_is( 'puock', 'enqueued' ) && ! wp_style_is( 'puock', 'registered' ) ) {
		error_log( 'Homepage sidebars: Puock stylesheet is not available; layout CSS was not attached.' );
		return;
	}
	$css = <<<'CSS'
.homepage-main{min-width:0;width:100%}
@media (min-width:1200px){
.homepage-layout--dual{display:grid;grid-template-columns:minmax(220px,.18fr) minmax(0,1fr) minmax(220px,.18fr);gap:clamp(16px,1.4vw,32px);align-items:start}
.homepage-layout--left{display:grid;grid-template-columns:minmax(220px,.18fr) minmax(0,1fr);gap:clamp(16px,1.4vw,32px);align-items:start}
.homepage-layout--right{display:grid;grid-template-columns:minmax(0,1fr) minmax(220px,.18fr);gap:clamp(16px,1.4vw,32px);align-items:start}
.homepage-sidebar{min-width:0}
.homepage-sidebar__inner{position:sticky;top:80px}
.homepage-sidebar .pk-widget .widget-common-media-post .img{width:72px;height:54px;flex-basis:72px;margin-right:8px}
.homepage-sidebar .pk-widget .widget-common-media-post .description{display:none}
.homepage-sidebar .widget-puock-tag-cloud{justify-content:flex-start}
.homepage-sidebar .widget-puock-tag-cloud a{flex:0 0 auto}
.homepage-sidebar .widget-puock-author .header{height:110px}
.homepage-sidebar .widget-puock-author .header .avatar{top:70px;width:72px;height:72px}
.homepage-sidebar .widget-puock-author .content{padding-top:36px}
}
@media (min-width:1600px){
.homepage-layout--dual{grid-template-columns:minmax(260px,.16fr) minmax(0,1fr) minmax(260px,.16fr)}
.homepage-layout--left{grid-template-columns:minmax(260px,.16fr) minmax(0,1fr)}
.homepage-layout--right{grid-template-columns:minmax(0,1fr) minmax(260px,.16fr)}
}
@media (min-width:1920px){
.homepage-layout--dual{grid-template-columns:minmax(280px,.14fr) minmax(0,1fr) minmax(280px,.14fr);gap:clamp(20px,1.6vw,36px)}
.homepage-layout--dual .homepage-main #posts>.me-0{
display:grid;
grid-template-columns:minmax(0,1fr) minmax(0,1fr);
gap:16px;
}
.homepage-layout--dual .homepage-main .post-item-list{margin-bottom:0}
}
@media (max-width:1199.98px){
.homepage-sidebar{display:none}
.homepage-layout--dual,.homepage-layout--left,.homepage-layout--right{display:block}
}
CSS;
	wp_add_inline_style( 'puock', $css );
}

add_action( 'wp_enqueue_scripts', 'homepageSidebarsEnqueueStyles', 20 );

/**
 * Layout class reflecting which homepage sidebars currently have widgets.
 */
function homepageSidebarsLayoutClass(): string {
	$hasLeft  = is_active_sidebar( HOMEPAGE_SIDEBARS_LEFT_ID );
	$hasRight = is_active_sidebar( HOMEPAGE_SIDEBARS_RIGHT_ID );
	if ( $hasLeft && $hasRight ) {
		return 'homepage-layout homepage-layout--dual';
	}
	if ( $hasLeft ) {
		return 'homepage-layout homepage-layout--left';
	}
	if ( $hasRight ) {
		return 'homepage-layout homepage-layout--right';
	}
	return 'homepage-layout';
}

/**
 * Render one homepage sidebar if it has widgets.
 */
function homepageSidebarsRender( string $sidebarId, string $label, string $extraClass ): void {
	if ( ! is_active_sidebar( $sidebarId ) ) {
		return;
	}
	echo '<aside class="homepage-sidebar ' . esc_attr( $extraClass ) . '" aria-label="' . esc_attr( $label ) . '">';
	echo '<div class="homepage-sidebar__inner sidebar-main">';
	if ( ! dynamic_sidebar( $sidebarId ) ) {
		error_log( 'Homepage sidebars: dynamic_sidebar returned no output for ' . $sidebarId );
	}
	echo '</div></aside>';
}
