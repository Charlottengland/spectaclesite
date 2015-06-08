<?php
class HeadwayVisualEditor {


	protected static $modes = array();
	
	
	protected static $default_mode = 'grid';
	
	
	protected static $default_layout = 'index';
	
	
	public static function init() {
		
		if ( !HeadwayCapabilities::can_user_visually_edit() )
			return;
					
		//If no child theme is active or if a child theme IS active and the grid is supported, use the grid mode.
		if ( current_theme_supports('headway-grid') )
			self::$modes['Grid'] = 'Add blocks and arrange your website structure';		
		
		self::$modes['Design'] = 'Choose fonts, colors, and other styles';
		
		//If the grid is disabled, set Design as the default mode.
		if ( !current_theme_supports('headway-grid') )
			self::$default_mode = 'design';
		
		//Attempt to raise memory limit to max
		@ini_set('memory_limit', apply_filters('headway_memory_limit', WP_MAX_MEMORY_LIMIT));
			
		//Put in action so we can run top level functions
		do_action('headway_visual_editor_init');
				
		//Visual Editor AJAX		
		add_action('wp_ajax_headway_visual_editor', array(__CLASS__, 'ajax'));
		
		if ( HeadwayOption::get('debug-mode') )
			add_action('wp_ajax_nopriv_headway_visual_editor', array(__CLASS__, 'ajax'));

		//Iframe handling
		add_action('headway_body_close', array(__CLASS__, 'iframe_load_flag'));
		add_action('headway_grid_iframe_footer', array(__CLASS__, 'iframe_load_flag'));

		add_action('headway_grid_iframe_footer', array(__CLASS__, 'iframe_tooltip_container'));
		add_action('headway_body_close', array(__CLASS__, 'iframe_tooltip_container'));
				
	}
	
	
	public static function ajax() {
				
		Headway::load('visual-editor/display', 'VisualEditorDisplay');
		Headway::load('visual-editor/visual-editor-ajax');
		
		//Authenticate nonce
		check_ajax_referer('headway-visual-editor-ajax', 'security');
		
		$method = headway_post('method') ? headway_post('method') : headway_get('method');

		//Check for a non-secure (something that doesn't save data) AJAX request first (let debug mode authentication pass through)
		if ( method_exists('HeadwayVisualEditorAJAX', 'method_' . $method) && HeadwayCapabilities::can_user_visually_edit() ) {
			do_action('headway_visual_editor_ajax_pre_' . $method);
			call_user_func(array('HeadwayVisualEditorAJAX', 'method_' . $method));
			do_action('headway_visual_editor_ajax_post_' . $method);
		}
						
		//Check for a secure (something that saves data) AJAX request and require genuine authentication
		elseif ( method_exists('HeadwayVisualEditorAJAX', 'secure_method_' . $method) && HeadwayCapabilities::can_user_visually_edit(true) ) {
			do_action('headway_visual_editor_ajax_pre_' . $method);
			call_user_func(array('HeadwayVisualEditorAJAX', 'secure_method_' . $method));
			do_action('headway_visual_editor_ajax_post_' . $method);
		}
			
		die();
						
	}


	public static function ajax_error_handler($errno, $errstr, $errfile, $errline) {

		if ( !defined( 'E_STRICT' ) )
			define( 'E_STRICT', 2048 );

		if ( !defined( 'E_RECOVERABLE_ERROR' ) )
			define( 'E_RECOVERABLE_ERROR', 4096 );

		if ( !defined( 'E_DEPRECATED' ) )
			define( 'E_DEPRECATED', 8192 );

		if ( !defined( 'E_USER_DEPRECATED' ) )
			define( 'E_USER_DEPRECATED', 16384 );

		$severity =
			1 * E_ERROR |
			1 * E_WARNING |
			0 * E_PARSE |
			0 * E_NOTICE |
			0 * E_CORE_ERROR |
			0 * E_CORE_WARNING |
			0 * E_COMPILE_ERROR |
			0 * E_COMPILE_WARNING |
			0 * E_USER_ERROR |
			0 * E_USER_WARNING |
			0 * E_USER_NOTICE |
			0 * E_STRICT |
			0 * E_RECOVERABLE_ERROR |
			0 * E_DEPRECATED |
			0 * E_USER_DEPRECATED;

		$error_ex = new ErrorException( $errstr, 0, $errno, $errfile, $errline );

		if ( ( $error_ex->getSeverity() & $severity ) != 0 ) {
			throw $error_ex;
		}

	}


