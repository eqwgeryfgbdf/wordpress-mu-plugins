<?php
/**
 * Plugin Name: Post Related Reading
 * Description: Series navigation and related-reading cards on single posts, without editing the Puock theme.
 * Version: 1.0.0
 *
 * @package PostRelatedReading
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Series hubs used to group related posts. Order is the matching priority.
 *
 * @return array<int, array{title:string,type:string,slug:string,icon:string,hubSlug?:string}>
 */
function postRelatedReadingSeriesSpecs(): array {
	return array(
		array(
			'title'   => '歌曲推薦',
			'type'    => 'category',
			'slug'    => 'song-recommendations',
			'hubSlug' => 'lyrics-guide',
			'icon'    => 'fa-solid fa-music',
		),
		array(
			'title' => '音樂',
			'type'  => 'category',
			'slug'  => 'music',
			'icon'  => 'fa-solid fa-headphones',
		),
		array(
			'title'   => '本地 AI',
			'type'    => 'tag',
			'slug'    => 'local-ai',
			'hubSlug' => 'jetson-local-ai',
			'icon'    => 'fa-solid fa-microchip',
		),
		array(
			'title' => '遊戲伺服器',
			'type'  => 'category',
			'slug'  => 'game',
			'icon'  => 'fa-solid fa-gamepad',
		),
		array(
			'title'   => '台股盤後',
			'type'    => 'category',
			'slug'    => 'stock-views',
			'hubSlug' => 'taiwan-stock-watch',
			'icon'    => 'fa-solid fa-chart-line',
		),
	);
}

/**
 * Prefer a topic hub page when it exists, otherwise the series archive.
 *
 * @param array{title:string,type:string,slug:string,icon:string,hubSlug?:string} $spec
 */
function postRelatedReadingHubUrl( array $spec, WP_Term $term ): string {
	if ( isset( $spec['hubSlug'] ) && is_string( $spec['hubSlug'] ) && $spec['hubSlug'] !== '' ) {
		$hubPage = get_page_by_path( $spec['hubSlug'] );
		if ( $hubPage instanceof WP_Post ) {
			$hubUrl = get_permalink( $hubPage );
			if ( is_string( $hubUrl ) && $hubUrl !== '' ) {
				return $hubUrl;
			}
			error_log( 'Post related reading: hub permalink is empty for ' . $spec['hubSlug'] );
		}
	}

	$archiveUrl = get_term_link( $term );
	if ( is_wp_error( $archiveUrl ) || ! is_string( $archiveUrl ) || $archiveUrl === '' ) {
		return '';
	}
	return $archiveUrl;
}

/**
 * Whether related-reading UI should run on this request.
 */
function postRelatedReadingShouldRender(): bool {
	if ( is_admin() || is_feed() || is_embed() ) {
		return false;
	}
	if ( ! is_singular( 'post' ) ) {
		return false;
	}
	return true;
}

/**
 * Resolve the series hub for a post, including sibling posts ordered by date.
 *
 * @return array{title:string,icon:string,archiveUrl:string,posts:array<int,WP_Post>,currentIndex:int}|null
 */
function postRelatedReadingResolveSeries( int $postId ): ?array {
	if ( $postId <= 0 ) {
		error_log( 'Post related reading: refused to resolve a series for an invalid post ID.' );
		return null;
	}

	foreach ( postRelatedReadingSeriesSpecs() as $spec ) {
		$taxonomy = $spec['type'] === 'tag' ? 'post_tag' : 'category';
		if ( ! has_term( $spec['slug'], $taxonomy, $postId ) ) {
			continue;
		}

		$term = get_term_by( 'slug', $spec['slug'], $taxonomy );
		if ( ! $term instanceof WP_Term ) {
			error_log( 'Post related reading: series term is missing for ' . $spec['slug'] );
			continue;
		}

		$archiveUrl = postRelatedReadingHubUrl( $spec, $term );
		if ( $archiveUrl === '' ) {
			error_log( 'Post related reading: series archive URL is missing for ' . $spec['slug'] );
			continue;
		}

		$query = new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 20,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'orderby'             => 'date',
				'order'               => 'ASC',
				'tax_query'           => array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => array( (int) $term->term_id ),
					),
				),
			)
		);

		$posts = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$posts[] = $post;
			}
		}
		wp_reset_postdata();

		$currentIndex = null;
		foreach ( $posts as $index => $post ) {
			if ( (int) $post->ID === $postId ) {
				$currentIndex = $index;
				break;
			}
		}
		if ( $currentIndex === null ) {
			error_log( 'Post related reading: current post is missing from series query for ' . $spec['slug'] );
			return null;
		}

		return array(
			'title'        => $spec['title'],
			'icon'         => $spec['icon'],
			'archiveUrl'   => $archiveUrl,
			'posts'        => $posts,
			'currentIndex' => $currentIndex,
		);
	}

	return null;
}

