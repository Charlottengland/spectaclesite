<?php
class HeadwayVisualEditorDisplay {


	public static function init() {

		Headway::load('visual-editor/layout-selector');

		//Load boxes
		Headway::load('api/api-box');

		require_once HEADWAY_LIBRARY_DIR . '/visual-editor/boxes/grid-wizard.php';
		require_once HEADWAY_LIBRARY_DIR . '/visual-editor/boxes/snapshots.php';

		//Load panels
		if ( current_theme_supports('headway-grid') ) {
			require_once HEADWAY_LIBRARY_DIR . '/visual-editor/panels/grid/setup.php';
		}

		if ( current_theme_supports('headway-design-editor') ) {
			Headway::load('visual-editor/panels/design/side-panel-design-editor', 'SidePanelDesignEditor');
		}

		//Put in action so we can run top level functions
		do_action('headway_visual_editor_display_init');

		//System for scripts/styles
		add_action('headway_visual_editor_head', array(__CLASS__, 'print_scripts'), 12);
		add_action('headway_visual_editor_head', array(__CLASS__, 'print_styles'), 12);

		//Meta
		add_action('headway_visual_editor_head', array(__CLASS__, 'robots'));

		//Enqueue Styles
		remove_all_actions('wp_print_styles'); /* Removes bad plugin CSS */
		add_action('headway_visual_editor_styles', array(__CLASS__, 'enqueue_styles'));
		add_action('headway_visual_editor_head', array(__CLASS__, 'output_inline_loading_css'), 10);

		//Enqueue Scripts
		remove_all_actions('wp_print_scripts'); /* Removes bad plugin JS */

		add_filter('clean_url', array(__CLASS__, 'require_js_attr' ), 10, 3 );
		add_action('headway_visual_editor_scripts', array(__CLASS__, 'require_js'));

		//Localize Scripts
		add_action('headway_visual_editor_scripts', array(__CLASS__, 'add_visual_editor_js_vars'));

		//Content
		add_action('headway_visual_editor_menu', array(__CLASS__, 'layout_selector'));
		add_action('headway_visual_editor_modes', array(__CLASS__, 'mode_navigation'));
		add_action('headway_visual_editor_menu_links', array(__CLASS__, 'menu_links'));
		add_action('headway_visual_editor_footer', array(__CLASS__, 'block_type_selector'));

		add_action('headway_visual_editor_panel_top_right', array(__CLASS__, 'panel_top_right'), 12);
		add_action('headway_visual_editor_menu_mode_buttons', array(__CLASS__, 'menu_mode_buttons'));

		//Prevent any type of caching on this page
		if ( !defined('DONOTCACHEPAGE') ) { 
			define('DONOTCACHEPAGE', true);
		}

		if ( !defined('DONOTMINIFY') ) { 
			define('DONOTMINIFY', true);
		}

	}


	public static function robots() {

		echo '<meta name="robots" content="noindex" />' . "\n";

	}


	public static function display() {

		do_action('headway_visual_editor_display');

		require_once HEADWAY_LIBRARY_DIR . '/visual-editor/template.php';

	}


	public static function require_js() {

		$script_folder = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? 'scripts-src' : 'scripts';

		wp_enqueue_script('headway-editor', headway_url() . '/library/visual-editor/' . $script_folder . '/deps/require-and-jquery.js');

	}


	public static function require_js_attr($good_protocol_url, $original_url, $_context) {

		$script_folder = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'scripts-src' : 'scripts';

		if ( false !== strpos( $original_url, 'require-and-jquery.js' ) ) {

			return $good_protocol_url . "' id='headway-editor' data-main='" . headway_url() . "/library/visual-editor/" . $script_folder . "/app.js";

		} else if ( false !== strpos( $original_url, 'js' ) ) {

			return $good_protocol_url . "' async='true";

		}

		return $good_protocol_url;

	}


	public static function enqueue_styles() {

		$styles = array(
			'reset' => headway_url() . '/library/media/css/reset.css',
			'open-sans',
			'dashicons',
			'headway_visual_editor' => headway_url() . '/library/visual-editor/css/editor.css'
		);

		wp_enqueue_multiple_styles($styles);

	}


	public static function output_inline_loading_css() {

		$css = '';
		$path = HEADWAY_LIBRARY_DIR . '/visual-editor/css-src/_loading.scss';

		/* Insure file exists */
			if ( !file_exists($path) )
				return false;

		/* Load in editor-loading.css */
			$temp_handler = fopen($path, 'r');
			$css .= fread($temp_handler, filesize($path));
			fclose($temp_handler);

		/* Echo content */
			echo "\n" . '<style type="text/css">' . HeadwayCompiler::strip_whitespace($css) . '</style>' . "\n\n";

	}


