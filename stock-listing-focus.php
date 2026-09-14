<?php
/**
 * Plugin Name: Stock Listing Focus
 * Description: Homepage shows only the latest trading-day stock posts; older dates stay on the 台股觀察總覽 page.
 * Version: 1.0.0
 *
 * @package StockListingFocus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const STOCK_LISTING_CACHE_KEY     = 'stock_listing_focus_posts_v2';
const STOCK_LISTING_SETUP_VERSION = 1;
const STOCK_LISTING_SETUP_OPTION  = 'stock_listing_focus_setup';
const STOCK_LISTING_HUB_SLUG      = 'taiwan-stock-watch';
const STOCK_LISTING_SHORTCODE     = 'stock_watch_archive';

/**
 * Category slugs treated as stock-market posts.
 *
 * @return array<int, string>
 */
function stockListingCategorySlugs(): array {
	return array(
		'stock-views',
		'taiwan-stock-afterhours',
		'taiwan-stock-premarket',
	);
}

/**
 * One-time hub page shortcode + 股市 menu retarget.
 */
function stockListingMaybeSetup(): void {
	if ( (int) get_option( STOCK_LISTING_SETUP_OPTION, 0 ) === STOCK_LISTING_SETUP_VERSION ) {
		return;
	}

	$hubUpdated  = stockListingReplaceHubStaticList();
	$menuUpdated = stockListingRetargetStockMenu();
	if ( ! $hubUpdated || ! $menuUpdated ) {
		return;
	}

	$stored = update_option( STOCK_LISTING_SETUP_OPTION, STOCK_LISTING_SETUP_VERSION, false );
	if ( false === $stored && (int) get_option( STOCK_LISTING_SETUP_OPTION, 0 ) !== STOCK_LISTING_SETUP_VERSION ) {
		error_log( 'Stock listing: failed to store setup version after migrating the hub page and menu.' );
	}
}

add_action( 'init', 'stockListingMaybeSetup', 20 );

/**
 * Replace the hardcoded 每日觀察 links with a live shortcode.
 */
function stockListingReplaceHubStaticList(): bool {
	$hubPage = get_page_by_path( STOCK_LISTING_HUB_SLUG );
	if ( ! $hubPage instanceof WP_Post ) {
		error_log( 'Stock listing: cannot update the hub page because ' . STOCK_LISTING_HUB_SLUG . ' is missing.' );
		return false;
	}
	if ( strpos( $hubPage->post_content, '[' . STOCK_LISTING_SHORTCODE . ']' ) !== false ) {
		return true;
	}

	$updatedContent = preg_replace(
		'#(<h2>每日觀察</h2>)\s*<ul>.*?</ul>#su',
		"$1\n[" . STOCK_LISTING_SHORTCODE . ']',
		$hubPage->post_content,
		1,
		$count
	);
	if ( ! is_string( $updatedContent ) || $count !== 1 ) {
		error_log( 'Stock listing: could not replace the static 每日觀察 list on the hub page.' );
		return false;
	}

	$result = wp_update_post(
		array(
			'ID'           => (int) $hubPage->ID,
			'post_content' => $updatedContent,
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		error_log( 'Stock listing: failed to save hub page content: ' . $result->get_error_message() );
		return false;
	}

	return true;
}

/**
 * Point the primary 股市 menu item at the hub page instead of the category archive.
 */
function stockListingRetargetStockMenu(): bool {
	$hubPage = get_page_by_path( STOCK_LISTING_HUB_SLUG );
	if ( ! $hubPage instanceof WP_Post ) {
		error_log( 'Stock listing: cannot retarget the 股市 menu because the hub page is missing.' );
		return false;
	}

	$hubUrl = get_permalink( $hubPage );
	if ( ! is_string( $hubUrl ) || $hubUrl === '' ) {
		error_log( 'Stock listing: hub permalink is empty; 股市 menu was not updated.' );
		return false;
	}

	$locations = get_nav_menu_locations();
	$menuId    = isset( $locations['primary'] ) ? (int) $locations['primary'] : 0;
	if ( $menuId <= 0 ) {
		error_log( 'Stock listing: primary menu location is missing; 股市 menu was not updated.' );
		return false;
	}

	$items = wp_get_nav_menu_items( $menuId );
	if ( ! is_array( $items ) ) {
		error_log( 'Stock listing: primary menu items could not be loaded.' );
		return false;
	}

	foreach ( $items as $item ) {
		if ( ! $item instanceof WP_Post || $item->title !== '股市' ) {
			continue;
		}
		if ( $item->type === 'post_type' && (int) $item->object_id === (int) $hubPage->ID ) {
			return true;
		}

		$updated = wp_update_nav_menu_item(
			$menuId,
			(int) $item->ID,
			array(
				'menu-item-object-id' => (int) $hubPage->ID,
				'menu-item-object'    => 'page',
				'menu-item-parent-id' => (int) $item->menu_item_parent,
				'menu-item-position'  => (int) $item->menu_order,
				'menu-item-type'      => 'post_type',
				'menu-item-title'     => '股市',
				'menu-item-url'       => $hubUrl,
				'menu-item-status'    => 'publish',
			)
		);
		if ( is_wp_error( $updated ) ) {
			error_log( 'Stock listing: failed to retarget the 股市 menu: ' . $updated->get_error_message() );
			return false;
		}

		return true;
	}

	error_log( 'Stock listing: 股市 menu item was not found in the primary menu.' );
	return false;
}

/**
 * Send the old 股市 category URL to the hub page.
 */
function stockListingRedirectStockCategory(): void {
	$requestUri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	if ( $requestUri === '' ) {
		return;
	}
	$path = (string) wp_parse_url( $requestUri, PHP_URL_PATH );
	if ( $path === '' ) {
		return;
	}

	$patterns = array(
		'#/(index\.php/)?category/stock-views(/page/\d+)?/?$#',
		'#/(index\.php/)?category/股市觀點(/page/\d+)?/?$#',
	);
	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $path ) ) {
			stockListingRedirectToHub( 'stock-views category' );
			return;
		}
	}
}

