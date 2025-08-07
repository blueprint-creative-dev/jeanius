<?php
/**
 * Gravity-Forms integration for the Jeanius plugin.
 */
namespace Jeanius;

class Gravity {

	/**  <<<—---------  SET THIS TO YOUR FORM’S ID  */
	const FORM_ID = 4;	// 5 is just an example — look in Gravity Forms list.

	/**
	 * Called once from the main plugin class to register hooks.
	 */
	public static function init() {

        /**
         * Fires right after Gravity Forms creates the new WP user.
         *
         * @param int   $user_id  The ID of the user just created.
         * @param array $feed     The User-Registration feed object.
         * @param array $entry    The full GF entry array.
         * @param array $user_pass Unused here.
         */
        add_action(
            'gform_user_registered',
            [ __CLASS__, 'create_assessment_after_user_created' ],
            10,
            4
        );
    }
    

	/**
	 * Create (or reset) the Jeanius CPT right after the form is submitted.
	 *
	 * @param array $entry Gravity Forms entry.
	 * @param array $form  Gravity Forms form object.
	 */
    public static function create_assessment_after_user_created( $user_id, $feed, $entry, $user_pass ) {

        if ( (int) $entry['form_id'] !== self::FORM_ID ) {
            return;
        }
    
        // 1. Ensure CPT exists and grab its ID
        $post_id = Provisioner::create_or_reset_assessment( $user_id );
    
        // 2. Pull raw values from the entry (update IDs to your own)
        $dob_raw      = rgar( $entry, '4' );   // <- DOB field ID
        $colleges_raw = rgar( $entry, '7' );   // <- List field ID 
		$parent_email = rgar( $entry, '8' );   // <- Parent Email 
    
        // 3. Save to ACF
        //    (ACF will handle date formatting and repeater rows)
        update_field( 'dob', $dob_raw, $post_id );
        update_field( 'parent_email', $parent_email, $post_id );
        update_field( 'target_colleges', $colleges_raw, $post_id );   
        // 4. Email welcome (optional if GF already sent one)
        wp_new_user_notification( $user_id, null, 'both' );
    }
    
    
}
