<?php
class HeadwayGoogleFonts extends HeadwayWebFontProvider {


	public $id = 'google';

	public $name = 'Google Web Fonts';

	public $webfont_provider = 'google';

	public $load_with_ajax = true;


	public $sorting_options = array(
		'popularity' => 'Popularity',
		'trending' => 'Trending',
		'alpha' => 'Alphabetically',
		'date' => 'Date Added',
		'style' => 'Style'
	);


	protected $api_url = 'http://headwaythemes.com/api/google-fonts';

	protected $backup_api_url = 'http://cdn.headwaythemes.com/api/google-fonts';


	public function query_fonts($sortby = 'date', $retry = false) {
		
		$fonts_query = wp_remote_get(add_query_arg(array(
			'license' => 'legacy', 
			'sortby' => $sortby,
		), trailingslashit($this->api_url)), array(
			'timeout' => 20
		));

		/* If the original query to Headway cannot connect, find a way to proxy to Headway's CDN */
		if ( is_wp_error($fonts_query) ) {

			$fonts_query = wp_remote_get($this->backup_api_url . '/legacy/' . $sortby, array(
				'timeout' => 20
			));

		}

		return json_decode(wp_remote_retrieve_body($fonts_query), true);

	}


}
headway_register_web_font_provider('HeadwayGoogleFonts');