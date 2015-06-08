<?php
class HeadwayCompiler {
	
	
	private static $accepted_formats = array('css', 'js');


	public static function init() {

		add_action('headway_visual_editor_save', array(__CLASS__, 'flush_cache'));
		add_action('headway_visual_editor_reset_layout', array(__CLASS__, 'flush_cache'));
		add_action('headway_visual_editor_delete_template', array(__CLASS__, 'flush_cache'));
		add_action('headway_visual_editor_assign_template', array(__CLASS__, 'flush_cache'));
		add_action('headway_visual_editor_unassign_template', array(__CLASS__, 'flush_cache'));

		add_action('publish_post', array(__CLASS__, 'flush_cache'));
		add_action('delete_post', array(__CLASS__, 'flush_cache'));

		add_action('headway_switch_skin', array(__CLASS__, 'flush_cache'));

		add_action('activate_plugin', array(__CLASS__, 'flush_cache_hard'));
		add_action('deactivate_plugin', array(__CLASS__, 'flush_cache_hard'));
		add_action('switch_theme', array(__CLASS__, 'flush_cache_hard'));

		add_action('headway_db_upgrade', array(__CLASS__, 'flush_cache_hard'));
		add_action('headway_activation', array(__CLASS__, 'flush_cache_hard'));
		add_action('headway_global_reset', array(__CLASS__, 'flush_cache_hard'));

		add_action('headway_snapshot_rollback', array(__CLASS__, 'flush_cache_hard'));

	}


	/**
	 * @param string
	 * @param string
	 * @param mixed
	 * @param bool
	 * 
	 * @uses HeadwayCompiler::enqueue_file()
	 * 
	 * @return bool
	 **/
	public static function register_file($args) {

		$defaults = array(
			'name' => null,
			'format' => null,
			'fragments' => array(),
			'dependencies' => array(),
			'footer-js' => true,
			'enqueue' => true,
			'require-hard-flush' => false,
			'iframe-cache' => false,
			'output-inline' => false
		);
		
		$args = array_merge($defaults, $args);

		if ( !$cache = get_transient('hw_compiler_template_' . HeadwayOption::$current_skin) )
			$cache = array();

		if ( is_ssl() )
			$args['name'] = $args['name'] . '-https';

		$args['fragments'] = array_map('headway_change_to_unix_path', $args['fragments']);
		$args['dependencies'] = array_map('headway_change_to_unix_path', $args['dependencies']);

		if ( !in_array($args['format'], self::$accepted_formats) )
			wp_die('<strong>' . $args['format'] .'</strong> is not an accepted filetype for the HeadwayCompiler class.');

		/* Prep possibly already cached settings for comparison */
			$already_cached = isset($cache[$args['name']]) ? $cache[$args['name']] : array();

			unset($already_cached['filename']);
			unset($already_cached['hash']);

		/* If file is not registered or fragments are not the same, add it to the DB. */
			if ( $already_cached != $args ) {
				
				$cache[$args['name']] = $args;

				$cache[$args['name']]['filename'] = null;
				$cache[$args['name']]['hash'] = null;

				//Update cache option
				if ( !set_transient('hw_compiler_template_' . HeadwayOption::$current_skin, $cache) )
					return false;

			}

		/* Output or Enqueue script */
			if ( $args['output-inline'] && $args['format'] != 'js' ) {

				return add_action('wp_print_styles', create_function('', 'return HeadwayCompiler::output_inline(\'' . $args['name'] . '\');'));

			} else if ( $args['enqueue'] ) {

				return self::enqueue_file($args['name'], $args['footer-js']);

			}

		return true;

	}


	public static function output_inline($file) {

		$cache = get_transient('hw_compiler_template_' . HeadwayOption::$current_skin);

		if ( !isset($cache[$file]) )
			return false;

		echo "\n\n" . '<style type="text/css" id="' . $cache[$file]['name'] . '">' . "\n" . self::combine_fragments($cache[$file]) . "\n" . '</style>' . "\n\n";

		return true;

	}
	
