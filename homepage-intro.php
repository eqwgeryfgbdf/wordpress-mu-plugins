<?php
/**
 * Plugin Name: Homepage Intro
 * Description: Renders an animated personal signature on the blog homepage.
 * Version: 1.2.0
 *
 * @package HomepageIntro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the homepage intro should render on this request.
 */
function homepageIntroShouldRender(): bool {
	if ( is_admin() ) {
		return false;
	}
	if ( ! is_front_page() ) {
		return false;
	}
	if ( is_paged() ) {
		return false;
	}
	return true;
}

/**
 * Print the homepage signature card before the post list.
 *
 * @param string      $slug Template slug.
 * @param string|null $name Template name.
 */
function homepageIntroMaybeRender( string $slug, $name ): void {
	static $hasRendered = false;
	if ( $hasRendered ) {
		return;
	}
	if ( $slug !== 'templates/module' || $name !== 'posts' ) {
		return;
	}
	if ( ! homepageIntroShouldRender() ) {
		return;
	}
	$hasRendered = true;
	homepageIntroRender();
}

add_action( 'get_template_part_templates/module', 'homepageIntroMaybeRender', 10, 2 );

/**
 * Keep legacy URLs working after page and category merges.
 */
function homepageIntroRedirectLegacyUrls(): void {
	$requestUri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	if ( $requestUri === '' ) {
		return;
	}
	$path = (string) wp_parse_url( $requestUri, PHP_URL_PATH );

	if ( is_404() && preg_match( '#/sample-page/?$#', $path ) ) {
		homepageIntroRedirectToPage( 'about', 'sample-page' );
		return;
	}

	$songHubTargets = array(
		'#/category/lyrics-appreciation/?$#',
		'#/index\.php/category/lyrics-appreciation/?$#',
		'#/category/music/?$#',
		'#/index\.php/category/music/?$#',
	);
	foreach ( $songHubTargets as $pattern ) {
		if ( preg_match( $pattern, $path ) ) {
			homepageIntroRedirectToPage( 'lyrics-guide', 'lyrics-appreciation category' );
			return;
		}
	}
}

/**
 * 301 to a published page by path, or log why the redirect was skipped.
 */
function homepageIntroRedirectToPage( string $pageSlug, string $fromLabel ): void {
	$targetPage = get_page_by_path( $pageSlug );
	if ( ! $targetPage instanceof WP_Post ) {
		error_log( 'Homepage intro: cannot redirect ' . $fromLabel . ' because page ' . $pageSlug . ' is missing.' );
		return;
	}
	$targetUrl = get_permalink( $targetPage );
	if ( ! is_string( $targetUrl ) || $targetUrl === '' ) {
		error_log( 'Homepage intro: permalink is empty for ' . $pageSlug . '; ' . $fromLabel . ' redirect skipped.' );
		return;
	}
	wp_safe_redirect( $targetUrl, 301 );
	exit;
}

add_action( 'template_redirect', 'homepageIntroRedirectLegacyUrls', 1 );

/**
 * Signature lines shown by the typewriter on the homepage.
 *
 * @return array<int, string>
 */
function homepageIntroSignatureLines(): array {
	return array(
		'天上有日月和星辰，地上沒有異鄉人',
		'我被藏在你的過去，我籍籍無名',
		'我忘了怎麼溝通，甚至忘了怎麼寫字',
		'研究還沒做完，歌也還沒聽完',
		'花開忘憂，願你無憂'
	);
}

/**
 * Topic entry cards shown under the homepage signature.
 *
 * @return array<int, array{title:string,description:string,url:string,count:int,icon:string}>
 */
