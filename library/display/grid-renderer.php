<?php

class HeadwayGridRenderer {
	private $wrapper;
	public $blocks = array();
	private $layout = array();
	private $rows = array();
	private $columns = array();
	private $column_positions = array();
	private $section_classes = array();
	private $column_top_tolerance = 30;
	private $row_top_tolerance = 20;

	public function __construct( $blocks, $wrapper ) {
		$this->blocks  = $blocks;
		$this->wrapper = $wrapper;
	}

	private function step_1_sort_blocks_by_position() {
		@uasort( $this->blocks, array( __CLASS__, 'uasort_blocks_by_top_to_left' ) );
	}

	private function uasort_blocks_by_top_to_left( $a, $b ) {
		if ( is_array( $a ) && is_string( reset( $a ) ) )
			$a = reset( $a );
		if ( is_array( $b ) && is_string( reset( $b ) ) )
			$b = reset( $b );
		if ( is_string( $a ) )
			$a = $this->blocks[ $a ];
		if ( is_string( $b ) )
			$b = $this->blocks[ $b ];
		$a_top  = $a['position']['top'];
		$a_left = $a['position']['left'];
		$b_top  = $b['position']['top'];
		$b_left = $b['position']['left'];
		if ( $a_top === $b_top && $a_left === $b_left )
			return 0;
		if ( $a_top === $b_top )
			return ( $a_left < $b_left ) ? - 1 : 1;

		return ( $a_top < $b_top ) ? - 1 : 1;
	}

	private function step_2_build_rows() {
		$prev_block = null;
		$row_count  = 0;
		foreach ( $this->blocks as $block_id => $block ) {
			$range_beginning = is_array( $prev_block ) ? $prev_block['position']['top'] - $this->row_top_tolerance : null;
			$range_end       = is_array( $prev_block ) ? $prev_block['position']['top'] + $this->row_top_tolerance : null;
			if ( $range_beginning && headway_in_numeric_range( $block['position']['top'], $range_beginning, $range_end ) ) {
				$this->rows[ $row_count ][] = $block['id'];
			} else {
				$row_count ++;
				$this->rows[ $row_count ] = array( $block['id'] );
			}
			$prev_block = $block;
		}
	}

	private function step_3_construct_columns() {
		$column_id = 0;
		foreach ( $this->blocks as $block_id => $block ) {
			if ( isset( $this->blocks_in_sub_columns ) && in_array( $block['id'], $this->blocks_in_sub_columns ) )
				continue;
			$column_id ++;
			$this->columns[ $column_id ] = array( $block_id );
			$sub_column_blocks           = $this->step_3a_extract_sub_columns( $block['id'] );
			if ( ! is_array( $sub_column_blocks ) )
				continue;
			$sub_column_row_id      = 1;
			$prev_sub_column_offset = 0;
			$existing_sub_columns   = array();
			foreach ( $sub_column_blocks as $sub_column_block_id ) {
				$sub_column_block = $this->blocks[ $sub_column_block_id ];
				$sub_column       = $sub_column_block['dimensions']['width'] == $block['dimensions']['width'] ? false : true;
				$this->step_3b_remove_block_from_columns( $sub_column_block_id );
				if ( isset( $prev_sub_column_top ) && $prev_sub_column_top + $prev_sub_column_height < $block['position']['top'] )
					if ( $sub_column_block['position']['top'] > $block['position']['top'] + $block['dimensions']['height'] )
						$sub_column_row_id ++;
				$sub_column_original = 'sub-column-' . $sub_column_block['dimensions']['width'] . ':' . $sub_column_block['position']['left'];
				$sub_column_id       = $sub_column_original . '-' . $sub_column_row_id;
				$origin_width        = (int) $block['dimensions']['width'];
				$origin_left         = (int) $block['position']['left'];
				if ( $prev_sub_column_offset + $sub_column_block['dimensions']['width'] - $origin_left > $origin_width ) {
					$prev_sub_column_offset = 0;
					if ( ! in_array( $sub_column_original, $existing_sub_columns ) )
						$sub_column_row_id ++;
				}
				if ( $sub_column ) {
					$existing_sub_columns[] = $sub_column_original;
					if ( ! isset( $this->columns[ $column_id ][ $sub_column_id ] ) )
						$this->columns[ $column_id ][ $sub_column_id ] = array();
					$this->columns[ $column_id ][ $sub_column_id ][] = $sub_column_block_id;
				} else {
					$this->columns[ $column_id ][] = $sub_column_block_id;
				}
				$prev_sub_column_offset = (int) $sub_column_block['dimensions']['width'] + (int) $sub_column_block['position']['left'];
				$prev_sub_column_top    = (int) $sub_column_block['position']['top'];
				$prev_sub_column_height = (int) $sub_column_block['dimensions']['height'];
			}
			$sub_columns = false;
			foreach ( $this->columns[ $column_id ] as $block_or_sub_column )
				if ( is_array( $block_or_sub_column ) )
					$sub_columns = true;
			if ( $sub_columns ) {
				$this->step_3c_modify_rows_for_sub_column_above_origin( $block, $this->blocks[ reset( $sub_column_blocks ) ], $sub_column_blocks );
			} elseif ( ! $sub_columns && isset( $this->blocks_in_sub_columns ) ) {
				foreach ( $this->columns[ $column_id ] as $block_or_sub_column )
					headway_remove_from_array( $this->blocks_in_sub_columns, $block_or_sub_column );
				foreach ( $this->columns as $test_column_id => $blocks_or_sub_columns ) {
					if ( $column_id == $test_column_id )
						continue;
					foreach ( $blocks_or_sub_columns as $index => $block_id_or_sub_column ) {
						if ( $block_id_or_sub_column == $block['id'] )
							unset( $this->columns[ $test_column_id ][ $index ] );
						if ( count( $this->columns[ $test_column_id ] ) === 0 )
							unset( $this->columns[ $test_column_id ] );
					}
				}
			}
			@uasort( $this->columns[ $column_id ], array( __CLASS__, 'uasort_blocks_by_top_to_left' ) );
			if ( isset( $this->columns[ $column_id ] ) )
				$this->columns[ $column_id ] = array_values( $this->columns[ $column_id ] );
		}
		$this->columns = array_values( $this->columns );
	}