	/**
	 * @param string
	 * 
	 * @return string
	 **/
	public static function enqueue_file($file, $footer_js = true) {
				
		$cache = get_transient('hw_compiler_template_' . HeadwayOption::$current_skin);

		if ( $cache[$file]['format'] == 'js' )
			return wp_enqueue_script('headway-' . $file, self::get_url($file), false, false, false, $footer_js);
		elseif ( $cache[$file]['format'] == 'css' )
			return wp_enqueue_style('headway-' . $file, self::get_url($file));
			
		return false;	
			
	}
	
	
	/**
	 * @param string
	 * 
	 * @return string
	 **/
	public static function get_url($file) {
				
		$cache = get_transient('hw_compiler_template_' . HeadwayOption::$current_skin);

		if ( is_ssl() && strpos($file, '-https') === false )
			$file = $file . '-https';									
										
		//If the file isn't in the DB at all								
		if ( !isset($cache[$file]) )
			return false;
													
		//If cache exists
		if ( 
			self::caching_enabled() /* Make sure caching is enabled and possible */
			&& headway_get('filename', $cache[$file]) /* Filename in DB must be present */
			&& file_exists(HEADWAY_CACHE_DIR . '/' . headway_get('filename', $cache[$file]))  /* Cached file must be present */
			&& !(HeadwayRoute::is_visual_editor_iframe() && !headway_get('iframe-cache', $cache[$file])) /* Either not be iframe or if is iframe, iframe-cache must be true */
		) {
									
			return apply_filters('headway_compiler_file_url', headway_cache_url() . '/' . headway_get('filename', $cache[$file]));
		
		//Cache doesn't exist	
		} else {
			
			//If file doesn't exist, but we can still cache, let's cache the damn thing.
			if ( self::caching_enabled() && !(HeadwayRoute::is_visual_editor_iframe() && !headway_get('iframe-cache', $cache[$file])) ) {
												
				return self::cache_file($file) ? self::get_url($file) : null;
			
			//No caching available, now we have to use fallback method.
			} else {

				$query_args = array(
					'headway-trigger' => 'compiler', 
					'file' => $file,
					'layout-in-use' => HeadwayLayout::get_current_in_use(),
					'rand' => rand()
				);

				if ( HeadwayRoute::is_visual_editor_iframe() )
					$query_args['visual-editor-open'] = 'true';

				if ( HeadwayRoute::is_visual_editor_iframe() && headway_get('ve-preview') )
					$query_args['ve-preview'] = 'true';

				if ( HeadwayRoute::is_theme_preview() ) {
					$query_args['preview'] = '1';
					$query_args['template'] = 'headway';
					$query_args['stylesheet'] = 'headway';
					$query_args['preview_iframe'] = '1';
					$query_args['TB_iframe'] = 'true';
				}

				return apply_filters('headway_compiler_trigger_url', add_query_arg($query_args, home_url('/')));
								
			}
						
		}
		
	}
	
	
	/**
	 * @param string
	 * 
	 * @return bool
	 **/
	public static function cache_file($file) {
				
		$cache = get_transient('hw_compiler_template_' . HeadwayOption::$current_skin);
		
		//Get the current layout here directly and set is as GET since the output trigger can use POST, but this cannot.
		$_GET['layout-in-use'] = HeadwayLayout::get_current_in_use(); 
		$_GET['compiler-cache'] = true;
		
		$content = self::combine_fragments($cache[$file]);
		
		//If existing cache file exists, delete it.		
		self::delete_cache_file($cache[$file]['filename']);

		//If the file is set to only clear on a hard flush, then add that to the filename
		$hard_cache_suffix = $cache[$file]['require-hard-flush'] ? '-hard-cache' : null;
		
		//MD5 the contents that way we can check for differences down the road
		$cache[$file]['hash'] = md5($content);
		$cache[$file]['filename'] = $file . '-' . substr($cache[$file]['hash'], 0, 7) . $hard_cache_suffix . '.' . $cache[$file]['format'];

		//Build file
		$file_handle = @fopen(HEADWAY_CACHE_DIR . '/' . $cache[$file]['filename'], 'w');
		
		if ( !@fwrite($file_handle, $content) )
			return false;
	
		@chmod(HEADWAY_CACHE_DIR . '/' . $cache[$file]['filename'], 0755);
			
		@fclose($file_handle);

		set_transient('hw_compiler_template_' . HeadwayOption::$current_skin, $cache);

		return true;		
				
	}
	

	/**
	 * @return void
	 **/
	public static function output_trigger() {
		
		$file = headway_get('file');
		
		//No GET parameter set		
		if ( !$file )
			return false;

		headway_gzip();
		
		$cache = get_transient('hw_compiler_template_' . HeadwayOption::$current_skin);

		//File does not exist
		if ( !isset($cache[$file]))
			return;
			
		$format = $cache[$file]['format'];
		$expires = 60 * 60 * 24 * 30;
				
		header("Pragma: public");
		header("Cache-Control: max-age=".$expires);
		header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
		
		if ( $format == 'css' )
			header("Content-type: text/css");
		elseif ( $format == 'js' )
			header("content-type: application/x-javascript");
										
		echo self::combine_fragments($cache[$file]);
		
 	}


