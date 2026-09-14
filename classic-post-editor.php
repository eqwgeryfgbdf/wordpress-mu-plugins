<?php
/**
 * Plugin Name: Classic Post Editor
 * Description: Always use the classic Edit Post screen. Replaces the Classic Editor plugin.
 * Version: 1.0.0
 *
 * @package ClassicPostEditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disable the block editor for every post type.
 *
 * @param bool   $useBlockEditor Whether the post type uses the block editor.
 * @param string $postType       Post type slug.
 */
function classicPostEditorDisableBlockEditor( $useBlockEditor, $postType ): bool {
	if ( ! is_string( $postType ) || $postType === '' ) {
		error_log( 'Classic Post Editor: refused to decide the editor for an empty post type.' );
		return false;
	}
	return false;
}

add_filter( 'use_block_editor_for_post_type', 'classicPostEditorDisableBlockEditor', 100, 2 );
add_filter( 'use_block_editor_for_post', '__return_false', 100 );
add_filter( 'gutenberg_can_edit_post_type', '__return_false', 100 );

/**
 * Remove the old "Try Gutenberg" dashboard prompt.
 */
function classicPostEditorRemoveGutenbergNudge(): void {
	remove_action( 'try_gutenberg_panel', 'wp_try_gutenberg_panel' );
}

add_action( 'admin_init', 'classicPostEditorRemoveGutenbergNudge' );

/**
 * Keep the Privacy Policy help notice under the title on the classic screen.
 */
function classicPostEditorMovePrivacyNotice(): void {
	global $pagenow;

	if ( $pagenow !== 'post.php' ) {
		return;
	}
	if ( ! class_exists( 'WP_Privacy_Policy_Content' ) ) {
		return;
	}

	remove_action( 'admin_notices', array( 'WP_Privacy_Policy_Content', 'notice' ) );
	add_action( 'edit_form_after_title', array( 'WP_Privacy_Policy_Content', 'notice' ) );
}

add_action( 'admin_init', 'classicPostEditorMovePrivacyNotice' );

/**
 * Classic Edit Post CSS fixes for current WordPress admin chrome.
 */
function classicPostEditorPrintAdminFixes(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->base !== 'post' ) {
		return;
	}

	$clearSide = is_rtl() ? 'right' : 'left';
	echo '<style id="classic-post-editor-admin-fixes">'
		. '#major-publishing-actions{flex-wrap:wrap;}'
		. '_::-webkit-full-page-media,_:future,:root #post-body #postbox-container-2{clear:' . esc_attr( $clearSide ) . ';}'
		. '</style>' . "\n";
}

add_action( 'admin_print_styles', 'classicPostEditorPrintAdminFixes' );
