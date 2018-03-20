<?php

class Tribe__Queue {
	/**
	 * The name of the option that stores the queue current works and their stati.
	 */
	public static $works_option = 'tribe_q_works';

	/**
	 * Tells the factory to work on all the available works.
	 *
	 * @since TBD
	 *
	 * This is the method that should be hooked to actions and crons.
	 *
	 * @return bool
	 */
	public static function work() {
		$instance = new self();

		$list = array_keys( $instance->get_work_list() );

		if ( empty( $list ) ) {
			return true;
		}

		return $instance->work_on( reset( $list ) );
	}

	/**
	 * Tells the factory to work on a particular work immediately.
	 *
	 * @since TBD
	 *
	 * @param string|\Tribe__Queue__Worker $work_id A work ID or a work object.
	 *
	 * @return bool `true` if the work was done successfully, `false` otherwise.
	 *              Successfully means that something was done, it does not mean complete.
	 */
	public function work_on( $work_id ) {
		if ( $work_id instanceof Tribe__Queue__Worker ) {
			$work_id = $work_id->get_id();
		}

		$work = $this->get_work( $work_id );

		if ( empty( $work ) ) {
			return false;
		}

		$work->work();

		if ( $work->get_status() === Tribe__Queue__Worker::$done ) {
			$this->remove_work_from_list( $work );
		} else {
			$this->update_work_status( $work );
		}

		return true;
	}

	/**
	 * Removes a work from the list of active works and removes its data from the database.
	 *
	 * @since TBD
	 *
	 * @param \Tribe__Queue__Worker|string $work A work object or a work ID
	 * @param int                          $expire The worker data transient expiration time in seconds;
	 *                                             set this to `0` to remove the transient immediately;
	 *                                             defaults to 10 seconds.
	 */
	public function remove_work_from_list( $work, $expire = 10 ) {
		$list = $this->get_work_list();

		if ( ! $work instanceof Tribe__Queue__Worker ) {
			$work = $this->get_work( $work );
		}

		// Prevent conflicts when work has already been worked.
		if ( ! $work instanceof Tribe__Queue__Worker ) {
			return;
		}

		unset( $list[ $work->get_id() ] );

		if ( ! empty( $expire ) ) {
			$work->save( $expire );
		} else {
			delete_transient( $work->get_transient_name() );
		}

		update_option( self::$works_option, $list );
	}

	/**
	 * Appends a new work to the queue.
	 *
	 * @since TBD
	 *
	 * @param array                 $targets
	 * @param       callable| array $callback  Either a callable object or array or a container reference in the
	 *                                         ['tribe', <alias>, <method>] format.
	 *                                         The callback will receive three arguments: the current target, the
	 *                                         target index in the complete list of targets and the data for this work.
	 * @param mixed                 $data      Some additional data that will be passed to the work callback.
	 * @param string                $group     The group this work will belong to.
	 *
	 * @return \Tribe__Queue__Worker
	 */
	public function queue_work( array $targets, $callback, $data = null, $group = null ) {
		// let the Tribe__Queue__Work class make its own verifications
		$work = new Tribe__Queue__Worker( $targets, $targets, $callback, $data, Tribe__Queue__Worker::$queued, 10, $group );

		$this->update_work_status( $work );

		return $work;
	}

	/**
	 * Updates the status of a work in the Factory managed option.
	 *
	 * @since TBD
	 *
	 * @param $work
	 */
	protected function update_work_status( Tribe__Queue__Worker $work ) {
		$list = $this->get_work_list();

		$list[ $work->get_id() ] = $work->get_status();

		update_option( self::$works_option, $list );
	}

	/**
	 * Returns a list of registered works.
	 *
	 * @since TBD
	 *
	 * @param bool $workable_only Whether only in progress or queued works should be returned (`true`) or all (`false`).
	 *
	 * @return array
	 */
	public function get_work_list( $workable_only = true ) {
		$list = get_option( self::$works_option );

		if ( empty( $list ) ) {
			return array();
		}

		$valid = array_filter( array_map( array( $this, 'get_work' ), array_keys( $list ) ) );
		if ( $workable_only ) {
			$valid = array_filter( $valid, array( $this, 'can_work' ) );
		}

		// sort the works by priority
		uasort( $valid, array( $this, 'compare_priorities' ) );

		$ids = array_map( array( $this, 'get_work_id' ), $valid );
		$stati = array_map( array( $this, 'get_work_status' ), $valid );

		if ( empty( $ids ) || empty( $stati ) ) {
			return array();
		}

		return array_combine( $ids, $stati );
	}

	/**
	 * Returns the status of a work.
	 *
	 * @since TBD
	 *
	 * @param Tribe__Queue__Worker|string $work_id or a Worker object
	 *
	 * @see Tribe__Queue__Worker for possible stati.
	 *
	 * @return string
	 */
	public function get_work_status( $work_id ) {
		if ( ! $work_id instanceof Tribe__Queue__Worker ) {
			$work = $this->get_work( $work_id );
		} else {
			$work = $work_id;
		}

		return false !== $work ? $work->get_status() : Tribe__Queue__Worker::$not_found;
	}

