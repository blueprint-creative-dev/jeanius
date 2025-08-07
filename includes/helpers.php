<?php
namespace Jeanius;

/**
 * Return the current user’s Jeanius-assessment post-ID.
 * Create the post (status =draft) if it does not exist yet.
 */
function current_assessment_id() : int {

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return 0;                           // not logged-in
	}

	$ids = get_posts( [
		'post_type'   => 'jeanius_assessment',
		'author'      => $user_id,
		'numberposts' => 1,
		'fields'      => 'ids',
	] );

	if ( $ids ) {
		return (int) $ids[0];               // already exists
	}

	return (int) wp_insert_post( [
		'post_type'   => 'jeanius_assessment',
		'post_status' => 'publish',
		'post_author' => $user_id,
		'post_title'  => 'Assessment for user ' . $user_id,
	] );
}
