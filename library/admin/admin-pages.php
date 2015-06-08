<?php
class HeadwayAdminPages {
	
	
	/**
	 * @see HeadwayAdmin::visual_editor_redirect
	 * 
	 * This function is here strictly for backup.  The PHP header location should replace all of this.
	 **/
	public static function visual_editor() {
		
		HeadwayAdmin::show_header('Headway Visual Editor');
			echo '<p>You are now being redirected.  If you are not redirected within 3 seconds, click <a href="' . home_url() . '/?visual-editor=true"><strong>here</strong></a>.</p>';
			echo '<meta http-equiv="refresh" content="3;URL=' . home_url() . '/?visual-editor=true">';
		HeadwayAdmin::show_footer();

	}
	
	
	public static function getting_started() {
		
		HeadwayAdmin::show_header();
		
			require_once HEADWAY_LIBRARY_DIR . '/admin/pages/getting-started.php';
			
		HeadwayAdmin::show_footer();
		
	}


	public static function whats_new() {

		require_once HEADWAY_LIBRARY_DIR . '/admin/pages/whats-new.php';

	}


	public static function templates() {

		HeadwayAdmin::show_header();

			require_once HEADWAY_LIBRARY_DIR . '/admin/pages/templates.php';

		HeadwayAdmin::show_footer();

	}

	
	public static function options() {
		
		HeadwayAdmin::show_header();
		
			require_once HEADWAY_LIBRARY_DIR . '/admin/pages/options.php';
			
		HeadwayAdmin::show_footer();
		
	}
	
	
	public static function tools() {
		
		HeadwayAdmin::show_header();
		
			require_once HEADWAY_LIBRARY_DIR . '/admin/pages/tools.php';
			
		HeadwayAdmin::show_footer();
		
	}
		

}