	public static function print_scripts() {

		/* Remove all other enqueued scripts from plugins that don't use 'headway_visual_editor_scripts' to reduce conflicts */
			global $wp_scripts;
			$wp_scripts = null;
			remove_all_actions('wp_print_scripts');

		echo "\n<!-- Scripts -->\n";

		do_action('headway_visual_editor_scripts');

		wp_print_scripts();

		echo "\n<!-- End Scripts -->\n";

	}


	public static function print_styles() {

		/* Remove all other enqueued styles from plugins that don't use 'headway_visual_editor_styles' to reduce conflicts */
			global $wp_styles;
			$wp_styles = null;
			remove_all_actions('wp_print_styles');

		echo "\n<!-- Styles -->\n";

		do_action('headway_visual_editor_styles');

		wp_print_styles();

		echo "\n<!-- End Styles -->\n";

	}


	public static function add_visual_editor_js_vars() {

		global $wp_scripts;

		//Gather the URLs for the block types
		$block_types = HeadwayBlocks::get_block_types();
		$block_type_urls = array();

		foreach ( $block_types as $block_type => $block_type_options )
			$block_type_urls[$block_type] = $block_type_options['url'];

		$current_layout_status = HeadwayLayout::get_status(HeadwayLayout::get_current());

		wp_localize_script('headway-editor', 'Headway', array(
			'ajaxURL' => admin_url('admin-ajax.php'),
			'security' => wp_create_nonce('headway-visual-editor-ajax'),

			'currentLayout' => HeadwayLayout::get_current(),
			'currentLayoutName' => HeadwayLayout::get_name(HeadwayLayout::get_current()),
			'currentLayoutCustomized' => $current_layout_status['customized'],
			'currentLayoutTemplate' => $current_layout_status['template'],
			'currentLayoutTemplateName' => HeadwayLayout::get_name('template-' . $current_layout_status['template']),

			'siteName' => get_bloginfo('name'),
			'siteDescription' => get_bloginfo('description'),
			'headwayURL' => get_template_directory_uri(),
			'scriptFolder' => ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'scripts-src' : 'scripts',
			'siteURL' => site_url(),
			'homeURL' => home_url(),
			'adminURL' => admin_url(),
			'frontPage' => get_option('show_on_front', 'posts'),

			'mode' => HeadwayVisualEditor::get_current_mode(),
			'designEditorSupport' => current_theme_supports('headway-design-editor'),
			'gridSupported' => current_theme_supports('headway-grid'),

			'disableTooltips' => HeadwayOption::get('disable-visual-editor-tooltips', false, false),

			'designEditorProperties' => HeadwayVisualEditor::is_mode('design') ? json_encode(HeadwayElementProperties::get_properties()) : json_encode(array()),
			'colorpickerSwatches' => HeadwaySkinOption::get('colorpicker-swatches', false, array()),
			'gridSafeMode' => HeadwayOption::get('grid-safe-mode', false, false),

			'ranTour' => json_encode(array(
				'legacy' => HeadwayOption::get('ran-tour', false, false),
				'grid' => HeadwayOption::get('ran-tour-grid', false, false),
				'design' => HeadwayOption::get('ran-tour-design', false, false)
			)),

			'blockTypeURLs' => json_encode($block_type_urls),
			'allBlockTypes' => json_encode($block_types),

			'defaultGridColumnCount' => HeadwayWrappers::$default_columns,
			'globalGridColumnWidth' => HeadwayWrappers::$global_grid_column_width,
			'globalGridGutterWidth' => HeadwayWrappers::$global_grid_gutter_width,

			'responsiveGrid' => HeadwayResponsiveGrid::is_enabled(),

			'touch' => (stripos($_SERVER['HTTP_USER_AGENT'], 'iPad') !== false) ? true : false,

			'layouts' => json_encode(array(
				'pages' => HeadwayLayoutSelector::get_basic_pages(),
				'shared' => HeadwayLayoutSelector::get_templates()
			)),


			'snapshots' => HeadwayDataSnapshots::list_snapshots(),

			'viewModels' => array(),

            'rJSCacheBuster' => ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? true : false
		));

	}


	//////////////////    Content   ///////////////////////


	public static function panel_top_right() {

		echo '<li id="minimize">
			<span title="Minimize Panel &lt;strong&gt;Shortcut: Ctrl + P&lt;/strong&gt;" class="tooltip-bottom-right">g</span>
		</li>';

	}


