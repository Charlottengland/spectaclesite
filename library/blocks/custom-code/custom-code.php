<?php
headway_register_block('HeadwayCustomCodeBlock', headway_url() . '/library/blocks/custom-code');

class HeadwayCustomCodeBlock extends HeadwayBlockAPI {
	
	
	public $id = 'custom-code';
	
	public $name = 'Custom Code';
		
	public $options_class = 'HeadwayCustomCodeBlockOptions';

	public $description = 'Place in custom HTML, PHP, or even WordPress shortcodes into this block.';
	
	
	function content($block) {
		
		$content = parent::get_setting($block, 'content');	
			
		if ( $content != null )
			echo headway_parse_php(do_shortcode(stripslashes($content)));
		else
			echo '<p>There is no custom code to display.</p>';
		
	}
	
	
}


class HeadwayCustomCodeBlockOptions extends HeadwayBlockOptionsAPI {
	
	public $tabs = array(
		'content' => 'Content'
	);

	public $inputs = array(
		'content' => array(
			'content' => array(
				'type' => 'code',
				'mode' => 'html',
				'name' => 'content',
				'label' => 'Content',
				'default' => null
			)
		)
	);
	
}