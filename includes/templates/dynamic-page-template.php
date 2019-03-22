<?php get_header(); ?>

<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

	<?php $post_id = ( get_the_ID() )? get_the_ID() : get_option('page_on_front') ; ?>

	<?php $meta_areas = LH_Page_Builder::get_page_meta_zones( $post_id ); ?>

	<div class="row page-heading">

		<div class="col-sm-6 col-sm-offset-3 center">

			<h1 class="page-title"><?php the_title() ?></h1>

			<?php echo wpautop(get_post_meta( $post_id, 'lh_subheading', true )); ?>

		</div>

		<div class="clearfix"></div>

	</div>

	<?php if( $meta_areas ): ?>

		<div class="row">

			<div class="col-xs-12">	

			<?php the_content() ?>		

			<?php foreach( $meta_areas as $id ) {		

				$zone = LH_Page_Builder::get_single_meta_zone( $id );
				if ( ! $zone ) {
					continue;
				}
				$zone_type = LH_Page_Builder::get_single_meta_zone_type( $zone->zone_type );
				if ( ! $zone_type ) {
					continue;
				}
				$feild_ids = LH_Page_Builder::get_zones_meta_fields( $zone_type->id );
				$data = array();
				$data['zone_id'] = $id;
				if( $feild_ids ){
					foreach($feild_ids as $feild_id){
						$feild = LH_Page_Builder::get_meta_field( $feild_id );
						$meta_key = 'pb_meta_zone_'.$zone->id.'_'.$feild->name;
						$data[$feild->name] =  get_post_meta( $post_id, $meta_key, true );
					}
				}
				set_query_var( 'data', $data );

				if( locate_template( '/pb_components/pb-'.$zone_type->name.'.php', false, false ) ){
					get_template_part('/pb_components/pb',$zone_type->name);
				}elseif( file_exists(plugin_dir_path( __FILE__ )."pb_components/pb-".$zone_type->name.'.php') ){
					//Try to load template from plugin
					include plugin_dir_path( __FILE__ )."pb_components/pb-".$zone_type->name.'.php';
				}
				
			} 
			
			?>

			</div>

			<div class="clearfix"></div>

		</div>

	<?php endif; ?>

<?php endwhile; else : ?>

	<p><?php _e( 'Sorry, no posts matched your criteria.' ); ?></p>

<?php endif; ?>			

<?php get_footer(); ?>
