<?php
namespace Jeanius;

/**
 * Regenerate the Jeanius assessment for a given post ID.
 *
 * Temporarily switches to the assessment's author, invokes the generator,
 * then restores the original user context.
 */
function regenerate_assessment( int $post_id ): void {
    $author_id = (int) \get_post_field( 'post_author', $post_id );
    if ( ! $author_id ) {
        return;
    }

    $original = \get_current_user_id();
    \wp_set_current_user( $author_id );

    Rest::generate_report( $post_id );

    \wp_set_current_user( $original );
}