	public static function save($options, $current_layout = false, $mode = false) {

		set_error_handler(array(__CLASS__, "ajax_error_handler"));

		$output = array(
			'errors' => array()
		);

		if ( !$current_layout )
			$current_layout = headway_post('layout');

		if ( !$mode )
			$mode = headway_post('mode');

		$blocks = isset($options['blocks']) ? $options['blocks'] : null;
		$wrappers = isset($options['wrappers']) ? $options['wrappers'] : null;
		$layout_options = isset($options['layout-options']) ? $options['layout-options'] : null;
		$options_inputs = isset($options['options']) ? $options['options'] : null;
		$design_editor_inputs = isset($options['design-editor']) ? $options['design-editor'] : null;

		try {

			/* Add wrappers */
			if ( $wrappers ) {

				foreach ( $wrappers as $id => $methods ) {

					foreach ( $methods as $method => $value ) {

						switch ( $method ) {

							case 'new':

								if ( HeadwayWrappersData::get_wrapper($id) )
									continue;

								if ( isset($wrappers[$id]['delete']) )
									continue;

								$args = array(
									'position' => headway_get('position', $wrappers[$id], 9999),
									'settings' => headway_get('settings', $wrappers[$id], array())
								);

								if ( $wrappers[$id]['insert_id'] ) {
									$args['id'] = $wrappers[$id]['insert_id'];
								}

								$new_wrapper = HeadwayWrappersData::add_wrapper($current_layout, $args);

								if ( is_wp_error($new_wrapper) ) {
									$output['errors'][] = $new_wrapper->get_error_code() . ($new_wrapper->get_error_message() ? ' - ' . $new_wrapper->get_error_code() : '');
								} else {
									$output['wrapper-id-mapping'][$id] = $new_wrapper;
								}

							break;

						}

					}

				}

			}
			/* End Adding wrappers */


			/* Blocks */
			if ( $blocks ) {

				foreach ( $blocks as $id => $methods ) {

					foreach ( $methods as $method => $value ) {

						switch ( $method ) {

							case 'new':

								if ( HeadwayBlocksData::get_block($id) )
									continue;

								if ( isset($blocks[$id]['delete']) )
									continue;

								$dimensions = explode(',', $blocks[$id]['dimensions']);
								$position = explode(',', $blocks[$id]['position']);

								$settings = isset($blocks[$id]['settings']) ? $blocks[$id]['settings'] : array();

								/* Check if the wrapper ID for the block is temporary, if it is get the real block ID */
								if ( isset($output['wrapper-id-mapping']) && $added_wrapper_id = headway_get(HeadwayWrappers::format_wrapper_id($blocks[$id]['wrapper']), $output['wrapper-id-mapping']) ) {
									$blocks[$id]['wrapper'] = $added_wrapper_id;
								}

								/* If 'duplicateOf' is present in the $settings array then remove that key and pull in the options from the block that's being duplicated */
								$duplicate = headway_get('duplicateOf', $settings);

								if ( $duplicate ) {

									$duplicated_block = HeadwayBlocksData::get_block($duplicate);
									$settings = headway_array_merge_recursive_simple(headway_get('settings', $duplicated_block), $settings);

									unset($settings['duplicateOf']);

								}

								$args = array(
									'type' => $value,
									'wrapper' => $blocks[$id]['wrapper'],
									'position' => array(
										'left' => $position[0],
										'top' => $position[1]
									),
									'dimensions' => array(
										'width' => $dimensions[0],
										'height' => $dimensions[1]
									),
									'settings' => $settings
								);

								if ( $blocks[$id]['insert_id'] ) {
									$args['id'] = $blocks[$id]['insert_id'];
								}

								$new_block = HeadwayBlocksData::add_block($current_layout, $args);

								if ( is_wp_error($new_block) ) {
									$output['errors'][] = $new_block->get_error_code() . ($new_block->get_error_message() ? ' - ' . $new_block->get_error_code() : '');
								} else {

									/* Add styling for duplicate if necessary */
									if ( $duplicate ) {

										$duplicated_block_styling = HeadwayBlocksData::get_block_styling($duplicated_block);

										/* Go through and process styling */
										foreach ( $duplicated_block_styling as $instance_id => $instance ) {

											foreach ( headway_get('properties', $instance, array()) as $property => $property_value ) {

												$instance_id = str_replace('block-' . $duplicated_block['id'], 'block-' . $new_block, $instance_id);

												HeadwayElementsData::set_special_element_property(null, $instance['element'], 'instance', $instance_id, $property, $property_value);

											}

										}

									}

									$output['block-id-mapping'][$id] = $new_block;

								}

								break;

							case 'delete':

								if ( isset($blocks[$id]['new']) )
									continue;

								HeadwayBlocksData::delete_block($id);

								break;

							case 'dimensions':

								if ( isset($blocks[$id]['new']) )
									continue;

								$dimensions = explode(',', $value);

								$args = array(
									'dimensions' => array(
										'width' => $dimensions[0],
										'height' => $dimensions[1]
									)
								);

								HeadwayBlocksData::update_block($id, $args);

								break;

							case 'position':

								if ( isset($blocks[$id]['new']) )
									continue;

								$position = explode(',', $value);

								$args = array(
									'position' => array(
										'left' => $position[0],
										'top' => $position[1]
									)
								);

								HeadwayBlocksData::update_block($id, $args);

								break;

							case 'wrapper':

								if ( isset($blocks[$id]['new']) )
									continue;

								/* Check if the wrapper ID for the block is temporary, if it is get the real wrapper ID */
								if ( isset($output['wrapper-id-mapping']) && headway_get($value, $output['wrapper-id-mapping']) )
									$value = headway_get($value, $output['wrapper-id-mapping']);

								$args = array(
									'wrapper' => $value
								);


								HeadwayBlocksData::update_block($id, $args);

								break;

							case 'settings':

								if ( isset($blocks[$id]['new']) )
									continue;

								//Get the block from the layout
								$block = HeadwayBlocksData::get_block($id);

								//If block doesn't exist, we can't do anything.
								if ( !$block || !is_array(headway_get('settings', $block)) )
									continue;

								//If there aren't any options, then don't do anything either
								if ( !is_array($value) || count($value) === 0 )
									continue;

								$block['settings'] = array_merge($block['settings'], $value);

								HeadwayBlocksData::update_block($id, $block);

								break;

						}

					}

				}

			}
			/* End Blocks */


			/* Do everything else with wrappers.  Reason being the wrapper IDs need to be established for adding blocks, but if we move a block from a wrapper then delete that wrapper, we don't want those blocks to be deleted. */
			if ( $wrappers ) {

				foreach ( $wrappers as $id => $methods ) {

					foreach ( $methods as $method => $value ) {

						switch ( $method ) {

							case 'delete':

								if ( isset($wrappers[$id]['new']) )
									continue;

								HeadwayWrappersData::delete_wrapper($current_layout, $id);

								break;

							case 'position':

								if ( isset($wrappers[$id]['new']) )
									continue;

								$args = array(
									'position' => $value
								);

								HeadwayWrappersData::update_wrapper($id, $args);

								break;

							case 'settings':

								if ( isset($wrappers[$id]['new']) )
									continue;

								//Get the wrapper from the layout so we can merge settings
								$wrapper = HeadwayWrappersData::get_wrapper($id);

								//If wrapper doesn't exist, we can't do anything.
								if ( !$wrapper )
									continue;

								//If there aren't any options, then don't do anything either
								if ( !is_array($value) || count($value) === 0 )
									continue;

								$wrapper['settings'] = array_merge($wrapper['settings'], $value);

								HeadwayWrappersData::update_wrapper($id, $wrapper);

								break;

						}

					}

				}

			}
			/* End everything else wrappers (delete and options) */


			/* Layout Options */
			if ( $layout_options ) {

				foreach ( $layout_options as $group => $options ) {

					foreach ( $options as $option => $value ) {
						HeadwayLayoutOption::set($current_layout, $option, $value, $group);
					}

				}

			}
			/* End Layout Options */

			/* Options */
			if ( $options_inputs ) {

				foreach ( $options_inputs as $group => $options ) {

					foreach ( $options as $option => $value ) {
						HeadwaySkinOption::set($option, $value, $group);
					}

				}

			}
			/* End Options */

			/* Design Editor Inputs */
			if ( $design_editor_inputs ) {

				/* Loop through to get every element and its properties */
				foreach ( $design_editor_inputs as $element_id => $element_data ) {

					if ( !is_array($element_data) )
						continue;

					//Dispatch depending on type of element data
					foreach ( $element_data as $element_data_node => $element_data_node_data ) {

						//Handle different nodes depending on what they are
						if ( $element_data_node == 'properties' ) {

							//Set each property for the regular element
							foreach ( $element_data_node_data as $property_id => $property_value )
								HeadwayElementsData::set_property(null, $element_id, $property_id, $property_value);

							//Handle instances, states, etc.
						} else if ( strpos($element_data_node, 'special-element-') === 0 ) {

							$special_element_type = str_replace('special-element-', '', $element_data_node);

							//Loop through the special elements
							foreach ( $element_data_node_data as $special_element => $special_element_properties ) {

								/* If block ID mapping exists, make sure that none of the temporary IDs are being a saved as instances.  This is mainly to make block settimgs import work if they do it on a block that hasn't been saved yet. */
								if ( isset($output['block-id-mapping']) && count($output['block-id-mapping']) ) {

									foreach ( $output['block-id-mapping'] as $old_block_id => $new_block_id ) {
										$special_element = str_replace('block-' . $old_block_id, 'block-' . $new_block_id, $special_element);
									}

								}

								/* If wrapper ID mapping exists, do same thing as block ID mapping */
								if ( isset( $output['wrapper-id-mapping'] ) && count( $output['wrapper-id-mapping'] ) ) {

									foreach ( $output['wrapper-id-mapping'] as $old_wrapper_id => $new_wrapper_id ) {
										$special_element = str_replace( 'wrapper-' . HeadwayWrappers::format_wrapper_id($old_wrapper_id), 'wrapper-' . HeadwayWrappers::format_wrapper_id( $new_wrapper_id ), $special_element );
									}

								}

								//Set the special element properties now
								foreach ( $special_element_properties as $special_element_property => $special_element_property_value ) {
									HeadwayElementsData::set_special_element_property(null, $element_id, $special_element_type, $special_element, $special_element_property, $special_element_property_value);
								}
							}

						}

					}

				}
				/* End loop */

			}
			/* End Design Editor Inputs */

			//This hook is used by cache flushing, plugins, etc.  Do not fire on preview save because it'll flush preview options
			if ( !headway_get('ve-preview') )
				do_action('headway_visual_editor_save');

			/* Save snapshot if allowed */
			if ( !defined('HEADWAY_DISABLE_AUTO_SNAPSHOT') || HEADWAY_DISABLE_AUTO_SNAPSHOT !== true ) {
				$output['snapshot'] = HeadwayDataSnapshots::save_snapshot(true);
			}

		} catch (Exception $e) {

			/* Disable error output on saving right now */
			
			/*
			if ( !isset($output['errors']) || !is_array($output['errors']) )
				$output['errors'] = array();

			$output['errors'][] = $e->getMessage() . '<br /><br/><pre style="overflow: scroll;user-select: all;max-width: 220px;-webkit-user-select: all;-moz-user-select:all;border: 1px solid rgba(255, 255, 255, 0.2);">' . $e->getTraceAsString() . '</pre>';
			*/

		}

		if ( !count($output['errors']) )
			unset($output['errors']);

		return $output;

	}

	
	public static function display() {
		
		self::check_if_ie();
		
		Headway::load('visual-editor/display', 'VisualEditorDisplay');
		HeadwayVisualEditorDisplay::display();
		
	}