/**
 * Sibling posts around the current article, newest first, excluding the current post.
 *
 * @param array{title:string,icon:string,archiveUrl:string,posts:array<int,WP_Post>,currentIndex:int} $series
 * @return array<int, WP_Post>
 */
function postRelatedReadingSiblingPosts( array $series, int $limit = 3 ): array {
	$currentIndex = $series['currentIndex'];
	$posts        = $series['posts'];
	$picked       = array();
	$seenIds      = array();

	$adjacentIndexes = array( $currentIndex - 1, $currentIndex + 1 );
	foreach ( $adjacentIndexes as $index ) {
		if ( ! isset( $posts[ $index ] ) ) {
			continue;
		}
		$picked[]                     = $posts[ $index ];
		$seenIds[ (int) $posts[ $index ]->ID ] = true;
	}

	for ( $index = count( $posts ) - 1; $index >= 0; $index-- ) {
		if ( count( $picked ) >= $limit ) {
			break;
		}
		if ( $index === $currentIndex ) {
			continue;
		}
		$postId = (int) $posts[ $index ]->ID;
		if ( isset( $seenIds[ $postId ] ) ) {
			continue;
		}
		$picked[]           = $posts[ $index ];
		$seenIds[ $postId ] = true;
	}

	return $picked;
}

/**
 * Enqueue related-reading styles on single posts.
 */
function postRelatedReadingEnqueueAssets(): void {
	if ( ! postRelatedReadingShouldRender() ) {
		return;
	}
	if ( ! wp_style_is( 'puock', 'enqueued' ) && ! wp_style_is( 'puock', 'registered' ) ) {
		error_log( 'Post related reading: Puock stylesheet is not available; related-reading CSS was not attached.' );
		return;
	}

	$css = <<<'CSS'
.related-reading{margin:0 0 1.25em;padding:14px 16px 12px;border-radius:var(--pk-g-box-radius);background:var(--pk-bg-box-content);border-left:3px solid var(--pk-c-primary)}
.related-reading--footer{margin:1.6em 0 0}
.related-reading__kicker{display:flex;align-items:center;gap:8px;margin:0 0 10px;font-size:12px;letter-spacing:.08em;color:var(--pk-c-sub)}
.related-reading__kicker i{color:var(--pk-c-primary)}
.related-reading__kicker a{color:inherit}
.related-reading__kicker a:hover{color:var(--pk-c-primary)}
.related-reading__nav{display:grid;gap:8px}
.related-reading__nav--single{grid-template-columns:1fr}
.related-reading__link{display:flex;gap:10px;align-items:flex-start;padding:8px 10px;border-radius:8px;color:inherit;background:var(--pk-bg-box);min-height:44px}
.related-reading__link:hover{color:inherit;background:rgba(28,96,243,.08)}
.related-reading__dir{flex:0 0 auto;margin-top:2px;font-size:11px;letter-spacing:.06em;color:var(--pk-c-primary)}
.related-reading__title{margin:0;font-size:14px;line-height:1.55;font-weight:600}
.related-reading__cards{display:grid;gap:10px}
.related-reading__card{display:grid;grid-template-columns:96px minmax(0,1fr);gap:12px;align-items:center;padding:0;border-radius:10px;overflow:hidden;color:inherit;background:var(--pk-bg-box);min-height:72px}
.related-reading__card:hover{color:inherit;background:rgba(28,96,243,.08)}
.related-reading__thumb{display:block;width:96px;height:72px;object-fit:cover;background:rgba(28,96,243,.12)}
.related-reading__thumb--empty{display:flex;align-items:center;justify-content:center;color:var(--pk-c-primary);font-size:16px}
.related-reading__body{padding:8px 12px 8px 0;min-width:0}
.related-reading__card-title{margin:0;font-size:14px;line-height:1.5;font-weight:600}
.related-reading__meta{margin:4px 0 0;font-size:12px;color:var(--pk-c-sub)}
.related-reading__more{display:inline-flex;align-items:center;gap:6px;margin:10px 0 0;font-size:13px}
@media (min-width:768px){
.related-reading__nav{grid-template-columns:1fr 1fr}
.related-reading__nav--single{grid-template-columns:1fr}
.related-reading__cards{grid-template-columns:1fr 1fr}
.related-reading__cards--single{grid-template-columns:1fr}
}
@media (prefers-reduced-motion:reduce){
.related-reading__link,.related-reading__card{transition:none}
}
CSS;
	wp_add_inline_style( 'puock', $css );
}