	private function step_3a_extract_sub_columns( $origin_block_id ) {
		if ( isset( $this->blocks_in_sub_columns ) && in_array( $origin_block_id, $this->blocks_in_sub_columns ) )
			return false;
		$matches = array();
		$origin  = $this->blocks[ $origin_block_id ];
		foreach ( $this->blocks as $check ) {
			if ( $origin['id'] == $check['id'] )
				continue;
			if ( isset( $this->blocks_in_sub_columns ) && in_array( $check['id'], $this->blocks_in_sub_columns ) )
				continue;
			if ( $check['position']['left'] > $origin['position']['left'] + $origin['dimensions']['width'] )
				continue;
			if ( $check['position']['left'] < $origin['position']['left'] )
				continue;
			if ( $check['position']['left'] + $check['dimensions']['width'] > $origin['position']['left'] + $origin['dimensions']['width'] )
				continue;
			$matches[] = $check['id'];
		}
		if ( count( $matches ) === 0 )
			return null;
		$bad_matches   = array();
		$match_row_ids = array();
		foreach ( $matches as $match_id )
			$match_row_ids[ $match_id ] = $this->get_block_row( $match_id );
		foreach ( $match_row_ids as $match_block_id => $match_row_id ) {
			reset( $this->rows[ $match_row_id ] );
			while ( $current_match = current( $this->rows[ $match_row_id ] ) ) {
				if ( $current_match == $match_block_id ) {
					$neighbors          = headway_array_key_neighbors( $this->rows[ $match_row_id ], key( $this->rows[ $match_row_id ] ) );
					$left_block         = ( is_string( $neighbors['prev'] ) && isset( $this->blocks[ $neighbors['prev'] ] ) ) ? $this->blocks[ $neighbors['prev'] ] : null;
					$right_block        = ( is_string( $neighbors['next'] ) && isset( $this->blocks[ $neighbors['next'] ] ) ) ? $this->blocks[ $neighbors['next'] ] : null;
					$origin_block_left  = $origin['position']['left'];
					$origin_block_width = $origin['dimensions']['width'];
					if ( $left_block ) {
						$left_block_left  = $left_block['position']['left'];
						$left_block_width = $left_block['dimensions']['width'];
					}
					if ( $right_block ) {
						$right_block_left  = $right_block['position']['left'];
						$right_block_width = $right_block['dimensions']['width'];
					}
					if ( $left_block && $left_block_left + $left_block_width > $origin_block_left && $left_block_left < $origin_block_left )
						$bad_matches[ $match_block_id ] = 'left-block-outside-origin';
					if ( $right_block && $right_block_left < $origin_block_left + $origin_block_width )
						if ( $right_block_left + $right_block_width > $origin_block_left + $origin_block_width )
							$bad_matches[ $match_block_id ] = 'right-block-outside-origin';
					if ( isset( $bad_matches[ $match_block_id ] ) )
						headway_remove_from_array( $matches, $match_block_id );
					break;
				}
				next( $this->rows[ $match_row_id ] );
			}
		}
		$bad_matches       = array();
		$prev_row_block_id = $origin['id'];
		foreach ( $matches as $match_id ) {
			$match_block    = $this->blocks[ $match_id ];
			$prev_row_block = $this->blocks[ $prev_row_block_id ];
			$is_first_match = $match_id == reset( $matches );
			if ( $match_block['position']['top'] < $origin['position']['top'] )
				continue;
			if ( ! ( $prev_row_block['position']['top'] === $match_block['position']['top'] ) || $is_first_match ) {
				if ( $match_block['position']['top'] <= $prev_row_block['dimensions']['height'] + $prev_row_block['position']['top'] )
					$bad_matches[ $match_id ] = 'not below previous';
				if ( $match_block['position']['top'] > $prev_row_block['dimensions']['height'] + $prev_row_block['position']['top'] + $this->column_top_tolerance )
					$bad_matches[ $match_id ] = 'below previous block and tolerance';
			}
			if ( isset( $bad_matches[ $match_id ] ) )
				headway_remove_from_array( $matches, $match_id ); elseif ( $match_block['position']['top'] > $prev_row_block['position']['top'] )
				$prev_row_block_id = $match_id;
		}
		$reversed_matches  = array_reverse( $matches );
		$prev_row_block_id = $origin['id'];
		foreach ( $reversed_matches as $match_id ) {
			$match_block    = $this->blocks[ $match_id ];
			$prev_row_block = $this->blocks[ $prev_row_block_id ];
			$is_first_match = $match_id == reset( $reversed_matches );
			if ( $match_block['position']['top'] > $origin['position']['top'] )
				continue;
			if ( ! ( $prev_row_block['position']['top'] === $match_block['position']['top'] ) || $is_first_match ) {
				if ( $match_block['position']['top'] + $match_block['dimensions']['height'] > $prev_row_block['position']['top'] )
					$bad_matches[ $match_id ] = 'not above previous';
				if ( $match_block['position']['top'] + $match_block['dimensions']['height'] < $prev_row_block['position']['top'] - $this->column_top_tolerance )
					$bad_matches[ $match_id ] = 'above previous block and tolerance';
			}
			if ( isset( $bad_matches[ $match_id ] ) )
				headway_remove_from_array( $matches, $match_id ); elseif ( $match_block['position']['top'] < $prev_row_block['position']['top'] )
				$prev_row_block_id = $match_id;
		}
		if ( count( $matches ) === 0 )
			return null;
		$check_1 = false;
		$check_2 = false;
		foreach ( $this->blocks as $block ) {
			if ( in_array( $block['id'], $matches ) || $block['id'] == $origin_block_id )
				continue;
			$check_left         = $block['position']['left'];
			$check_top          = $block['position']['top'];
			$check_width        = $block['dimensions']['width'];
			$check_height       = $block['dimensions']['height'];
			$origin_width       = $origin['dimensions']['width'];
			$origin_left        = $origin['position']['left'];
			$origin_height      = $origin['dimensions']['height'];
			$origin_top         = $origin['position']['top'];
			$top_block_top      = $this->blocks[ reset( $matches ) ]['position']['top'];
			$top_block_height   = $this->blocks[ reset( $matches ) ]['dimensions']['height'];
			$height_check_block = ( $top_block_top < $origin_top ) ? $top_block_height : $origin_height;
			$top_check_block    = ( $top_block_top < $origin_top ) ? $top_block_top : $origin_top;
			if ( $check_left < $origin_left || $check_left >= $origin_left + $origin_width )
				$check_1 = true;
			if ( $check_top >= $top_check_block && $check_top < $top_check_block + $height_check_block )
				$check_2 = true;
		}
		if ( ! ( $check_1 && $check_2 ) )
			return false;
		$this->blocks_in_sub_columns = isset( $this->blocks_in_sub_columns ) ? array_merge( $this->blocks_in_sub_columns, $matches ) : $matches;

		return count( $matches ) > 0 ? $matches : null;
	}

