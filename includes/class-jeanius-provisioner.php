<?php
namespace Jeanius;

/**
 * Creates or resets the single Jeanius CPT tied to a user.
 */
class Provisioner {

	public static function create_or_reset_assessment( $user_id ) {

		// Does one already exist?
		$existing = get_posts( [
			'post_type'   => 'jeanius_assessment',
			'author'      => $user_id,
			'numberposts' => 1,
			'post_status' => [ 'draft', 'publish', 'pending', 'private' ],
			'fields'      => 'ids',
		] );

		if ( $existing ) {
			// Reset by putting it back to draft and wiping meta (optional)
			$post_id = $existing[0];
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
			delete_post_meta_by_key( 'jeanius_status', $post_id ); // example
		} else {
			// Create a fresh one
			$post_id = wp_insert_post( [
				'post_type'   => 'jeanius_assessment',
				'post_status' => 'draft',
				'post_author' => $user_id,
				'post_title'  => 'Assessment for user ' . $user_id,
			] );
		}

		// Log for debugging
		error_log( "Jeanius: assessment post_id {$post_id} ensured for user {$user_id}" );
        return (int) $post_id;   // <= ADD THIS LINE

	}
}