	/**
	 * @param array
	 * @param string
	 **/
	public static function combine_fragments($file) {
				
		extract($file);		

		$num_fragments = (int)count($fragments);

		$data = '';
		
		//Load dependencies if there are dependents
		if ( is_array($dependencies) && count($dependencies) > 0 ) {
			
			foreach ( $dependencies as $dependent ) {
				
				if ( !is_file($dependent) )
					continue;
					
				include_once $dependent;
				
			}
			
		}

		//Go through and merge the fragments
		foreach ( $fragments as $fragment_key => $fragment ) {
					
			//Determine if it's a function or file
			if ( !is_array($fragment) && strpos($fragment, '.') !== false && strpos($fragment, '()') === false && file_exists($fragment) ) {
				
				if ( filesize($fragment) === 0 ) 
					continue;

				$temp_handler = fopen($fragment, 'r');
				$data .= fread($temp_handler, filesize($fragment));
				fclose($temp_handler);
				
			//It's a function	
			} else {
				
				//Remove unneeded paratheses if is a string
				if ( is_string($fragment) )
					$fragment = str_replace('()', '', $fragment);
				
				//Check if method or function
				if ( !is_callable($fragment) ) 
					continue;
					
				$data .= call_user_func($fragment);
				
			}	
					
			if ( $format == 'js' && count($fragments) > 1 )
				$data .= "\n\n;";
			else
				$data .= "\n\n";
			
		}
		
		return self::format_content($data, $file);
		
	}
	
	
	/**
	 * @param string
	 * @param string
	 **/
	public static function format_content($content, $file) {
		
		extract($file);
		
		if ( $format == 'css' ) {
			
			$content = self::strip_whitespace($content);
			
		}
	
		$search = array(
			'%HEADWAY_URL%',
			'%HEADWAY_LIBRARY_URL%',
			'%VISUALEDITOR%',
			'%SITE_URL%',
			'%HOME_URL%'
		);

		$replace = array(
			headway_url(),
			headway_url() . '/library',
			headway_url() . '/library/visual-editor',
			site_url(),
			home_url()
		);

		$content = str_replace($search, $replace, $content);

		//SSL URL fixing
		if ( is_ssl() || strpos($file['name'], '-https') !== false )
			$content = str_replace('http://', 'https://', $content);

		return $content;
		
	}


		public static function strip_whitespace($content) {

			if ( defined('HEADWAY_COMPILER_STRIP_WHITESPACE') && HEADWAY_COMPILER_STRIP_WHITESPACE === false )
				return $content;

			$replace = array(
				"#/\*.*?\*/#s" => '',  // Strip comments.
				"#\s\s+#"      => ' ', // Strip excess whitespace.
			);
			
			$search = array_keys($replace);
			$content = preg_replace($search, $replace, $content);

			$replace = array(
				": "  => ":",
				"; "  => ";",
				" {"  => "{",
				" }"  => "}",
				", "  => ",",
				"{ "  => "{",
				";}"  => "}", // Strip optional semicolons.
				",\n" => ",", // Don't wrap multiple selectors.
				"\n}" => "}", // Don't wrap closing braces.
				"} "  => "}\n", // Put each rule on it's own line.
				"\n" => "" //Take out all line breaks
			);

			$search = array_keys($replace);

			return trim(str_replace($search, $replace, $content));

		}