	public static function menu_mode_buttons() {

		switch ( HeadwayVisualEditor::get_current_mode() ) {

			case 'design':

				if ( current_theme_supports('headway-design-editor') ) {

					$tooltip = '
						<strong>Toggle Inspector</strong><br />
						<em>Shortcut:</em> Ctrl + I<br /><br />
						<strong>How to use:</strong> <em>Right-click</em> highlighted elements to style them.  Once an element is selected, you may nudge it using your arrow keys.<br /><br />
						The faded orange and purple are the margins and padding.  These colors are only visible when the inspector is active.';

					echo '<div class="menu-mode-buttons">';
						echo '<span class="menu-mode-button tooltip-bottom-right" id="toggle-inspector" title="' . esc_attr($tooltip) . '"></span>';
						echo '<span class="menu-mode-button tooltip-bottom-right" id="open-live-css" title="Open Live CSS Editor"></span>';
					echo '</div><!-- .menu-mode-buttons -->';

				}

			break;

		}

	}


	public static function block_type_selector() {

		$block_types = HeadwayBlocks::get_block_types();

		echo "\n". '<div class="block-type-selector block-type-selector-original" style="display: none;">' . "\n";

				foreach ( $block_types as $block_type_id => $block_type ) {

					echo '
						<div id="block-type-' . $block_type_id . '" class="block-type" title="' . $block_type['description'] . '">
							<h4 style="background-image: url(' . $block_type['url'] . '/icon.png);">' . $block_type['name'] . '</h4>

							<div class="block-type-description">
								<p>' . $block_type['description'] . '</p>
							</div>
						</div>
					';

				}

		echo '</div><!-- div.block-type-selector -->' . "\n\n";

	}


	public static function layout_selector() {

		require_once HEADWAY_LIBRARY_DIR . '/visual-editor/template-layout-selector.php';

	}


	public static function is_any_layout_child_customized($children) {

		if ( !is_array($children) || count($children) == 0 )
			return false;

		foreach ( $children as $id => $grand_children ) {

			$status = HeadwayLayout::get_status($id);

			if ( headway_get('customized', $status) || headway_get('template', $status) )
				return true;

			if ( is_array($grand_children) && count($grand_children) > 0 && self::is_any_layout_child_customized($grand_children) === true )
				return true;

		}

		return false;

	}


	public static function mode_navigation() {

		foreach ( HeadwayVisualEditor::get_modes() as $mode => $tooltip ) {

			$current = ( HeadwayVisualEditor::is_mode($mode) ) ? ' class="active"' : null;

			$mode_id = strtolower($mode);

			echo '
				<li' . $current . ' id="mode-'. $mode_id . '">
					<a href="' . home_url() . '/?visual-editor=true&amp;visual-editor-mode=' . $mode_id . '" title="' . esc_attr($tooltip) . '" class="tooltip-top-left">
						<span>' . ucwords($mode) . '</span>
					</a>
				</li>
			';

		}

	}


	public static function menu_links() {

		echo '<li id="menu-link-tools" class="has-submenu">
				<span>Tools</span>

				<ul>';

					// echo '<li id="tools-undo"><span>Undo <small>Ctrl + Z</small></span></li>
					// <li id="tools-redo"><span>Redo <small>Ctrl + Y</small></span></li>';

					if ( HeadwayVisualEditor::is_mode('grid') )
						echo '<li id="tools-grid-wizard"><span>Grid Wizard</span></li>';

					if ( HeadwayCompiler::can_cache() )
						echo '<li id="tools-clear-cache"><span>Clear Cache' . (!HeadwayCompiler::caching_enabled() ? ' (Disabled)' : '') . '</span></li>';

					echo '<li id="tools-tour"><span>Tour</span></li>
				</ul>

			</li>';


		echo '<li id="menu-link-admin" class="has-submenu">
				<span>Admin</span>

				<ul>
					<li><a href="' . admin_url()  . '" target="_blank">Dashboard</a></li>
					<li><a href="' . admin_url('widgets.php')  . '" target="_blank">Widgets</a></li>
					<li><a href="' . admin_url('nav-menus.php')  . '" target="_blank">Menus</a></li>
					<li><a href="' . admin_url('admin.php?page=headway-options')  . '" target="_blank">Headway Options</a></li>
					<li><a href="http://docs.headwaythemes.com" target="_blank">Documentation</a></li>
					<li><a href="http://support.headwaythemes.com" target="_blank">Support</a></li>
				</ul>

			</li>';


		echo '<li id="menu-link-view-site"><a href="' . home_url() . '" target="_blank">View Site</a></li>';

	}


}