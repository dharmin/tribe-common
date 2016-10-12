<?php

namespace Tribe;

require_once codecept_data_dir( 'classes/Tabbed_View_Extension.php' );
require_once codecept_data_dir( 'classes/Tab_Extension.php' );

use Tabbed_View_Extension as Tabbed_View;

class Tabbed_ViewTest extends \Codeception\TestCase\WPTestCase {

	public function setUp() {
		// before
		parent::setUp();

		// your set up methods here
	}

	public function tearDown() {
		// your tear down methods here

		// then
		parent::tearDown();

	}

	/**
	 * @test
	 * it should be instantiatable
	 */
	public function it_should_be_instantiatable() {
		$sut = $this->make_instance();

		$this->assertInstanceOf( \Tribe__Tabbed_View::class, $sut );
	}

	/**
	 * @return Tabbed_View
	 */
	private function make_instance() {
		return new Tabbed_View();
	}

	/**
	 * @test
	 * it should store the tab instance if registered
	 */
	public function it_should_store_the_tab_instance_if_registered() {
		$tab = $this->prophesize( \Tribe__Tabbed_View__Tab::class );
		$tab->get_slug()->willReturn( 'one' );
		$tab->get_priority()->willReturn( 10 );

		$sut          = $this->make_instance();
		$revealed_tab = $tab->reveal();
		$sut->register( $revealed_tab );

		$this->assertSame( $revealed_tab, $sut->get( 'one' ) );
	}

	/**
	 * @test
	 * it should build the tab if registering a tab class
	 */
	public function it_should_build_the_tab_if_registering_a_tab_class() {
		$sut = $this->make_instance();
		$sut->register( \Tab_Extension::class );

		// slug hard-coded in class
		$this->assertInstanceOf( \Tribe__Tabbed_View__Tab::class, $sut->get( 'tab_extension' ) );
	}

	/**
	 * @test
	 * it should not register non tab or class
	 */
	public function it_should_not_register_non_tab_or_class() {
		$sut = $this->make_instance();
		$this->assertFalse( $sut->register( 'bar' ) );
	}

	/**
	 * @test
	 * it should not register tabs without a slug
	 */
	public function it_should_not_register_tabs_without_a_slug() {
		$tab = $this->prophesize( \Tribe__Tabbed_View__Tab::class );
		$tab->get_slug()->willReturn( null );

		$sut          = $this->make_instance();
		$revealed_tab = $tab->reveal();
		$registered   = $sut->register( $revealed_tab );

		$this->assertFalse( $registered );
	}

	/**
	 * @test
	 * it should return set default tab
	 */
	public function it_should_return_set_default_tab() {
		$sut = $this->make_instance();
		foreach ( [ 'two' => 2, 'three' => 3, 'one' => 1 ] as $slug => $priority ) {
			$tab = $this->prophesize( \Tribe__Tabbed_View__Tab::class );
			$tab->get_slug()->willReturn( $slug );
			$tab->get_priority()->willReturn( $priority );
			$revealed_tab = $tab->reveal();
			$sut->register( $revealed_tab );
		}
		$sut->set_default_tab( 'three' );

		$this->assertEquals( 'three', $sut->get_default_tab() );
	}

	/**
	 * @test
	 * it should return lowest priority tab as default if default not set
	 */
	public function it_should_return_lowest_priority_tab_as_default_if_default_not_set() {
		$sut = $this->make_instance();
		foreach ( [ 'two' => 2, 'three' => 3, 'one' => 1 ] as $slug => $priority ) {
			$tab = $this->prophesize( \Tribe__Tabbed_View__Tab::class );
			$tab->get_slug()->willReturn( $slug );
			$tab->get_priority()->willReturn( $priority );
			$revealed_tab = $tab->reveal();
			$sut->register( $revealed_tab );
		}

		$this->assertEquals( 'one', $sut->get_default_tab() );
	}

	/**
	 * @test
	 * it should return false for default tab if no tab is registered
	 */
	public function it_should_return_false_for_default_tab_if_no_tab_is_registered() {
		$sut = $this->make_instance();

		$this->assertFalse( $sut->get_default_tab() );
	}

	/**
	 * @test
	 * it should return lowest priority tab is default set to non registered tab
	 */
	public function it_should_return_lowest_priority_tab_is_default_set_to_non_registered_tab() {
		$sut = $this->make_instance();
		foreach ( [ 'two' => 2, 'three' => 3, 'one' => 1 ] as $slug => $priority ) {
			$tab = $this->prophesize( \Tribe__Tabbed_View__Tab::class );
			$tab->get_slug()->willReturn( $slug );
			$tab->get_priority()->willReturn( $priority );
			$revealed_tab = $tab->reveal();
			$sut->register( $revealed_tab );
		}
		$sut->set_default_tab( 'four' );

		$this->assertEquals( 'one', $sut->get_default_tab() );
	}