function homepageIntroTopicCards(): array {
	$specs = array(
		array(
			'title'       => '歌曲推薦',
			'description' => '從周深、華晨宇到 MC HotDog，把聽過的歌再讀一次。',
			'icon'        => 'fa-solid fa-music',
			'hubSlug'     => 'lyrics-guide',
			'type'        => 'category',
			'slug'        => 'song-recommendations',
		),
		array(
			'title'       => '本地 AI',
			'description' => 'Jetson Orin NX 上的 llama.cpp 與邊緣部署。',
			'icon'        => 'fa-solid fa-microchip',
			'hubSlug'     => 'jetson-local-ai',
			'type'        => 'tag',
			'slug'        => 'local-ai',
		),
		array(
			'title'       => '遊戲伺服器',
			'description' => 'CS:S 死鬥、自架與長期養成型伺服器。',
			'icon'        => 'fa-solid fa-gamepad',
			'type'        => 'category',
			'slug'        => 'game',
		),
		array(
			'title'       => '台股盤後',
			'description' => '盤勢、法人與族群強弱的當日記錄。',
			'icon'        => 'fa-solid fa-chart-line',
			'hubSlug'     => 'taiwan-stock-watch',
			'type'        => 'category',
			'slug'        => 'stock-views',
		),
	);

	$cards = array();
	foreach ( $specs as $spec ) {
		$resolved = homepageIntroResolveTopicCard( $spec );
		if ( $resolved === null ) {
			continue;
		}
		$cards[] = $resolved;
	}

	return $cards;
}

/**
 * Resolve a homepage topic card to a hub page when one exists.
 *
 * @param array{title:string,description:string,icon:string,type:string,slug:string,hubSlug?:string} $spec
 * @return array{title:string,description:string,url:string,count:int,icon:string}|null
 */
function homepageIntroResolveTopicCard( array $spec ): ?array {
	$taxonomy = $spec['type'] === 'tag' ? 'post_tag' : 'category';
	$term     = get_term_by( 'slug', $spec['slug'], $taxonomy );
	if ( ! $term instanceof WP_Term ) {
		error_log( 'Homepage intro: topic card term missing for ' . $spec['slug'] );
		return null;
	}

	$url = '';
	if ( isset( $spec['hubSlug'] ) && is_string( $spec['hubSlug'] ) && $spec['hubSlug'] !== '' ) {
		$hubPage = get_page_by_path( $spec['hubSlug'] );
		if ( $hubPage instanceof WP_Post ) {
			$hubUrl = get_permalink( $hubPage );
			if ( is_string( $hubUrl ) && $hubUrl !== '' ) {
				$url = $hubUrl;
			} else {
				error_log( 'Homepage intro: hub permalink is empty for ' . $spec['hubSlug'] );
			}
		}
	}

	if ( $url === '' ) {
		$termUrl = get_term_link( $term );
		if ( is_wp_error( $termUrl ) || ! is_string( $termUrl ) || $termUrl === '' ) {
			error_log( 'Homepage intro: topic card URL missing for ' . $spec['slug'] );
			return null;
		}
		$url = $termUrl;
	}

	return array(
		'title'       => $spec['title'],
		'description' => $spec['description'],
		'url'         => $url,
		'count'       => (int) $term->count,
		'icon'        => $spec['icon'],
	);
}

/**
 * Enqueue homepage signature styles and typewriter script.
 */
