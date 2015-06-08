<?php
headway_register_block('HeadwaySearchBlock', headway_url() . '/library/blocks/search');

class HeadwaySearchBlock extends HeadwayBlockAPI {


	public $id = 'search';

	public $name = 'Search';

	public $fixed_height = false;

	public $description = 'This will output the default search form';

	public $options_class = 'HeadwaySearchBlockOptions';


	function setup_elements() {

		$this->register_block_element(array(
			'id' => 'search_wrap',
			'name' => 'Search Form',
			'selector' => '.search-form'
		));

		$this->register_block_element(array(
			'id' => 'search_input',
			'name' => 'Search Input',
			'selector' => '.search-form input.field'
		));

		$this->register_block_element(array(
			'id' => 'search_button',
			'name' => 'Search Button',
			'selector' => '.search-form .submit'
		));

	}


	function content($block) {

		$search_query = get_search_query();

		$button_hidden_class = parent::get_setting( $block, 'show-button', true ) ? 'search-button-visible' : 'search-button-hidden';

		echo '<form method="get" id="searchform-' . $block['id'] . '" class="search-form ' . $button_hidden_class . '" action="' . esc_url(home_url('/')) . '">' . "\n";

			if ( parent::get_setting( $block, 'show-button', true ) ) {
				echo '<input type="submit" class="submit" name="submit" id="searchsubmit-' . $block['id'] . '" value="' . esc_attr( parent::get_setting( $block, 'search-button', 'Search' ) ) . '" />' . "\n";
			}

			printf('<div><input id="search-' . $block['id'] . '" class="field" type="text" name="s" value="%1$s" placeholder="%2$s" /></div>' . "\n",
				$search_query ? esc_attr($search_query) : '',
				esc_attr(parent::get_setting($block, 'search-placeholder', 'Enter search term and hit enter.'))
			);

		echo '</form>' . "\n";

	}

}


class HeadwaySearchBlockOptions extends HeadwayBlockOptionsAPI {

	public $tabs = array(
		'general' => 'General'
	);

	public $inputs = array(
		'general' => array(
			'search-placeholder' => array(
				'name' => 'search-placeholder',
				'label' => 'Input Text Placeholder',
				'type' => 'text',
				'tooltip' => 'The placeholder is text that will be shown in the Search input and immediately removed after you start typing in the search input.',
				'default' => 'Enter search term and hit enter.'
			),

			'show-button' => array(
				'name'    => 'show-button',
				'label'   => 'Show Search Button',
				'type'    => 'checkbox',
				'default' => true,
				'toggle' => array(
					'true' => array(
						'show' => '#input-search-button'
					),
					'false' => array(
						'hide' => '#input-search-button'
					)
				)
			),

			'search-button' => array(
				'name' => 'search-button',
				'label' => 'Button Text',
				'type' => 'text',
				'tooltip' => 'This will update the Search button text.',
				'default' => 'Search'
			)
		)
	);

}