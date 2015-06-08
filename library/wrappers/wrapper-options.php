<?php
class HeadwayWrapperOptions extends HeadwayVisualEditorPanelAPI {


	public function register() {

		return true;

	}


	public function display($wrapper, $layout) {
		
		//Set block properties
		$this->wrapper = $wrapper;

		//Set up arguments
		$args = array(
			'wrapper' => $this->wrapper,
			'layoutID' => $layout
		);

		//Get and display panel
		$this->modify_arguments($args);
		$this->panel_content($args);
		
	}
	
	
	public $id = 'wrapper-options';
	public $name = 'Wrapper Options';
	public $mode = 'grid';
	
	public $tabs = array(
		'setup' => 'Grid &amp; Margins',
		'config' => 'Mirroring &amp; Config',
		'responsive' => 'Responsive'
	);
	
	public $inputs = array(
		'setup' => array(		
			'grid-setup-heading' => array(
				'type' => 'heading',
				'name' => 'grid-setup-heading',
				'label' => 'Grid'
			),

				'column-count' => array(
					'type' => 'slider',
					'name' => 'columns',
					'label' => 'Columns',
					'default' => 24,
					'tooltip' => 'Number of columns in the Grid.  Suggested values 9, 12, 16, and 24.<br /><br /><strong>Note:</strong> The wrapper must be empty of all blocks prior to changing the column count.  Either move the blocks to another wrapper or delete them if they are not needed.',
					'slider-min' => 6,
					'slider-max' => 24,
					'slider-interval' => 1,
					'callback' => 'wrapperOptionCallbackColumnCount(input, value);'
				),

				'use-independent-grid' => array(
					'type' => 'checkbox',
					'name' => 'use-independent-grid',
					'label' => 'Use Independent Grid',
					'tooltip' => 'Check this if you would like this wrapper to have different Grid settings than the Global Grid settings.',
					'callback' => 'wrapperOptionCallbackIndependentGrid(input, value);',
					'toggle' => array(
						'true' => array(
							'show' => array(
								'#input-column-width',
								'#input-gutter-width',
								'#input-grid-width'
							)
						),
						'false' => array(
							'hide' => array(
								'#input-column-width',
								'#input-gutter-width',
								'#input-grid-width'
							)
						)
					)
				),

				'column-width' => array(
					'type' => 'slider',
					'name' => 'column-width',
					'label' => 'Column Width',
					'default' => 20,
					'tooltip' => 'The column width is the amount of space inside of each column.  This is represented by the grey regions on the grid.',
					'unit' => 'px',
					'slider-min' => 10,
					'slider-max' => 80,
					'slider-interval' => 1,
					'callback' => 'wrapperOptionCallbackColumnWidth(input, value);'
				),
				
				'gutter-width' => array(
					'type' => 'slider',
					'name' => 'gutter-width',
					'label' => 'Gutter Width',
					'default' => 20,
					'tooltip' => 'The gutter width is the amount of space between each column.  This is the space between each of the grey regions on the grid.',
					'unit' => 'px',
					'slider-min' => 0,
					'slider-max' => 40,
					'slider-interval' => 1,
					'callback' => 'wrapperOptionCallbackGutterWidth(input, value);'
				),
				
				'grid-width' => array(
					'type' => 'integer',
					'unit' => 'px',
					'default' => 940,
					'name' => 'grid-width',
					'label' => 'Grid Width',
					'readonly' => true
				),

			'wrapper-margins-heading' => array(
				'type' => 'heading',
				'name' => 'wrapper-margins-heading',
				'label' => 'Wrapper Margins'
			),

				'wrapper-margin-top' => array(
					'type' => 'slider',
					'name' => 'wrapper-margin-top',
					'label' => 'Top Margin',
					'default' => 30,
					'tooltip' => 'Space in between the top of this wrapper and the top of the page or the wrapper above it.',
					'unit' => 'px',
					'slider-min' => 0,
					'slider-max' => 200,
					'slider-interval' => 1,
					'callback' => 'wrapperOptionCallbackMarginTop(input, value);',
					'data-handler-callback' => 'dataSetDesignEditorProperty({
						element: "wrapper", 
						property: "margin-top", 
						value: args.value.toString(), 
						specialElementType: "instance", 
						specialElementMeta: "wrapper-" + args.wrapper.id
					});'
				),

