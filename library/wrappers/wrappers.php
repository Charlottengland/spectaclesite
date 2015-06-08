<?php
class HeadwayWrappers {


	public static $default_wrappers = array(
		'default' => array(
			'id' => 'default',
			'position' => 0,
			'settings' => array(
				'fluid' => false,
				'fluid-grid' => false,
				'columns' => null,
				'column-width' => null,
				'gutter-width' => null
			)
		)
	);

	public static $default_wrapper_id = 'default';

	public static $default_columns = 24;

	public static $default_column_width = 20;

	public static $default_gutter_width = 20;

	public static $default_wrapper_margin_top = 30;

	public static $default_wrapper_margin_bottom = 30;


	public static $global_grid_column_width = null;

	public static $global_grid_gutter_width = null;


	public static function init() {

		/* Set defaults */
			self::$default_columns = HeadwaySkinOption::get('columns', false, self::$default_columns);
			self::$global_grid_column_width = HeadwaySkinOption::get('column-width', false, self::$default_column_width);
			self::$global_grid_gutter_width = HeadwaySkinOption::get('gutter-width', false, self::$default_gutter_width);

			self::$default_wrappers['default']['settings']['use-independent-grid'] = false;
			self::$default_wrappers['default']['settings']['columns'] = self::$default_columns;
			self::$default_wrappers['default']['settings']['column-width'] = self::$default_column_width;
			self::$default_wrappers['default']['settings']['gutter-width'] = self::$default_gutter_width;

		/* Setup hooks */
		add_action('headway_register_elements_instances', array(__CLASS__, 'register_wrapper_instances'), 11);
		add_action('headway_wrapper_options', array(__CLASS__, 'options_panel'), 10, 2);

	}


	public static function format_wrapper_id($wrapper_id) {

		return str_replace('wrapper-', '', $wrapper_id);

	}


	public static function register_wrapper_instances() {

		$all_wrappers = HeadwayWrappersData::get_all_wrappers();

		if ( !$all_wrappers )
			return false;

		foreach ( $all_wrappers as $wrapper_id => $wrapper_options ) {

			/* Do NOT register the default wrapper instance */
			if ( $wrapper_id == 'default' )
				continue;

			/* Do not register instance for mirrored wrapper */
			if ( HeadwayWrappersData::is_wrapper_mirrored($wrapper_options) )
				continue;

			$wrapper_id_for_selector    = HeadwayWrappersData::get_legacy_id( $wrapper_options );

			$wrapper_name = headway_get('alias', headway_get('settings', $wrapper_options, array())) ? 'Wrapper: ' . headway_get( 'alias', headway_get( 'settings', $wrapper_options, array() ) ) : 'Wrapper (Unnamed)';

			HeadwayElementAPI::register_element_instance(array(
				'group' => 'structure',
				'element' => 'wrapper',
				'id' => 'wrapper-' . HeadwayWrappers::format_wrapper_id($wrapper_id),
				'name' => $wrapper_name,
				'selector' => '#wrapper-' . self::format_wrapper_id( $wrapper_id_for_selector) . ', div.wrapper-mirroring-' . self::format_wrapper_id($wrapper_id_for_selector),
				'layout' => $wrapper_options['layout']
			));

		}

	}


	public static function is_fluid($wrapper) {

		return headway_get('fluid', headway_get('settings', $wrapper, array()), false, true);

	}


	public static function is_grid_fluid($wrapper) {

		$wrapper_settings = headway_get('settings', $wrapper, array());

		return headway_get('fluid', $wrapper_settings, false, true) && headway_get('fluid-grid', $wrapper_settings, false, true);

	}


	public static function is_independent_grid($wrapper) {

		return headway_get('use-independent-grid', headway_get('settings', $wrapper, array()), false, true);

	}


	public static function get_columns($wrapper) {

		return headway_get('columns', headway_get('settings', $wrapper, array()), false, true);

	}


	public static function get_column_width($wrapper) {

		$wrapper_settings = headway_get('settings', $wrapper, array());

		return headway_get('use-independent-grid', $wrapper_settings, false, true) ? headway_get('column-width', $wrapper_settings, false, true) : HeadwayWrappers::$global_grid_column_width;

	}


	public static function get_gutter_width($wrapper) {

		$wrapper_settings = headway_get('settings', $wrapper, array());

		return headway_get('use-independent-grid', $wrapper_settings, false, true) ? headway_get('gutter-width', $wrapper_settings, false, true) : HeadwayWrappers::$global_grid_gutter_width;

	}


	public static function get_grid_width($wrapper) {

		if ( !is_array($wrapper) )
			return false;

		/* If wrapper is mirrored then use settings from it for the grid */
		if ( $potential_wrapper_mirror = HeadwayWrappersData::get_wrapper_mirror($wrapper) )
			$wrapper = $potential_wrapper_mirror;

		$columns = self::get_columns($wrapper);

		$column_width = self::get_column_width($wrapper);
		$gutter_width = self::get_gutter_width($wrapper);

		return ($column_width * $columns) + (($columns - 1) * $gutter_width);

	}


	public static function options_panel($wrapper, $layout) {

		require_once HEADWAY_LIBRARY_DIR . '/wrappers/wrapper-options.php';

		//Initiate options class
		$options = new HeadwayWrapperOptions;
		$options->display($wrapper, $layout);

	}


	public static function get_layout_wrappers( $layout ) {

		_deprecated_function( __FUNCTION__, '3.7', 'HeadwayDataWrappers::get_wrappers_by_layout()' );

		$wrappers = HeadwayWrappersData::get_wrappers_by_layout( $layout );

		if ( ! $wrappers )
			return $wrappers;

		/* Merge settings array with each wrapper so it's single dimension */
		foreach ( $wrappers as $wrapper_id => $wrapper ) {

			$wrappers[ $wrapper_id ]['mirror-wrapper'] = headway_get( 'mirror_id', $wrapper );

			$wrappers[ $wrapper_id ] = array_merge( $wrappers[ $wrapper_id ], headway_get( 'settings', $wrappers[ $wrapper_id ], array() ) );

		}

		return $wrappers;

	}


	public static function get_all_wrappers() {

		_deprecated_function( __FUNCTION__, '3.7', 'HeadwayWrappersData::get_all_wrappers()' );

		$wrappers = HeadwayWrappersData::get_all_wrappers();

		return $wrappers;

	}


	public static function get_wrapper($wrapper_id, $deprecated = null) {

		_deprecated_function( __FUNCTION__, '3.7', 'HeadwayWrappersData::get_wrapper()' );

		return HeadwayWrappersData::get_wrapper($wrapper_id);

	}


}