	/**
	 * @test
	 * it should return false if default tab is non existent and no tabs are registered
	 */
	public function it_should_return_false_if_default_tab_is_non_existent_and_no_tabs_are_registered() {
		$sut = $this->make_instance();
		$sut->set_default_tab( 'foo' );

		$this->assertFalse( $sut->get_default_tab() );
	}

	/**
	 * @test
	 * it should default a tab priority to 0 if not provided
	 */
	public function it_should_default_a_tab_priority_to_0_if_not_provided() {
		$one = $this->prophesize( \Tribe__Tabbed_View__Tab::class );
		$one->get_slug()->willReturn( 'one' );
		$one->get_priority()->willReturn( null );
		$two = $this->prophesize( \Tribe__Tabbed_View__Tab::class );
		$two->get_slug()->willReturn( 'two' );
		$two->get_priority()->willReturn( 1 );

		$sut = $this->make_instance();
		$sut->register( $one->reveal() );
		$sut->register( $two->reveal() );

		$tabs  = $sut->get_tabs();
		$first = reset( $tabs );
		$this->assertEquals( 'one', $first->get_slug() );
	}

	/**
	 * @test
	 * it should return default as active if tab not set in GET request
	 */
	public function it_should_return_default_as_active_if_tab_not_set_in_get_request() {
		unset( $_GET['tab'] );
		$tab = $this->prophesize( \Tribe__Tabbed_View__Tab::class );
		$tab->get_slug()->willReturn( 'foo' );
		$tab->get_priority()->willReturn( 10 );

		$sut = $this->make_instance();
		$revealed_tab = $tab->reveal();
		$sut->register( $revealed_tab );
		$sut->set_default_tab( 'foo' );

		$this->assertSame( $revealed_tab, $sut->get_active() );
	}

	/**
	 * @test
	 * it should return false as active if no tab registered
	 */
	public function it_should_return_false_as_active_if_no_tab_registered() {
		unset( $_GET['tab'] );

		$sut = $this->make_instance();

		$this->assertFalse( $sut->get_active() );
	}

	/**
	 * @test
	 * it should return `tab` value set in GET in active as set if tab is registered
	 */
	public function it_should_return_tab_value_set_in_get_in_active_as_set_if_tab_is_registered() {
		$_GET['tab'] = 'bar';
		$one         = $this->prophesize( \Tribe__Tabbed_View__Tab::class );
		$one->get_slug()->willReturn( 'foo' );
		$one->get_priority()->willReturn( 10 );
		$two = $this->prophesize( \Tribe__Tabbed_View__Tab::class );
		$two->get_slug()->willReturn( 'bar' );
		$two->get_priority()->willReturn( 10 );

		$sut = $this->make_instance();
		$sut->register( $one->reveal() );
		$revealed_two = $two->reveal();
		$sut->register( $revealed_two );
		$sut->set_default_tab( 'foo' );

		$this->assertSame( $revealed_two, $sut->get_active() );
	}

	/**
	 * @test
	 * it should return default tab is `tab` value in GET is not a registered tab
	 */
	public function it_should_return_default_tab_is_tab_value_in_get_is_not_a_registered_tab() {
		$_GET['tab'] = 'baz';
		$one         = $this->prophesize( \Tribe__Tabbed_View__Tab::class );
		$one->get_slug()->willReturn( 'foo' );
		$one->get_priority()->willReturn( 10 );
		$two = $this->prophesize( \Tribe__Tabbed_View__Tab::class );
		$two->get_slug()->willReturn( 'bar' );
		$two->get_priority()->willReturn( 10 );

		$sut = $this->make_instance();
		$sut->register( $one->reveal() );
		$revealed_two = $two->reveal();
		$sut->register( $revealed_two );
		$sut->set_default_tab( 'bar' );

		$this->assertSame( $revealed_two, $sut->get_active() );
	}

	/**
	 * @test
	 * it should return false if `tab` value in GET but there are no registered tabs
	 */
	public function it_should_return_false_if_tab_value_in_get_but_there_are_no_registered_tabs() {
		$_GET['tab'] = 'baz';

		$sut = $this->make_instance();

		$this->assertFalse( $sut->get_active() );
	}
}
