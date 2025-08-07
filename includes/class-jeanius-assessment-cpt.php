<?php
/**
 * Registers the CPT that stores every student’s assessment data.
 */
namespace Jeanius;

class Assessment_CPT {

	public static function register() {

		register_post_type( 'jeanius_assessment', [
			'labels' => [
				'name'          => 'Jeanius Assessments',
				'singular_name' => 'Jeanius Assessment',
			],
			'public'      => false,      // not queryable on the front end
			'show_ui'     => true,      // students don’t see it
			'show_in_rest'=> true,
			'supports'    => [ 'author' ],
		] );
	}
}

/* Hook it to WordPress */
add_action( 'init', [ '\Jeanius\Assessment_CPT', 'register' ] );