/**
 * 301 to the published stock hub page.
 */
function stockListingRedirectToHub( string $fromLabel ): void {
	$hubPage = get_page_by_path( STOCK_LISTING_HUB_SLUG );
	if ( ! $hubPage instanceof WP_Post ) {
		error_log( 'Stock listing: cannot redirect ' . $fromLabel . ' because the hub page is missing.' );
		return;
	}
	$targetUrl = get_permalink( $hubPage );
	if ( ! is_string( $targetUrl ) || $targetUrl === '' ) {
		error_log( 'Stock listing: hub permalink is empty; ' . $fromLabel . ' redirect skipped.' );
		return;
	}
	wp_safe_redirect( $targetUrl, 301 );
	exit;
}

add_action( 'template_redirect', 'stockListingRedirectStockCategory', 1 );

/**
 * Remember that this request is the blog homepage, including later widget queries.
 */
function stockListingHomeRequest( ?bool $set = null ): bool {
	static $isHomeRequest = false;
	if ( $set === true ) {
		$isHomeRequest = true;
	}
	return $isHomeRequest;
}

/**
 * Keep older stock posts off the homepage list and homepage widgets.
 */
function stockListingExcludeOlderFromHome( WP_Query $query ): void {
	if ( is_admin() || $query->is_feed() || $query->is_preview() ) {
		return;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	if ( $query->is_home() && $query->is_main_query() ) {
		stockListingHomeRequest( true );
	} elseif ( ! stockListingHomeRequest() ) {
		return;
	}

	if ( $query->is_singular() || $query->is_search() || $query->is_category() || $query->is_tag() || $query->is_page() ) {
		return;
	}

	$postType = $query->get( 'post_type' );
	if ( $postType !== '' && $postType !== 'post' && $postType !== array( 'post' ) ) {
		return;
	}

	$olderIds = stockListingOlderPostIds();
	if ( $olderIds === array() ) {
		return;
	}

	$existing = $query->get( 'post__not_in' );
	if ( ! is_array( $existing ) ) {
		$existing = array();
	}

	$merged = array_values( array_unique( array_map( 'intval', array_merge( $existing, $olderIds ) ) ) );
	$query->set( 'post__not_in', $merged );
}

add_action( 'pre_get_posts', 'stockListingExcludeOlderFromHome', 20 );

/**
 * Puock's random widget uses raw SQL, so also exclude older stock IDs there.
 *
 * @param string $query SQL.
 */
function stockListingExcludeOlderFromRandomSql( $query ) {
	if ( ! is_string( $query ) || $query === '' ) {
		return $query;
	}
	if ( ! stockListingHomeRequest() ) {
		return $query;
	}
	if ( stripos( $query, 'ORDER BY rand()' ) === false ) {
		return $query;
	}

	$olderIds = stockListingOlderPostIds();
	if ( $olderIds === array() ) {
		return $query;
	}

	$idList  = implode( ',', $olderIds );
	$updated = preg_replace(
		'/\bWHERE\s+post_type\s*=\s*\'post\'/i',
		'WHERE post_type = \'post\' AND ID NOT IN (' . $idList . ')',
		$query,
		1,
		$count
	);
	if ( ! is_string( $updated ) || $count !== 1 ) {
		error_log( 'Stock listing: could not exclude older stock posts from the random widget SQL.' );
		return $query;
	}

	return $updated;
}

add_filter( 'query', 'stockListingExcludeOlderFromRandomSql', 20 );

/**
 * Stock posts that should stay off the homepage.
 *
 * @return array<int, int>
 */
function stockListingOlderPostIds(): array {
	$catalog = stockListingCatalog();
	if ( $catalog === null ) {
		return array();
	}

	$olderIds = array();
	foreach ( $catalog['posts'] as $post ) {
		if ( $post['date'] !== $catalog['latestDate'] ) {
			$olderIds[] = $post['id'];
		}
	}

	return $olderIds;
}

/**
 * Published stock posts grouped for the homepage filter and hub archive.
 *
 * @return array{latestDate:string,posts:array<int,array{id:int,date:string,title:string,url:string,label:string}>}|null
 */
function stockListingCatalog(): ?array {
	static $runtime = null;
	if ( is_array( $runtime ) ) {
		return $runtime;
	}

	$cached = get_transient( STOCK_LISTING_CACHE_KEY );
	if ( is_array( $cached ) && isset( $cached['latestDate'], $cached['posts'] ) && is_array( $cached['posts'] ) ) {
		$runtime = $cached;
		return $runtime;
	}

	$built = stockListingBuildCatalog();
	if ( $built === null ) {
		return null;
	}

	$set = set_transient( STOCK_LISTING_CACHE_KEY, $built, HOUR_IN_SECONDS );
	if ( false === $set ) {
		error_log( 'Stock listing: failed to cache the stock post catalog.' );
	}

	$runtime = $built;
	return $runtime;
}

/**
 * Query published stock posts and derive the latest trading date.
 *
 * @return array{latestDate:string,posts:array<int,array{id:int,date:string,title:string,url:string,label:string}>}|null
 */
function stockListingBuildCatalog(): ?array {
	$termIds = stockListingCategoryTermIds();
	if ( $termIds === array() ) {
		error_log( 'Stock listing: none of the stock categories exist, so the homepage filter was skipped.' );
		return null;
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => 200,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy' => 'category',
					'field'    => 'term_id',
					'terms'    => $termIds,
				),
			),
		)
	);

	$posts = array();
	foreach ( $query->posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		$url = get_permalink( $post );
		if ( ! is_string( $url ) || $url === '' ) {
			error_log( 'Stock listing: permalink is empty for post ' . $post->ID );
			continue;
		}
		$title = get_the_title( $post );
		if ( ! is_string( $title ) || $title === '' ) {
			error_log( 'Stock listing: title is empty for post ' . $post->ID );
			continue;
		}
		$dateKey = stockListingMarketDate( $post );
		if ( $dateKey === '' ) {
			error_log( 'Stock listing: market date is missing for post ' . $post->ID );
			continue;
		}
		$posts[] = array(
			'id'    => (int) $post->ID,
			'date'  => $dateKey,
			'title' => $title,
			'url'   => $url,
			'label' => stockListingSessionLabel( $post ),
		);
	}
	wp_reset_postdata();

	if ( $posts === array() ) {
		error_log( 'Stock listing: no published stock posts were found.' );
		return array(
			'latestDate' => '',
			'posts'      => array(),
		);
	}

	$latestDate = $posts[0]['date'];
	foreach ( $posts as $post ) {
		if ( $post['date'] > $latestDate ) {
			$latestDate = $post['date'];
		}
	}

	return array(
		'latestDate' => $latestDate,
		'posts'      => $posts,
	);
}