				'wrapper-margin-bottom' => array(
					'type' => 'slider',
					'name' => 'wrapper-margin-bottom',
					'label' => 'Bottom Margin',
					'default' => 0,
					'tooltip' => 'Space in between this wrapper and the bottom of the page.',
					'unit' => 'px',
					'slider-min' => 0,
					'slider-max' => 200,
					'slider-interval' => 1,
					'callback' => 'wrapperOptionCallbackMarginBottom(input, value);',
					'data-handler-callback' => 'dataSetDesignEditorProperty({
						element: "wrapper", 
						property: "margin-bottom", 
						value: args.value.toString(), 
						specialElementType: "instance", 
						specialElementMeta: "wrapper-" + args.wrapper.id
					});'
				)
		),
		
		'config' => array(
			'mirror-wrapper' => array(
				'type' => 'select',
				'name' => 'mirror-wrapper',
				'label' => 'Mirror Blocks From Another Wrapper',
				'default' => '',
				'tooltip' => 'By using this option, you can tell a wrapper to "mirror" another wrapper and all of its blocks.  This option is useful if you are wanting to share a wrapper&mdash;such as a header&mdash;across layouts on your site.  Select the wrapper you wish to mirror the content from in the select box to the right.',
				'options' => 'get_wrappers_select_options_for_mirroring()',
				'callback' => 'updateWrapperMirrorStatus(args.wrapper.id, value, input);'
			),

			'alias' => array(
				'type' => 'text',
				'name' => 'alias',
				'label' => 'Wrapper Alias',
				'default' => '',
				'tooltip' => 'Enter an easily recognizable name for the wrapper alias and it will be used throughout your site admin.  Aliases are used in the Design Editor, mirroring menu, and are a great way of keeping track of a specific wrapper.'
			),

			'css-classes' => array(
				'type' => 'text',
				'name' => 'css-classes',
				'callback' => 'updateWrapperCustomClasses(args.wrapper.id, value);',
				'label' => 'Custom CSS Class(es)',
				'default' => '',
				'tooltip' => 'Need more finite control?  Enter the custom CSS class selectors here and they will be added to the wrappers\'s class attribute. <strong>DO NOT</strong> put regular CSS in here.  Use the Live CSS editor for that.'
			)
		),

		'responsive' => array(
			
			array(
				'type' => 'repeater',
				'name' => 'responsive-wrapper-options',
				'label' => 'Configure Breakpoints.',
				'inputs' => array(

					array(
					'type' => 'select',
					'name' => 'breakpoint',
					'label' => 'Set Breakpoint',
					'options' => array(
						'' => 'Off - No Breakpoint',
						'custom' => 'Custom Width',
						'1824px' => '1824px - Very Large Screens',
						'1224px' => '1224px - Desktop and Laptop',
						'1024px' => '1024px - Popular Tablet Landscape',
						'768px' => '768px - Popular Tablet Portrait',
						'600px' => '600px - Popular Breakpoint in Headway',
						'568px' => '568px - iPhone 5 Landscape',
						'480px' => '480px - iPhone 3 & 4 Landscape',
						'320px' => '320px - iPhone 3 & 4 & 5 & Android Portrait'
					),
					'toggle'    => array(
						'' => array(
							'hide' => array(
								'#input-max-width'
							),
						),
						'custom' => array(
							'show' => array(
								'#input-max-width'
							),
						),
						'1824px' => array(
							'hide' => array(
								'#input-max-width'
							),
						),
						'1224px' => array(
							'hide' => array(
								'#input-max-width'
							),
						),
						'1024px' => array(
							'hide' => array(
								'#input-max-width'
							),
						),
						'768px' => array(
							'hide' => array(
								'#input-max-width'
							),
						),
						'600px' => array(
							'hide' => array(
								'#input-max-width'
							),
						),
						'568px' => array(
							'hide' => array(
								'#input-max-width'
							),
						),
						'480px' => array(
							'hide' => array(
								'#input-max-width'
							),
						),
						'320px' => array(
							'hide' => array(
								'#input-max-width'
							),
						)
					),
					'tooltip' => 'Select a screen width for these change to take effect.',
					'default' => ''
				),

					array(
						'type' => 'text',
						'name' => 'max-width',
						'label' => 'Custom Width',
						'tooltip' => 'Add px value as well. eg: 600px'
					),

					array(
						'type' => 'select',
						'name' => 'breakpoint-min-or-max',
						'label' => 'Min or Max width',
						'options' => array(
							'min' => 'Min Width (applies to screens that are wider than breakpoint)',
							'max' => 'Max Width (applies to screens that are narrower than breakpoint)'
						),
						'default' => 'max'
					),

					array(
						'name' => 'adaptive-heading',
						'type' => 'heading',
						'label' => 'Adaptive Options'
					),

					array(
						'type' => 'checkbox',
						'name' => 'stretch',
						'label' => 'Stretch blocks for mobile',
						'default' => false,
						'tooltip' => 'Enable this option to make all blocks in this wrapper stretch the full wrapper width on smaller screens. Blocks placed side by side may not look good on smaller screens as they fight for the horizontal space. Setting this option makes each block go full width at the set break point.'
					),

					array(
						'type' => 'checkbox',
						'name' => 'auto-center',
						'label' => 'Attempt to Center items',
						'default' => false,
						'tooltip' => 'This will attempt to center the elements in this block at the set breakpoint. NOTE: This will not work for all elements but give it a try and if it works for you then great. More complex html like menus will require custom code.'
					),

					array(
						'type' => 'checkbox',
						'name' => 'hide-wrapper',
						'label' => 'Hide this wrapper',
						'default' => false,
						'tooltip' => 'This will hide this wrapper for the set breakpoint.'
					)

				),
				'sortable' => true,
				'limit' => false,
				'callback' => ''
			)

		)
	);
	
	
	function modify_arguments($args = false) {

		/* Do not show Wrapper Setup tab in the Design Mode */
		if ( headway_post('mode') == 'design')  {

			unset($this->tabs['setup']);
			unset($this->inputs['setup']);

			return;

		}
		
		/* Grid Settings Defaults */
			$this->inputs['setup']['column-width']['default'] = HeadwayWrappers::$default_column_width; 
			$this->inputs['setup']['gutter-width']['default'] = HeadwayWrappers::$default_gutter_width; 
		/* End Grid Settings Defaults */

		/* Margins */
			$wrapper_instance_id = 'wrapper-' . $args['wrapper']['id'];

			$this->inputs['setup']['wrapper-margin-top']['value'] = HeadwayElementsData::get_special_element_property('wrapper', 'instance', $wrapper_instance_id, 'margin-top', HeadwayWrappers::$default_wrapper_margin_top, 'structure'); 
			$this->inputs['setup']['wrapper-margin-bottom']['value'] = HeadwayElementsData::get_special_element_property('wrapper', 'instance', $wrapper_instance_id, 'margin-bottom', HeadwayWrappers::$default_wrapper_margin_bottom, 'structure'); 
		/* End Margins */

		/* Wrapper Mirror Value */
		$this->inputs['config']['mirror-wrapper']['value'] = HeadwayWrappersData::is_wrapper_mirrored($args['wrapper']);
		
	}


	public function get_wrappers_select_options_for_mirroring() {
							
		$wrappers = HeadwayWrappersData::get_all_wrappers();

		$options = array('' => '&ndash; Do Not Mirror &ndash;');
		
		//If there are no wrappers to mirror, then just return the Do Not Mirror option.
		if ( empty($wrappers) || !is_array($wrappers) )
			return $options;
		
		foreach ( $wrappers as $wrapper_id => $wrapper ) {
			
			/* If we can't get a name for the layout, then things probably aren't looking good.  Just skip this wrapper. */
			if ( !($layout_name = HeadwayLayout::get_name($wrapper['layout'])) )
				continue;

			/* Check for mirroring here */
			if ( HeadwayWrappersData::is_wrapper_mirrored($wrapper) )
				continue;

			$current_layout_suffix = ( $this->wrapper['layout'] == $wrapper['layout'] ) ? ' (Warning: Same Layout)' : null;
			$wrapper_alias = headway_get('alias', $wrapper['settings']) ? ' &ndash; ' . headway_get('alias', $wrapper['settings']) : null;

			/* Build info that shows if wrapper is fixed or fluid since a wrapper may not have alias and that can be confusing if it just says "Wrapper - Some Layout" over and over */
			$wrapper_info = array();

			if ( headway_fix_data_type($wrapper['settings']['fluid']) )
				$wrapper_info[] = 'Fluid';

			if ( headway_fix_data_type($wrapper['settings']['fluid-grid']) )
				$wrapper_info[] = 'Fluid Grid';

			$wrapper_info_str = $wrapper_info ? ' (' . implode( ', ', $wrapper_info ) . ')' : '';
			
			//Get alias if it exists, otherwise use the default name
			$options[$wrapper_id] = 'Wrapper' . $wrapper_alias . ' &ndash; ' . $layout_name . $wrapper_info_str  . $current_layout_suffix;
			
		}

		//Remove the current wrapper from the list
		if ( isset($this->wrapper['id']) && $this->wrapper['id'] )
			unset($options[$this->wrapper['id']]);

		return $options;
		
	}
	
	
}