<?php

if( ! class_exists('LH_Page_Builder_Database') ){

	class LH_Page_Builder_Database{

		/**
		* Build the tables that the plugin will use
		*/
		public static function setup_database(){

			global $wpdb;

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );			

			$charset_collate = $wpdb->get_charset_collate();


			$table_name = $wpdb->prefix . 'pb_meta_field_types';
			$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				field_type varchar(255) NOT NULL,				
				UNIQUE KEY id (id)
			) $charset_collate;";
			
			dbDelta( $sql );


			$table_name = $wpdb->prefix . 'pb_meta_fields';
			$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,				
				field_type varchar(255) NOT NULL,
				name varchar(255) NOT NULL,
				title varchar(255) NOT NULL,
				additional_parameters longtext,
				zone_id mediumint(9) NOT NULL,
				UNIQUE KEY id (id)
			) $charset_collate;";
			
			dbDelta( $sql );


			//Join table between pb_meta_fields and pb_meta_zone_types
			$table_name = $wpdb->prefix . 'pb_zone_fields';
			$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				zone_id mediumint(9) NOT NULL,
				meta_ids longtext,
				UNIQUE KEY id (id)
			) $charset_collate;";
			
			dbDelta( $sql );


			$table_name = $wpdb->prefix . 'pb_meta_zone_types';
			$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				title varchar(255) NOT NULL,
				for_post_type varchar(20) NOT NULL,
				sort_order int NOT NULL,
				UNIQUE KEY id (id)
			) $charset_collate;";
			
			dbDelta( $sql );


			$table_name = $wpdb->prefix . 'pb_meta_zones';
			$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				zone_type mediumint(9) NOT NULL,
				title varchar(255) NOT NULL,
				UNIQUE KEY id (id)
			) $charset_collate;";
		
			dbDelta( $sql );


			//Join table between pb_meta_zones and posts
			$table_name = $wpdb->prefix . 'pb_page_meta_zones';
			$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				meta_zones longtext,
				post_id mediumint(9) NOT NULL,
				UNIQUE KEY id (id)
			) $charset_collate;";

			dbDelta( $sql );


			$table_name = $wpdb->prefix . 'pb_post_types';
			$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				post_type varchar(20) NOT NULL,
				UNIQUE KEY id (id)
			) $charset_collate;";
			
			dbDelta( $sql );

		}

		/**
		* Add the inital data required in the database
		*/
		public static function install_data(){
			global $wpdb;

			$defaluts = array('text','textarea','upload','select','list-item');
			$table_name = $wpdb->prefix . 'pb_meta_field_types';

			foreach ( $defaluts as $defalut ) {

				$sql = "INSERT INTO $table_name (field_type)
				SELECT * FROM (SELECT '$defalut') AS tmp
				WHERE NOT EXISTS (
				    SELECT field_type FROM $table_name WHERE field_type = '$defalut'
				) LIMIT 1";

				$wpdb->query( $sql );
			}

			//Default zone types
			LH_Page_Builder_Database::title_and_text_zone_setup();

		} //End install_data

		public static function title_and_text_zone_setup(){
			global $wpdb;

			$zone_table_name = $wpdb->prefix . 'pb_meta_zone_types';

			$wpdb->insert( 
				$zone_table_name,
				array(
					'name' 				=> 'text-with-title',
					'title' 			=> 'Text with title',
					'for_post_type' 	=> 'page',
					'sort_order'		=> 0,
				),
				array(
					'%s',
					'%s',
					'%s',
					'%d',
				)
			);	

			$zone_id = $wpdb->insert_id;

			$field_table_name = $wpdb->prefix . 'pb_meta_fields';
			$zone_fields = array();

			$wpdb->insert($field_table_name,
				array(
					'field_type' => '1',
					'name' => 'title',
					'title' => 'Title',
					'additional_parameters' => '',
					'zone_id' => $zone_id,
				),
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%d'
				)
			);
			$zone_fields[] = $wpdb->insert_id;

			$wpdb->insert(
				$field_table_name,
				array(
					'field_type' => '2',
					'name' => 'text',
					'title' => 'Textarea',
					'additional_parameters' => '',
					'zone_id' => $zone_id,
				),
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%d'
				)
			);
			$zone_fields[] = $wpdb->insert_id;

			$zone_fields_table_name = $wpdb->prefix . 'pb_zone_fields';
			$wpdb->insert(
				$zone_fields_table_name,
				array(
					'zone_id' => $zone_id,
					'meta_ids' => maybe_serialize($zone_fields)
				),
				array( 
					'%d', 
					'%s'
				)
			);
		}

	} //End Class

} // End if