/**
 * Term IDs for stock categories that currently exist.
 *
 * @return array<int, int>
 */
function stockListingCategoryTermIds(): array {
	$ids = array();
	foreach ( stockListingCategorySlugs() as $slug ) {
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( ! $term instanceof WP_Term ) {
			error_log( 'Stock listing: stock category is missing for slug ' . $slug );
			continue;
		}
		$ids[] = (int) $term->term_id;
	}
	return $ids;
}

/**
 * Trading-day date for a stock post, preferring the slug over publish time.
 */
function stockListingMarketDate( WP_Post $post ): string {
	if ( preg_match( '/(20\d{2}-\d{2}-\d{2})/', $post->post_name, $matches ) === 1 ) {
		return $matches[1];
	}

	$metaDate = get_post_meta( $post->ID, '_stock_hot_news_date', true );
	if ( is_string( $metaDate ) && preg_match( '/^20\d{2}-\d{2}-\d{2}$/', $metaDate ) === 1 ) {
		return $metaDate;
	}

	$timestamp = get_post_timestamp( $post );
	if ( ! is_int( $timestamp ) || $timestamp <= 0 ) {
		return '';
	}

	return (string) wp_date( 'Y-m-d', $timestamp );
}

/**
 * Short session label for a stock post.
 */
function stockListingSessionLabel( WP_Post $post ): string {
	if ( has_category( 'taiwan-stock-premarket', $post ) ) {
		return '盤前';
	}
	if ( has_category( 'taiwan-stock-afterhours', $post ) ) {
		return '盤後';
	}
	return '盤勢';
}

