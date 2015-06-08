<?php
/**
 * 3.7.1
 *
 * Change wrapper instance IDs and change MySQL collate and charset
 */
add_action('headway_do_upgrade_371', 'headway_do_upgrade_371');
function headway_do_upgrade_371() {

	global $wpdb;

	/* Alter MySQL schema */
	Headway::mysql_dbdelta();

	/* Loop through installed Templates and fix wrapper instance IDs */
	$templates = HeadwayTemplates::get_all(true, true);

	foreach ( $templates as $template_id => $template ) {

		$template_design_settings = get_option( 'headway_|template=' . $template_id . '|_option_group_design', array() );

		if ( !empty($template_design_settings) ) {

			$template_design_settings = headway_preg_replace_json( "/-layout-[\w-]*/", '', $template_design_settings );
			update_option( 'headway_|template=' . $template_id . '|_option_group_design', $template_design_settings );

		}

	}

	/* Alter MySQL charset and collate */
	$charset = 'utf8';

	if ( ! empty($wpdb->collate ) ) {
		$collate = $wpdb->collate;
	} else {
		$collate = 'utf8_general_ci';
	}

	$wpdb->query("ALTER TABLE $wpdb->hw_blocks CONVERT TO CHARACTER SET $charset COLLATE $collate;");
	$wpdb->query("ALTER TABLE $wpdb->hw_wrappers CONVERT TO CHARACTER SET $charset COLLATE $collate;");
	$wpdb->query("ALTER TABLE $wpdb->hw_layout_meta CONVERT TO CHARACTER SET $charset COLLATE $collate;");
	$wpdb->query("ALTER TABLE $wpdb->hw_snapshots CONVERT TO CHARACTER SET $charset COLLATE $collate;");

}