	/**
	 * @return bool
	 **/
	public static function caching_enabled() {
		
		if ( defined('HEADWAY_DISABLE_CACHE') && HEADWAY_DISABLE_CACHE === true )
			return false;
			
		if ( defined('HEADWAY_FORCE_CACHE') && HEADWAY_FORCE_CACHE === true )
			return true;
		
		if ( defined('WP_DEBUG') && WP_DEBUG === true )
			return false;
			
		if ( HeadwayOption::get('disable-caching') )
			return false;

		if ( !self::can_cache() )
			return false;

		return true;
		
	}
	
	
	/**
	 * @return bool
	 **/
	public static function can_cache() {
		
		if ( !is_dir(HEADWAY_CACHE_DIR) || !is_writable(HEADWAY_CACHE_DIR) )
			return false;

		return true;
		
	}
	
	
	/**
	 * @return bool
	 **/
	public static function flush_cache($hard = false) {
		
		if ( self::can_cache() ) {
			
			if ( $hard ) {

				delete_transient('hw_compiler_template_' . HeadwayOption::$current_skin);

			} else {

				$cache = get_transient('hw_compiler_template_' . HeadwayOption::$current_skin);

				if ( is_array($cache) ) {

					foreach ( $cache as $cached_file_id => $cached_file )
						if ( !headway_get('require-hard-flush', $cached_file, false) )
							unset($cache[$cached_file_id]);

				}

				set_transient('hw_compiler_template_' . HeadwayOption::$current_skin, $cache);

			}
			
			$no_delete = array(
				'..',
				'.'
			);
				
			if ( $handle = opendir(HEADWAY_CACHE_DIR) ) {
			
			    while (false !== ($file = readdir($handle)) ) {
		       		
					if ( in_array($file, $no_delete) || (strpos($file, 'hard-cache') !== false && !$hard) )
						continue;
					
					@unlink(HEADWAY_CACHE_DIR . '/' . $file);
		
			    }
		
			    closedir($handle);
		
			}
			
		}
		
		self::flush_plugin_caches();

		wp_cache_flush();

		do_action('headway_flush_cache');

	}


	public static function flush_cache_hard() {

		self::flush_cache( true );

		do_action( 'headway_flush_cache_hard' );

	}
	
	
	/**
	 * @param string
	 * @param string
	 * 
	 * @return bool
	 **/
	public static function delete_cache_file($filename) {

		if ( !$filename || !file_exists(HEADWAY_CACHE_DIR . '/' . $filename) )
			return false;
		
		return @unlink(HEADWAY_CACHE_DIR . '/' . $filename);
		
	}
	
	
	/**
	 * Check if W3 Total Cache or if WP Super Cache are running.
	 *
	 * @return bool
	 **/
	public static function is_plugin_caching() {
		
		if ( class_exists('W3_Plugin_TotalCache') )
			return 'W3 Total Cache';
		
		elseif ( function_exists('prune_super_cache'))
			return 'WP Super Cache';

		elseif ( class_exists( 'WpeCommon' ) )
			return 'WPEngine';

		elseif ( class_exists('GD_System_Plugin_Cache_Purge') )
			return 'GoDaddy or MediaTemple';

		elseif ( isset($GLOBALS['quick_cache']) )
			return 'Quick Cache';

		else
			return false;
		
	}
	
	
	/**
	 * Flush Super Cache and W3 Total Cache
	 * 
	 * @return void
	 **/
	public static function flush_plugin_caches() {
		
		if ( function_exists('prune_super_cache') ) {
			
			global $cache_path;
			prune_super_cache($cache_path . 'supercache/', true );
			prune_super_cache($cache_path, true );
			
		}

		if ( class_exists('W3_Plugin_TotalCache') ) {
			
			if ( function_exists('w3_instance') )
				$w3_plugin_totalcache =& w3_instance('W3_Plugin_TotalCache');
			elseif ( is_callable(array('W3_Plugin_TotalCache', 'instance')) )
				$w3_plugin_totalcache =& W3_Plugin_TotalCache::instance();

			if ( method_exists($w3_plugin_totalcache, 'flush') )
				$w3_plugin_totalcache->flush();
			elseif ( method_exists($w3_plugin_totalcache, 'flush_all') )
				$w3_plugin_totalcache->flush_all();

			/* Flush varnish */
			if ( function_exists('w3tc_varnish_flush') )
				w3tc_varnish_flush();
		
		}

		if ( class_exists( 'WpeCommon' ) ) {

			if ( method_exists( 'WpeCommon', 'purge_memcached' ) )
				WpeCommon::purge_memcached();

			if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) )
				WpeCommon::purge_varnish_cache();

		}

		if ( class_exists('GD_System_Plugin_Cache_Purge') ) {

			if ( method_exists( 'GD_System_Plugin_Cache_Purge', 'do_ban_cache' ) )
				GD_System_Plugin_Cache_Purge::do_ban_cache();

		}

		if ( isset( $GLOBALS['quick_cache'] ) && method_exists($GLOBALS['quick_cache'], 'auto_clear_cache') ) {

			$GLOBALS['quick_cache']->auto_clear_cache();

		}
		
	}

}