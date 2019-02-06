<?php
/**
 * Organize Media Library by Folders
 * 
 * @package    Organize Media Library
 * @subpackage OrganizeMediaLibrary Main Functions
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

class OrganizeMediaLibrary {

	private $upload_dir;
	private $upload_url;
	private $upload_path;

	/* ==================================================
	 * Construct
	 * @since	6.20
	 */
	public function __construct() {

		list($this->upload_dir, $this->upload_url, $this->upload_path) = $this->upload_dir_url_path();

	}

	/* ==================================================
	 * @param	int		$re_id_attache
	 * @param	string	$target_folder
	 * @param	string	$character_code
	 * @return	string	$message
	 * @since	1.0
	 */
	public function regist($re_id_attache, $target_folder, $character_code){

		$target_folder = urldecode($target_folder);
		if ( $target_folder <> '/' ) { $target_folder = trailingslashit($target_folder); }
		$file = get_post_meta($re_id_attache, '_wp_attached_file', true);
		$filename = wp_basename($file);
		$current_folder = '/'.str_replace($filename, '', $file);
		$exts = explode('.', $filename);
		$ext = end($exts);

		if ( $target_folder === $current_folder ) { return; }

		$re_attache = get_post( $re_id_attache );
		$new_attach_title = $re_attache->post_title;

		$current_file = $this->mb_encode_multibyte($this->upload_dir.$current_folder.$filename, $character_code);
		$target_file = $this->mb_encode_multibyte($this->upload_dir.$target_folder.$filename, $character_code);
		if ( file_exists( $current_file ) ) {
			$err_copy1 = @copy( $current_file, $target_file );
			if ( !$err_copy1 ) {
				return sprintf(__('%1$s: Failed a copy from %2$s to %3$s.', 'organize-media-library'), $new_attach_title, wp_normalize_path($this->mb_utf8($current_file, $character_code)), wp_normalize_path($this->mb_utf8($target_file, $character_code)));
			}
			unlink($current_file);
		}

		update_post_meta($re_id_attache, '_wp_attached_file', ltrim($target_folder.$filename, '/'));

		if ( wp_ext2type($ext) === 'image' || strtolower($ext) === 'pdf' ){

			$metadata = wp_get_attachment_metadata( $re_id_attache );

			if ( !empty($metadata) ) {
				foreach ( (array)$metadata as $key1 => $key2 ){
					if ( $key1 === 'sizes' ) {
						foreach ( $metadata[$key1] as $key2 => $key3 ){
							$current_thumb_file = $this->mb_encode_multibyte($this->upload_dir.$current_folder.$metadata['sizes'][$key2]['file'], $character_code);
							$target_thumb_file = $this->mb_encode_multibyte($this->upload_dir.$target_folder.$metadata['sizes'][$key2]['file'], $character_code);
							if ( file_exists( $current_thumb_file ) ) {
								$err_copy2 = @copy( $current_thumb_file, $target_thumb_file );
								if ( !$err_copy2 ) {
									return sprintf(__('%1$s: Failed a copy from %2$s to %3$s.', 'organize-media-library'), $new_attach_title, wp_normalize_path($this->mb_utf8($current_thumb_file, $character_code)), wp_normalize_path($this->mb_utf8($target_thumb_file, $character_code)));
								}
								unlink($current_thumb_file);
							}
						}
					}
				}
				update_post_meta($re_id_attache, '_wp_attachment_metadata', $metadata);
			}

		}

		$url_attach = $this->upload_url.$current_folder.$filename;
		$new_url_attach = $this->upload_url.$target_folder.$filename;

		global $wpdb;
		// Change DB contents
		$search_url = str_replace('.'.$ext, '', $url_attach);
		$replace_url = str_replace('.'.$ext, '', $new_url_attach);

		// Replace
		$sql = $wpdb->prepare(
			"UPDATE `$wpdb->posts` SET post_content = replace(post_content, %s, %s)",
			$search_url,
			$replace_url
		);
		$wpdb->query($sql);

		// Change DB Attachement post guid
		$update_array = array(
						'guid'=> $new_url_attach
					);
		$id_array= array('ID'=> $re_id_attache);
		$wpdb->show_errors();
		$wpdb->update( $wpdb->posts, $update_array, $id_array, array('%s'), array('%d') );
		if ( $wpdb->last_error !== '' ) {
			$message = $wpdb->print_error();
		} else {
			$message = 'success';
		}
		unset($update_array, $id_array);

		return $message;

	}

	/* ==================================================
	 * @param	string	$dir
	 * @return	array	$dirlist
	 * @since	3.0
	 */
	public function scan_dir($dir) {

		$excludedir = 'media-from-ftp-tmp';	// tmp dir for Media from FTP
		global $blog_id;
		if ( is_multisite() && is_main_site($blog_id) ) {
			$excludedir .= '|\/sites\/';
		}

		$files = scandir($dir);
		$list = array();
		foreach ($files as $file) {
			if($file == '.' || $file == '..'){
				continue;
			}
			$fullpath = rtrim($dir, '/') . '/' . $file;
			if (is_dir($fullpath)) {
				if (!preg_match("/".$excludedir."/", $fullpath)) {
					$list[] = $this->mb_utf8($fullpath, 'UTF-8');
				}
				$list = array_merge($list, $this->scan_dir($fullpath));
			}
		}

		arsort($list);
		return $list;

	}

	/* ==================================================
	 * @param	string	none
	 * @param	string	none
	 * @return	string	$oml_dirs
	 * @since	6.41
	 */
	private function oml_dirs() {

		$oml_dirs = NULL;
		$wp_options_name = 'organizemedialibrary_settings'.'_'.get_current_user_id();
		if ( get_option($wp_options_name) ) {
			$organizemedialibrary_settings = get_option($wp_options_name);
			if ( !array_key_exists( "dirs", (array)$organizemedialibrary_settings ) ) {
				$organizemedialibrary_settings['dirs'] = json_encode($this->scan_dir($this->upload_dir));
				update_option( $wp_options_name, $organizemedialibrary_settings );
			} else {
				if ( empty($organizemedialibrary_settings['dirs']) ) {
					$organizemedialibrary_settings['dirs'] = json_encode($this->scan_dir($this->upload_dir));
					update_option( $wp_options_name, $organizemedialibrary_settings );
				}
			}
			$oml_dirs = json_decode($organizemedialibrary_settings['dirs'],true);
		}

		return $oml_dirs;

	}

	/* ==================================================
	 * @param	string	$searchdir
	 * @param	string	$character_code
	 * @return	string	$dirlist
	 * @since	3.0
	 */
	public function dir_selectbox($searchdir, $character_code) {

		$oml_dirs = $this->oml_dirs();
		if ( !$oml_dirs ) return;

		$linkselectbox = NULL;
		$wordpress_path = wp_normalize_path(ABSPATH);
		foreach ( $oml_dirs as $linkdir) {
			$linkdirenc = $this->mb_utf8(str_replace($this->upload_dir, '', $linkdir), $character_code);
			if( $searchdir === $linkdirenc ){
				$linkdirs = '<option value="'.urlencode($linkdirenc).'" selected>'.str_replace($this->upload_path, '', $linkdirenc).'</option>';
			}else{
				$linkdirs = '<option value="'.urlencode($linkdirenc).'">'.str_replace($this->upload_path, '', $linkdirenc).'</option>';
			}
			$linkselectbox = $linkselectbox.$linkdirs;
		}
		if( $searchdir ===  '/' ){
			$linkdirs = '<option value="'.urlencode('/').'" selected>/</option>';
		}else{
			$linkdirs = '<option value="'.urlencode('/').'">/</option>';
		}
		$linkselectbox = $linkselectbox.$linkdirs;

		return $linkselectbox;

	}

	/* ==================================================
	 * @param	string	$base
	 * @param	string	$relationalpath
	 * @return	string	realurl
	 * @since	3.4
	 */
	private function realurl( $base, $relationalpath ){
	     $parse = array(
	          "scheme" => null,
	          "user" => null,
	          "pass" => null,
	          "host" => null,
	          "port" => null,
	          "query" => null,
	          "fragment" => null
	     );
	     $parse = parse_url( $base );

	     if( strpos($parse["path"], "/", (strlen($parse["path"])-1)) !== false ){
	          $parse["path"] .= ".";
	     }

	     if( preg_match("#^https?://#", $relationalpath) ){
	          return $relationalpath;
	     }else if( preg_match("#^/.*$#", $relationalpath) ){
	          return $parse["scheme"] . "://" . $parse["host"] . $relationalpath;
	     }else{
	          $basePath = explode("/", dirname($parse["path"]));
	          $relPath = explode("/", $relationalpath);
	          foreach( $relPath as $relDirName ){
	               if( $relDirName == "." ){
	                    array_shift( $basePath );
	                    array_unshift( $basePath, "" );
	               }else if( $relDirName == ".." ){
	                    array_pop( $basePath );
	                    if( count($basePath) == 0 ){
	                         $basePath = array("");
	                    }
	               }else{
	                    array_push($basePath, $relDirName);
	               }
	          }
	          $path = implode("/", $basePath);
	          return $parse["scheme"] . "://" . $parse["host"] . $path;
	     }

	}

	/* ==================================================
	 * @param	none
	 * @return	array	$upload_dir, $upload_url, $upload_path
	 * @since	4.0
	 */
	public function upload_dir_url_path(){

		$wp_uploads = wp_upload_dir();

		$relation_path_true = strpos($wp_uploads['baseurl'], '../');
		if ( $relation_path_true > 0 ) {
			$relationalpath = substr($wp_uploads['baseurl'], $relation_path_true);
			$basepath = substr($wp_uploads['baseurl'], 0, $relation_path_true);
			$upload_url = $this->realurl($basepath, $relationalpath);
			$upload_dir = wp_normalize_path(realpath($wp_uploads['basedir']));
		} else {
			$upload_url = $wp_uploads['baseurl'];
			$upload_dir = wp_normalize_path($wp_uploads['basedir']);
		}

		if(is_ssl()){
			$upload_url = str_replace('http:', 'https:', $upload_url);
		}

		if ( $relation_path_true > 0 ) {
			$upload_path = $relationalpath;
		} else {
			$upload_path = str_replace(site_url('/'), '', $upload_url);
		}

		$upload_dir = untrailingslashit($upload_dir);
		$upload_url = untrailingslashit($upload_url);
		$upload_path = untrailingslashit($upload_path);

		return array($upload_dir, $upload_url, $upload_path);

	}

	/* ==================================================
	 * @param	string	$character_code
	 * @return	string	none
	 * @since	5.02
	 */
	public function mb_initialize($character_code) {

		if ( function_exists('mb_language') && $character_code <> 'none' ) {
			if( get_locale() === 'ja' ) {
				mb_language('Japanese');
			} else if( get_locale() === 'en_US' ) {
				mb_language('English');
			} else {
				mb_language('uni');
			}
		}

	}

	/* ==================================================
	 * @param	string	$str
	 * @param	string	$character_code
	 * @return	string	$str
	 * @since	5.10
	 */
	public function mb_encode_multibyte($str, $character_code) {

		if ( function_exists('mb_language') && $character_code <> 'none' ) {
			$str = mb_convert_encoding($str, $character_code, "auto");
		}

		return $str;

	}

	/* ==================================================
	 * @param	string	$str
	 * @param	string	$character_code
	 * @return	string	$str
	 * @since	5.02
	 */
	public function mb_utf8($str, $character_code) {

		if ( function_exists('mb_convert_encoding') && $character_code <> 'none' ) {
			$str = mb_convert_encoding($str, "UTF-8", "auto");
		}

		return $str;

	}

}

?>