	public static function check_if_ie() {
		
		/* Only show this on IE versions less than 9 */
		if ( !headway_is_ie() || (headway_is_ie(9) || headway_is_ie(10) || headway_is_ie(11)) )
			return false;
			
		$message = '
			<span style="text-align: center;font-size: 26px;width: 100%;display: block;margin-bottom: 20px;">Error</span>

			Unfortunately, the Headway Visual Editor does not work with Internet Explorer due to its lack of modern features.<br /><br />

			Please upgrade to a modern browser such as <a href="http://www.google.com/chrome" target="_blank">Google Chrome</a> or <a href="http://firefox.com" target="_blank">Mozilla Firefox</a>.<br /><br />

			If this message persists after upgrading to a modern browser, please visit <a href="http://support.headwaythemes.com" target="_blank">Headway Support</a>.
		';

		return wp_die($message);
		
	}

	
	public static function get_modes() {
				
		return apply_filters('headway_visual_editor_get_modes', self::$modes);
		
	}	
		
	
	public static function get_current_mode() {
		
		$mode = headway_get('visual-editor-mode');		
				
		if ( $mode ) {
			
			if ( array_search(strtolower($mode), array_map('strtolower', array_keys(self::$modes))) ) {
				
				return strtolower($mode);
				
			} 
		
		}
			
		return strtolower(self::$default_mode);
	
	}	
		
	
	public static function is_mode($mode) {
				
		if ( self::get_current_mode() === strtolower($mode) )
			return true;
			
		if ( !headway_get('visual-editor-mode') && strtolower($mode) === strtolower(self::$default_mode) )
			return true;
			
		return false;
		
	}


	//////////////////    iframe handling   ///////////////////////
	public static function iframe_load_flag() {

		echo '<script type="text/javascript">
			/* Set the iframe as loaded for the iframe load checker */
			document.getElementsByTagName("body")[0].className += " iframe-loaded";
		</script>';

	}


	public static function iframe_tooltip_container() {

		echo '<div id="headway-tooltip-container" style="position:fixed;top:0;left:0;width:100%;height:100%;background:transparent;z-index: 0;pointer-events:none;"></div>';

	}
	
	
}