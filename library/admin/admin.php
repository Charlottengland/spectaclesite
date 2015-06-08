<?php
class HeadwayAdmin {
	
	
	public static function init() {
		
		self::setup_hooks();
		
		Headway::load(array(
			'api/api-admin-meta-box',
			'admin/admin-write' => true,
			'admin/admin-pages',
			'admin/api-admin-inputs'
		));
		
	}
	
	
	public static function setup_hooks() {

		/* Actions */
		add_action('admin_init', array(__CLASS__, 'activation'), 1);
		add_action('admin_init', array(__CLASS__, 'enqueue'));
		add_action('admin_init', array(__CLASS__, 'visual_editor_redirect'), 12);

		add_action('init', array(__CLASS__, 'form_action_save'), 12); // Init runs before admin_menu; admin_menu runs before admin_init
		add_action('init', array(__CLASS__, 'form_action_licenses'), 12);
		add_action('init', array(__CLASS__, 'form_action_reset'), 12);
		add_action('init', array(__CLASS__, 'form_action_delete_snapshots'), 12);

		add_action('admin_menu', array(__CLASS__, 'add_menus'));
		add_action('admin_head', array(__CLASS__, 'remove_system_submenus'));
				
		add_action('headway_admin_save_message', array(__CLASS__, 'save_message'));
		add_action('headway_admin_save_error_message', array(__CLASS__, 'save_error_message'));
		
		add_action('admin_notices', array(__CLASS__, 'notice_no_widgets_or_menus'));
		add_action('admin_notices', array(__CLASS__, 'theme_install_template_notice'));

		add_filter('page_row_actions', array(__CLASS__, 'row_action_visual_editor'), 10, 2);
		add_filter('post_row_actions', array(__CLASS__, 'row_action_visual_editor'), 10, 2);
		add_filter('tag_row_actions', array(__CLASS__, 'row_action_visual_editor'), 10, 2);

		add_filter('mce_buttons_2', array(__CLASS__, 'tiny_mce_buttons'));
		add_filter('tiny_mce_before_init', array(__CLASS__, 'tiny_mce_formats'));

	}
	
	
	public static function form_action_save() {
		
		//Form action for all Headway configuration panels.  Not in function/hook so it can load before everything else.
		if ( !headway_post('headway-submit', false))
			return false;
			
		if ( !wp_verify_nonce(headway_post('headway-admin-nonce', false), 'headway-admin-nonce') ) {
			
			global $headway_admin_save_message;
			$headway_admin_save_message = 'Security nonce did not match.';
			
			return false;
			
		}

		foreach ( headway_post('headway-admin-input', array()) as $option => $value ) {
			
			HeadwayOption::set($option, $value);
			
		}
		
		global $headway_admin_save_message;
		$headway_admin_save_message = 'Settings saved.';

		return true;
		
	}
	

	public static function form_action_licenses() {

		if ( !headway_post('headway-licenses', false))
			return false;

		if ( !wp_verify_nonce(headway_post('headway-admin-nonce', false), 'headway-admin-nonce') )
			return false;
			
		if ( !is_array(headway_post('headway-licenses')) )
			return false;

		global $headway_admin_save_message;
		global $headway_admin_save_error_message;


		/* Save and activations */
			if ( $save_and_activations = headway_get('save-and-activate', headway_post('headway-licenses')) ) {

				if ( is_array($save_and_activations) && count($save_and_activations) ) {

					foreach ( $save_and_activations as $item_slug_to_activate => $submit_value ) {

						HeadwayOption::set('license-key-' . $item_slug_to_activate, headway_get('license-key-' . $item_slug_to_activate, headway_post('headway-admin-input')));
						$activation_request = headway_activate_license($item_slug_to_activate);

						self::set_license_activation_message($activation_request);

					}

				}

			}

		/* Activations */
			if ( $activations = headway_get('activate', headway_post('headway-licenses')) ) {

				if ( is_array($activations) && count($activations) ) {

					foreach ( headway_get('activate', headway_post('headway-licenses')) as $item_slug_to_activate => $submit_value ) {

						$activation_request = headway_activate_license($item_slug_to_activate);

						self::set_license_activation_message($activation_request);

					}

				}

			}

		/* Deactivations */
			if ( $deactivations = headway_get('deactivate', headway_post('headway-licenses')) ) {

				if ( is_array($deactivations) && count($deactivations) ) {

					foreach ( headway_get('deactivate', headway_post('headway-licenses')) as $item_slug_to_deactivate => $submit_value ) {
						
						$deactivation_request = headway_deactivate_license($item_slug_to_deactivate);

						if ( $deactivation_request == 'deactivated' ) {

							$headway_admin_save_message = 'License deactivated.';

						} else if ( !is_wp_error($deactivation_request) ) {

							$headway_admin_save_error_message = '<strong>Whoops!</strong> Could not deactivate license.  Please check that you have entered your license correctly.';

						} else {

							$headway_admin_save_error_message = '
								<strong>Error While Deactivating:</strong> (' . $deactivation_request->get_error_code() . ') ' . $deactivation_request->get_error_message() . '<br /><br />
								'  . __('Please contact Headway Support if this error persists.', 'headway') . '
							';

						}

					}

				}

			}


		return true;

	}


