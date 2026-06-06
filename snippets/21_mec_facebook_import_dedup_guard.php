<?php
/**
 * Snippet #21: MEC Facebook Import — Dedup Guard
 * Scope: global | Active: Yes | Priority: 30
 * Prevents duplicate imports: if a non-trashed event with the same FB id AND same start_date already exists, the newer copy is removed. Same FB id + different date (real recurrences) are left intact. Fixes the daily-cron re-import duplication.
 */

/**
 * GASF: MEC Facebook Import - Dedup Guard
 *
 * Root-cause fix for duplicate imports. The MEC Advanced Importer dedups by
 * Facebook event id, but its lookup (loadResult, no post_status filter) is
 * unreliable once a trashed copy exists - so the hourly cron kept RE-CREATING
 * the same single event (one Biergarten became 200+ copies on 2026-10-31).
 *
 * This guard runs right after an imported event gets its FB id meta. If another
 * NON-TRASHED mec-event already exists with the SAME Facebook id AND the SAME
 * start_date, the newer copy is deleted and the original kept. That key matters:
 *   - same FB id + same date  = true duplicate  -> remove the newcomer
 *   - same FB id + diff date   = real recurrence -> keep (do nothing)
 * so genuine recurring occurrences (expanded by snippet #18) are never harmed.
 *
 * Priority 30 = runs after the importer and after #18's expansion, so it sees
 * the final start_date. Update-safe: no plugin files touched.
 */
add_action( 'added_post_meta', function( $mid, $post_id, $meta_key, $meta_value ) {

    if ( $meta_key !== 'mec_advimp_facebook_event_id' ) return;
    if ( get_post_type( $post_id ) !== 'mec-events' ) return;

    // This event's own start_date (may be set just before/after this meta;
    // re-read fresh to be safe).
    $my_date = get_post_meta( $post_id, 'mec_start_date', true );
    if ( ! $my_date ) return; // no date yet -> let #18/importer finish; nothing to compare

    global $wpdb;

    // Find the OLDEST non-trashed event with same FB id AND same start_date.
    $oldest = $wpdb->get_var( $wpdb->prepare(
        "SELECT p.ID
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} fb ON fb.post_id=p.ID AND fb.meta_key='mec_advimp_facebook_event_id' AND fb.meta_value=%s
         JOIN {$wpdb->postmeta} sd ON sd.post_id=p.ID AND sd.meta_key='mec_start_date'              AND sd.meta_value=%s
         WHERE p.post_type='mec-events' AND p.post_status NOT IN ('trash','auto-draft')
         ORDER BY p.ID ASC
         LIMIT 1",
        $meta_value, $my_date
    ) );

    // If the oldest existing copy is something OTHER than this post, this post
    // is a duplicate -> remove it, keep the original.
    if ( $oldest && (int) $oldest !== (int) $post_id ) {
        wp_delete_post( (int) $post_id, true );
    }

}, 30, 4 );
