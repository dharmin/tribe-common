<?php
/**
 * @for Week Grid Template
 * This file contains hooks and functions required to set up the week grid view.
 *
 * @package TribeEventsCalendarPro
 * @since  2.1
 * @author Modern Tribe Inc.
 *
 */

if ( !defined( 'ABSPATH' ) ) { die( '-1' ); }

if ( !class_exists( 'Tribe_Events_Pro_Week_Template' ) ) {
	class Tribe_Events_Pro_Week_Template extends Tribe_Pro_Template_Factory {

		protected $asset_packages = array( 'ajax-weekview' );
		public static $tribe_bar_args = array();
		public static $today;
		public static $start_of_week_date;
		public static $start_of_week;
		public static $week_length = 7;
		public static $week_days;
		public static $events;
		public static $current_day = -1;
		public static $event_id = -1;
		public static $prior_event_date = null;
		public static $daily_span_ids = array();
		public static $event_key_track = array();
		public static $loop_type = 'hourly';

		public function __construct() {

			parent::__construct();

			self::$prior_event_date = (object) array( 'EventStartDate'=>null, 'EventEndDate'=>null );
			self::$today = date_i18n( 'Y-m-d', strtotime( 'today' ) );
			self::$start_of_week_date = tribe_get_first_week_day();
			self::$start_of_week = get_option( 'start_of_week', 0 );

			// let's get this show on the road
			self::set_current_day( self::$start_of_week );
			self::set_week_days();
			self::setup_loop();

			// save tribe bar args
			if ( empty( self::$tribe_bar_args ) ) {
				foreach ( $_REQUEST as $key => $value ) {
					if ( $value && strpos( $key, 'tribe-bar-' ) === 0 && $key != 'tribe-bar-date' ) {
						self::$tribe_bar_args[$key] = $value;
					}
				}
			}
		}

		/**
		 * Set the notices used on week view
		 *
		 * @return void
		 * @since 3.0
		 * */
		function set_notices() {
			global $wp_query;
			// setup a search term for query or via ajax
			if ( !empty( $wp_query->query_vars['s'] ) ) {
				$search_term = $wp_query->query_vars['s'];
			} else if ( !empty( $_POST['tribe-bar-search'] ) ) {
					$search_term = $_POST['tribe-bar-search'];
				}

			if ( !empty( $search_term ) && !have_posts() ) {
				TribeEvents::setNotice( 'event-search-no-results', sprintf( __( 'There were no results found for <strong>"%s"</strong> this week. Try searching another week.', 'tribe-events-calendar' ), $search_term ) );
			}
		}

		/**
		 * Set up hooks for week view
		 *
		 * @return void
		 * @since 3.0
		 **/
		protected function hooks() {
			parent::hooks();
			add_filter( 'tribe_events_header_attributes',  array( $this, 'header_attributes'), 10, 2 );
		}

		/**
		 * Add header attributes for week view
		 *
		 * @return string
		 * @since 3.0
		 **/
		function header_attributes( $attrs, $current_view ) {
			switch( $current_view ){
				case 'week-all-day':
					$attrs['data-hour'] = 'all-day';
					break;
				case 'week-hourly':
					$event = self::get_hourly_event();
					if ( $event->days_between > 0 ) {
						$daily_mins = 1440;
						$data_hour = 0;
						$data_min = 0;
						if ( in_array( $event->ID, self::$daily_span_ids ) && date( 'Y-m-d', strtotime( $event->EventEndDate ) ) == self::get_current_date() ) {
							// if the event is longer than a day we want to account for that with an offset for the ending time
							$duration = abs( ( strtotime( self::get_current_date() ) - strtotime( $event->EventEndDate ) ) / 60 );
						} else if ( in_array( $event->ID, self::$daily_span_ids ) && date( 'Y-m-d', strtotime( $event->EventEndDate ) ) > self::get_current_date() ) {
								// if there is a day in between start/end we just want to fill the spacer with the total mins in the day.
								$duration = $daily_mins;
							} else {
							self::$daily_span_ids[] = $event->ID;
							// if the event is longer than a day we want to account for that with an offset
							$duration = $daily_mins - abs( ( strtotime( self::get_current_date() ) - strtotime( $event->EventStartDate ) ) / 60 );
							$data_hour = date( 'G', strtotime( $event->EventStartDate ) );
							$data_min = date( 'i', strtotime( $event->EventStartDate ) );
						}
					} else {
						// for a default event continue as everything is normal
						$duration = ( $event->EventDuration / 60 );
						$data_hour = date( 'G', strtotime( $event->EventStartDate ) );
						$data_min = date( 'i', strtotime( $event->EventStartDate ) );
					}
					$attrs['data-duration'] = $duration;
					$attrs['data-hour'] = $data_hour;
					$attrs['data-min'] = $data_min;
					break;
			}

			unset( $attrs['data-title'] );

			return apply_filters('tribe_events_pro_header_attributes', $attrs, $current_view );
		}

		/**
		 * This will set the self::week_days array with proper offset for start day in settings
		 */
		function set_week_days() {
			$week_days = array();
			for ( $n = self::$start_of_week; $n < self::$start_of_week + self::$week_length; $n++ ) {
				$day_offset = ( 0 < self::$start_of_week ) ? $n - self::$start_of_week : $n;
				$date = date( 'Y-m-d', strtotime( self::$start_of_week_date . " +$day_offset days" ) );
				$week_days[ $n ] = (object) array(
					'date' => $date,
					'display' => date( 'D jS', strtotime( self::$start_of_week_date . " +$day_offset days" ) ),
					'is_today' => ( $date == self::$today ) ? true : false
				);
			}
			self::$week_days = $week_days;
		}

		/**
		 * Get access to the internal week day list
		 * @return array $week_days
		 */
		function get_week_days() {
			return self::$week_days;
		}

		/**
		 * Get events by type
		 * @param  string $obj
		 * @return object self::events->{$obj}
		 */
		function get_events( $obj = null ) {
			if ( !empty( self::$events->{$obj} ) )
				return self::$events->{$obj};

			return self::$events;
		}

		/**
		 * Break the $wp_query post loop apart into sorted events by type
		 * @return void
		 */
		function setup_loop() {
			global $wp_query;
			self::$events = (object) array( 'all_day_map' => array(), 'all_day' => array(), 'hourly' => array(), 'hours' => array( 'start'=>null, 'end'=>null ) );

			// get it started off with at least 1 row
			self::$events->all_day_map[] = array_fill( self::$start_of_week, self::$week_length, null );
			foreach ( $wp_query->posts as $event_key_id => $event ) {

				// convert the start date of the event into a timestamp
				$event_start_time = strtotime( $event->EventStartDate );

				// if the event start time is greater than the start time of the week then we use the event date otherwise use the beginning of the week date
				$start_date_compare = strtotime( self::$start_of_week_date ) < $event_start_time ? $event->EventStartDate : self::$start_of_week_date;

				// convert the starting event or week date into day of the week
				$event_start_day_of_week = date( 'w', strtotime( $start_date_compare ) );

				// determine the number of days between the starting date and the end of the event
				$event->days_between = tribe_get_days_between( $start_date_compare, $event->EventEndDate );

				// make sure that our days between will not extend past the end of the week
				$event->days_between = $event->days_between >= self::$week_length - $event_start_day_of_week ? ( self::$week_length - $event_start_day_of_week ) : (int) $event->days_between;

				// if this is an all day event
				if (  tribe_get_event_meta( $event->ID, '_EventAllDay' ) ) {

					// let's build our hashtable for add day events
					foreach ( self::$events->all_day_map as $hash_id => $days ) {

						// set bool for if we should inset the event id on the current hash row
						$insert_current_row = false;

						// loop through the columns of this hash row
						for ( $n = $event_start_day_of_week; $n <= $event_start_day_of_week + $event->days_between; $n++ ) {

							// create an offset id for cases where the day of the week is less the starting day of the week
							// thus looping the beginning days of the start week starting at 0 around to the end
							$all_day_offset = ( $n < self::$start_of_week ) ? ( self::$start_of_week + self::$week_length ) - ( $n + self::$start_of_week ) : $n;

							// check for hash collision and setup bool for going to the next row if we can't fit it on this row
							if ( ! empty( self::$events->all_day_map[$hash_id][$all_day_offset] ) ) {
								$insert_current_row = true;
								break;
							} else {
								$insert_current_row = false;
							}
						}
						// if we should actually insert a new row vs going to the next row
						if ( $insert_current_row && count( self::$events->all_day_map ) == $hash_id + 1 ) {

							// create a new row and fill with week day columns
							self::$events->all_day_map[] = array_fill( self::$start_of_week, self::$week_length, null );

							// change the row id to the last row
							$hash_id = count( self::$events->all_day_map ) -1;

						} else if ( $insert_current_row ) {

								// nullify the hash id
								$hash_id = null;
							}

						// if we still have a hash id then fill the row with the event id
						if ( ! is_null( $hash_id ) ) {

							// loop through each week day we want the event to be inserted
							for ( $n = $event_start_day_of_week; $n <= $event_start_day_of_week + $event->days_between; $n++ ) {
								// create an offset id for cases where the day of the week is less the starting day of the week
								// thus looping the beginning days of the start week starting at 0 around to the end
								$all_day_offset = ( $n < self::$start_of_week ) ? ( self::$start_of_week + self::$week_length ) - ( $n + self::$start_of_week ) : $n;

								// add the event array key id into the week day column
								self::$events->all_day_map[$hash_id][$all_day_offset] = $event_key_id;
							}

							// break the hashtable since we have successfully added the event into a row
							break;
						}
					}

					// using the array key for the event id for uniqueness of recurring events
					self::$events->all_day[ $event_key_id ] = $event;
				} else {
					$start_hour = date( 'G', strtotime( $event->EventStartDate ) );
					$end_hour = date( 'G', strtotime( $event->EventEndDate ) );
					if ( is_null( self::$events->hours['start'] ) || $start_hour < self::$events->hours['start'] ) {
						self::$events->hours['start'] = $start_hour;
					}
					if ( is_null( self::$events->hours['end'] ) || $end_hour > self::$events->hours['end'] ) {
						self::$events->hours['end'] = $end_hour;
					}
					self::$events->hourly[ $event->ID ] = $event;
				}
			}
		}

		/**
		 * increment the current day for tracking the current day of the week within the loop
		 * @return [type] [description]
		 */
		public static function increment_current_day(){
			if( self::$current_day < self::$week_length ) {
				self::$current_day++;
			} else {
				self::reset_current_day();
			}
		}

		public static function reset_current_day(){
			self::$current_day = self::$start_of_week;
		}

		/**
		 * set internal mechanism for tracking what the current day of the week is within the display loops
		 * @param int $day_id
		 */
		function set_current_day( $day_id ){
			self::$current_day = $day_id;
		}

		/**
		 * get internal increment for current day of the week
		 * @return int
		 */
		function get_current_day(){
			return self::$current_day;
		}

		/**
		 * get the current date based on the current day of week
		 * @return date( 'Y-m-d' )
		 */
		function get_current_date(){
			return date_i18n( 'Y-m-d', strtotime( self::$start_of_week . ' +' . self::$current_day . ' days' ) );
		}

		/**
		 * css column classes used during loop
		 * @return void
		 */
		function column_classes(){
			if( self::$week_days[ self::$current_day ]->is_today )
				echo 'tribe-week-today';
			if ( self::$current_day != 0 && ( ( self::$current_day % 4 == 0 ) || ( self::$current_day % 5 == 0 ) || ( self::$current_day % 6 == 0 ) ) )
				echo ' tribe-events-right';
		}

		/**
		 * css event wrapper classes used during loop
		 * @return void
		 */
		function event_classes(){

			if( self::$loop_type == 'allday') {
				$event = self::get_allday_event();
			} else {
				$event = self::get_hourly_event();
			}

			// Get our wrapper classes (for event categories, organizer, venue, and defaults)
			echo 'hentry vevent type-tribe_events post-' . $event->ID, ' tribe-clearfix ';

			// we need to adjust on behalf of weekly span scripts
			$day_span_length = $event->days_between + self::$start_of_week;
			if( $day_span_length > 0 )
				echo 'tribe-dayspan' . $day_span_length . ' ';

			// if we have a venue add the class
			if ( $venue_id = tribe_get_venue_id( $event->ID ) )
				'tribe-events-venue-'. $venue_id . ' ';

			// if we have an organizer add the class
			if ( $organizer_id = tribe_get_organizer_id( $event->ID ) )
				$classes[] = 'tribe-events-organizer-'. $organizer_id . ' ';
			
			// add classes for all assigned categories
			$tribe_cat_slugs = tribe_get_event_cat_slugs( $event->ID );
			foreach ( $tribe_cat_slugs as $tribe_cat_slug ) {
				echo 'tribe-events-category-'. $tribe_cat_slug . ' ';
			}

			if ( self::$loop_type == 'hourly' && strtotime( self::$prior_event_date->EventStartDate ) < strtotime( $event->EventStartDate ) ) {
				echo 'tribe-event-overlap ';
			}

			self::$prior_event_date->EventStartDate = $event->EventStartDate;
		}

		/**
		 * set the internal event id for tracking between methods/templates
		 * @param int $event_id
		 */
		function set_event_id( $event_id ){
			self::$event_id = $event_id;
		}

		/**
		 * access the internal var for tracking the event id
		 * @return int self::event_id
		 */
		function get_event_id(){
			return self::$event_id;
		}

		/**
		 * Based on set event id return an all day event
		 * @return object $event
		 */
		function get_allday_event(){
			$event = !empty( self::$events->all_day[ self::$event_id ] ) ? self::$events->all_day[ self::$event_id ] : null;
			return $event;
		}

		/**
		 * Based on set event id return an hourly type event
		 * @return object $event
		 */
		function get_hourly_event( $event_id = null ){
			$event_id = empty( $event_id ) ? self::$event_id : $event_id;
			$event = !empty( self::$events->hourly[ $event_id ] ) ? self::$events->hourly[ $event_id ] : null;
			return $event;
		}

	}
}