		public static function set_license_activation_message($activation_request) {

			global $headway_admin_save_message;
			global $headway_admin_save_error_message;

			if ( $activation_request == 'active' || $activation_request == 'valid' ) {

				$headway_admin_save_message = __('License saved and activated.', 'headway');

			} else if ( $activation_request == 'invalid' || $activation_request == 'expired' ) {

				$headway_admin_save_error_message = __('
					<strong>Whoops!</strong> Could not activate license.  Please check that you have entered your license correctly and that it has not expired.<br /><br />
					Make sure you copied your license correctly from the <a href="http://headwaythemes.com/dashboard" target="_blank">Headway Dashboard</a>.
				', 'headway');
			
			} else if ( is_wp_error($activation_request) ) {

				$headway_admin_save_error_message = '
					<strong>Error While Activating:</strong> (' . $activation_request->get_error_code() . ') ' . $activation_request->get_error_message() . '<br /><br />
					'  . __('Please contact Headway Support if this error persists.', 'headway') . '
				';

			}

		}


	public static function form_action_delete_snapshots() {

		global $wpdb;

		if ( ! headway_post( 'headway-delete-snapshots', false ) ) {
			return false;
		}

		if ( ! wp_verify_nonce( headway_post( 'headway-delete-snapshots-nonce', false ), 'headway-delete-snapshots-nonce' ) ) {

			$GLOBALS['headway_admin_save_message'] = 'Security nonce did not match.';

			return false;

		}

		/* Loop through WordPress options and delete the skin options */
		$wpdb->query( "TRUNCATE TABLE $wpdb->hw_snapshots" );

		do_action( 'headway_delete_all_snapshots' );

		$GLOBALS['headway_admin_save_message'] = 'Snapshots successfully deleted.';

		return true;

	}


	public static function form_action_reset() {

		global $wpdb;

		if ( !defined('HEADWAY_ALLOW_RESET') || HEADWAY_ALLOW_RESET !== true )
			return false;

		//Form action for all Headway configuration panels.  Not in function/hook so it can load before everything else.
		if ( !headway_post('reset-headway', false) )
			return false;
			
		//Verify the nonce so other sites can't maliciously reset a Headway installation.
		if ( !wp_verify_nonce(headway_post('headway-reset-nonce', false), 'headway-reset-nonce') ) {
			
			$GLOBALS['headway_admin_save_message'] = 'Security nonce did not match.';
			
			return false;
			
		}

		/* Loop through WordPress options and delete the skin options */
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name = 'headway'" );
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'headway_%'" );

		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_hw_%'" );

		/* Remove Headway post meta */
		$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_hw_%'" );

		/* Drop Headway tables */
		Headway::mysql_drop_tables();

		/* Flush WP cache */
		wp_cache_flush();

		do_action('headway_global_reset');

		$GLOBALS['headway_admin_save_message'] = 'Headway was successfully reset.';
		
		//This will hide the reset box if set to true.
		$GLOBALS['headway_reset_success'] = true;
		
		return true;
		
	}

	
	public static function activation() {
		
		if ( !is_admin() || !headway_get('activated') )
			return false;
		
		global $pagenow;
		
		if ( $pagenow !== 'themes.php' )
			return false;
				
		//Since they may be upgrading and files may change, let's clear the cache
		do_action('headway_activation');

		self::activation_redirect();
		
	}
	
	
	public static function activation_redirect() {
		
		do_action('headway_activation_redirect');
		
		//If a child theme has been activated rather than Headway, then don't redirect.
		//Let the child theme developer redirect if they want by using the hook above.
		if ( HEADWAY_CHILD_THEME_ACTIVE === true )
			return false;
			
		$parent_menu = self::parent_menu();
			
		//If header were sent, then don't do the redirect
		if ( headers_sent() )
			return false;
			
		//We're all good, redirect now
		wp_safe_redirect(admin_url('admin.php?page=headway-' . $parent_menu['id']));
		die();
		
	}
	
	
	public static function visual_editor_redirect() {
		
		if ( isset($_GET['page']) && strpos($_GET['page'], 'headway-visual-editor') !== false && !headers_sent() )
			wp_safe_redirect(home_url() . '/?visual-editor=true');
		
	}
	
	
	public static function add_admin_separator($position){
				
		global $menu;
				
		$menu[$position] = array('', 'read', 'separator-headway', '', 'wp-menu-separator headway-separator');
	
		ksort($menu);
		
	}
	
	
	public static function add_admin_submenu($name, $id, $callback) {
		
		$parent_menu = self::parent_menu();

		return add_submenu_page('headway-' . $parent_menu['id'], $name, $name, 'manage_options', $id, $callback);
		
	}
	
	
	public static function add_menus(){
		
		//If the hide menus constant is set to true, don't hide the menus!
		if (defined('HEADWAY_HIDE_MENUS') && HEADWAY_HIDE_MENUS === true)
		 	return false;
		
		//If user cannot access the admin panels, then don't bother running these functions
		if ( !HeadwayCapabilities::can_user_visually_edit() ) 
			return false;

		$menu_name = ( HeadwayOption::get('hide-menu-version-number', false, true) == true ) ? 'Headway' : 'Headway ' . HEADWAY_VERSION;

		$icon = (version_compare($GLOBALS['wp_version'], '3.8', '>=') && get_user_option('admin_color') != 'light') ? 'headway-32-white.png' : 'headway-16.png';
		$icon_url = headway_url() . '/library/admin/images/' . $icon;
		
		$parent_menu = self::parent_menu();

		self::add_admin_separator(48);		
				
		add_menu_page($parent_menu['name'], $menu_name, 'manage_options', 'headway-' . $parent_menu['id'], $parent_menu['callback'], $icon_url, 49); 
				
			switch ( $parent_menu['id'] ) {
				
				case 'getting-started':
					self::add_admin_submenu('Getting Started', 'headway-getting-started', array('HeadwayAdminPages', 'getting_started'));
					self::add_admin_submenu('Visual Editor', 'headway-visual-editor', array('HeadwayAdminPages', 'visual_editor'));

					self::add_admin_submenu('Templates', 'headway-templates', array('HeadwayAdminPages', 'templates'));
					self::add_admin_submenu('Options', 'headway-options', array('HeadwayAdminPages', 'options'));
					self::add_admin_submenu('Tools', 'headway-tools', array('HeadwayAdminPages', 'tools'));
				break;
				
				case 'visual-editor':
					self::add_admin_submenu('Visual Editor', 'headway-visual-editor', array('HeadwayAdminPages', 'visual_editor'));

					self::add_admin_submenu('Templates', 'headway-templates', array('HeadwayAdminPages', 'templates'));
					self::add_admin_submenu('Options', 'headway-options', array('HeadwayAdminPages', 'options'));
					self::add_admin_submenu('Tools', 'headway-tools', array('HeadwayAdminPages', 'tools'));
				break;
				
				case 'options':
					self::add_admin_submenu('Options', 'headway-options', array('HeadwayAdminPages', 'options'));
					self::add_admin_submenu('Visual Editor', 'headway-visual-editor', array('HeadwayAdminPages', 'visual_editor'));
					self::add_admin_submenu('Templates', 'headway-templates', array('HeadwayAdminPages', 'templates'));
					self::add_admin_submenu('Tools', 'headway-tools', array('HeadwayAdminPages', 'tools'));
				break;
				
			}

		self::add_admin_submenu( 'What\'s New', 'headway-whats-new', array( 'HeadwayAdminPages', 'whats_new' ) );

	}


	public static function remove_system_submenus() {

		$parent_menu = HeadwayAdmin::parent_menu();

		remove_submenu_page( 'headway-' . $parent_menu['id'], 'headway-whats-new' );

	}
	
	
	public static function parent_menu() {
		
		$menu_setup = HeadwayOption::get('menu-setup', false, 'getting-started');
		
		/* Figure out the primary page */
		switch ( $menu_setup ) {
			
			case 'getting-started':
				$parent_menu = array(
					'id' => 'getting-started',
					'name' => 'Getting Started',
					'callback' => array('HeadwayAdminPages', 'getting_started')
				);
			break;
			
			case 'visual-editor':
				$parent_menu = array(
					'id' => 'visual-editor',
					'name' => 'Visual Editor',
					'callback' => array('HeadwayAdminPages', 'visual_editor')
				);
			break;
			
			case 'options':
				$parent_menu = array(
					'id' => 'options',
					'name' => 'Options',
					'callback' => array('HeadwayAdminPages', 'options')
				);
			break;
			
		}
		
		return $parent_menu;
		
	}
	
	
	public static function enqueue() {
		
		global $pagenow;

		/* Scripts Path */
		$script_folder = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? 'scripts-src' : 'scripts';

		/* Global */
		wp_enqueue_style('headway_admin_global', headway_url() . '/library/admin/css/admin-headway-global.css');
				
		/* General Headway admin CSS/JS */
		if ( strpos(headway_get('page'), 'headway') !== false ) {
			
			wp_enqueue_script('headway_jquery_scrollto', headway_url() . '/library/admin/js/jquery.scrollto.js', array('jquery'));
			wp_enqueue_script('headway_jquery_tabby', headway_url() . '/library/admin/js/jquery.tabby.js', array('jquery'));
			wp_enqueue_script('headway_jquery_qtip', headway_url() . '/library/admin/js/jquery.qtip.js', array('jquery'));
			wp_enqueue_script('headway_admin_js', headway_url() . '/library/admin/js/admin-headway.js', array('jquery', 'headway_jquery_qtip'));
			
			wp_enqueue_style('headway_admin', headway_url() . '/library/admin/css/admin-headway.css');
			wp_enqueue_style('headway_alerts', headway_url() . '/library/media/css/alerts.css');
			
		}

		/* Templates */
		if ( headway_get('page') == 'headway-templates' ) {

			wp_enqueue_script('headway_knockout', headway_url() . '/library/admin/js/knockout.js', array('jquery'));
			wp_enqueue_script('headway_admin_templates', headway_url() . '/library/admin/js/admin-templates.js', array('jquery'));

			wp_localize_script('headway_admin_templates', 'Headway', array(
				'ajaxURL' => admin_url('admin-ajax.php'),
				'security' => wp_create_nonce('headway-visual-editor-ajax'),

				'templates' => HeadwayTemplates::get_all(),
				'templateActive' => HeadwayTemplates::get_active(),

				'viewModels' => array()
			));

			add_thickbox();
			wp_enqueue_media();

		}

		/* Meta Boxes */			
		wp_enqueue_style('headway_admin_write', headway_url() . '/library/admin/css/admin-write.css');
		wp_enqueue_style('headway_alerts', headway_url() . '/library/media/css/alerts.css');
		wp_enqueue_script('headway_admin_write', headway_url() . '/library/admin/js/admin-write.js', array('jquery'));
				
		/* Auto Updater */
		if ( $pagenow === 'update-core.php' ) {
			
			wp_enqueue_script('headway_admin', headway_url() . '/library/admin/js/admin-headway.js', array('jquery'));
			wp_enqueue_style('headway_admin', headway_url() . '/library/admin/css/admin-headway.css');
			wp_enqueue_style('headway_alerts', headway_url() . '/library/media/css/alerts.css');
			
		}
				
	}
	
		
	public static function save_message() {
		
		global $headway_admin_save_message;
		
		if ( !isset($headway_admin_save_message) || $headway_admin_save_message == false ) 
			return false;

		echo '<div id="setting-error-settings_updated" class="updated settings-error"><p>' . $headway_admin_save_message . '</p></div>';
		
	}


	public static function save_error_message() {
		
		global $headway_admin_save_error_message;
		
		if ( !isset($headway_admin_save_error_message) || $headway_admin_save_error_message == false ) 
			return false;
		
		echo '<div id="setting-error-settings_error" class="error settings-error"><p>' . $headway_admin_save_error_message . '</p></div>';
		
	}
	
	
	public static function notice_no_widgets_or_menus() {
		
		global $pagenow;
		
		if ( $pagenow != 'widgets.php' && $pagenow != 'nav-menus.php' )
			return false;
			
		$grid_mode_url = add_query_arg(array('visual-editor' => 'true', 'visual-editor-mode' => 'grid'), home_url());
		
		//Show the widgets message if no widget blocks exist.
		if ( $pagenow == 'widgets.php' ) {
						
			$widget_area_blocks = HeadwayBlocksData::get_blocks_by_type('widget-area');
						
			if ( !empty($widget_area_blocks) )
				return;
				
			if ( !current_theme_supports('headway-grid') )
				return;
			
			echo '<div class="updated" style="margin-top: 15px;">
			       <p>Headway has detected that you have no Widget Area blocks.  If you wish to use the WordPress widgets system with Headway, please add a Widget Area block in the <a href="' . $grid_mode_url . '" target="_blank">Visual Editor: Grid</a>.</p>
			
					<style type="text/css">
						div.error.below-h2 { display: none; }
						div.error.below-h2 + p { display: none; }
					</style>
			    </div>';

		}
				
		//Show the navigation menus message if no navigation blocks exist.
		if ( $pagenow == 'nav-menus.php' ) {

			$navigation_blocks = HeadwayBlocksData::get_blocks_by_type('navigation');

			if ( !empty($navigation_blocks) )
				return;
				
			if ( !current_theme_supports('headway-grid') )
				return;

			echo '<div class="updated">
			       <p>Headway has detected that you have no Navigation blocks.  If you wish to use the WordPress menus system with Headway, please add a Navigation block in the <a href="' . $grid_mode_url . '" target="_blank">Visual Editor: Grid</a>.</p>
			    </div>';

		}
		
	}


	public static function theme_install_template_notice() {

		global $pagenow;

		if ( $pagenow != 'theme-install.php' )
			return false;

		echo '<div class="error">
				<h3>Are you trying to install a Headway Template?</h3>
			  	 <p>Please go to <a href="' . admin_url('admin.php?page=headway-templates') . '">Headway &rsaquo; Templates</a> to install Templates.</p>
			</div>';


	}


	public static function show_header($title = false) {
		
		echo '<div class="wrap headway-page">';

		if ( $title ) 
			echo '<h2>' . $title . '</h2>';
			
	}


	public static function show_footer() {

		echo '</div><!-- #wrapper -->';

	}


	public static function row_action_visual_editor($actions, $item) {

		if ( !HeadwayCapabilities::can_user_visually_edit() )
			return $actions;

		/* Post */
		if ( isset($item->post_status) ) {

			if ( $item->post_status != 'publish' )
				return $actions;

			$post_type = get_post_type_object($item->post_type);

			if ( !$post_type->public )
				return $actions;

			$layout_id = 'single' . HeadwayLayout::$sep . $item->post_type . HeadwayLayout::$sep . $item->ID;

			if ( get_option('show_on_front') === 'page' ) {

				if ( $item->ID == get_option('page_on_front') )
					$layout_id = 'front_page';

				if ( $item->ID == get_option('page_for_posts') )
					$layout_id = 'index';

			}

		/* Category */
		} elseif ( isset($item->term_id) && $item->taxonomy == 'category' ) {

			$layout_id = 'archive' . HeadwayLayout::$sep . 'category' . HeadwayLayout::$sep . $item->term_id;

		/* Post Tag */
		} elseif ( isset($item->term_id) && $item->taxonomy == 'post_tag' ) {

			$layout_id = 'archive' . HeadwayLayout::$sep . 'post_tag' . HeadwayLayout::$sep . $item->term_id;

		/* Taxonomy */
		} elseif ( isset($item->term_id) ) {

			$layout_id = 'archive' . HeadwayLayout::$sep . 'taxonomy' . HeadwayLayout::$sep . $item->taxonomy . HeadwayLayout::$sep . $item->term_id;

		}

		$visual_editor_url = home_url('/?visual-editor=true&ve-layout=' . urlencode($layout_id));

		$actions['hw-visual-editor'] = '<a href="' . $visual_editor_url . '" title="Open in Headway Visual Editor" rel="permalink" target="_blank">Open in Visual Editor</a>';

		return $actions;

	}


	public static function tiny_mce_buttons($buttons) {

		array_unshift( $buttons, 'styleselect' );
		return $buttons;

	}


	public static function tiny_mce_formats($init_array) {

		$style_formats = array(
			array(
				'title' => 'Alerts',
				'items' => array(
					array(
						'title' => 'Red',
						'block' => 'div',
						'classes' => 'alert alert-red',
						'wrapper' => true
					),

					array(
						'title' => 'Yellow',
						'block' => 'div',
						'classes' => 'alert alert-yellow',
						'wrapper' => true
					),

					array(
						'title' => 'Green',
						'block' => 'div',
						'classes' => 'alert alert-green',
						'wrapper' => true
					),

					array(
						'title' => 'Blue',
						'block' => 'div',
						'classes' => 'alert alert-blue',
						'wrapper' => true
					),

					array(
						'title' => 'Gray',
						'block' => 'div',
						'classes' => 'alert alert-gray',
						'wrapper' => true
					)
				)
			)
		);

		if ( !empty( $init_array['style_formats'] ) ) {

			// json decode wp array
			$jd_orig_array = json_decode( $init_array['style_formats'], true );

			// merge new array with wp array (json encoded)
			$new_merge = json_encode( array_merge( $jd_orig_array, $style_formats ) );

			// populate back into function
			$init_array['style_formats'] = $new_merge;

		} else {

			$init_array['style_formats'] = json_encode($style_formats);

		}

		return $init_array;

	}


}