	private function step_3b_remove_block_from_columns( $block_id_to_remove ) {
		foreach ( $this->columns as $column_id => $column_blocks ) {
			if ( in_array( $block_id_to_remove, $column_blocks ) ) {
				headway_remove_from_array( $this->columns[ $column_id ], $block_id_to_remove );
				if ( empty( $this->columns[ $column_id ] ) )
					unset( $this->columns[ $column_id ] );

				return true;
			}
		}

		return false;
	}

	private function step_3c_modify_rows_for_sub_column_above_origin( $origin_block, $first_block, array $sub_column_blocks ) {
		if ( $origin_block['position']['top'] < $first_block['position']['top'] )
			return false;
		foreach ( $this->rows as $row_id => $row_blocks ) {
			foreach ( $row_blocks as $row_block_id ) {
				if ( $row_block_id === $origin_block['id'] )
					$origin_block_row_id = $row_id; elseif ( $row_block_id === $first_block['id'] )
					$first_block_row_id = $row_id;
			}
		}
		$sub_column_blocks_above_origin = array();
		foreach ( $sub_column_blocks as $sub_column_block_id ) {
			$test_block = $this->blocks[ $sub_column_block_id ];
			if ( $test_block['position']['top'] + $test_block['dimensions']['height'] >= $origin_block['position']['top'] )
				continue;
			$sub_column_blocks_above_origin[] = $sub_column_block_id;
		}
		$first_block_position_in_row = array_search( $first_block['id'], $this->rows[ $first_block_row_id ] );
		headway_array_insert( $this->rows[ $first_block_row_id ], array( $origin_block['id'] ), $first_block_position_in_row );
		foreach ( $sub_column_blocks_above_origin as $block_id )
			$this->step_3d_remove_block_from_rows( $block_id );
		headway_remove_from_array( $this->rows[ $origin_block_row_id ], $origin_block['id'] );
		if ( count( $this->rows[ $first_block_row_id ] ) === 0 )
			unset( $this->rows[ $first_block_row_id ] );
		if ( count( $this->rows[ $origin_block_row_id ] ) === 0 )
			unset( $this->rows[ $origin_block_row_id ] );
	}

