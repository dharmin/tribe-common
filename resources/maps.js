var map, tribe_map_geocoder, geocodes, tribe_map_bounds, markersArray = [], spinner;
tribe_map_geocoder = new google.maps.Geocoder();
tribe_map_bounds = new google.maps.LatLngBounds();

function tribe_process_geocoding( location, callback ) {

	var request = {
		address:location
	};

	tribe_map_geocoder.geocode( request, function ( results, status ) {
		if ( status == google.maps.GeocoderStatus.OK ) {
			callback( results );
			return results;
		}

		if ( status == google.maps.GeocoderStatus.ZERO_RESULTS ) {
			if( GeoLoc.map_view ) {
				spin_end();
			}
			return status;
		}

		return status;
	} );
}

jQuery( document ).ready( function ( $ ) {
	
	function tribe_test_location() {
		
		if( $( '#tribe-bar-geoloc' ).length ) {
			var tribe_map_val = $( '#tribe-bar-geoloc' ).val();
			if( tribe_map_val.length ) {
				if( $( "#tribe_events_filter_item_geofence" ).length )
					$( "#tribe_events_filter_item_geofence" ).show();
			} else {
				if( $( "#tribe_events_filter_item_geofence" ).length ) 
					$( "#tribe_events_filter_item_geofence" ).hide();
				if( $( '#tribe-bar-geoloc-lat, #tribe-bar-geoloc-lng' ).length )
					$( '#tribe-bar-geoloc-lat, #tribe-bar-geoloc-lng' ).val( '' );		
			}
		}
	}

	tribe_test_location();	

	$( '#tribe-geo-location' ).placeholder();	

	if( GeoLoc.map_view ) {
		
		var tribe_is_paged = tribe_ev.fn.get_url_param('tribe_paged');
		if( tribe_is_paged ) {
			tribe_ev.state.paged = tribe_is_paged;
		}		
		$( 'body' ).addClass( 'events-list' );
		tribe_ev.fn.tooltips();
	}
	
	
	
	if( GeoLoc.map_view && tribe_ev.data.params ) {
		
		var tp = tribe_ev.data.params;
		if ( tribe_ev.fn.in_params( tp, "geosearch" ) >= 0 ) {} else
			tp += '&action=geosearch';
		if ( tribe_ev.fn.in_params( tp, "tribe_paged" ) >= 0 ) {} else
			tp += '&tribe_paged=1';
		
		tribe_ev.state.params = tp;
					
		tribe_ev.state.do_string = false;
		tribe_ev.state.pushstate = false;	
		tribe_ev.state.popping = true;
		tribe_ev.fn.pre_ajax( function() { 
			tribe_map_processOption( null );	
		});
	} else if( GeoLoc.map_view ){
		
		tribe_ev.state.do_string = false;
		tribe_ev.state.pushstate = false;	
		tribe_ev.state.popping = false;
		tribe_ev.state.initial_load = true;
		tribe_ev.fn.pre_ajax( function() { 
			tribe_map_processOption( null );			
		});
	}
	
	if( tribe_ev.tests.pushstate && tribe_ev.tests.map_view() ) {	
		
		history.replaceState({
			"tribe_paged": tribe_ev.state.paged,
			"tribe_params": tribe_ev.state.params
		}, '', location.href);	

		$(window).on('popstate', function(event) {			
			
			var state = event.originalEvent.state;

			if( state ) {				
				tribe_ev.state.do_string = false;
				tribe_ev.state.pushstate = false;	
				tribe_ev.state.popping = true;
				tribe_ev.state.params = state.tribe_params;
				tribe_ev.state.paged = state.tribe_paged;
				tribe_ev.fn.pre_ajax( function() {
					tribe_map_processOption( null );
				});
				
				tribe_ev.fn.set_form( tribe_ev.state.params );					
			} 
		} );
	}
	
	var options = {
		zoom     :5,
		center   :new google.maps.LatLng( GeoLoc.center.max_lat, GeoLoc.center.max_lng ),
		mapTypeId:google.maps.MapTypeId.ROADMAP
	};

	if ( document.getElementById( 'tribe-geo-map' ) ) {
		map = new google.maps.Map( document.getElementById( 'tribe-geo-map' ), options );
		tribe_map_bounds = new google.maps.LatLngBounds();

		var minLatlng = new google.maps.LatLng( GeoLoc.center.min_lat, GeoLoc.center.min_lng );
		tribe_map_bounds.extend( minLatlng );

		var maxLatlng = new google.maps.LatLng( GeoLoc.center.max_lat, GeoLoc.center.max_lng );
		tribe_map_bounds.extend( maxLatlng );

		centerMap();
	}	

	$( "#tribe-geo-options" ).on( 'click', 'a', function ( e ) {
		spin_start();
		e.preventDefault();
		$( "#tribe-geo-options a" ).removeClass( 'tribe-option-loaded' );
		$( this ).addClass( 'tribe-option-loaded' );
		
		$( '#tribe-bar-geoloc' ).val( $( this ).text() );
		$( '#tribe-bar-geoloc-lat' ).val( geocodes[$( this ).attr( 'data-index' )].geometry.location.lat() );
		$( '#tribe-bar-geoloc-lng' ).val( geocodes[$( this ).attr( 'data-index' )].geometry.location.lng() );		
		
		
		if( tribe_ev.tests.pushstate ) {
			tribe_ev.fn.pre_ajax( function() { 			
				tribe_map_processOption( null );
				$( "#tribe-geo-options" ).hide();
			});
		} else {			
			tribe_ev.fn.pre_ajax( function() { 
				tribe_reload_old_browser();
			});
		}

	} );
	
	tribe_ev.fn.snap( '#tribe-geo-wrapper', '#tribe-geo-wrapper', '#tribe-events-footer .tribe-nav-previous a, #tribe-events-footer .tribe-nav-next a' );
		
	function tribe_generate_map_params() {
		tribe_ev.state.params = {
			action:'geosearch',				
			tribe_paged :tribe_ev.state.paged
		};	

		$( 'form#tribe-bar-form input' ).each( function () {
			var $this = $( this );
			if( $this.val().length && !$this.hasClass('tribe-no-param') ) {
				if( $this.is(':checkbox') ) {
					if( $this.is(':checked') ) {
						tribe_ev.state.params[$this.attr('name')] = $this.val();	
					}
				} else {
					tribe_ev.state.params[$this.attr('name')] = $this.val();	
				}					
			}						
		} );

		tribe_ev.state.params = $.param(tribe_ev.state.params);

		if( $('#tribe_events_filters_form').length ) {
			
			var tribe_filter_params = tribe_ev.fn.serialize( '#tribe_events_filters_form', 'input, select' );		
			if( tribe_filter_params.length )
				tribe_ev.state.params = tribe_ev.state.params + '&' + tribe_filter_params;			
		}
	}
	
	function tribe_reload_old_browser() {
		tribe_generate_map_params();		
		window.location = tribe_ev.data.cur_url + '?' + tribe_ev.state.params;
	}


	function tribe_map_processOption( geocode ) {
		spin_start();
		deleteMarkers();
		
		if( !tribe_ev.state.popping ) {
			tribe_generate_map_params();			
			tribe_ev.state.pushstate = false;
			if( !tribe_ev.state.initial_load ) {
				tribe_ev.state.do_string = true;
			} 			
		}	

			$.post( GeoLoc.ajaxurl, tribe_ev.state.params, function ( response ) {

				spin_end();
				tribe_ev.fn.enable_inputs( '#tribe_events_filters_form', 'input, select' );
				
				if ( response.success ) {
					
					tribe_ev.state.initial_load = false;
					
					tribe_ev.data.ajax_response = {
						'type':'tribe_events_ajax',
						'post_count':parseInt(response.total_count),
						'view':'map',
						'max_pages':response.max_pages,
						'page':tribe_ev.state.paged,
						'timestamp':new Date().getTime()
					};

					$( "#tribe-geo-results" ).html( response.html );					
					$( "#tribe-events-content" ).parent().removeAttr('id').find('.tribe-events-page-title').remove();	
					$( "#tribe-geo-results #tribe-events-header, #tribe-geo-results #tribe-events-footer" ).remove();	

					if ( response.max_pages > tribe_ev.state.paged ) {
						$( 'li.tribe-nav-next a' ).show();
					} else {
						$( 'li.tribe-nav-next a' ).hide();
					}
					if ( tribe_ev.state.paged > 1 ) {
						$( 'li.tribe-nav-previous a' ).show();
					} else {
						$( 'li.tribe-nav-previous a' ).hide();
					}

					$.each( response.markers, function ( i, e ) {
						tribe_map_addMarker( e.lat, e.lng, e.title, e.address, e.link );
					} );
					
					if( tribe_ev.tests.pushstate ) {

						if( tribe_ev.state.do_string ) {														
							history.pushState({	
								"tribe_paged": tribe_ev.state.paged,
								"tribe_params": tribe_ev.state.params
							}, '', tribe_ev.data.cur_url + '?' + tribe_ev.state.params);															
						}						

						if( tribe_ev.state.pushstate  ) {								
							history.pushState({
								"tribe_paged": tribe_ev.state.paged,
								"tribe_params": tribe_ev.state.params
							}, '', tribe_ev.data.cur_url);
						}
					
					}

					if ( response.markers.length > 0 ) {
						centerMap();
					}

				}

				spin_end();

			} );			
		
	}
	
	if ( GeoLoc.map_view ) {
		
		var center;
		
		$("#tribe-geo-map-wrapper").resize(function() {
			center = map.getCenter();
			google.maps.event.trigger(map, "resize");
			map.setCenter(center);			
		});		
		
		$( '#tribe-geo-wrapper' ).on( 'click', 'li.tribe-nav-next a', function ( e ) {
			e.preventDefault();
			tribe_ev.state.paged++;
			tribe_ev.state.popping = false;
			if( tribe_ev.tests.pushstate ) {
				tribe_ev.fn.pre_ajax( function() { 			
					tribe_map_processOption( null );
				});
			} else {			
				tribe_ev.fn.pre_ajax( function() { 
					tribe_reload_old_browser();
				});
			}
		} ).on( 'click', 'li.tribe-nav-previous a', function ( e ) {
			e.preventDefault();
			tribe_ev.state.paged--;
			tribe_ev.state.popping = false;
			if( tribe_ev.tests.pushstate ) {			
				tribe_ev.fn.pre_ajax( function() { 			
					tribe_map_processOption( null );
				});
			} else {
				tribe_ev.fn.pre_ajax( function() { 
					tribe_reload_old_browser();
				});
			}
		} );
		
	}
	
	function tribe_events_bar_mapajax_actions(e) {
		if ( tribe_events_bar_action != 'change_view' ) {
			e.preventDefault();
			tribe_ev.state.paged = 1;
			tribe_ev.state.popping = false;
			if( tribe_ev.tests.pushstate ) {	
				tribe_ev.fn.pre_ajax( function() { 						
					tribe_map_processOption( null );
				});
			} else {
				tribe_ev.fn.pre_ajax( function() { 						
					tribe_reload_old_browser();
				});
			}

		}
	}

	if ( GeoLoc.map_view  && $( 'form#tribe-bar-form' ).length ) {

		$( '.tribe-bar-settings button[name="settingsUpdate"]' ).on( 'click', function (e) {			
			tribe_events_bar_mapajax_actions(e);
			tribe_ev.fn.hide_settings();
		} );		
	}	
	
	if( GeoLoc.map_view  && $('#tribe_events_filters_form').length ) {		
		
		var $form = $('#tribe_events_filters_form');
		
		$form.on( 'submit', function ( e ) {
			if ( tribe_events_bar_action != 'change_view' ) {
				tribe_events_bar_mapajax_actions(e);		
			}
		} );

		function run_filtered_map_ajax() {
			tribe_ev.fn.disable_inputs( '#tribe_events_filters_form', 'input, select' );
			tribe_ev.state.paged = 1;
			tribe_ev.state.popping = false;
			if( tribe_ev.tests.pushstate ) {	
				tribe_ev.fn.pre_ajax( function() { 						
					tribe_map_processOption( null );
				});
			} else {
				tribe_ev.fn.pre_ajax( function() { 						
					tribe_reload_old_browser();
				});
			}
		}
		
		if( tribe_ev.tests.live_ajax() && tribe_ev.tests.pushstate ) {
			
			$form.find('input[type="submit"]').remove();
			
			$form.on( "slidechange", ".ui-slider", function() {
				tribe_ev.fn.setup_ajax_timer( function() {
					run_filtered_map_ajax();	
				} );				
			} );
			$form.on("change", "input, select", function(){
				tribe_ev.fn.setup_ajax_timer( function() {
					run_filtered_map_ajax();	
				} );
			});			
		}
	}
	

	function tribe_map_addMarker( lat, lng, title, address, link ) {
		var myLatlng = new google.maps.LatLng( lat, lng );

		var marker = new google.maps.Marker( {
			position:myLatlng,
			map     :map,
			title   :title
		} );

		var infoWindow = new google.maps.InfoWindow();

		var content_title = title;
		if ( link ) {
			content_title = $( '<div/>' ).append( $( "<a/>" ).attr( 'href', link ).text( title ) ).html();
		}

		var content = "Event: " + content_title;

		if ( address ) {
			content = content + "<br/>" + "Address: " + address;
		}

		infoWindow.setContent( content );

		google.maps.event.addListener( marker, 'click', function ( event ) {
			infoWindow.open( map, marker );
		} );

		markersArray.push( marker );
		tribe_map_bounds.extend( myLatlng );

	}

	function deleteMarkers() {
		if ( markersArray ) {
			for ( i in markersArray ) {
				markersArray[i].setMap( null );
			}
			markersArray.length = 0;
			tribe_map_bounds = new google.maps.LatLngBounds();
		}
	}

	function centerMap() {

		map.fitBounds( tribe_map_bounds );
		if ( map.getZoom() > 13 ) {
			map.setZoom( 13 );
		}

	}

	function spin_start() {
		$( '#tribe-events-footer, #tribe-events-header' ).find('.tribe-ajax-loading').show();
	}

	function spin_end() {
		$( '#tribe-events-footer, #tribe-events-header' ).find('.tribe-ajax-loading').hide();
	}
	if ( GeoLoc.map_view ) {
	
		$( 'form#tribe-bar-form' ).on( 'submit', function () {	
			if ( tribe_events_bar_action != 'change_view' ) {				
				tribe_ev.state.paged = 1;
				spin_start();

				var val = $( '#tribe-bar-geoloc' ).val();

				if ( val !== '' ) {

					deleteMarkers();
					$( "#tribe-geo-results" ).empty();
					$( "#tribe-geo-options" ).hide();
					$( "#tribe-geo-options #tribe-geo-links" ).empty();			

					tribe_process_geocoding( val, function ( results, selected_index ) {
						geocodes = results;

						spin_end();

						var lat = results[0].geometry.location.lat();
						var lng = results[0].geometry.location.lng();

						if ( lat )
							$( '#tribe-bar-geoloc-lat' ).val( lat );

						if ( lng )
							$( '#tribe-bar-geoloc-lng' ).val( lng );

						if ( geocodes.length > 1 ) {
							$( "#tribe-geo-options" ).show();

							for ( var i = 0; i < geocodes.length; i++ ) {
								$( "<a/>" ).text( geocodes[i].formatted_address ).attr( "href", "#" ).addClass( 'tribe-geo-option-link' ).attr( 'data-index', i ).appendTo( "#tribe-geo-options #tribe-geo-links" );
								tribe_map_addMarker( geocodes[i].geometry.location.lat(), geocodes[i].geometry.location.lng(), geocodes[i].formatted_address );
							}
							tribe_test_location();	
							centerMap();


						} else {
							if( tribe_ev.tests.pushstate ) {	
								tribe_test_location();	
								tribe_map_processOption( geocodes[0] );
							} else {								
								tribe_reload_old_browser();
							}						
						}

					} );

					return false;
				}

				if ( val === '' ) {
					$( '#tribe-bar-geoloc-lat' ).val( '' );
					$( '#tribe-bar-geoloc-lng' ).val( '' );
					$("#tribe-geo-options").hide();
					//We can show the map even if we don't get a geo query
					if( tribe_ev.tests.pushstate ) {	
						tribe_test_location();	
						tribe_map_processOption( null );
					} else {
						tribe_reload_old_browser();
					}	
					spin_end();
					return false;
					
				}
				return true;
			}
		} );
	}

} );