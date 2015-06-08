<?php
class HeadwayMaintenance {

	public static $available_upgrades = array(
		'3.6',
		'3.6.1',
		'3.7',
		'3.7.1'
	);

	/**
	 * Over time, there may be issues to be corrected between updates or naming conventions to be changed between updates.
	 * All of that will be processed here.
	 **/
	static function do_upgrades($version_to_upgrade = false) {

		$headway_settings = get_option('headway', array('version' => 0));
		$db_version = $headway_settings['version'];

		if ( get_transient('headway_upgrading') == 'upgrading' ) {
			return false;
		}

		self::setup_upgrade_environment();

		if ( $db_version == HEADWAY_VERSION ) {
			return false;
		}

		/* Add current version to upgrades if it's not there so the basic upgrade routine is still ran */
		if ( !in_array(HEADWAY_VERSION, self::$available_upgrades) ) {
			self::$available_upgrades[] = HEADWAY_VERSION;
		}


		if ( !$version_to_upgrade ) {

			foreach ( self::$available_upgrades as $possible_upgrade ) {

				if ( version_compare( $db_version, $possible_upgrade, '<' ) ) {

					$version_to_upgrade = $possible_upgrade;
					break;

				}

			}

		}

		/* Do specified upgrade routine */
		if ( $upgrade_in_progress = $version_to_upgrade ) {

			$version_filename = str_replace( '.', '', $upgrade_in_progress );

			if ( version_compare( $db_version, $upgrade_in_progress, '<' ) ) {

				self::start_upgrade($upgrade_in_progress);

				if ( file_exists(HEADWAY_LIBRARY_DIR . '/maintenance/upgrade-' . $version_filename . '.php') ) {
					require_once HEADWAY_LIBRARY_DIR . '/maintenance/upgrade-' . $version_filename . '.php';
				}

				do_action('headway_do_upgrade_' . $version_filename);

				self::after_upgrade($upgrade_in_progress);

			}

		}

		return true;

	}


	public static function setup_upgrade_environment() {

		@ignore_user_abort( true );
		@set_time_limit( 0 );

		/* Attempt to raise memory limit to max */
		@ini_set( 'memory_limit', apply_filters( 'headway_memory_limit', WP_MAX_MEMORY_LIMIT ) );

	}


	public static function output_status( $text ) {

		error_log('Headway Upgrade Status: ' . $text);

		return true;

	}


	public static function start_upgrade($version) {

		set_transient( 'headway_upgrading', 'upgrading', 15 );

		self::output_status('Currently Upgrading to ' . $version );

	}


	public static function after_upgrade($version) {

		/* Update the version here. */
		$headway_settings            = get_option( 'headway', array( 'version' => 0 ) );
		$headway_settings['version'] = $version;

		update_option( 'headway', $headway_settings );
		delete_transient( 'headway_upgrading' );

		/* Flush caches */
		do_action( 'headway_db_upgrade' );

		/* Run next upgrade if available */
		$index_of_current_version = array_search($version, self::$available_upgrades);

		if ( isset(self::$available_upgrades[$index_of_current_version + 1]) ) {

			$next_upgrade = self::$available_upgrades[$index_of_current_version + 1];

			return self::do_upgrades($next_upgrade);

		} else {

			if ( current_user_can('manage_options') ) {
				wp_safe_redirect( admin_url('admin.php?page=headway-whats-new') );
			} else {
				wp_safe_redirect( home_url() );
			}

			die();

		}

	}

}