	private function step_3d_remove_block_from_rows( $block_id_to_remove ) {
		foreach ( $this->rows as $row_id => $row_blocks ) {
			if ( in_array( $block_id_to_remove, $row_blocks ) ) {
				headway_remove_from_array( $this->rows[ $row_id ], $block_id_to_remove );
				if ( empty( $this->rows[ $row_id ] ) )
					unset( $this->rows[ $row_id ] );

				return true;
			}
		}

		return false;
	}

	private function step_4_fetch_column_row_positions() {
		foreach ( $this->columns as $column => $blocks ) {
			foreach ( $blocks as $block_id ) {
				foreach ( $this->rows as $row => $blocks ) {
					if ( in_array( $block_id, $blocks ) ) {
						$this->column_positions[ $column ] = $row;
						break;
					}
				}
				if ( isset( $this->column_positions[ $column ] ) )
					break;
			}
		}
	}

	private function step_5_add_columns_to_rows() {
		foreach ( $this->column_positions as $column => $row ) {
			if ( ! isset( $this->columns[ $column ] ) )
				continue;
			$this->layout[ $row ][ $column ] = $this->columns[ $column ];
		}
		foreach ( $this->layout as $row => $row_columns )
			@uasort( $this->layout[ $row ], array( __CLASS__, 'uasort_columns_by_left' ) );
		ksort( $this->layout, SORT_NUMERIC );
	}

	private function uasort_columns_by_left( $a, $b ) {
		foreach ( $a as $block_or_sub_column_a )
			if ( is_string( $block_or_sub_column_a ) && $a = $block_or_sub_column_a )
				break;
		foreach ( $b as $block_or_sub_column_b )
			if ( is_string( $block_or_sub_column_b ) && $b = $block_or_sub_column_b )
				break;
		$a      = $this->blocks[ $a ];
		$b      = $this->blocks[ $b ];
		$a_left = $a['position']['left'];
		$b_left = $b['position']['left'];
		if ( $a_left === $b_left )
			return 0;

		return ( $a_left < $b_left ) ? - 1 : 1;
	}