	/**
	 * Builds and returns a Tribe__Queue__Work object from its id.
	 *
	 * @since TBD
	 *
	 * @param string $work_id
	 *
	 * @return Tribe__Queue__Worker|false Either the built Work object or `false` if the work object could not be found.
	 */
	public function get_work( $work_id ) {
		$work_data = get_transient( $this->build_transient_name( $work_id ) );

		if ( empty( $work_data ) || false === $decoded = json_decode( $work_data ) ) {
			// either invalid or expired
			return false;
		}

		return $this->build_from_data( $decoded );
	}

	/**
	 * Builds the name of the transient storing a work information from its work id.
	 *
	 * @since TBD
	 *
	 * @param string $work_id
	 *
	 * @return string The complete transient name.
	 */
	protected function build_transient_name( $work_id ) {
		$transient = Tribe__Queue__Worker::$transient_prefix . $work_id;

		return $transient;
	}

	/**
	 * Builds a Work object from its JSON representation.
	 *
	 * @since TBD
	 *
	 * @param stdClass $work_data
	 *
	 * @return Tribe__Queue__Worker
	 */
	protected function build_from_data( stdClass $work_data ) {
		$targets = (array) $work_data->targets;
		$remaining = (array) $work_data->remaining;
		$group = ! empty( $work_data->group ) ? $work_data->group : null;
		$work = new Tribe__Queue__Worker( $targets, $remaining, $work_data->callback, $work_data->data, $work_data->status, $work_data->priority, $group );

		if ( isset( $work_data->batch_size ) ) {
			$work->set_batch_size( $work_data->batch_size );
		}

		return $work;
	}

	/**
	 * Appends a new work to the queue and starts it.
	 *
	 * @since TBD
	 *
	 * @param array                 $targets
	 * @param       callable| array $callback   Either a callable object or array or a container reference in the
	 *                                          ['tribe', <alias>, <method>] format.
	 *                                          The callback will receive three arguments: the current target, the
	 *                                          target index in the complete list of targets and the data for this work.
	 * @param mixed                 $data       Some additional data that will be passed to the work callback.
	 * @param int                   $batch_size The batch size to use for this work.
	 * @param string                $group      The group this work will belong to.
	 *
	 * @return \Tribe__Queue__Worker
	 */
	public function start_work( array $targets, $callback, $data = null, $batch_size = 10, $group = null ) {
		$work = $this->queue_work( $targets, $callback, $data, $group );

		$work_id = $work->set_batch_size( $batch_size )->save();

		$work = $this->get_work( $work_id );
		$work->work();

		return $work;
	}

	/**
	 * Removes a work group from the list.
	 *
	 * @since TBD
	 *
	 * @param string $group                        The group the works to remove belong to.
	 * @param int    $expire                       The worker data transient expiration time in seconds;
	 *                                             set this to `0` to remove the transient immediately;
	 *                                             defaults to 10 seconds.
	 *
	 * @see remove_work_from_list
	 *
	 * @return array A list of removed work IDs.
	 */
	public function remove_work_group_from_list( $group, $expire = 10 ) {
		$work_list = $this->get_work_list( false );

		$removed = array();
		foreach ( array_keys( $work_list ) as $work_id ) {
			if ( 0 === strpos( $work_id, "{$group}|" ) ) {
				$this->remove_work_from_list( $work_id, $expire );
				$removed[] = $work_id;
			}
		}

		return $removed;
	}

	/**
	 * Compares the priorities of two workers to sort them.
	 *
	 * @since TBD
	 *
	 * @param Tribe__Queue__Worker $worker_a
	 * @param Tribe__Queue__Worker $worker_b
	 *
	 * @return int
	 */
	protected function compare_priorities( Tribe__Queue__Worker $worker_a, Tribe__Queue__Worker $worker_b ) {
		$a = $worker_a->get_priority();
		$b = $worker_b->get_priority();

		if ( $a === $b ) {
			return 0;
		}

		return ( $a < $b ) ? - 1 : 1;
	}

	/**
	 * Whether the worker can work or not.
	 *
	 * @since TBD
	 *
	 * @param Tribe__Queue__Worker $worker
	 *
	 * @return bool
	 */
	protected function can_work( Tribe__Queue__Worker $worker ) {
		$working_stati = array( Tribe__Queue__Worker::$working, Tribe__Queue__Worker::$queued );

		return in_array( $worker->get_status(), $working_stati );
	}

	/**
	 * @param Tribe__Queue__Worker $worker
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	protected function get_work_id( Tribe__Queue__Worker $worker ) {
		return $worker->get_id();
	}
}