function homepageIntroEnqueueAssets(): void {
	if ( ! homepageIntroShouldRender() ) {
		return;
	}
	if ( ! wp_style_is( 'puock', 'enqueued' ) && ! wp_style_is( 'puock', 'registered' ) ) {
		error_log( 'Homepage intro: Puock stylesheet is not available; signature CSS was not attached.' );
		return;
	}

	$css = <<<'CSS'
.homepage-signature{position:relative;overflow:hidden;padding:28px 28px 24px}
.homepage-signature::before{content:"";position:absolute;inset:0 auto auto 0;width:120px;height:120px;background:radial-gradient(circle at 30% 30%,rgba(28,96,243,.18),transparent 70%);pointer-events:none}
.homepage-signature__kicker{position:relative;margin:0 0 10px;letter-spacing:.18em;font-size:12px;text-transform:uppercase}
.homepage-signature__quote{position:relative;display:flex;align-items:baseline;justify-content:center;gap:.15em;margin:0;min-height:2.4em;font-size:clamp(1.35rem,2.4vw,1.85rem);line-height:1.6;font-weight:600;text-align:center}
.homepage-signature__mark{flex:0 0 auto;color:var(--pk-c-primary);opacity:.55;font-size:1.15em;transform:translateY(.06em)}
.homepage-signature__text{min-width:0}
.homepage-signature__cursor{display:inline-block;width:.08em;height:1em;margin-left:.08em;background:var(--pk-c-primary);border-radius:1px;vertical-align:-.08em;animation:homepageSignatureBlink 1s steps(1) infinite}
.homepage-signature__underline{display:block;width:min(220px,46%);height:2px;margin:16px auto 0;border-radius:2px;background:linear-gradient(90deg,transparent,var(--pk-c-primary),transparent);transform-origin:center;animation:homepageSignatureDraw 2.8s ease-in-out infinite}
.homepage-signature__mobile-note{position:relative;margin:14px 0 0;text-align:center;line-height:1.7}
.homepage-signature__mobile-note a{margin-left:.35em}
.homepage-topics{margin-top:0}
.homepage-topics__grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.homepage-topics__card{display:flex;flex-direction:column;gap:6px;padding:14px 14px 12px;border-radius:var(--pk-g-box-radius);background:var(--pk-bg-box-content);color:inherit;min-height:100%;transition:transform .2s ease,background-color .2s ease}
.homepage-topics__card:hover{color:inherit;background:rgba(28,96,243,.08);transform:translateY(-1px)}
.homepage-topics__icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:rgba(28,96,243,.12);color:var(--pk-c-primary);font-size:13px}
.homepage-topics__title{margin:0;font-size:15px;font-weight:600;line-height:1.4}
.homepage-topics__desc{margin:0;font-size:12px;line-height:1.6;color:var(--pk-c-sub)}
.homepage-topics__count{margin:0;font-size:11px;letter-spacing:.04em;color:var(--pk-c-primary)}
@keyframes homepageSignatureBlink{0%,49%{opacity:1}50%,100%{opacity:0}}
@keyframes homepageSignatureDraw{0%,100%{transform:scaleX(.35);opacity:.45}50%{transform:scaleX(1);opacity:1}}
@media (min-width:768px){
.homepage-topics__grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
}
@media (prefers-reduced-motion:reduce){
.homepage-signature__cursor,.homepage-signature__underline{animation:none}
.homepage-signature__underline{transform:none;opacity:.7}
.homepage-topics__card{transition:none}
.homepage-topics__card:hover{transform:none}
}
CSS;
	wp_add_inline_style( 'puock', $css );

	if ( ! wp_script_is( 'puock', 'enqueued' ) && ! wp_script_is( 'puock', 'registered' ) ) {
		error_log( 'Homepage intro: Puock script is not available; signature animation was not attached.' );
		return;
	}

	$js = <<<'JS'
(function () {
	var homepageSignatureTimer = 0;

	function homepageSignaturePrefersReducedMotion() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function homepageSignatureClearTimer() {
		if (homepageSignatureTimer) {
			window.clearTimeout(homepageSignatureTimer);
			homepageSignatureTimer = 0;
		}
	}

	function homepageSignatureSchedule(callback, delayMs) {
		homepageSignatureClearTimer();
		homepageSignatureTimer = window.setTimeout(callback, delayMs);
	}

	function homepageSignatureInit() {
		var root = document.querySelector('[data-homepage-signature-root]');
		if (!(root instanceof HTMLElement)) {
			return;
		}
		var textEl = root.querySelector('[data-homepage-signature-text]');
		if (!(textEl instanceof HTMLElement)) {
			console.error('Homepage signature: text target is missing.');
			return;
		}

		var lines;
		try {
			lines = JSON.parse(root.getAttribute('data-homepage-signature-root') || '[]');
		} catch (error) {
			console.error('Homepage signature: could not parse signature lines.', error);
			return;
		}
		if (!Array.isArray(lines) || lines.length === 0) {
			console.error('Homepage signature: no lines to display.');
			return;
		}

		var lineIndex = 0;
		var charIndex = 0;
		var isDeleting = false;
		var reducedMotion = homepageSignaturePrefersReducedMotion();

		function homepageSignatureTick() {
			var currentLine = String(lines[lineIndex] || '');
			if (reducedMotion) {
				textEl.textContent = currentLine;
				lineIndex = (lineIndex + 1) % lines.length;
				homepageSignatureSchedule(homepageSignatureTick, 3200);
				return;
			}

			if (!isDeleting) {
				charIndex = Math.min(currentLine.length, charIndex + 1);
				textEl.textContent = currentLine.slice(0, charIndex);
				if (charIndex >= currentLine.length) {
					isDeleting = true;
					homepageSignatureSchedule(homepageSignatureTick, 2200);
					return;
				}
				homepageSignatureSchedule(homepageSignatureTick, 90);
				return;
			}

			charIndex = Math.max(0, charIndex - 1);
			textEl.textContent = currentLine.slice(0, charIndex);
			if (charIndex <= 0) {
				isDeleting = false;
				lineIndex = (lineIndex + 1) % lines.length;
				homepageSignatureSchedule(homepageSignatureTick, 380);
				return;
			}
			homepageSignatureSchedule(homepageSignatureTick, 42);
		}

		homepageSignatureClearTimer();
		if (reducedMotion) {
			textEl.textContent = String(lines[0] || '');
			lineIndex = lines.length > 1 ? 1 : 0;
			homepageSignatureSchedule(homepageSignatureTick, 3200);
			return;
		}
		charIndex = String(lines[0] || '').length;
		textEl.textContent = String(lines[0] || '');
		isDeleting = true;
		homepageSignatureSchedule(homepageSignatureTick, 1800);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', homepageSignatureInit);
	} else {
		homepageSignatureInit();
	}
	document.addEventListener('instantclick:change', homepageSignatureInit);
})();
JS;
	wp_add_inline_script( 'puock', $js, 'after' );
}