	private function step_6_add_section_classes() {
		$row_count = 1;
		foreach ( $this->layout as $row_index => $columns ) {
			$this->section_classes[ $row_index ]              = array();
			$this->section_classes[ $row_index ]['classes'][] = 'row';
			$this->section_classes[ $row_index ]['classes'][] = 'row-' . $row_count;
			$row_count ++;
			$previous_column_offset = 0;
			$column_count           = 1;
			foreach ( $columns as $column_index => $column_contents ) {
				$this->section_classes[ $row_index ][ $column_index ] = array();
				foreach ( $column_contents as $block_index_or_sub_index => $block_id_or_sub_contents ) {
					if ( is_string( $block_id_or_sub_contents ) && isset( $this->blocks[ $block_id_or_sub_contents ] ) ) {
						$first_block_in_column = $this->blocks[ $block_id_or_sub_contents ];
						break;
					} else continue;
				}
				$this->section_classes[ $row_index ][ $column_index ]['classes'][]     = 'column';
				$this->section_classes[ $row_index ][ $column_index ]['classes'][]     = 'column-' . $column_count;
				$this->section_classes[ $row_index ][ $column_index ]['classes'][]     = 'grid-left-' . ( (int) $first_block_in_column['position']['left'] - (int) $previous_column_offset );
				$this->section_classes[ $row_index ][ $column_index ]['classes'][]     = 'grid-width-' . ( (int) $first_block_in_column['dimensions']['width'] );
				$this->section_classes[ $row_index ][ $column_index ]['width']         = ( (int) $first_block_in_column['dimensions']['width'] );
				$this->section_classes[ $row_index ][ $column_index ]['absolute-left'] = (int) $first_block_in_column['position']['left'];
				$previous_column_offset                                                = (int) $first_block_in_column['dimensions']['width'] + (int) $first_block_in_column['position']['left'];
				$column_count ++;
				$sub_column_count                                                    = 1;
				$prev_sub_column_offset                                              = 0;
				$this->section_classes[ $row_index ][ $column_index ]['sub-columns'] = array();
				foreach ( $column_contents as $sub_index => $sub_contents ) {
					if ( is_string( $sub_contents ) )
						continue;
					$sub_column_block          = $this->blocks[ reset( $sub_contents ) ];
					$main_column_absolute_left = $this->section_classes[ $row_index ][ $column_index ]['absolute-left'];
					$main_column_width         = $this->section_classes[ $row_index ][ $column_index ]['width'];
					if ( $prev_sub_column_offset + (int) $sub_column_block['dimensions']['width'] - $main_column_absolute_left > $main_column_width ) {
						$sub_column_count       = 1;
						$prev_sub_column_offset = 0;
					}
					$main_column_left_offset                                                                      = $sub_column_count === 1 ? $main_column_absolute_left : 0;
					$this->section_classes[ $row_index ][ $column_index ]['sub-columns'][ $sub_index ]['classes'] = array(
						'sub-column',
						'sub-column-' . $sub_column_count,
						'column',
						'column-' . $sub_column_count,
						'grid-width-' . $sub_column_block['dimensions']['width'],
						'grid-left-' . ( $sub_column_block['position']['left'] - $prev_sub_column_offset - $main_column_left_offset )
					);
					$prev_sub_column_offset                                                                       = (int) $sub_column_block['dimensions']['width'] + (int) $sub_column_block['position']['left'];
					$sub_column_count ++;
				}
				if ( count( $this->section_classes[ $row_index ][ $column_index ]['sub-columns'] ) === 0 )
					unset( $this->section_classes[ $row_index ][ $column_index ]['sub-columns'] );
			}
		}
	}

	private function get_block_row( $block_id ) {
		foreach ( $this->rows as $row_id => $row_blocks )
			foreach ( $row_blocks as $row_block_id )
				if ( $row_block_id == $block_id )
					return $row_id;

		return null;
	}

