<?php

//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) 
    exit();

global $wpdb;
$option_names = array();
$wp_options = $wpdb->get_results("
				SELECT option_name
				FROM $wpdb->options
				WHERE option_name LIKE '%%organizemedialibrary_settings%%'
				");
foreach ( $wp_options as $wp_option ) {
	$option_names[] = $wp_option->option_name;
}

$wp_terms = $wpdb->get_results("
				SELECT term_id
				FROM $wpdb->term_taxonomy
				WHERE taxonomy = 'media_folder'
				");
$termids = array();
foreach ( $wp_terms as $wp_term ) {
	$termids[] = $wp_term->term_id;
}

// For Single site
if ( !is_multisite() ) 
{
	foreach ( $option_names as $option_name ) {
	    delete_option( $option_name );
	}
	foreach ( $termids as $termid ) {
		wp_delete_term( $termid, 'media_folder' );
	}
} 
// For Multisite
else 
{
    // For regular options.
    global $wpdb;
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
    $original_blog_id = get_current_blog_id();
    foreach ( $blog_ids as $blog_id ) 
    {
        switch_to_blog( $blog_id );
		foreach ( $option_names as $option_name ) {
	        delete_option( $option_name );  
		}
		foreach ( $termids as $termid ) {
			wp_delete_term( $termid, 'media_folder' );
		}
    }
    switch_to_blog( $original_blog_id );

    // For site options.
	foreach ( $option_names as $option_name ) {
	    delete_site_option( $option_name );  
	}
}

?>