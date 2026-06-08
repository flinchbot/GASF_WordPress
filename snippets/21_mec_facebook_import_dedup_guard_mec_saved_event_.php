<?php
/**
 * Snippet #21: MEC Facebook Import — Dedup Guard (mec_saved_event)
 * Scope: global | Active: Yes | Priority: 30
 * Deletes a just-saved event if an older non-trashed event with the same FB id AND same start_date exists. Hooks mec_saved_event (complete data, reliable timing). Fixes cron re-duplication.
 */

/**
 * GASF: MEC Facebook Import - Dedup Guard (mec_saved_event)
 *
 * Prevents duplicate imports at the moment of save. MEC core fires
 * 'mec_saved_event' right after wp_insert_post, passing the COMPLETE event
 * data array - so we read the Facebook id and start date directly from the
 * hook argument (no reliance on meta being written yet, which is what made the
 * earlier added_post_meta version miss on the live import path).
 *
 * Logic: if another NON-TRASHED mec-event already exists with the SAME
 * Facebook id AND the SAME start_date, and it is OLDER than the one just
 * saved, delete the newcomer and keep the original.
 *   same FB id + same date  = duplicate  -> delete newcomer
 *   same FB id + diff date   = recurrence -> keep (real occurrence)
 *
 * This is what stops the hourly cron from re-creating the same single event
 * (e.g. the Oct 31 Biergarten) dozens of times per day.
 *
 * Recursion-guarded (wp_delete_post can trigger save hooks). Update-safe.
 */
add_action( 'mec_saved_event', function( $event_id, $event ) {

    static $busy = false;
    if ( $busy ) return;
    if ( ! $event_id || is_wp_error( $event_id ) ) return;

    // Pull FB id + start date straight from the event data array.
    $fb_id = '';
    if ( isset( $event['meta'] ) && is_array( $event['meta'] ) && ! empty( $event['meta']['mec_advimp_facebook_event_id'] ) ) {
        $fb_id = (string) $event['meta']['mec_advimp_facebook_event_id'];
    }
    $start = isset( $event['start'] ) ? (string) $event['start'] : '';

    // Only act on Facebook-imported events that have both a FB id and a date.
    if ( $fb_id === '' || $start === '' ) return;

    global $wpdb;

    // Oldest non-trashed event with same FB id AND same start_date.
    $oldest = $wpdb->get_var( $wpdb->prepare(
        "SELECT p.ID
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} fb ON fb.post_id=p.ID AND fb.meta_key='mec_advimp_facebook_event_id' AND fb.meta_value=%s
         JOIN {$wpdb->postmeta} sd ON sd.post_id=p.ID AND sd.meta_key='mec_start_date'              AND sd.meta_value=%s
         WHERE p.post_type='mec-events' AND p.post_status NOT IN ('trash','auto-draft')
         ORDER BY p.ID ASC
         LIMIT 1",
        $fb_id, $start
    ) );

    // If an older copy exists and it's not this one, this is the duplicate.
    if ( $oldest && (int) $oldest !== (int) $event_id ) {
        $busy = true;
        wp_delete_post( (int) $event_id, true );
        $busy = false;
    }

}, 99, 2 );