	private function step_7_finalize() {
		$this->finalized_layout = array();
		foreach ( $this->layout as $row_index => $row_columns ) {
			$this->finalized_layout[ $row_index ] = array(
				'type'    => 'row',
				'classes' => $this->section_classes[ $row_index ]['classes'],
				'columns' => array()
			);
			$current_row                          =& $this->finalized_layout[ $row_index ];
			foreach ( $row_columns as $column_index => $column_content ) {
				$current_row['columns'][ $column_index ] = array(
					'type'     => 'column',
					'width'    => $this->section_classes[ $row_index ][ $column_index ]['width'],
					'classes'  => $this->section_classes[ $row_index ][ $column_index ]['classes'],
					'contents' => array()
				);
				$current_column                          =& $current_row['columns'][ $column_index ];
				foreach ( $column_content as $block_or_sub_index => $block_id_or_sub_content ) {
					if ( ! is_array( $block_id_or_sub_content ) ) {
						$block                                             = $this->blocks[ $block_id_or_sub_content ];
						$current_column['contents'][ $block_or_sub_index ] = array(
							'type'  => 'block',
							'block' => $block
						);
						$block_settings                                    = headway_get( 'settings', $block );
						if ( headway_get( 'css-classes-bubble', $block_settings, false, true ) === true && headway_get( 'css-classes', $block_settings ) ) {
							$current_row['classes']    = array_merge( $current_row['classes'], explode( ' ', headway_get( 'css-classes', $block_settings ) ) );
							$current_column['classes'] = array_merge( $current_column['classes'], explode( ' ', headway_get( 'css-classes', $block_settings ) ) );
						}
					} else {
						$current_column['contents'][ $block_or_sub_index ] = array(
							'type'    => 'sub-column',
							'classes' => $this->section_classes[ $row_index ][ $column_index ]['sub-columns'][ $block_or_sub_index ]['classes'],
							'blocks'  => array()
						);
						$current_sub_column                                =& $current_column['contents'][ $block_or_sub_index ];
						foreach ( $block_id_or_sub_content as $sub_block_id ) {
							$this->blocks[ $sub_block_id ]['parent-column-width'] = $current_column['width'];
							$current_sub_column['blocks'][]                       = $this->blocks[ $sub_block_id ];
							$block_settings                                       = headway_get( 'settings', $this->blocks[ $sub_block_id ] );
							if ( headway_get( 'css-classes-bubble', $block_settings, false, true ) === true && headway_get( 'css-classes', $block_settings ) ) {
								$current_row['classes']        = array_merge( $current_row['classes'], explode( ' ', headway_get( 'css-classes', $block_settings ) ) );
								$current_column['classes']     = array_merge( $current_column['classes'], explode( ' ', headway_get( 'css-classes', $block_settings ) ) );
								$current_sub_column['classes'] = array_merge( $current_sub_column['classes'], explode( ' ', headway_get( 'css-classes', $block_settings ) ) );
							}
						}
					}
				}
			}
		}

		return $this->finalized_layout;
	}

	public function process() {
		$this->step_1_sort_blocks_by_position();
		$this->step_2_build_rows();
		$this->step_3_construct_columns();
		$this->step_4_fetch_column_row_positions();
		$this->step_5_add_columns_to_rows();
		$this->step_6_add_section_classes();
		$this->step_7_finalize();
	}

	public function render_grid() {
		$this->process();
		echo '<div class="grid-container clearfix">' . "\n";
		do_action( 'headway_grid_container_open', $this->wrapper );
		foreach ( $this->finalized_layout as $row_index => $row ) {
			echo "\n" . '<section class="' . implode( ' ', array_unique( array_filter( $row['classes'] ) ) ) . '">' . "\n";
			do_action( 'headway_block_row_open', $this->wrapper );
			foreach ( $row['columns'] as $column_index => $column ) {
				echo "\n" . '<section class="' . implode( ' ', array_unique( array_filter( $column['classes'] ) ) ) . '">' . "\n";
				do_action( 'headway_block_column_open', $this->wrapper );
				foreach ( $column['contents'] as $index => $block_or_sub_column ) {
					if ( headway_get( 'type', $block_or_sub_column ) == 'block' ) {
						HeadwayBlocks::display_block( headway_get( 'block', $block_or_sub_column ), 'grid-renderer' );
					} elseif ( headway_get( 'type', $block_or_sub_column ) == 'sub-column' ) {
						echo "\n" . '<section class="' . implode( ' ', array_unique( array_filter( $block_or_sub_column['classes'] ) ) ) . '">' . "\n";
						do_action( 'headway_block_sub_column_open', $this->wrapper );
						foreach ( $block_or_sub_column['blocks'] as $sub_block )
							HeadwayBlocks::display_block( $sub_block, 'grid-renderer' );
						do_action( 'headway_block_sub_column_column', $this->wrapper );
						echo "\n" . '</section><!-- .sub-column -->' . "\n";
					}
				}
				do_action( 'headway_block_column_close', $this->wrapper );
				echo "\n" . '</section><!-- .column -->' . "\n";
			}
			do_action( 'headway_block_row_close', $this->wrapper );
			echo "\n" . '</section><!-- .row -->' . "\n\n";
		}
		do_action( 'headway_grid_container_close', $this->wrapper );
		echo "\n" . '</div><!-- .grid-container -->' . "\n\n";
	}
}