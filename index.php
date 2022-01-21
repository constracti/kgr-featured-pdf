<?php

/*
 * Plugin Name: KGR Featured PDF
 * Plugin URI: https://github.com/constracti/kgr-featured-pdf
 * Description: Set the post featured image from the thumbnail of a PDF file.
 * Version: 1.0.3
 * Requires at least: 4.7.0
 * Requires PHP: 7.0
 * Author: constracti
 * Author URI: https://github.com/constracti
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: kgr-featured-pdf
 */

if ( !defined( 'ABSPATH' ) )
	exit;

final class KGR_Featured_PDF {

	public static function dir( string $dir ): string {
		return plugin_dir_path( __FILE__ ) . $dir;
	}

	public static function url( string $url ): string {
		return plugin_dir_url( __FILE__ ) . $url;
	}

	public static function version(): string {
		$plugin_data = get_plugin_data( __FILE__ );
		return $plugin_data['Version'];
	}
}

add_action( 'add_meta_boxes', function( string $post_type ): void {
	if ( !in_array( $post_type, [ 'page', 'post' ], TRUE ) )
		return;
	$screen = get_current_screen();
	if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() )
		return;
	$title = esc_html__( 'Featured PDF', 'kgr-featured-pdf' );
	add_meta_box( 'kgr-featured-pdf', $title, function( WP_Post $post ): void {
?>
<input type="hidden" id="kgr-featured-pdf-metabox-id" name="kgr_featured_pdf" />
<div id="kgr-featured-pdf-metabox-img"></div>
<div><a id="kgr-featured-pdf-metabox-show" href="#"><?= esc_html__( 'Set featured PDF', 'kgr-featured-pdf' ) ?></a></div>
<?php
	}, NULL, 'side' );
} );

add_action( 'admin_enqueue_scripts', function( string $hook_suffix ): void {
	if ( $hook_suffix !== 'post.php' && $hook_suffix !== 'post-new.php' )
		return;
	$screen = get_current_screen();
	if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() )
		return;
	wp_enqueue_media();
	wp_register_script( 'kgr-featured-pdf-metabox', KGR_Featured_PDF::url( 'metabox.js' ), [ 'jquery' ], KGR_Featured_PDF::version() );
	wp_localize_script( 'kgr-featured-pdf-metabox', 'kgr_featured_pdf', [
		'frame_title' => esc_html__( 'Featured PDF', 'kgr-featured-pdf' ),
	] );
	wp_enqueue_script( 'kgr-featured-pdf-metabox' );
} );

add_action( 'save_post', function( int $post_ID, WP_Post $post, bool $update ): void {
	if ( $post->post_type !== 'post' && $post->post_type !== 'page' )
		return;
	if ( !array_key_exists( 'kgr_featured_pdf', $_POST ) )
		return;
	$pdf = $_POST['kgr_featured_pdf'];
	if ( $pdf === '' )
		return;
	$pdf = get_post( $pdf );
	if ( is_null( $pdf ) )
		wp_die( 'kgr_featured_pdf: not valid' );
	if ( $pdf->post_mime_type !== 'application/pdf' )
		wp_die( 'kgr_featured_pdf: not valid' );
	$url = wp_get_attachment_url( $pdf->ID );
	$upload = wp_upload_dir();
	if ( substr( $url, 0, strlen( $upload['baseurl'] ) ) !== $upload['baseurl'] )
		wp_die( 'attachment_url: not valid' );
	$dir = $upload['basedir'] . substr( $url, strlen( $upload['baseurl'] ) );
	$dir = trailingslashit( dirname( $dir ) );
	$metadata = wp_get_attachment_metadata( $pdf->ID );
	if ( $metadata === FALSE )
		wp_die( 'attachment metadata: not defined' );
	if ( !array_key_exists( 'sizes', $metadata ) )
		wp_die( 'attachment metadata sizes: not defined' );
	if ( !array_key_exists( 'full', $metadata['sizes'] ) )
		wp_die( 'attachment metadata sizes full: not defined' );
	$name = $metadata['sizes']['full']['file'];
	$source = $dir . $name;
	if ( !file_exists( $source ) )
		wp_die( 'source: not valid' );
	$ext = '.jpg';
	if ( mb_substr( $name, mb_strlen( $name ) - mb_strlen( $ext ) ) !== $ext )
		wp_die( 'name: not valid' );
	$name = mb_substr( $name, 0, mb_strlen( $name ) - mb_strlen( $ext ) );
	if ( mb_substr( $name, mb_strlen( $name ) - 4 ) === '-pdf' )
		$name = mb_substr( $name, 0, mb_strlen( $name ) - 4 );
	$num = NULL;
	while ( TRUE ) {
		$dest = $dir . $name;
		if ( is_null( $num ) ) {
			$num = 0;
		} else {
			$dest .= '-' . $num;
			$num++;
		}
		$dest .= $ext;
		if ( !file_exists( $dest ) )
			break;
	}
	if ( !copy( $source, $dest ) )
		wp_die( 'copy: failure' );
	$attachment = [
		'post_mime_type' => $metadata['sizes']['full']['mime-type'],
		'post_title' => $name,
		'post_status' => 'inherit',
	];
	$attach_id = wp_insert_attachment( $attachment, $dest, $post->ID );
	if ( !$attach_id )
		wp_die( 'wp_insert_attachment: failure' );
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	$attach_data = wp_generate_attachment_metadata( $attach_id, $dest );
	wp_update_attachment_metadata( $attach_id, $attach_data );
	update_post_meta( $attach_id, '_wp_attachment_image_alt', $name );
	if ( !set_post_thumbnail( $post, $attach_id ) )
		wp_die( 'set_post_thumbnail: failure' );
}, 10, 3 );
