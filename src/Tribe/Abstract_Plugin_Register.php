<?php


abstract class Tribe__Abstract_Plugin_Register {

	protected $base_dir;
	protected $main_class;
	protected $version;
	protected $dependencies = [];

	/**
	 * Registers a plugin with dependencies
	 */
	public function register_plugin() {
		return tribe_register_plugin( $this->base_dir, $this->main_class, $this->version, $this->dependencies );
	}

	/**
	 * Returns whether or not the dependencies have been met
	 *
	 * This is basically an aliased function - register_plugins, upon
	 * second calling, returns whether or not a plugin should load.
	 */
	public function has_valid_dependencies() {
		return $this->register_plugin();
	}
}