/**
 * Drop the catalog cache when stock posts change.
 */
function stockListingInvalidateCatalog(): void {
	$deleted = delete_transient( STOCK_LISTING_CACHE_KEY );
	if ( false === $deleted && get_transient( STOCK_LISTING_CACHE_KEY ) !== false ) {
		error_log( 'Stock listing: failed to delete the stock post catalog cache.' );
	}
	if ( function_exists( 'pk_cache_delete' ) && defined( 'PKC_WIDGET_NEW_POSTS' ) ) {
		pk_cache_delete( PKC_WIDGET_NEW_POSTS );
	}
}

add_action( 'save_post_post', 'stockListingInvalidateCatalog' );
add_action( 'deleted_post', 'stockListingInvalidateCatalog' );
add_action( 'trashed_post', 'stockListingInvalidateCatalog' );
add_action( 'untrashed_post', 'stockListingInvalidateCatalog' );

/**
 * Live archive used on 台股觀察總覽.
 */
function stockListingRenderArchiveHtml(): string {
	$catalog = stockListingCatalog();
	if ( $catalog === null || $catalog['posts'] === array() ) {
		error_log( 'Stock listing: archive renderer had no stock posts to render.' );
		return '<p class="stock-watch-archive__empty">目前還沒有已發佈的台股觀察。</p>';
	}

	$groups = array();
	foreach ( $catalog['posts'] as $post ) {
		$groups[ $post['date'] ][] = $post;
	}

	ob_start();
	?>
	<div class="stock-watch-archive">
		<p class="stock-watch-archive__note">首頁只保留最新一個交易日；較早的盤前／盤後都整理在這裡。</p>
		<?php foreach ( $groups as $dateKey => $dayPosts ) : ?>
			<?php
			$timestamp  = strtotime( $dateKey . ' 12:00:00' );
			$dateLabel  = is_int( $timestamp ) ? wp_date( 'Y年n月j日', $timestamp ) : $dateKey;
			$isLatest   = $dateKey === $catalog['latestDate'];
			$headingId  = 'stock-watch-' . $dateKey;
			?>
			<section class="stock-watch-archive__day<?php echo $isLatest ? ' stock-watch-archive__day--latest' : ''; ?>" aria-labelledby="<?php echo esc_attr( $headingId ); ?>">
				<h3 id="<?php echo esc_attr( $headingId ); ?>" class="stock-watch-archive__date">
					<?php echo esc_html( $dateLabel ); ?>
					<?php if ( $isLatest ) : ?>
						<span class="stock-watch-archive__badge">最新</span>
					<?php endif; ?>
				</h3>
				<ul class="stock-watch-archive__list">
					<?php foreach ( $dayPosts as $dayPost ) : ?>
						<li>
							<a href="<?php echo esc_url( $dayPost['url'] ); ?>">
								<span class="stock-watch-archive__session"><?php echo esc_html( $dayPost['label'] ); ?></span>
								<span class="stock-watch-archive__title"><?php echo esc_html( $dayPost['title'] ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
	</div>
	<?php
	$html = ob_get_clean();
	if ( ! is_string( $html ) || $html === '' ) {
		error_log( 'Stock listing: failed to render the hub archive HTML.' );
		return '<p class="stock-watch-archive__empty">台股觀察列表暫時無法顯示。</p>';
	}
	return $html;
}

/**
 * Insert the dated archive after Puock's wpautop so the list is not turned into line breaks.
 *
 * @param string $content Page HTML.
 */
function stockListingInjectArchive( string $content ): string {
	if ( is_admin() ) {
		return $content;
	}
	if ( ! is_page( STOCK_LISTING_HUB_SLUG ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$marker = '[' . STOCK_LISTING_SHORTCODE . ']';
	if ( strpos( $content, $marker ) === false ) {
		error_log( 'Stock listing: hub page is missing the archive marker.' );
		return $content;
	}

	$replaced = preg_replace(
		'#(?:<p>\s*)?' . preg_quote( $marker, '#' ) . '(?:\s*<br\s*/?>)?(?:\s*</p>)?#',
		stockListingRenderArchiveHtml(),
		$content,
		1,
		$count
	);
	if ( ! is_string( $replaced ) || $count !== 1 ) {
		error_log( 'Stock listing: failed to inject the hub archive.' );
		return $content;
	}

	return $replaced;
}

add_filter( 'the_content', 'stockListingInjectArchive', 20 );

/**
 * Hub-page styles for the dated archive.
 */
function stockListingEnqueueAssets(): void {
	if ( is_admin() || ! is_page( STOCK_LISTING_HUB_SLUG ) ) {
		return;
	}
	if ( ! wp_style_is( 'puock', 'enqueued' ) && ! wp_style_is( 'puock', 'registered' ) ) {
		error_log( 'Stock listing: Puock stylesheet is not available; archive CSS was not attached.' );
		return;
	}

	$css = <<<'CSS'
.stock-watch-archive{margin:18px 0 28px}
.stock-watch-archive__note{margin:0 0 16px;color:var(--pk-c-sub);font-size:14px;line-height:1.7}
.stock-watch-archive__day{margin:0 0 14px;padding:12px 14px;border-radius:var(--pk-g-box-radius);background:var(--pk-bg-box)}
.stock-watch-archive__day--latest{background:rgba(28,96,243,.06)}
.stock-watch-archive__date{display:flex;align-items:center;gap:8px;margin:0 0 8px;padding:0;border:0;background:none;font-size:16px;font-weight:600;line-height:1.4}
.stock-watch-archive__date::after,.stock-watch-archive__date::before{content:none;display:none}
.stock-watch-archive__badge{display:inline-flex;align-items:center;padding:1px 8px;border-radius:999px;background:var(--pk-c-primary);color:#fff;font-size:11px;letter-spacing:.06em}
.stock-watch-archive__list{margin:0;padding:0;list-style:none}
.stock-watch-archive__list li{margin:0}
.stock-watch-archive__list a{display:flex;align-items:flex-start;gap:10px;padding:8px 0;color:inherit;text-decoration:none}
.stock-watch-archive__list a:hover{color:var(--pk-c-primary)}
.stock-watch-archive__session{flex:0 0 auto;margin-top:1px;padding:1px 8px;border-radius:999px;background:rgba(28,96,243,.12);color:var(--pk-c-primary);font-size:12px;line-height:1.5}
.stock-watch-archive__title{min-width:0;font-size:15px;line-height:1.55;font-weight:600}
.stock-watch-archive__empty{margin:12px 0;color:var(--pk-c-sub)}
CSS;
	wp_add_inline_style( 'puock', $css );
}

add_action( 'wp_enqueue_scripts', 'stockListingEnqueueAssets', 20 );
