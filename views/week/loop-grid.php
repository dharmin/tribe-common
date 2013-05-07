<?php 
/**
 * Week Grid Loop
 * This file sets up the structure for the week grid loop
 *
 * Override this template in your own theme by creating a file at [your-theme]/tribe-events/week/loop-grid.php
 * *
 * @package TribeEventsCalendar
 * @since  3.0
 * @author Modern Tribe Inc.
 *
 */
?>
<div class="tribe-events-grid hfeed vcalendar clearfix">
	<div class="tribe-grid-header clearfix">
		<div class="column first">
			<span class="tribe-events-visuallyhidden"><?php _e( 'Hours', 'tribe-events-calendar-pro' ); ?></span>
		</div>
		<div class="tribe-grid-content-wrap">
			<?php foreach( tribe_events_week_get_days() as $day ) : tribe_events_week_increment_day(); ?>
			<div title="<?php tribe_events_week_get_current_date(); ?>" class="column <?php echo tribe_events_week_is_current_today() ? 'tribe-week-today' : ''; ?>">
				<a href="<?php echo tribe_get_day_permalink( tribe_events_week_get_current_date( false ) ); ?>" rel="bookmark"><?php tribe_events_week_get_current_day_display(); ?></a>
			</div><!-- header column -->
			<?php endforeach; ?>
		</div><!-- .tribe-grid-content-wrap -->
	</div><!-- .tribe-grid-header -->
	<?php tribe_get_template_part('week/loop', 'grid-allday'); ?>
	<?php tribe_get_template_part('week/loop', 'grid-hourly'); ?>
</div><!-- .tribe-events-grid -->
