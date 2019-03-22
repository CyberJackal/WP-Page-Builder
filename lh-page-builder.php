<?php
/*
Plugin Name: LH Page Builder
Description: Page builder used for creation of dynamic pages. Powered by Derek Herman's Option Tree
Version: 1.2
Author: paulbarnett
Author URI:
License: GPLv2 or later
Text Domain: lighthouse
*/

include_once plugin_dir_path( __FILE__ )."option-tree/ot-loader.php";
include_once plugin_dir_path( __FILE__ ) .'includes/db_setup.php';
require_once plugin_dir_path( __FILE__ ) .'includes/functions/add_page_template.php';

if( ! class_exists('LH_Page_Builder') ){

	class LH_Page_Builder {

		public function __construct() {

			register_activation_hook( __FILE__, array( $this, 'activate' ) );
			register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );

			//Setup the admin pages for page builder configuration
			add_action( 'admin_menu', array( $this, 'register_page_builder_admin_page' ) );

			//Setup option tree
			add_filter( 'ot_show_pages', '__return_false' );
			add_filter( 'ot_show_new_layout', '__return_false' );
			add_action( 'after_setup_theme',  array( $this, 'alx_load' ) );

			//Sets a global order for meta boxes
			add_action( 'save_post', array( $this, 'update_global_metabox_order' ) );
			add_action( 'current_screen', array( $this, 'set_user_metaboxes' ) );

			add_action( 'edit_form_after_title', array( $this, 'add_metabox_button' ) );

			add_action( 'admin_init', array( $this, 'hide_editor' ) );

			//Ajax callback funtions
			add_action( 'wp_ajax_new_metabox', array( $this, 'new_metabox_callback' ) );
			add_action( 'wp_ajax_delete_metabox', array( $this, 'delete_metabox_callback' ) );
			add_action( 'wp_ajax_delete_meta_field', array( $this, 'delete_meta_field_callback' ) );

			add_action('wp_ajax_delete_metazone_type', array( $this, 'delete_metzone_type_callback' ) );

			add_action( 'wp_ajax_update_metazone_type_order', array( $this, 'update_metazone_type_order_callback' ) );

			//Allow wysiwyg editor text boxes in metaboxes
			add_filter( 'ot_override_forced_textarea_simple', '__return_true' );
		}

		/**
		* Inital setup of the plugin upon activation
		*/
		public function activate() {
			//Sets up post types then flushes permalinks to fix urls
			flush_rewrite_rules();
			LH_Page_Builder_Database::setup_database();
			LH_Page_Builder_Database::install_data();

			$allowed = $this->get_allowed_post_types();
			if( !$allowed ){
				$this->update_allowed_post_types( array( 'page' ) );
			}
  	}

  	/**
		* Clean up when the plugin is deactivated
		*/
    public function deactivate(){
    	flush_rewrite_rules();
    }

		/**
		 * Loads the required scripts for page builders backend functionality
		 */
		function load_admin_scripts( $hook ) {
			$screen = get_current_screen();
			$allowed = $this->get_allowed_post_types();
			if ( in_array($screen->post_type, $allowed) && $screen->base == 'post' && isset( $_GET['action'] ) && $_GET['action'] == 'edit' ) {

				wp_enqueue_script( 'lh_page_builder_admin_scripts', plugin_dir_url( __FILE__ ) . 'includes/scripts/pb_admin_scripts.js', array( 'jquery', 'jquery-ui-dialog' ) );

				wp_enqueue_style ('wp-jquery-ui-dialog');

				wp_enqueue_script( 'thickbox' );
				wp_enqueue_style( 'thickbox' );

				wp_enqueue_style( 'lh_page_builder_admin_styles', plugin_dir_url( __FILE__ ) . 'includes/styles/pb_admin_styles.css' );

			} elseif ( $screen->base == 'toplevel_page_page-builder' || $screen->id == 'admin_page_page-builder-zone' ) {

				wp_enqueue_script( 'lh_page_builder_admin_scripts', plugin_dir_url( __FILE__ ) . 'includes/scripts/pb_admin_scripts.js', array( 'jquery', 'jquery-ui-sortable', 'jquery-ui-dialog' ) );

				wp_enqueue_style ('wp-jquery-ui-sortable', 'https://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css');

				wp_enqueue_style ('wp-jquery-ui-dialog');

				wp_enqueue_script( 'thickbox' );
				wp_enqueue_style( 'thickbox' );

				wp_enqueue_style( 'lh_page_builder_admin_styles', plugin_dir_url( __FILE__ ) . 'includes/styles/pb_admin_styles.css' );
			}
		}

		/**
		* Load in the option tree functions
		*/
		function alx_load() {
			load_template( plugin_dir_path( __FILE__ ) . 'includes/functions/meta-boxes.php' );
		}

		/**
		* Creates the menu items in the wordpress admin for page builder settings
		*/
		function register_page_builder_admin_page(){
			add_menu_page( 'Page Builder', 'Page Builder', 'manage_options', 'page-builder', array( $this, 'lh_page_builder_settings_page' ), 'dashicons-schedule', 81 );

			add_submenu_page( NULL, 'Page Builder Zone', 'Page Builder Zone', 'manage_options', 'page-builder-zone', array( $this, 'lh_page_builder_zone_page' ) );
		}

  	/**
		* Display admin settings page content
		*/
  	function lh_page_builder_settings_page(){

  		if( isset($_POST['save']) && $_POST['save'] == '1' ){
  			$this->update_allowed_post_types( $_POST['include'] );
  			$allowed = $_POST['include'];

  			echo '<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible">
				<p><strong>Settings saved.</strong></p>
				<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
				</div>';
  		}else{
  			$allowed = $this->get_allowed_post_types();
  		}

			$args = array(
				'public'   => true,
   				'show_ui' => true,
			);
			$post_types = get_post_types( $args ); ?>

  		<div class="wrap">

				<h1>Page Builder Settings</h1>

				<form method="post">

					<input type="hidden" name="save" value="1" />

					<label>Allow page builder for post types:</label>

					<?php foreach ( $post_types as $post_type ):
						//Skips attachment post type
						if( $post_type == 'attachment' )
							continue;
						?>

						<br /><input type="checkbox" name="include[]" value="<?php echo $post_type ?>" <?php checked( in_array( $post_type, $allowed ) ) ?> /><?php echo $post_type ?>

					<?php endforeach; ?>

					<br /><input type="submit" class="button button-primary" value="Save" />

				</form>

				<div>
					<form method="get" action="<?php echo get_admin_url() ?>admin.php">
						<input type="hidden" name="page" value="page-builder-zone" />
						<input type="hidden" name="zone_id" value="" />
						<input type="hidden" name="action" value="" />
					</form>

					<h3>Existing zone types</h3>
					<?php $zones = $this->get_all_meta_zone_types(); ?>
					<?php if( $zones ): ?>
						<table id="sortableTable"><tbody>
							<?php foreach( $zones as $zone ): ?>
								<tr id="zone-<?php echo $zone->id ?>">
									<td><?php echo $zone->title ?></td>
									<td> <a href="#" class="sortable-handle">Reorder</a> </td>
									<td> <a href="?page=page-builder-zone&zone_id=<?php echo $zone->id ?>">Edit</a> </td>
									<td> <a href="#" class="delete-zone">Delete</a> </td>
								</tr>
							<?php endforeach; ?>
						</tbody></table>
					<?php else: ?>
						<p></p>
					<?php endif; ?>
					<a href="<?php echo get_admin_url() ?>admin.php?page=page-builder-zone" class="button button-primary">Create New Zone Type</a>
				</div>

			</div>
	  	<?php
		}

		/**
		* Display the page for the creation and management of page builder zones
		*/
		function lh_page_builder_zone_page(){

			if( isset( $_POST['new_zone'] ) && $_POST['zone_id'] == '0' ){

				$new_zone_id = $this->add_new_meta_zone_type( $_POST['name'], $_POST['title'], $_POST['for_post_type'] );

			}elseif( isset( $_POST['new_zone'] ) ){

				$edited_zone_id = $this->update_meta_zone_type( $_POST['zone_id'], $_POST['name'], $_POST['title'], $_POST['for_post_type'] );
			}	?>

	  	<div class="wrap">

				<h1>Page Builder Zone Type</h1>

				<?php if( isset($new_zone_id) ){
					$zone_id = $new_zone_id;
				}elseif( isset($edited_zone_id) ){
					$zone_id = $edited_zone_id;
				}elseif(isset( $_GET['zone_id'] ) && $_GET['zone_id'] != ''){
					$zone_id = (int)$_GET['zone_id'];
				}elseif(isset( $_POST['zone_id'] ) && $_POST['zone_id'] != ''){
					$zone_id = (int)$_POST['zone_id'];
				}else{
					$zone_id = 0;
				}

				if( $zone_id > 0 ){
					$current_zone = $this->get_single_meta_zone_type( $zone_id );
				}
					?>

				<form method="post">

					<input type="hidden" name="zone_id" value="<?php echo $zone_id ?>" />
					<input type="hidden" name="new_zone" value="1" />

					<label for="name">Unique identifying name</label><br />
					<input type="text" name="name" value="<?php echo $current_zone->name ?>" required /><br />
					<br />

					<label for="title">Nice name to be shown in admin</label><br />
					<input type="text" name="title" value="<?php echo $current_zone->title ?>" required /><br />
					<br />

					<label for="for_post_type">Post type this zone will be active for</label><br />
					<?php $allowed = $this->get_allowed_post_types(); ?>
					<select name="for_post_type" required>
						<option value="">-- Select a post type --</option>
						<?php foreach($allowed as $post_type): ?>
							<option value="<?php echo $post_type ?>" <?php selected( $post_type, $current_zone->for_post_type ) ?>><?php echo $post_type ?></option>
						<?php endforeach; ?>
					</select><br />
					<br />

					<input type="submit" class="button button-primary" value="Save Zone Type" />

				</form>

				<?php if( isset($_POST['fields']) && count($_POST['fields']) > 0 ):
					$this->update_zone_meta_fields( $zone_id, $_POST['fields'] );
				endif;
				$fields = $this->get_zones_meta_fields( $zone_id );
				?>

				<?php if( $zone_id != 0 ): ?>

					<style>

						.ui-state-default .ui-icon{ float: left;}
						.ui-state-default i{ float: right; margin-right: 4px; }
						.ui-state-default{background: #EDEDED;}
					</style>

					<form method="post">

						<input type="hidden" name="zone_id" id="zone_id" value="<?php echo $zone_id ?>" />

						<ul id="sortable">
						  <?php if($fields): $i = 0; foreach ($fields as $field_id):

						  	$field = $this->get_meta_field( $field_id );
						  	$field_name = $this->get_field_type_name( $field->field_type );

						  	$placeholder = '';
								if( $field_name == 'select' ){
									$placeholder = 'Label : Value';
								}elseif($field_name == 'list-item'){
									$placeholder = 'Title : ID : Type';
								}

						  	echo '<li class="ui-state-default">
										<span class="ui-icon ui-icon-arrowthick-2-n-s"></span>
										<b>'.$field_name.'</b><i><a class="remove_field" id="remove_field_'.$field->id.'" href="#">Remove</a></i>
										<input type="hidden" name="fields['.$i.'][id]" class="fields" value="'.$field->field_type.'" />
										<table>
								  		<tr><td>field ID<br /><small>(must be unique for this zone)</small></td><td><input type="text" name="fields['.$i.'][name]" value="'.$field->name.'" class="widefat" required /></td></tr>
								  		<tr><td>field Title</td><td><input type="text" name="fields['.$i.'][title]" value="'.$field->title.'" class="widefat" /></td></tr>
								  		<tr><td>Additional Parameters</td><td><textarea name="fields['.$i.'][extra]" rows="5" cols="80" placeholder="'.$placeholder.'">'.$field->additional_parameters.'</textarea></td></tr>
								  	</table>
								  	<input type="hidden" name="fields['.$i.'][existing]" value="'.$field->id.'" />
									</li>';
								$i++;
						  endforeach;
						  	echo'<div id="remove_field_dialog" data-id="1" data-page-id="21" title="Remove Content Area" style="display:none;">This process is irreversible. Continue?</div>';
						  endif; ?>
						</ul>
						<input type="hidden" name="count" id="count" value="<?php echo $i ?>" />
						<input type="submit" class="button button-primary" value="Save fields" />

					</form>

					<select name="new_field_type" id="new_field_type">
						<option value="">-- Select a post type --</option>
						<?php $field_types = $this->get_all_meta_field_types();
							foreach( $field_types as $field_type ){
								echo '<option value="'.$field_type->id.'">'.$field_type->field_type.'</option>';
							}
						?>
					</select>
					<a class="button button-primary" id="add-to-order-list" href="#">Add new field to zone</a>

				<?php endif; ?>

			</div>
		  <?php
		}

		/**
		* Database functions for allowed post types
		*/
		function update_allowed_post_types( $allowed ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_post_types';
			$delete = $wpdb->query("TRUNCATE TABLE $table_name");

			if( $allowed ){
				foreach ($allowed as $post_type) {
					$wpdb->insert(
						$table_name,
						array(
							'post_type' => $post_type,
						),
						array(
							'%s',
						)
					);
				}
			}
		}


		static function get_allowed_post_types(){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_post_types';

			$allowed = $wpdb->get_col( "SELECT post_type FROM $table_name" );

			return $allowed;
		}

		/**
		* Database functions for meta fields
		*/
		function get_all_meta_field_types(){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_field_types';

			$fields = $wpdb->get_results( "SELECT * FROM $table_name" );

			return $fields;
		}


		static function get_field_type_name( $id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_field_types';

			$fields_name = $wpdb->get_var( $wpdb->prepare( "SELECT field_type FROM $table_name WHERE id = %d", $id ) );

			return $fields_name;
		}


		function new_meta_field( $fields_set, $zone_id ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_fields';

			$wpdb->insert(
				$table_name,
				array(
					'field_type' => $fields_set['id'],
					'name' => $fields_set['name'],
					'title' => $fields_set['title'],
					'additional_parameters' => $fields_set['extra'],
					'zone_id' => $zone_id
				),
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%d'
				)
			);

			return $wpdb->insert_id;
		}


		function update_meta_field( $fields_set, $meta_id ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_fields';

			$fields_set['extra'] = str_replace('\\', '', $fields_set['extra']);

			$wpdb->update(
				$table_name,
				array(
					'name' => $fields_set['name'],
					'title' => $fields_set['title'],
					'additional_parameters' => $fields_set['extra'],
				),
				array( 'id' => $meta_id ),
				array(
					'%s',
					'%s',
					'%s',
				),
				array( '%d' )
			);

			return $meta_id;
		}

		static function get_meta_field( $id ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_fields';

			$sql = "SELECT * FROM $table_name WHERE id = %d";
			$field = $wpdb->get_row( $wpdb->prepare( $sql, $id ) );

			return $field;
		}

		/**
		* Database functions for meta fields belonging to an individual zone
		*/
		function update_zone_meta_fields( $zone_id, $fields_sets ){
			global $wpdb;
			$zone_table_name = $wpdb->prefix . 'pb_zone_fields';
			$meta_table_name = $wpdb->prefix . 'pb_meta_fields';

			$fields = array();

			$current_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $zone_table_name WHERE zone_id = %d LIMIT 1", $zone_id ) );

			foreach( $fields_sets as $fields_set ){

				if( $fields_set['existing'] ){
					$fields[] = (int)$this->update_meta_field( $fields_set, $fields_set['existing'] );
				}else{
					$fields[] = (int)$this->new_meta_field( $fields_set, $zone_id );
				}

			}

			$fields_string = serialize($fields);

			if($current_row->id){

				$wpdb->update(
					$zone_table_name,
					array(
						'zone_id' => $zone_id,
						'meta_ids' =>  $fields_string
					),
					array( 'id' => $current_row->id ),
					array(
						'%d',
						'%s'
					),
					array( '%d' )
				);
			}else{

				$wpdb->insert(
					$zone_table_name,
					array(
						'zone_id' => $zone_id,
						'meta_ids' =>  $fields_string
					),
					array(
						'%d',
						'%s'
					)
				);
			}
		}


		static function get_zones_meta_fields( $zone_id ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_zone_fields';

			if ( ! $zone_id ) {
				$zone_id = '';
			}

			$sql = "SELECT meta_ids FROM $table_name WHERE zone_id = %d";
			$fields_sting = $wpdb->get_var( $wpdb->prepare( $sql, $zone_id ) );

			$fields = unserialize( $fields_sting );

			if( gettype( $fields ) == 'string' ){
				$fields_array[0] = $fields;
				return $fields_array;
			}
			return $fields;
		}

		/**
		* Database functions for types of zone
		*/
		function get_all_meta_zone_types(){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_zone_types';

			$sql = "SELECT * FROM $table_name ORDER BY sort_order ASC";
			$zones = $wpdb->get_results( $sql );

			return $zones;
		}


		function get_post_types_meta_zone_types( $post_type ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_zone_types';

			$sql = "SELECT * FROM $table_name WHERE for_post_type = %s ORDER BY sort_order ASC";
			$zones = $wpdb->get_results( $wpdb->prepare( $sql, $post_type ) );

			return $zones;
		}


		static function get_single_meta_zone_type( $id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_zone_types';

			if ( ! $id ) {
				return null;
			}

			$sql = "SELECT * FROM $table_name WHERE id = %d";
			$zone = $wpdb->get_row( $wpdb->prepare( $sql, $id ) );

			return $zone;
		}


		function delete_single_meta_zone_type( $id ){
			global $wpdb;
			//Check pb_meta_zones for all zones of this type
			$table_name = $wpdb->prefix . 'pb_meta_zones';
			$sql = "SELECT id FROM $table_name WHERE zone_type = %d";
			$zone_ids = $wpdb->get_results( $wpdb->prepare( $sql, $id ) );
			if( $zone_ids ){
				foreach ($zone_ids as $key => $zone_id) {
					//Delete instances of zone type
					$this->delete_meta_zone( $zone_id );
				}
			}
			//Remove zone type from database
			$r = $wpdb->delete( $wpdb->prefix . 'pb_meta_zone_types', array( 'id' => $id ), array( '%d' ) );
			if( $r ){
				return 1;
			}
			return 0;
		}


		function update_meta_zone_type_order( $zone_ids ){
			global $wpdb;
			if( $zone_ids ){
				foreach( $zone_ids as $i => $zone_id ){
					$table_name = $wpdb->prefix . 'pb_meta_zone_types';
					$wpdb->update(
						$table_name,
						array(
							'sort_order' => $i,
						),
						array( 'id' => $zone_id ),
						array(
							'%d',
						)
					);
				}
			}
		}


		function add_new_meta_zone_type( $name, $title, $for_post_type ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_zone_types';

			$existing_zones = $this->get_all_meta_zone_types();

			$name = str_replace(' ', '-', strtolower($name));

			$insert = $wpdb->insert(
				$table_name,
				array(
					'name' => $name,
					'title' => $title,
					'for_post_type' => $for_post_type,
					'sort_order' => count( $existing_zones ) + 1
				),
				array(
					'%s',
					'%s',
					'%s',
					'%d'
				)
			);

			return $wpdb->insert_id;
		}


		function update_meta_zone_type( $id, $name, $title, $for_post_type ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_zone_types';

			$updated = $wpdb->update(
				$table_name,
				array(
					'name' => $name,
					'title' => $title,
					'for_post_type' => $for_post_type
				),
				array( 'id' => $id ),
				array(
					'%s',
					'%s',
					'%s'
				),
				array( '%d' )
			);
			if( $updated >= 0 ){
				return $id;
			}

			return $updated;
		}

		/**
		* Database functions for individual zones
		*/
		function add_new_meta_zone( $type, $title ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_zones';

			$rows = $wpdb->insert(
				$table_name,
				array(
					'zone_type' => $type,
					'title' => $title
				),
				array(
					'%d',
					'%s'
				)
			);
			if( $rows > 0){
				return $wpdb->insert_id;
			}

			return false;
		}
		function delete_meta_zone( $id ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_zones';
			$wpdb->delete($table_name, array( 'id' => $id ), array( '%d' ) );
		}

		static function get_single_meta_zone( $id ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_meta_zones';

			$zone = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d LIMIT 1", $id ) );

			return $zone;
		}

		/**
		* Get meta zones belonging to a page
		*/
		static function get_page_meta_zones( $page_id ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_page_meta_zones';

			$zones = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_zones
				FROM $table_name
				WHERE post_id = %d",
			$page_id
			) );

			$zones = maybe_unserialize( $zones );

			return $zones;
		}

		function add_meta_zone_to_page( $page_id, $meta_zones ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_page_meta_zones';

			$zones = maybe_serialize( $meta_zones );

			$wpdb->insert(
				$table_name,
				array(
					'post_id' => $page_id,
					'meta_zones' => $zones
				),
				array(
					'%d',
					'%s'
				)
			);
		}
		function update_page_meta_zones( $page_id, $meta_zones ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'pb_page_meta_zones';

			$zones = maybe_serialize( $meta_zones );

			$wpdb->update(
				$table_name,
				array(
					'meta_zones' => $zones,
				),
				array( 'post_id' => $page_id ),
				array(
					'%s',
				),
				array( '%d' )
			);
		}

		function get_zone_field_types( $zone_id ){
			global $wpdb;
			$zones_table = $wpdb->prefix . 'pb_meta_zones';
			$fields_table = $wpdb->prefix . 'pb_meta_fields';

			$sql = "SELECT $fields_table.name as name
				FROM $zones_table
				JOIN $fields_table
				ON $zones_table.zone_type = $fields_table.zone_id
				WHERE $zones_table.id = %d";

			$fields = $wpdb->get_col( $wpdb->prepare( $sql, $zone_id ) );

			return $fields;
		}

		/**
		* AJAX Callback functions
		*/

		/**
		* Callback function for the new meta box button on page builder admin pages
		*/
	    function new_metabox_callback() {
	    	global $wpdb;

			$meta_id = intval( $_POST['metabox_type'] );
			$name = $_POST['metabox_name'];

			$new_zone = $this->add_new_meta_zone( $meta_id, $name );

			if( $new_zone ){
				$post_id = $_POST['page_id'];

				//get meta zones allready on page
				$current_zones = $this->get_page_meta_zones( $post_id );

				if( $current_zones ){
					//if meta zones exists then do an update
					array_push( $current_zones, $new_zone );
					$this->update_page_meta_zones( $post_id, $current_zones );

				}else{
					//else insert a new row
					$current_zones[0] = $new_zone;
					$this->add_meta_zone_to_page( $post_id, $current_zones );
				}

				//Get pb_page_meta_zones and add it to pb-meta-box-order
				$global_order = array();
				foreach( $current_zones as $zone ){
					$global_order[] = 'pb_meta_zone_'.$zone;
				}
				$pb_meta_box_order = $global_order;
				update_metadata( 'post', $post_id, 'pb-meta-box-order', $pb_meta_box_order );

			}

		}

		/**
		* Callback function for deleting meta box on the admin pages
		*/
		function delete_metabox_callback(){

			$meta_id = intval( $_POST['metabox_id'] );
			$page_id = intval( $_POST['page_id'] );
			if ( wp_is_post_revision( $page_id ) ) {
				return;
			}

			//Remove from postmeta pb-meta-box-order
			$meta_fields = get_post_meta( $page_id, 'pb-meta-box-order', true );
			if(($key = array_search('pb_meta_zone_'.$meta_id, $meta_fields)) !== false) {
		    	unset($meta_fields[$key]);
			}
			$meta_fields = array_values($meta_fields);
			$pb_meta_box_order = $meta_fields;
			update_post_meta( $page_id, 'pb-meta-box-order', $pb_meta_box_order );

			//Remove meta data for each field belonging to this metazone
			$fields = $this->get_zone_field_types( $meta_id );
			foreach($fields as $field){
				$meta_key = 'pb_meta_zone_'.$meta_id.'_'.$field;
				delete_post_meta( $page_id, $meta_key );
			}

			//Remove from table pb_page_meta_zones & pb_meta_zones
			$meta_zones = $this->get_page_meta_zones( $page_id );
			if(($key = array_search( $meta_id, $meta_zones)) !== false) {
		    	unset($meta_zones[$key]);
			}
			$meta_zones = array_values($meta_zones);
			if( empty($meta_zones) ){
				global $wpdb;
				$table_name = $wpdb->prefix . 'pb_page_meta_zones';
				$wpdb->delete( $table_name, array( 'post_id' => $page_id ), array( '%d' ) );
			}else{
				$this->update_page_meta_zones( $page_id, $meta_zones );
			}

			$this->delete_meta_zone( $meta_id );
		}

		function delete_meta_field_callback(){
			global $wpdb;
			$field_id = $_POST['field_id'];
			$field_name = $_POST['field_name'];
			$zone_id = $_POST['zone_id'];

			$table_name = $wpdb->prefix . 'pb_meta_fields';
			$wpdb->delete( $table_name, array( 'id' => $field_id ), array( '%d' ) );

			$zone_fields = $this->get_zones_meta_fields( $zone_id );
			if(($key = array_search( $field_id, $zone_fields)) !== false) {
		    	unset($zone_fields[$key]);
			}
			$zone_fields = serialize( array_values($zone_fields) );

			$table_name = $wpdb->prefix . 'pb_zone_fields';
			$wpdb->update( $table_name, array('meta_ids' => $zone_fields), array('zone_id' => $zone_id), array( '%s' ), array( '%d' ) );

			$meta_key = 'pb_meta_zone_'.$zone_id.'_'.$field_name;
			delete_post_meta_by_key( $meta_key );
		}

		function delete_metzone_type_callback(){
			$zonetype_id = (int)$_POST['zonetype_id'];
			$result = $this->delete_single_meta_zone_type( $zonetype_id );

			echo json_encode( $result );

			die();
		}

		function update_metazone_type_order_callback(){
			$zone_ids = $_POST['zone_ids'];
			$this->update_meta_zone_type_order( $zone_ids );
		}

		/**
		 * Updates the order of the meta boxes for a page in the global scope
		 */
		function update_global_metabox_order( $post_id ){

			if ( wp_is_post_revision( $post_id ) ) {
				return;
			}

			$user_id = get_current_user_id();

			$pb_meta_areas = array();
			$meta_zones = array();

			$found = false;

			$post_type = get_post_type( $post_id );
			$meta_value = get_metadata( 'user', $user_id, 'meta-box-order_' . $post_type, true );
			$prev_pb_meta_areas = get_post_meta( $post_id, 'pb-meta-box-order', true );
			if ( ! is_array( $prev_pb_meta_areas ) ) {
				$prev_pb_meta_areas = array();
			}

			if ( $meta_value ) {
				$meta_areas = explode(',', $meta_value['normal']);
				foreach ($meta_areas as $key => $meta_area) {
					// Only user page builder metaboxes
					if (strpos($meta_area,'pb_meta_zone') !== false) {
						if ( in_array( $meta_area, $prev_pb_meta_areas ) ) {
							$pb_meta_areas[] =  $meta_area;
							$meta_zones[] = str_replace('pb_meta_zone_', '', $meta_area);
							$found = true;
						}
					}
				}
				if ( $found ){
					$new_pb_meta_areas = array_unique(array_merge($pb_meta_areas,$prev_pb_meta_areas), SORT_REGULAR);
					update_post_meta( $post_id, 'pb-meta-box-order', $new_pb_meta_areas );

					$this->update_page_meta_zones( $post_id, $meta_zones );
				}
			}
		}

		/**
		 * Sets the order of the meta boxes on a page to match the golbal scope
		 */
		function set_user_metaboxes( $screen ){
			// Get the Post ID.
			if ( isset( $_GET['post'] ) ) {
				$post_id = $_GET['post'];
			} elseif ( isset( $_POST['post_ID'] ) ) {
				$post_id = $_POST['post_ID'];
			} else {
				return;
			}
			$user_id = get_current_user_id();
			$allowed = $this->get_allowed_post_types();

			if ( $post_id && in_array( $screen->post_type, $allowed ) && ! isset( $_POST['action'] ) ) {

    		$meta_value = get_metadata( 'post', $post_id, 'pb-meta-box-order', true );
    		if($meta_value){
    			$pb_meta_box_order = $meta_value;

    			$meta_value = get_metadata( 'user', $user_id, 'meta-box-order_'.$screen->post_type, true );
    			if( $meta_value && $meta_value['normal'] ){

    				$user_meta_box_order =  explode( ',', $meta_value['normal'] );
	    			foreach( $user_meta_box_order as $key => $value ){
	    				if (strpos($value,'pb_meta_zone') !== false) {
		    				unset($user_meta_box_order[$key]);
		    			}
	    			}
	    			$user_meta_box_order = array_values($user_meta_box_order);
    			}else{
    				$user_meta_box_order = array();
    			}
    			$final_array = array_merge( $pb_meta_box_order, $user_meta_box_order );

    			$meta_value['normal'] = implode( ',', $final_array );

    			update_metadata( 'user', $user_id, 'meta-box-order_'.$screen->post_type, $meta_value );

				}

			}
		}

		/**
		* Adds the new meta box button to admin pages
		*/
		function add_metabox_button(){
			global $post;

			$allowed = $this->get_allowed_post_types();
			if( in_array($post->post_type, $allowed) ){

				//Only show for pages with dynamic template selected
				if( $post->post_type == 'page' ){
					$template_file = get_post_meta($post->ID,'_wp_page_template',true);
					if( $template_file != '../templates/dynamic-page-template.php' && $template_file != get_stylesheet_directory() . '/dynamic-page-template.php' )
						return false;
				}

		    	$screen = get_current_screen();
		    	if( $screen->base == 'post' && $_GET['action'] == 'edit' ){
		    	?>

					<div style="text-align:center;">
						<a id="content-area-button"
							class="button button-primary thickbox"
							style="display:inline-block;position:relative;"
							href="#TB_inline?width=600&height=450&inlineId=select_new_metabox">
						Add new content area</a>
					</div>

					<div id="select_new_metabox" style="display:none;">

			    	<?php $meta_areas = $this->get_post_types_meta_zone_types( $post->post_type ); ?>

			    	<h4>Please enter the details of your new content area.</h4>

			    	<input type="hidden" name="page_id" id="page_id" value="<?php echo $_GET['post'] ?>" />

			    	<label for="new_metabox_type">Type of content area:</label><br />
			    	<select name="new_metabox_type" id="new_metabox_type">
			    		<option value="">Please choose one</option>
			    		<?php foreach ($meta_areas as $meta_area): ?>
			    			<option value="<?php echo $meta_area->id ?>"><?php echo $meta_area->title ?></option>
			    		<?php endforeach; ?>
			    	</select><br />
			    	<br /><br />
			    	<label for="new_metabox_type">Content area name:</label><br />
			    	<input type="text" id="new_metabox_name" name="new_metabox_name" value="" /><br />
			    	<br /><br />
			    	<input type="submit" id="submit_new_metabox" class="button button-primary" value="Add content area" />
					</div>

					<?php
				}elseif( $screen->base == 'post' && $screen->action == 'add' ){
					echo '<div style="text-align:center;">
						<a id="content-area-button"
							class="button button-primary disabled"
							style="display:inline-block;margin-bottom:10px;top:-40px;position:relative;"
							href="#">
						Add new content area</a>
						<span style="display:block;margin-bottom:40px;top:-40px;position:relative;" ><b><i>Please save the post before adding new content areas</i></b></span>
					</div>';
				}

			}

		}

		/**
		* Disable the editor on pages using the dynamic page template
		*/
		function hide_editor() {
			// Get the Post ID.
			if ( isset( $_GET['post'] ) ) {
				$post_id = $_GET['post'];
			} elseif ( isset( $_POST['post_ID'] ) ) {
				$post_id = $_POST['post_ID'];
			} else {
				return;
			}

			// Get the name of the Page Template file.
			$template_file = get_post_meta($post_id, '_wp_page_template', true);

		    if($template_file == '../templates/dynamic-page-template.php' || $template_file == get_stylesheet_directory() . '/dynamic-page-template.php'){ // edit the template name
		    	remove_post_type_support('page', 'editor');
		    }
		}

	} //End class
}

if( class_exists('LH_Page_Builder') ){
	$lh_page_builder = new LH_Page_Builder();
}
