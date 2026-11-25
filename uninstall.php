<?php
/**
 * Removes everything the plugin stored. Runs only when the plugin is deleted.
 *
 * @package AmitShaw\FormSteps
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes the plugin's options and form meta on the current site.
 */
function svfs_uninstall_site() {
	delete_option( 'svfs_settings' );
	delete_option( 'svfs_version' );
	delete_option( 'svfs_welcome' );
	delete_post_meta_by_key( '_svfs_form' );
}

if ( is_multisite() ) {
	$svfs_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $svfs_site_ids as $svfs_site_id ) {
		switch_to_blog( (int) $svfs_site_id );
		svfs_uninstall_site();
		restore_current_blog();
	}
} else {
	svfs_uninstall_site();
}