add_action( 'wp_enqueue_scripts', 'homepageIntroEnqueueAssets', 20 );

/**
 * Render the homepage personal-signature card.
 */
function homepageIntroRender(): void {
	$signatureLines = homepageIntroSignatureLines();
	if ( $signatureLines === array() ) {
		error_log( 'Homepage intro: signature lines are empty; card was not rendered.' );
		return;
	}

	$signatureJson = wp_json_encode( $signatureLines, JSON_UNESCAPED_UNICODE );
	if ( ! is_string( $signatureJson ) || $signatureJson === '' ) {
		error_log( 'Homepage intro: failed to encode signature lines as JSON.' );
		$signatureJson = '[]';
	}

	$aboutPage = get_page_by_path( 'about' );
	$aboutUrl  = ( $aboutPage instanceof WP_Post ) ? get_permalink( $aboutPage ) : '';
	$firstLine = $signatureLines[0];
	?>
	<section
		class="p-block homepage-intro homepage-signature puock-text"
		data-homepage-signature-root="<?php echo esc_attr( $signatureJson ); ?>"
		aria-label="個性簽名"
	>
		<h1 class="visually-hidden">Roy 的部落格</h1>
		<p class="homepage-signature__kicker c-sub">個性簽名</p>
		<p class="homepage-signature__quote" aria-live="polite">
			<span class="homepage-signature__mark" aria-hidden="true">「</span>
			<span class="homepage-signature__text" data-homepage-signature-text><?php echo esc_html( $firstLine ); ?></span><span class="homepage-signature__cursor" aria-hidden="true"></span>
			<span class="homepage-signature__mark homepage-signature__mark--end" aria-hidden="true">」</span>
		</p>
		<span class="homepage-signature__underline" aria-hidden="true"></span>
		<p class="homepage-signature__mobile-note c-sub t-sm d-xl-none">
			研究、音樂與自架服務的筆記都在這裡。
			<?php if ( is_string( $aboutUrl ) && $aboutUrl !== '' ) : ?>
				<a href="<?php echo esc_url( $aboutUrl ); ?>">關於我</a>
			<?php endif; ?>
		</p>
	</section>
	<?php
	homepageIntroRenderTopicCards();
}

/**
 * Render the topic entry cards under the signature.
 */
function homepageIntroRenderTopicCards(): void {
	$cards = homepageIntroTopicCards();
	if ( $cards === array() ) {
		error_log( 'Homepage intro: no topic cards could be rendered.' );
		return;
	}
	?>
	<nav class="p-block homepage-topics puock-text" aria-label="主題入口">
		<div class="homepage-topics__grid">
			<?php foreach ( $cards as $card ) : ?>
				<a class="homepage-topics__card" href="<?php echo esc_url( $card['url'] ); ?>">
					<span class="homepage-topics__icon" aria-hidden="true"><i class="<?php echo esc_attr( $card['icon'] ); ?>"></i></span>
					<h2 class="homepage-topics__title"><?php echo esc_html( $card['title'] ); ?></h2>
					<p class="homepage-topics__desc"><?php echo esc_html( $card['description'] ); ?></p>
					<p class="homepage-topics__count"><?php echo esc_html( sprintf( '%d 篇', $card['count'] ) ); ?></p>
				</a>
			<?php endforeach; ?>
		</div>
	</nav>
	<?php
}
