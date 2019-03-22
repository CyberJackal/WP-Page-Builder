<?php
if( ! class_exists('LH_Page_Builder_Meta_Boxes') ){

	class LH_Page_Builder_Meta_Boxes {

		public function __construct() {
			add_action( 'admin_init', array( $this, '_custom_meta_boxes' ) );
		}

		function _custom_meta_boxes(){
			if ( isset( $_GET['post'] ) ) {
				$post_id = $_GET['post'];
			} elseif ( isset( $_POST['post_ID'] ) ) {
				$post_id = $_POST['post_ID'];
			} else {
				return;
			}
			if ( $post_id ) {
				$post_type = get_post_type( $post_id );
				$allowed = LH_Page_Builder::get_allowed_post_types();

				if( !in_array( $post_type, $allowed ) )
					return false;

				$meta_areas = LH_Page_Builder::get_page_meta_zones($post_id);

				if( $meta_areas ){

					foreach( $meta_areas as $id ) {			

						$meta_area = LH_Page_Builder::get_single_meta_zone( $id );
						if ( ! $meta_area ) {
							return;
						}
						$this->create_meta_area( $meta_area->id, $meta_area->zone_type, $meta_area->title );				
					}

				}

			}

			return false;
		}

		function create_meta_area( $id, $zone_type, $title = '' ) {

			$zone_type_obj = LH_Page_Builder::get_single_meta_zone_type( $zone_type );
			if ( ! $zone_type_obj ) {
				return;
			}
			$zone_title = empty( $title ) ? $zone_type_obj->title : $title;
			$for_post_type = $zone_type_obj->for_post_type;

			$prefix = 'pb_meta_zone_'.$id;
			$fields = array();

			$field_ids = LH_Page_Builder::get_zones_meta_fields( $zone_type_obj->id );
			if($field_ids){
				//echo '<style>pre{margin-left:200px;}</style>';
				foreach ($field_ids as $field_id ) {
					$field = LH_Page_Builder::get_meta_field( $field_id );
					$field_type = LH_Page_Builder::get_field_type_name( $field->field_type );
					$additional_parameters = $field->additional_parameters;

					$fields[] = $this->create_field_array( $field->title, $field->name, $field_type, $prefix, $additional_parameters );					
				}			

			}

			//$fields[] = $this->create_field_array( 'Include in menu?', 'in_menu', 'checkbox', $prefix, '' );
			array_push( $fields,
				array(
					'id'		=> $prefix.'_in_menu',
					'type'		=> 'checkbox',
					'choices'   => array(
						array(
				            'value'       => '1',
				            'label'       => __( 'Include in menu?', 'theme-text-domain' ),
				            'src'         => ''
				        ),
					)
				)
			);

			$fields = $this->add_remove_feild( $fields, $prefix, $id );

			$options = array(
				'id'          => 'pb_meta_zone_'.$id,
				'title'       => $zone_title,
				'pages'       => array( $for_post_type ),
				'context'     => 'normal',
				'priority'    => 'high',
				'fields'      => $fields,
			);
			ot_register_meta_box( $options );
			
		}

		function create_field_array( $title, $name, $type, $prefix, $extra ){

			$extra = $this->format_extra_data( $type, $extra );
			
			$prefix = $prefix.'_';
			$field = array(
				'label'		=> $title,
				'id'		=> $prefix.$name,
				'type'		=> $type,
			);

			if( $type == 'select' ){
				$field['choices'] = $extra;
			}elseif( $type == 'list-item' ){
				$field['settings'] = $extra;
			}

			return $field;
		}

		function add_remove_feild( $fields, $prefix, $id ){

			//if($fields){

				array_push( $fields,
					array(
						'label'		=> 'Delete',
						'id'		=> $prefix.'_delete',
						'type'		=> 'textblock',
						'desc'        => '<a class="remove_metabox" id="remove_'.$prefix.'" href="#">Remove Content Area</a>
						<div id="remove_metabox_dialog_'.$id.'" data-id="'.$id.'" data-page-id="'.$_GET['post'].'" title="Remove Content Area" style="display:none;">This process is irreversible. Continue?</div>',
					)
				);
			//}
			return $fields;
		}	

		function format_extra_data( $type, $data ){
			$formatted = array();

			switch( $type ){
				case 'select':
					$arr = explode("\n", $data);
					$temp = array();
					foreach ($arr as $k => $v) {
						$split = explode(":", $v);
						$temp['label'] = trim( $split[0] );
						$temp['value'] = trim( $split[1] );
						$formatted[] = $temp;
					}
					break;

				case 'list-item':
					$arr = explode("\n", $data);
					foreach ($arr as $k => $v) {
						$split = explode(":", $v);

						$formatted[] = $this->create_field_array( trim($split[0]), trim($split[1]), trim($split[2]), '', '' );
					}
					break;
			}

			return $formatted;
		}
		
	}
}

if( class_exists('LH_Page_Builder_Meta_Boxes') ){
	$lh_page_builder_meta_boxes = new LH_Page_Builder_Meta_Boxes();
}

