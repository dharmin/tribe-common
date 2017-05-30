<?php

namespace Tribe;

use Tribe__Terms as Terms;

class TermsTest extends \Codeception\TestCase\WPTestCase {
	public function translate_terms_to_ids_bad_inputs() {
		return [
			[ '' ],
			[ 23 ],
			[ [ 23, 89 ] ],
			[ '23' ],
			[ [ '23', '89' ] ],
		];
	}

	/**
	 * Test translate_terms_to_ids with bad inputs
	 *
	 * @test
	 * @dataProvider translate_terms_to_ids_bad_inputs
	 */
	public function test_translate_terms_to_ids_with_bad_inputs( $input ) {
		$this->assertEquals( [], Terms::translate_terms_to_ids( $input, 'post_tag' ) );
	}

	/**
	 * Test translate_terms_to_ids should create not found terms
	 *
	 * @test
	 */
	public function test_translate_terms_to_ids_should_create_not_found_terms() {
		$this->assertFalse( (bool) term_exists( 'foo', 'post_tag' ) );

		$created = Terms::translate_terms_to_ids( 'foo', 'post_tag' );
		$this->assertCount( 1, $created );

		$this->assertTrue( (bool) term_exists( 'foo', 'post_tag' ) );
	}

	/**
	 * Test translate_terms_to_ids does not create existing terms
	 *
	 * @test
	 */
	public function test_translate_terms_to_ids_does_not_create_existing_terms() {
		$foo = $this->factory()->term->create( [ 'slug' => 'foo', 'taxonomy' => 'post_tag' ] );
		$bar = $this->factory()->term->create( [ 'slug' => 'bar', 'taxonomy' => 'post_tag' ] );

		$this->assertTrue( (bool) term_exists( 'foo', 'post_tag' ) );
		$this->assertTrue( (bool) term_exists( 'bar', 'post_tag' ) );
		$this->assertFalse( (bool) term_exists( 'baz', 'post_tag' ) );

		$terms = [ 'foo', 'bar', 'baz' ];
		$created = Terms::translate_terms_to_ids( $terms, 'post_tag' );

		$this->assertCount( 3, $created );
		$this->assertContains( $foo, $created );
		$this->assertContains( $bar, $created );
		$this->assertTrue( (bool) term_exists( 'foo', 'post_tag' ) );
		$this->assertTrue( (bool) term_exists( 'bar', 'post_tag' ) );
		$this->assertTrue( (bool) term_exists( 'baz', 'post_tag' ) );
	}

	/**
	 * Test translate_terms_to_ids detects existing terms by id and slug
	 *
	 * @test
	 */
	public function test_translate_terms_to_ids_detects_existing_terms_by_id_and_slug() {
		$foo = $this->factory()->term->create( [ 'slug' => 'foo', 'taxonomy' => 'post_tag' ] );

		$this->assertTrue( (bool) term_exists( 'foo', 'post_tag' ) );

		$created = Terms::translate_terms_to_ids( $foo, 'post_tag' );

		$this->assertCount( 1, $created );
		$this->assertEquals( [ $foo ], $created );

		$created = Terms::translate_terms_to_ids( 'foo', 'post_tag' );
		$this->assertCount( 1, $created );
		$this->assertEquals( [ $foo ], $created );

		$created = Terms::translate_terms_to_ids( [ 'foo', $foo ], 'post_tag' );
		$this->assertCount( 1, $created );
		$this->assertEquals( [ $foo ], $created );
	}

	/**
	 * Test translate_term_to_ids does not create terms for non valid taxonomy
	 *
	 * @test
	 */
	public function test_translate_term_to_ids_does_not_create_terms_for_non_valid_taxonomy() {
		$created = Terms::translate_terms_to_ids( 'foo', 'bar' );
		$this->assertCount( 0, $created );
	}
}