/* globals ajaxurl */

( function( $ ) {

	$( document ).ready(function() {

		$('#submit_new_metabox').on( 'click', function( e ){

			var meta_id = $('#new_metabox_type').find(":selected").val();
			var meta_name = $('#new_metabox_name').val();
			if ( meta_name === '' ) {
				meta_name = $('#new_metabox_type').find(":selected").html();
			}

			var page_id = $('#page_id').val();

			var data = {
				'action': 'new_metabox',
				'metabox_type': meta_id,
				'metabox_name': meta_name,
				'page_id': page_id
			};

			$.post(ajaxurl, data, function(response) {
				location.reload();
			});

		});

		$('.remove_metabox').on( 'click', function( e ){

			e.preventDefault();

			var id = $(this).attr('id').replace( 'remove_pb_meta_zone_', '' );

			$( "#remove_metabox_dialog_" + id ).dialog({
				dialogClass: "no-close",
				autoOpen: false,
				buttons: [
					{
						text: "OK",
						click: function() {
							var data_id = $(this).attr('data-id');
							var page_id = $(this).attr('data-page-id');
							remove_metabox( data_id, page_id );
						}
					},
					{
						text: "Cancel",
						click: function() {
							$( this ).dialog( "close" );
						}
					}
				]
			});

			$( "#remove_metabox_dialog_" + id ).dialog( "open" );

		});

		$( "#sortable" ).sortable();
    	$( "#sortable" ).disableSelection();

    	$( "#sortableTable tbody" ).sortable({
			handle: ".sortable-handle",
			update: function( event, ui ){
				//Send AJAX request to update list order
				var ids = [];
				$('#sortableTable tr').each(function(i, elem){
					ids.push( $(elem).attr('id').replace('zone-','') );
				});
				console.log('Send AJAX request to update list order');
				console.log(ids);
				var data = {
					'action': 'update_metazone_type_order',
					'zone_ids': ids
				};
				$.post(ajaxurl, data, function(response) {
					console.log(response);
				});
			}
		});
		$( "#sortableTable tbody" ).disableSelection();

		$(document).on('click', '.delete-zone', function(e){
			e.preventDefault();
			var r = confirm("This will permanently delete this zone. Continue?");
			if ( r === true ) {
				// Send AJAX request to delete zone type from database
				var id = $(this).closest('tr').attr('id').replace('zone-','');
				console.log('Send AJAX request to delete zone type from database. ' + id );
				var data = {
					'action': 'delete_metazone_type',
					'zonetype_id': id
				};
				$.post(ajaxurl, data, function(response) {
					if( response == 1 ){
						$('#zone-'+id).remove();
					}
				});
			}
		});

		$('#add-to-order-list').on( 'click', function( e ){
			e.preventDefault();

			if ( $('#new_field_type').val() !== '' ) {
				var count = $('#count').val();
				count++;

				var val = $('#new_field_type').val();
				var name = $('#new_field_type option:selected').text();
				var placeholder = '';
				if ( name === 'select' ){
					placeholder = 'Label : Value';
				} else if ( name === 'list-item' ) {
					placeholder = 'Title : ID : Type';
				}

				var html = '<li class="ui-state-default">'+
					'<span class="ui-icon ui-icon-arrowthick-2-n-s"></span>'+
					'<b>'+name+'</b>'+
					'<input type="hidden" name="fields['+count+'][id]" class="fields" value="'+val+'" />'+
					'<table>'+
			  		'<tr><td>field ID<br /><small>(must be unique for this zone)</small></td><td><input type="text" name="fields['+count+'][name]" value="" class="widefat" required /></td></tr>'+
			  		'<tr><td>field Title</td><td><input type="text" name="fields['+count+'][title]" value="" class="widefat" /></td></tr>'+
			  		'<tr><td>Additional Parameters</td><td><textarea name="fields['+count+'][extra]" rows="5" cols="80" placeholder="'+placeholder+'"></textarea></td></tr>'+
			  	'</table>'+
				'</li>';

				$('#sortable').append(html);

				$('#count').val(count);
			}
		});

		$('.remove_field').click(function(e){
			e.preventDefault();
			var field_id = $(this).attr('id').replace( 'remove_field_', '' );
			var zone_id = $('#zone_id').val();
			var	field_name = $(this).parent().parent().find('b').html();

			$('#remove_field_dialog').dialog({
			  dialogClass: "no-close",
			  autoOpen: false,
			  buttons: [
			    {
			      text: "OK",
			      click: function() {
			      	remove_meta_field( field_id, field_name, zone_id );
			      }
			    },
			    {
			      text: "Cancel",
			      click: function() {
			        $( this ).dialog( "close" );
			      }
			    }
			  ]
			});

			$( "#remove_field_dialog" ).dialog( "open" );
		});

	});

	function remove_metabox( id, page_id ){
		var data = {
			'action': 'delete_metabox',
			'metabox_id': id,
			'page_id': page_id
		};

		$.post(ajaxurl, data, function(response) {
			location.reload();
		});
	}

	function remove_meta_field( id, field_name, zone_id ) {

		var data = {
			'action': 'delete_meta_field',
			'field_id': id,
			'field_name': field_name,
			'zone_id': zone_id
		};

		$.post(ajaxurl, data, function(response) {
			location.reload();
		});
	}

} )( jQuery );
