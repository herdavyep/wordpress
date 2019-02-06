<?php
/**
 * Organize Media Library by Folders
 * 
 * @package    Organize Media Library
 * @subpackage OrganizeMediaLibraryRegist registered in the database
/*  Copyright (c) 2015- Katsushi Kawamori (email : dodesyoswift312@gmail.com)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; version 2 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

$organizemedialibraryregist = new OrganizeMediaLibraryRegist();

class OrganizeMediaLibraryRegist {

	private $upload_dir;
	private $upload_path;

	/* ==================================================
	 * Construct
	 * @since	6.20
	 */
	public function __construct() {

		if (!class_exists('OrganizeMediaLibrary')){
			include_once dirname(__FILE__).'/OrganizeMediaLibrary.php';
		}

		$organizemedialibrary = new OrganizeMediaLibrary();
		list($this->upload_dir, $upload_url, $this->upload_path) = $organizemedialibrary->upload_dir_url_path();

		add_action( 'admin_init', array($this, 'register_settings'), 10 );
		add_action( 'admin_init', array($this, 'media_folder_taxonomies'), 11 );
		add_action( 'admin_head-upload.php', array($this, 'media_folder_term'), 12 );

	}

	/* ==================================================
	 * Settings register
	 * @since	1.0
	 */
	public function register_settings(){

		$dirs = array();
		if( strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && get_locale() === 'ja' ) { // Japanese Windows
			$character_code = 'CP932';
		} else {
			$character_code = 'UTF-8';
		}

		$wp_options_name = 'organizemedialibrary_settings'.'_'.get_current_user_id();

		if ( !get_option($wp_options_name) ) {
			if ( get_option('organizemedialibrary_settings') ) { // old settings
				$organizemedialibrary_settings = get_option('organizemedialibrary_settings');
				if ( array_key_exists( "character_code", $organizemedialibrary_settings ) ) {
					$character_code = $organizemedialibrary_settings['character_code'];
				}
				delete_option( 'organizemedialibrary_settings' );
			}
			$organizemedialibrary = new OrganizeMediaLibrary();
			$dirs = json_encode($organizemedialibrary->scan_dir($this->upload_dir));
		} else {
			$organizemedialibrary_settings = get_option($wp_options_name);
			$dirs = $this->get_dirs_json($organizemedialibrary_settings);
			if ( array_key_exists( "character_code", $organizemedialibrary_settings ) ) {
				$character_code = $organizemedialibrary_settings['character_code'];
			}
		}
		$organizemedialibrary_tbl = array(
						'dirs' => $dirs,
						'character_code' => $character_code
					);
		update_option( $wp_options_name, $organizemedialibrary_tbl );

	}

	/* ==================================================
	 * @param	array	$organizemedialibrary_settings
	 * @return	string	$dirs
	 * @since	6.39
	 */
	private function get_dirs_json($organizemedialibrary_settings) {

		if ( array_key_exists( "dirs", $organizemedialibrary_settings ) ) {
			if ( !empty($organizemedialibrary_settings['dirs']) ) {
				$dirs = $organizemedialibrary_settings['dirs'];
			} else {
				$organizemedialibrary = new OrganizeMediaLibrary();
				$dirs = json_encode($organizemedialibrary->scan_dir($this->upload_dir));
			}
		} else {
			$organizemedialibrary = new OrganizeMediaLibrary();
			$dirs = json_encode($organizemedialibrary->scan_dir($this->upload_dir));
		}

		return $dirs;

	}

	/* ==================================================
	 * Register Taxonomy
	 * @since	6.0
	 */
	public function media_folder_taxonomies() {

		$args = array(
			'hierarchical'			=> false,
			'label'					=> __('Folder', 'organize-media-library'),
			'show_ui'				=> false,
			'show_admin_column'		=> false,
			'update_count_callback'	=> '_update_generic_term_count',
			'query_var'				=> true,
			'rewrite'				=> true
		);

		register_taxonomy( 'media_folder', 'attachment', $args);

	}

	/* ==================================================
	 * Register Media Folder Term
	 * @since	6.0
	 */
	public function media_folder_term() {

		$wp_options_name = 'organizemedialibrary_settings'.'_'.get_current_user_id();
		$organizemedialibrary_settings = get_option($wp_options_name);
		$character_code = $organizemedialibrary_settings['character_code'];

		$organizemedialibrary = new OrganizeMediaLibrary();

		$dirs = json_decode($this->get_dirs_json($organizemedialibrary_settings),true);
		$wordpress_path = wp_normalize_path(ABSPATH);
		foreach ($dirs as $linkdir) {
			if ( strstr($linkdir, $wordpress_path ) ) {
				$linkdirenc = $organizemedialibrary->mb_utf8(str_replace($wordpress_path, '', $linkdir), $character_code);
				$linkdirenc = str_replace($this->upload_path, '', $linkdirenc);
			} else {
				$linkdirenc = $organizemedialibrary->mb_utf8(str_replace($this->upload_dir, "", $linkdir), $character_code);
			}

			$term_args = array(
						'slug' => 'oml-'.str_replace('/', '-', ltrim($linkdirenc, '/'))
					);
			$term = term_exists($linkdirenc, 'media_folder');
			if ( !$term ) {
				wp_insert_term( $linkdirenc, 'media_folder', $term_args );
			} else {
				wp_update_term( $term['term_id'], 'media_folder', $term_args );
			}
		}
		unset($organizemedialibrary);
		$term_args = array(
					'slug' => 'oml'
				);
		$term = term_exists('/', 'media_folder');
		if ( !$term ) {
			wp_insert_term( '/', 'media_folder', $term_args );
		} else {
			wp_update_term( $term['term_id'], 'media_folder', $term_args );
		}

		global $wpdb;
		$attachments = $wpdb->get_results("
						SELECT	ID
						FROM	$wpdb->posts
						WHERE	post_type = 'attachment'
						");
		foreach ( $attachments as $attachment ) {
			$wp_attached_file = get_post_meta($attachment->ID, '_wp_attached_file', TRUE);
			$filename = wp_basename($wp_attached_file);
			$foldername = '/'.untrailingslashit(str_replace($filename, '', $wp_attached_file));
			$terms = $wpdb->get_results($wpdb->prepare("
							SELECT	term_id
							FROM	$wpdb->terms
							WHERE	name = %s
							",$foldername
							),ARRAY_A);
			if ( $terms ) {
				$term_taxonomy_ids = wp_set_object_terms( intval($attachment->ID), intval($terms[0]['term_id']), 'media_folder' );
				if ( is_wp_error( $term_taxonomy_ids ) ) {
				} else {
				}
			}
		}

	}

}

?>