add_action( 'wp_enqueue_scripts', 'postRelatedReadingEnqueueAssets', 20 );

/**
 * Prepend previous/next series links at the top of the article.
 *
 * @param string $content Post HTML.
 */
function postRelatedReadingPrependSeriesNav( string $content ): string {
	if ( ! postRelatedReadingShouldRender() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$postId = get_the_ID();
	if ( ! is_int( $postId ) && ! is_numeric( $postId ) ) {
		error_log( 'Post related reading: current post ID is missing while building series navigation.' );
		return $content;
	}

	$series = postRelatedReadingResolveSeries( (int) $postId );
	if ( $series === null ) {
		return $content;
	}

	$currentIndex = $series['currentIndex'];
	$prevPost     = $series['posts'][ $currentIndex - 1 ] ?? null;
	$nextPost     = $series['posts'][ $currentIndex + 1 ] ?? null;
	if ( ! $prevPost instanceof WP_Post && ! $nextPost instanceof WP_Post ) {
		return $content;
	}

	$hasBothNeighbors = $prevPost instanceof WP_Post && $nextPost instanceof WP_Post;
	$navHtml          = postRelatedReadingRenderSeriesNav( $series, $prevPost, $nextPost, $hasBothNeighbors );
	if ( $navHtml === '' ) {
		return $content;
	}

	return $navHtml . $content;
}

add_filter( 'the_content', 'postRelatedReadingPrependSeriesNav', 12 );

/**
 * Print related-reading cards after the article body.
 */
function postRelatedReadingRenderCards(): void {
	if ( ! postRelatedReadingShouldRender() ) {
		return;
	}

	$postId = get_the_ID();
	if ( ! is_int( $postId ) && ! is_numeric( $postId ) ) {
		error_log( 'Post related reading: current post ID is missing while rendering related cards.' );
		return;
	}

	$series = postRelatedReadingResolveSeries( (int) $postId );
	if ( $series === null ) {
		return;
	}

	$siblings = postRelatedReadingSiblingPosts( $series, 3 );
	if ( $siblings === array() ) {
		return;
	}

	$cardCountClass = count( $siblings ) === 1 ? ' related-reading__cards--single' : '';
	?>
	<nav class="related-reading related-reading--footer no-indent" aria-label="<?php echo esc_attr( '延伸閱讀：' . $series['title'] ); ?>">
		<p class="related-reading__kicker">
			<i class="<?php echo esc_attr( $series['icon'] ); ?>" aria-hidden="true"></i>
			<span>延伸閱讀 · <?php echo esc_html( $series['title'] ); ?></span>
		</p>
		<div class="related-reading__cards<?php echo esc_attr( $cardCountClass ); ?>">
			<?php foreach ( $siblings as $sibling ) : ?>
				<?php echo postRelatedReadingRenderCard( $sibling, $series['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>
		</div>
		<a class="related-reading__more a-link" href="<?php echo esc_url( $series['archiveUrl'] ); ?>">
			看全部<?php echo esc_html( $series['title'] ); ?>
			<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
		</a>
	</nav>
	<?php
}

add_action( 'pk_post_content_footer', 'postRelatedReadingRenderCards' );

/**
 * Build previous/next series navigation HTML.
 *
 * @param array{title:string,icon:string,archiveUrl:string,posts:array<int,WP_Post>,currentIndex:int} $series
 */
function postRelatedReadingRenderSeriesNav( array $series, ?WP_Post $prevPost, ?WP_Post $nextPost, bool $hasBothNeighbors ): string {
	$navClass = $hasBothNeighbors ? 'related-reading__nav' : 'related-reading__nav related-reading__nav--single';
	ob_start();
	?>
	<nav class="related-reading related-reading--series no-indent" aria-label="<?php echo esc_attr( '本系列：' . $series['title'] ); ?>">
		<p class="related-reading__kicker">
			<i class="<?php echo esc_attr( $series['icon'] ); ?>" aria-hidden="true"></i>
			<a href="<?php echo esc_url( $series['archiveUrl'] ); ?>">本系列 · <?php echo esc_html( $series['title'] ); ?></a>
		</p>
		<div class="<?php echo esc_attr( $navClass ); ?>">
			<?php if ( $prevPost instanceof WP_Post ) : ?>
				<a class="related-reading__link" href="<?php echo esc_url( get_permalink( $prevPost ) ); ?>">
					<span class="related-reading__dir">上一篇</span>
					<span class="related-reading__title"><?php echo esc_html( get_the_title( $prevPost ) ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $nextPost instanceof WP_Post ) : ?>
				<a class="related-reading__link" href="<?php echo esc_url( get_permalink( $nextPost ) ); ?>">
					<span class="related-reading__dir">下一篇</span>
					<span class="related-reading__title"><?php echo esc_html( get_the_title( $nextPost ) ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</nav>
	<?php
	$html = ob_get_clean();
	if ( ! is_string( $html ) || $html === '' ) {
		error_log( 'Post related reading: failed to render series navigation HTML.' );
		return '';
	}
	return $html;
}

/**
 * Build one related-reading card.
 */
function postRelatedReadingRenderCard( WP_Post $post, string $icon ): string {
	$permalink = get_permalink( $post );
	if ( ! is_string( $permalink ) || $permalink === '' ) {
		error_log( 'Post related reading: permalink is empty for post ' . $post->ID );
		return '';
	}

	$title = get_the_title( $post );
	if ( ! is_string( $title ) || $title === '' ) {
		error_log( 'Post related reading: title is empty for post ' . $post->ID );
		return '';
	}

	$thumbnail = get_the_post_thumbnail(
		$post,
		'medium',
		array(
			'class'    => 'related-reading__thumb',
			'alt'      => '',
			'loading'  => 'lazy',
			'decoding' => 'async',
		)
	);

	$dateLabel = get_the_date( 'Y/m/d', $post );
	if ( ! is_string( $dateLabel ) || $dateLabel === '' ) {
		error_log( 'Post related reading: date is empty for post ' . $post->ID );
		$dateLabel = '';
	}

	ob_start();
	?>
	<a class="related-reading__card" href="<?php echo esc_url( $permalink ); ?>">
		<?php if ( is_string( $thumbnail ) && $thumbnail !== '' ) : ?>
			<?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
			<span class="related-reading__thumb related-reading__thumb--empty" aria-hidden="true">
				<i class="<?php echo esc_attr( $icon ); ?>"></i>
			</span>
		<?php endif; ?>
		<span class="related-reading__body">
			<span class="related-reading__card-title"><?php echo esc_html( $title ); ?></span>
			<?php if ( $dateLabel !== '' ) : ?>
				<span class="related-reading__meta"><?php echo esc_html( $dateLabel ); ?></span>
			<?php endif; ?>
		</span>
	</a>
	<?php
	$html = ob_get_clean();
	if ( ! is_string( $html ) || $html === '' ) {
		error_log( 'Post related reading: failed to render card HTML for post ' . $post->ID );
		return '';
	}
	return $html;
}
