<?php
/**
 * Snippet #21: MEC Facebook Import — Dedup Guard (added_post_meta v3)
 * Scope: global | Active: No | Priority: 30
 * Deletes a just-saved event if an older non-trashed event with the same FB id AND same start_date exists. Hooks mec_saved_event (complete data, reliable timing). Fixes cron re-duplication.
 */

/**
 * GASF: MEC Facebook Import - Dedup Guard v3 (added_post_meta on FB-id key)
 *
 * EVIDENCE-BASED: the importer saves via MEC main.php save_event(), which writes
 * mec_advimp_facebook_event_id as the LAST meta (after mec_start_date). It does
 * NOT fire mec_saved_event (that hook lives in a different, unused save_event).
 * So we hook added_post_meta on the FB-id key: at that instant mec_start_date is
 * already written, so we have a complete record to dedup against.
 *
 * If an OLDER non-trashed mec-event has the SAME FB id AND SAME start_date,
 * delete the newcomer. (same id+date = dup; same id+diff date = real recurrence.)
 *
 * Includes a diagnostic log so we can VERIFY it fires on the live cron path.
 */
add_action( 'added_post_meta', function( $mid, $post_id, $meta_key, $meta_value ) {

    if ( $meta_key !== 'mec_advimp_facebook_event_id' ) return;

    static $busy = false;
    if ( $busy ) return;

    $type = get_post_type( $post_id );
    $date = get_post_meta( $post_id, 'mec_start_date', true );
    $fb   = (string) $meta_value;

    // DIAG: prove this fires + what we see
    @file_put_contents( '/home4/germanta/dedup_diag.log',
        date('Y-m-d H:i:s') . " FIRED post=$post_id type=$type fb=$fb date=" . ($date ?: '(empty)') . "\n",
        FILE_APPEND );

    if ( $type !== 'mec-events' || $fb === '' || ! $date ) return;

    global $wpdb;
    $oldest = $wpdb->get_var( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} fb ON fb.post_id=p.ID AND fb.meta_key='mec_advimp_facebook_event_id' AND fb.meta_value=%s
         JOIN {$wpdb->postmeta} sd ON sd.post_id=p.ID AND sd.meta_key='mec_start_date'              AND sd.meta_value=%s
         WHERE p.post_type='mec-events' AND p.post_status NOT IN ('trash','auto-draft')
         ORDER BY p.ID ASC LIMIT 1",
        $fb, $date
    ) );

    @file_put_contents( '/home4/germanta/dedup_diag.log',
        date('Y-m-d H:i:s') . "   oldest=" . ($oldest ?: 'NULL') . " this=$post_id" .
        ( ($oldest && (int)$oldest !== (int)$post_id) ? " -> DELETE $post_id" : " -> keep" ) . "\n",
        FILE_APPEND );

    if ( $oldest && (int) $oldest !== (int) $post_id ) {
        $busy = true;
        wp_delete_post( (int) $post_id, true );
        $busy = false;
    }

}, 99, 4 );