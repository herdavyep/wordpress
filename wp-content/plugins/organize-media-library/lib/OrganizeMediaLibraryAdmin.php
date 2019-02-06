<?php
/**
 * Organize Media Library by Folders
 * 
 * @package    Organize Media Library
 * @subpackage OrganizeMediaLibraryAdmin Main & Management screen
/*  Copyright (c) 2013- Katsushi Kawamori (email : dodesyoswift312@gmail.com)
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

$organizemedialibraryadmin = new OrganizeMediaLibraryAdmin();
add_action( 'admin_notices', array($organizemedialibraryadmin, 'notices') );

class OrganizeMediaLibraryAdmin {

	private $plugin_base_dir;
	private $plugin_base_url;
	private $upload_dir;
	private $upload_path;
	private $is_add_on_activate;

	/* ==================================================
	 * Construct
	 * @since	6.20
	 */
	public function __construct() {

		$this->plugin_base_dir = untrailingslashit(plugin_dir_path( __DIR__ ));
		$this->plugin_base_url = untrailingslashit(plugin_dir_url( __DIR__ ));

		if (!class_exists('OrganizeMediaLibrary')){
			include_once dirname(__FILE__).'/OrganizeMediaLibrary.php';
		}

		$organizemedialibrary = new OrganizeMediaLibrary();
		list($this->upload_dir, $upload_url, $this->upload_path) = $organizemedialibrary->upload_dir_url_path();

		add_filter( 'plugin_action_links', array($this, 'settings_link'), 10, 2 );
		add_action( 'admin_menu', array($this, 'add_pages') );
		add_action( 'admin_enqueue_scripts', array($this, 'load_custom_wp_admin_style') );
		add_filter( 'manage_media_columns', array($this, 'muc_column') );
		add_action( 'manage_media_custom_column', array($this, 'muc_value'), 12, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'add_folder_filter' ), 13 );
		add_action( 'admin_footer', array( $this, 'custom_bulk_admin_footer') );
		add_action( 'load-upload.php', array( $this, 'custom_bulk_action') );
		add_action( 'admin_notices', array( $this, 'custom_bulk_admin_notices') );
		add_action( 'wp_enqueue_media', array( $this, 'insert_media_custom_filter') );

	}

	/* ==================================================
	 * Add a "Settings" link to the plugins page
	 * @since	1.0
	 */
	public function settings_link( $links, $file ) {
		static $this_plugin;
		if ( empty($this_plugin) ) {
			$this_plugin = 'organize-media-library/organizemedialibrary.php';
		}
		if ( $file == $this_plugin ) {
			$links[] = '<a href="'.admin_url('upload.php?page=organizemedialibrary-settings').'">'.__('Settings').'</a>';
		}
			return $links;
	}

	/* ==================================================
	 * Settings page
	 * @since	1.0
	 */
	public function add_pages() {
		add_media_page(
				__('Make folder', 'organize-media-library').'&'.__('Settings'),
				__('Make folder', 'organize-media-library').'&'.__('Settings'),
				'upload_files',
				'organizemedialibrary-settings',
				array($this, 'settings_page')
		);

	}

	/* ==================================================
	 * Add Css and Script
	 * @since	2.23
	 */
	public function load_custom_wp_admin_style() {
		if ($this->is_my_plugin_screen()) {
			wp_enqueue_style( 'jquery-responsiveTabs', $this->plugin_base_url.'/css/responsive-tabs.css' );
			wp_enqueue_style( 'jquery-responsiveTabs-style', $this->plugin_base_url.'/css/style.css' );
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-responsiveTabs', $this->plugin_base_url.'/js/jquery.responsiveTabs.min.js' );
			wp_enqueue_script( 'organizemedialibrary-js', $this->plugin_base_url.'/js/jquery.organizemedialibrary.js', array('jquery') );
		}
	}

	/* ==================================================
	 * For only admin style
	 * @since	4.3
	 */
	private function is_my_plugin_screen() {
		$screen = get_current_screen();
		if (is_object($screen) && $screen->id == 'media_page_organizemedialibrary-settings') {
			return TRUE;
		} else if (is_object($screen) && $screen->id == 'upload') {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/* ==================================================
	 * Sub Menu
	 */
	public function settings_page() {

		if ( !current_user_can( 'upload_files' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		if( !empty($_POST) ) {
			$post_nonce_field = 'organizemedialibrary_tabs';
			if ( isset($_POST[$post_nonce_field]) && $_POST[$post_nonce_field] ) {
				if ( check_admin_referer( 'oml_settings', $post_nonce_field ) ) {
					$this->options_updated();
				}
			}
		}

		$organizemedialibrary_settings = get_option($this->wp_options_name());
		$scriptname = admin_url('upload.php?page=organizemedialibrary-settings');

		$plugin_datas = get_file_data( $this->plugin_base_dir.'/organizemedialibrary.php', array('version' => 'Version') );
		$plugin_version = __('Version:').' '.$plugin_datas['version'];

		?>
		<div class="wrap">

		<h2>Organize Media Library by Folders</h2>

		<div id="organizemedialibrary-admin-tabs">
		  	<ul>
				<li><a href="#organizemedialibrary-admin-tabs-1"><?php _e('Make folder', 'organize-media-library'); ?>&<?php _e('Settings'); ?></a></li>
				<li><a href="#organizemedialibrary-admin-tabs-2"><?php _e('Donate to this plugin &#187;'); ?></a></li>
			</ul>
			<div id="organizemedialibrary-admin-tabs-1">
				<h2><?php _e('Make folder', 'organize-media-library'); ?>&<?php _e('Settings'); ?></h2>
				<form method="post" action="<?php echo $scriptname; ?>">
					<?php wp_nonce_field('oml_settings', 'organizemedialibrary_tabs'); ?>

					<?php
					if ( function_exists('mb_check_encoding') ) {
					?>
					<div style="width: 100%; height: 100%; float: left; margin: 5px; padding: 5px; border: #CCC 2px solid;">
						<h3><?php _e('Character Encodings for Server', 'organize-media-library'); ?></h3>
						<p><?php _e('It may receive an error if you are using a multi-byte name to the file or directory name. In that case, please change.', 'organize-media-library'); ?></p>
						<select name="organizemedialibrary_character_code" style="width: 210px">
						<?php
						foreach (mb_list_encodings() as $chrcode) {
							if ( $chrcode <> 'pass' && $chrcode <> 'auto' ) {
								if ( $chrcode === $organizemedialibrary_settings['character_code'] ) {
									?>
									<option value="<?php echo $chrcode; ?>" selected><?php echo $chrcode; ?></option>
									<?php
								} else {
									?>
									<option value="<?php echo $chrcode; ?>"><?php echo $chrcode; ?></option>
									<?php
								}
							}
						}
						?>
						</select>
						<div style="clear: both;"></div>
					</div>
					<?php
					}
					?>

					<div style="width: 100%; height: 100%; float: left; margin: 5px; padding: 5px; border: #CCC 2px solid;">
						<h3><?php _e('Make folder', 'organize-media-library'); ?></h3>
						<p><?php _e('If you created or deleted a folder in another way, you can leave the field blank and press the following button to apply the changes.', 'organize-media-library'); ?></p>
						<div style="display: block; padding:5px 5px;">
							<code><?php echo $this->upload_path.'/'; ?></code>
							<input type="text" name="newdir">
						</div>
						<div style="clear: both;"></div>
					</div>
					<?php submit_button( __('Make folder', 'organize-media-library').'&'.__('Save Changes'), 'large', 'Submit', FALSE); ?>
				</form>
			</div>
			<div id="organizemedialibrary-admin-tabs-2">
			<?php $this->credit(); ?>
			</div>
		</div>

		</div>
		<?php
	}

	/* ==================================================
	 * Credit
	 */
	private function credit() {

		$plugin_name = NULL;
		$plugin_ver_num = NULL;
		$plugin_path = plugin_dir_path( __DIR__ );
		$plugin_dir = untrailingslashit($plugin_path);
		$slugs = explode('/', $plugin_dir);
		$slug = end($slugs);
		$files = scandir($plugin_dir);
		foreach ($files as $file) {
			if($file == '.' || $file == '..' || is_dir($plugin_path.$file)){
				continue;
			} else {
				$exts = explode('.', $file);
				$ext = strtolower(end($exts));
				if ( $ext === 'php' ) {
					$plugin_datas = get_file_data( $plugin_path.$file, array('name'=>'Plugin Name', 'version' => 'Version') );
					if ( array_key_exists( "name", $plugin_datas ) && !empty($plugin_datas['name']) && array_key_exists( "version", $plugin_datas ) && !empty($plugin_datas['version']) ) {
						$plugin_name = $plugin_datas['name'];
						$plugin_ver_num = $plugin_datas['version'];
						break;
					}
				}
			}
		}
		$plugin_version = __('Version:').' '.$plugin_ver_num;
		$faq = __('https://wordpress.org/plugins/'.$slug.'/faq', $slug);
		$support = 'https://wordpress.org/support/plugin/'.$slug;
		$review = 'https://wordpress.org/support/view/plugin-reviews/'.$slug;
		$translate = 'https://translate.wordpress.org/projects/wp-plugins/'.$slug;
		$facebook = 'https://www.facebook.com/katsushikawamori/';
		$twitter = 'https://twitter.com/dodesyo312';
		$youtube = 'https://www.youtube.com/channel/UC5zTLeyROkvZm86OgNRcb_w';
		$donate = __('https://shop.riverforest-wp.info/donate/', $slug);

		?>
		<span style="font-weight: bold;">
		<div>
		<?php echo $plugin_version; ?> | 
		<a style="text-decoration: none;" href="<?php echo $faq; ?>" target="_blank"><?php _e('FAQ'); ?></a> | <a style="text-decoration: none;" href="<?php echo $support; ?>" target="_blank"><?php _e('Support Forums'); ?></a> | <a style="text-decoration: none;" href="<?php echo $review; ?>" target="_blank"><?php _e('Reviews', 'media-from-ftp'); ?></a>
		</div>
		<div>
		<a style="text-decoration: none;" href="<?php echo $translate; ?>" target="_blank"><?php echo sprintf(__('Translations for %s'), $plugin_name); ?></a> | <a style="text-decoration: none;" href="<?php echo $facebook; ?>" target="_blank"><span class="dashicons dashicons-facebook"></span></a> | <a style="text-decoration: none;" href="<?php echo $twitter; ?>" target="_blank"><span class="dashicons dashicons-twitter"></span></a> | <a style="text-decoration: none;" href="<?php echo $youtube; ?>" target="_blank"><span class="dashicons dashicons-video-alt3"></span></a>
		</div>
		</span>

		<div style="width: 250px; height: 180px; margin: 5px; padding: 5px; border: #CCC 2px solid;">
		<h3><?php _e('Please make a donation if you like my work or would like to further the development of this plugin.', $slug); ?></h3>
		<div style="text-align: right; margin: 5px; padding: 5px;"><span style="padding: 3px; color: #ffffff; background-color: #008000">Plugin Author</span> <span style="font-weight: bold;">Katsushi Kawamori</span></div>
		<button type="button" style="margin: 5px; padding: 5px;" onclick="window.open('<?php echo $donate; ?>')"><?php _e('Donate to this plugin &#187;'); ?></button>
		</div>

		<?php

	}

	/* ==================================================
	 * Update	wp_options table.
	 * @param	none
	 * @since	1.0
	 */
	private function options_updated(){

		$organizemedialibrary = new OrganizeMediaLibrary();
		$organizemedialibrary_settings = get_option($this->wp_options_name());

		if ( !empty($_POST) ) {
			$newdir = NULL;
			if (!empty($_POST['newdir'])){
				$newdir = sanitize_text_field(urldecode($_POST['newdir']));
				$new_realdir = wp_normalize_path($this->upload_dir).'/'.$newdir;
				$mkdir_new_realdir = $organizemedialibrary->mb_encode_multibyte($new_realdir, $organizemedialibrary_settings['character_code']);
				if ( !file_exists($mkdir_new_realdir) ) {
					$err_mkdir = @wp_mkdir_p($mkdir_new_realdir);
					if ( !$err_mkdir ) {
						echo '<div class="notice notice-error is-dismissible"><ul><li>'.sprintf(__('Unable to create folder[%1$s].', 'organize-media-library'), wp_normalize_path($organizemedialibrary->mb_utf8($mkdir_new_realdir, $organizemedialibrary_settings['character_code']))).'</li></ul></div>';
						return;
					} else {
						$organizemedialibrary_settings['dirs'] = json_encode($organizemedialibrary->scan_dir($this->upload_dir));
						update_option( $this->wp_options_name(), $organizemedialibrary_settings );
						echo '<div class="notice notice-success is-dismissible"><ul><li>'.sprintf(__('Created folder[%1$s].', 'organize-media-library'), wp_normalize_path($organizemedialibrary->mb_utf8($mkdir_new_realdir, $organizemedialibrary_settings['character_code']))).'</li></ul></div>';
						return;
					}
				} else {
					echo '<div class="notice notice-error is-dismissible"><ul><li>'.sprintf(__('Folder[%1$s] already exists.', 'organize-media-library'), wp_normalize_path($organizemedialibrary->mb_utf8($mkdir_new_realdir, $organizemedialibrary_settings['character_code']))).'</li></ul></div>';
					return;
				}
			}
			$organizemedialibrary_settings['dirs'] = json_encode($organizemedialibrary->scan_dir($this->upload_dir));
			$organizemedialibrary_settings['character_code'] = sanitize_text_field($_POST['organizemedialibrary_character_code']);
			update_option( $this->wp_options_name(), $organizemedialibrary_settings );
			echo '<div class="notice notice-success is-dismissible"><ul><li>'.__('Settings').' --> '.__('Changes saved.').'</li></ul></div>';
		}

	}

	/* ==================================================
	 * Media Library Column
	 * @param	array	$cols
	 * @return	array	$cols
	 * @since	6.0
	 */
	public function muc_column( $cols ) {

		global $pagenow;
		if($pagenow == 'upload.php') {
			$cols["media_folder"] = __('Folder', 'organize-media-library');
		}

		return $cols;

	}

	/* ==================================================
	 * Media Library Column
	 * @param	string	$column_name
	 * @param	int		$id
	 * @since	6.0
	 */
	public function muc_value( $column_name, $id ) {

		if ( $column_name == "media_folder" ) {

			$organizemedialibrary_settings = get_option($this->wp_options_name());

			$html = NULL;

			$organizemedialibrary = new OrganizeMediaLibrary();

			$attach_rel_dir = get_post_meta( $id, '_wp_attached_file', true );
			$attach_rel_dir = '/'.untrailingslashit(str_replace(wp_basename($attach_rel_dir), '', $attach_rel_dir));
			$html .= '<select name="targetdirs['.$id.']" style="width: 100%; font-size: small; text-align: left;">';
			$html .= $organizemedialibrary->dir_selectbox($attach_rel_dir, $organizemedialibrary_settings['character_code']);
			$html .= '</select>';

			unset($organizemedialibrary);

			echo $html;

		}

	}

	/* ==================================================
	 * Media Library Search Filter for folders
	 * @since	6.0
	 */
	public function add_folder_filter() {

		global $wp_list_table;

		if ( empty( $wp_list_table->screen->post_type ) &&
			isset( $wp_list_table->screen->parent_file ) &&
			$wp_list_table->screen->parent_file == 'upload.php' )
			$wp_list_table->screen->post_type = 'attachment';

		if ( is_object_in_taxonomy( $wp_list_table->screen->post_type, 'media_folder' ) ) {
			$get_media_folder = NULL;
			if( isset($_REQUEST['media_folder']) ) { $get_media_folder = sanitize_text_field($_REQUEST['media_folder']); }
			?>
			<select name="media_folder">
				<option value="" <?php if(empty($get_media_folder)) echo 'selected="selected"'; ?>><?php _e('All Folders', 'organize-media-library'); ?></option>
				<?php
				$terms = get_terms('media_folder');
				foreach ($terms as $term) {
					?>
					<option value="<?php echo $term->slug; ?>" <?php if($get_media_folder == $term->slug) echo 'selected="selected"'; ?>><?php echo $term->name; ?></option>
				<?php
				}
				?>
			</select>
			<?php
		}

	}

	/* ==================================================
	 * Bulk Action Select
	 * @since	6.0
	 */
	public function custom_bulk_admin_footer() {

		global $pagenow;
		if($pagenow == 'upload.php') {

			$organizemedialibrary_settings = get_option($this->wp_options_name());

			$organizemedialibrary = new OrganizeMediaLibrary();

			$html = '<select name="bulk_folder" style="width: 100%; font-size: small; text-align: left;">';
			$html .= '<option value="">'.__('Bulk Select').'</option>';
			$html .= $organizemedialibrary->dir_selectbox($this->plugin_base_dir, $organizemedialibrary_settings['character_code']);
			$html .= '</select>';

			unset($organizemedialibrary);

			?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery('<option>').val('movefolder').text('<?php _e('Move to selected folder', 'organize-media-library')?>').appendTo("select[name='action']");
					jQuery('<option>').val('movefolder').text('<?php _e('Move to selected folder', 'organize-media-library')?>').appendTo("select[name='action2']");
				});
				jQuery('<?php echo $html; ?>').appendTo("#media_folder");
			</script>
			<?php
		}

	}

	/* ==================================================
	 * Bulk Action
	 * @since	6.0
	 */
	public function custom_bulk_action() {

		if ( !isset( $_REQUEST['detached'] ) ) {

			// get the action
			$wp_list_table = _get_list_table('WP_Media_List_Table');  
			$action = $wp_list_table->current_action();

			$allowed_actions = array("movefolder");
			if(!in_array($action, $allowed_actions)) return;

			check_admin_referer('bulk-media');

			if(isset($_REQUEST['media'])) {
				$post_ids = array_map('intval', $_REQUEST['media']);
			}

			if(empty($post_ids)) return;

			$sendback = remove_query_arg( array('foldermoved', 'message', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
			if ( ! $sendback )
			$sendback = admin_url( "upload.php?post_type=$post_type" );

			$pagenum = $wp_list_table->get_pagenum();
			$sendback = add_query_arg( 'paged', $pagenum, $sendback );

			switch($action) {
				case 'movefolder':
					$foldermoved = 0;
					$target_dirs = $this->sanitize_array($_REQUEST['targetdirs']);
					$messages = array();

					$organizemedialibrary_settings = get_option($this->wp_options_name());
					$character_code = $organizemedialibrary_settings['character_code'];

					$organizemedialibrary = new OrganizeMediaLibrary();

					foreach( $post_ids as $post_id ) {
						$message = $organizemedialibrary->regist($post_id, $target_dirs[$post_id], $character_code);
						if ( $message ) {
							$messages[$foldermoved] = $message;
							$foldermoved++;
						}
					}
					unset($organizemedialibrary);
					$sendback = add_query_arg( array('foldermoved' => $foldermoved, 'ids' => join(',', $post_ids), 'message' => join(',',  $messages)), $sendback );
				break;
				default: return;
			}

			$sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );
			wp_redirect($sendback);
			exit();

		}

	}

	/* ==================================================
	 * Bulk Action Message
	 * @since	6.0
	 */
	public function custom_bulk_admin_notices() {

	    global $post_type, $pagenow;

		if ( $pagenow == 'upload.php' && $post_type == 'attachment' && isset($_REQUEST['foldermoved']) && intval($_REQUEST['foldermoved']) > 0 && isset($_REQUEST['message']) ) {
			$messages = explode( ',', sanitize_text_field(urldecode($_REQUEST['message'])) );
			$success_count = 0;
			foreach ( $messages as $message ) {
				if ( $message === 'success' ) {
					++$success_count;
				} else {
					echo '<div class="notice notice-error is-dismissible"><ul><li>'.$message.'</li></ul></div>';
				}
			}
			if ( $success_count > 0 ) {
				echo '<div class="notice notice-success is-dismissible"><ul><li>'.sprintf(__('%1$d media files updated.', 'organize-media-library'),$success_count).'</li></ul></div>';
			}
		}

	}

	/* ==================================================
	 * Insert Media Custom Filter enqueue
	 * @since	6.04
	 */
	public function insert_media_custom_filter() {
		wp_enqueue_script( 'media-library-taxonomy-filter', $this->plugin_base_url.'/js/collection-filter.js', array( 'media-editor', 'media-views' ) );
		wp_localize_script( 'media-library-taxonomy-filter', 'MediaLibraryTaxonomyFilterData', array( 'terms' => get_terms( 'media_folder')) );
		add_action( 'admin_footer', array($this, 'insert_media_custom_filter_styling') );
	}


	/* ==================================================
	 * Insert Media Custom Filter style
	 * @since	6.04
	 */
	public function insert_media_custom_filter_styling() {
		?>
		<style>
		.media-modal-content .media-frame select.attachment-filters {
			max-width: -webkit-calc(33% - 12px);
			max-width: calc(33% - 12px);
		}
		</style>
		<?php
	}

	/* ==================================================
	* Sanitize Array
	* @param	array	$a
	* @return	string	$_a
	* @since	6.07
	*/
	private function sanitize_array($a) {

		$_a = array();
		foreach($a as $key=>$value) {
			if ( is_array($value) ) {
				$_a[$key] = $this->sanitize_array($value);
			} else {
				$_a[$key] = htmlspecialchars($value);
			}
		}

		return $_a;

	}

	/* ==================================================
	 * @param	none
	 * @return	string
	 * @since	6.31
	 */
	public function notices() {

		if ($this->is_my_plugin_screen()) {
			if ( is_multisite() ) {
				$emu_install_url = network_admin_url('plugin-install.php?tab=plugin-information&plugin=extend-media-upload');
			} else {
				$emu_install_url = admin_url('plugin-install.php?tab=plugin-information&plugin=extend-media-upload');
			}
			$emu_install_html = '<a href="'.$emu_install_url.'" target="_blank" style="text-decoration: none; word-break: break-all;">Extend Media Upload</a>';
			if ( !class_exists('ExtendMediaUploadAdmin') ) {
				echo '<div class="notice notice-warning is-dismissible"><ul><li>'.sprintf(__('If you want to add a folder designation function to the media uploader, Please use the %1$s.','organize-media-library'), $emu_install_html).'</li></ul></div>';
			}
		}

	}

	/* ==================================================
	 * @param	none
	 * @return	string	$this->wp_options_name()
	 * @since	6.41
	 */
	private function wp_options_name() {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			include_once(ABSPATH . 'wp-includes/pluggable.php');
		}
		return 'organizemedialibrary_settings'.'_'.get_current_user_id();
	}

}

?>