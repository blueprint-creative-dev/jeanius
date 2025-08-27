<?php
namespace Jeanius;

/**
 * Regenerate the Jeanius assessment for a given post ID.
 *
 * Clears the current user to the assessment's author so the existing REST
 * generation logic runs against the correct record, invokes the generator,
 * then restores the original user context.
 */
function regenerate_assessment( int $post_id ): void {
    $author_id = (int) \get_post_field( 'post_author', $post_id );
    if ( ! $author_id ) {
        return;
    }

    $original = \get_current_user_id();
    \wp_set_current_user( $author_id );

    Rest::generate_report( new \WP_REST_Request( 'POST', '/jeanius/v1/generate' ) );

    \wp_set_current_user( $original );
}
