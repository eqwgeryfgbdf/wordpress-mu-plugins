<?php
/**
 * Homepage template with left and right sidebars.
 *
 * @package HomepageSidebars
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( pk_get_option( 'index_mode' ) === 'company' ) {
	get_template_part( 'templates/page', 'company' );
	get_footer();
	return;
}
?>

<div id="content" class="mt15 container homepage-dual-sidebars">
	<?php get_template_part( 'templates/box', 'global-top' ); ?>
	<div class="<?php echo esc_attr( homepageSidebarsLayoutClass() ); ?>">
		<?php homepageSidebarsRender( HOMEPAGE_SIDEBARS_LEFT_ID, '首頁左側欄', 'homepage-sidebar--left' ); ?>
		<div class="homepage-main <?php pk_open_box_animated( 'animated fadeInLeft' ); ?>">
			<?php if ( isset( $paged ) && $paged <= 1 ) : ?>
				<?php
				$homeBannerAva = pk_ava_home_banners();
				if ( is_array( $homeBannerAva ) && count( $homeBannerAva ) > 0 ) :
					?>
					<div class="<?php pk_open_box_animated( 'animated fadeInLeft' ); ?>">
						<?php get_template_part( 'templates/module', 'banners' ); ?>
					</div>
				<?php endif; ?>
				<?php if ( pk_get_option( 'index_mode' ) === 'cms' && pk_is_checked( 'cms_show_four_grid' ) ) : ?>
					<?php
					$cmsFourGridAva = pk_ava_cms_four_grid_items();
					if ( is_array( $cmsFourGridAva ) && count( $cmsFourGridAva ) > 0 ) :
						?>
						<div class="<?php pk_open_box_animated( 'animated fadeInLeft' ); ?>">
							<?php get_template_part( 'templates/module', 'four-grid' ); ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
			<div class="<?php pk_open_box_animated( 'animated fadeInLeft' ); ?> <?php echo pk_post_style_list() ? '' : 'pe-0 ps-0'; ?>">
				<div>
					<?php get_template_part( 'templates/module', 'posts' ); ?>
				</div>
			</div>
		</div>
		<?php homepageSidebarsRender( HOMEPAGE_SIDEBARS_RIGHT_ID, '首頁右側欄', 'homepage-sidebar--right' ); ?>
	</div>

	<?php get_template_part( 'templates/module', 'cms' ); ?>
	<?php get_template_part( 'templates/module', 'links' ); ?>
	<?php get_template_part( 'templates/box', 'global-bottom' ); ?>
	<?php dynamic_sidebar( 'index_bottom' ); ?>
</div>

<?php
get_footer();
