<?php
/**
 * Snippet #18: MEC Facebook Recurring Event Expander
 * Scope: global | Active: No | Priority: 5
 * Intercepts MEC Facebook imports and expands recurring events (event_times) into individual MEC events. MEC natively ignores event_times. Plugin-update-safe.
 */

/**
 * GASF: MEC Facebook Recurring Event Expander  (+ window guard + recurring tag)
 *
 * MEC's Facebook importer fetches event_times from the Graph API but
 * silently ignores it, storing recurring events as one spanning MEC event.
 * This snippet intercepts the post_meta write and expands each occurrence
 * into its own MEC event - one per Facebook event_times entry.
 *
 * Additions:
 *  - WINDOW GUARD: On a manual date-range sync (POST start_date/end_date),
 *    only occurrences whose start falls inside the window are CREATED.
 *    Out-of-window occurrences are never inserted (no trashing, no cleanup).
 *  - RECURRING TAG: Appends " (recurring)" to the title of every event that
 *    came from a recurring series, as a visual pointer in the calendar.
 *
 * Hooks into: added_post_meta (mec_advimp_facebook_event_id on mec-events)
 * Safe against: recursion, re-syncs, duplicate imports
 * Plugin-update-safe: touches no plugin files
 */
add_action( 'added_post_meta', function( $mid, $post_id, $meta_key, $meta_value ) {

    if ( $meta_key !== 'mec_advimp_facebook_event_id' ) return;
    if ( get_post_type( $post_id ) !== 'mec-events' ) return;

    static $processing = [];
    if ( isset( $processing[ $meta_value ] ) ) return;
    $processing[ $meta_value ] = true;

    // Manual-sync window (if any)
    $win_start = null;
    $win_end   = null;
    $si = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
    $ei = isset( $_POST['end_date'] )   ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) )   : '';
    if ( $si !== '' && $ei !== '' ) {
        $win_start = strtotime( $si . ' 00:00:00' );
        $win_end   = strtotime( $ei . ' 23:59:59' );
        if ( ! $win_start || ! $win_end ) { $win_start = null; $win_end = null; }
    }
    $in_window = function( $start_str ) use ( $win_start, $win_end ) {
        if ( $win_start === null ) return true; // no window = scheduled sync, allow all
        $t = strtotime( $start_str );
        return ( $t !== false && $t >= $win_start && $t <= $win_end );
    };

    $tag = function( $title ) {
        return ( substr( $title, -12 ) === ' (recurring)' ) ? $title : $title . ' (recurring)';
    };

    // Get the live Facebook token
    $config = get_option( 'mec_advimp_auth_facebook', [] );
    $token  = null;
    foreach ( $config as $account ) {
        if ( ! empty( $account['access_token'] ) ) {
            $token = $account['access_token'];
        }
    }
    if ( ! $token ) { unset( $processing[ $meta_value ] ); return; }

    // Query Facebook for event_times
    $url  = 'https://graph.facebook.com/v18.0/' . rawurlencode( $meta_value )
          . '?fields=id,name,description,start_time,end_time,event_times,timezone,cover,place'
          . '&access_token=' . $token;
    $resp = wp_remote_get( $url, [ 'timeout' => 15 ] );

    if ( is_wp_error( $resp ) ) { unset( $processing[ $meta_value ] ); return; }

    $event = json_decode( wp_remote_retrieve_body( $resp ) );

    // Not a recurring event or only one occurrence - nothing to expand.
    if ( ! $event || isset( $event->error ) || empty( $event->event_times ) || count( $event->event_times ) <= 1 ) {
        unset( $processing[ $meta_value ] ); return;
    }

    // Sort occurrences chronologically (Facebook returns newest first)
    $occurrences = $event->event_times;
    usort( $occurrences, function( $a, $b ) { return strtotime( $a->start_time ) <=> strtotime( $b->start_time ); } );

    $timezone = isset( $event->timezone ) ? $event->timezone : get_option( 'timezone_string', 'America/New_York' );

    $parse = function( $time_str, $tz ) {
        try {
            $dt = new DateTime( $time_str, new DateTimeZone( $tz ) );
        } catch ( Exception $e ) {
            $dt = new DateTime( $time_str );
        }
        return [
            'date'    => $dt->format( 'Y-m-d' ),
            'hour'    => $dt->format( 'g' ),
            'minutes' => $dt->format( 'i' ),
            'ampm'    => $dt->format( 'A' ),
        ];
    };

    $apply_dates = function( $pid, $start_str, $end_str, $tz ) use ( $parse ) {
        $s = $parse( $start_str, $tz );
        $e = $end_str ? $parse( $end_str, $tz ) : $s;
        update_post_meta( $pid, 'mec_start_date',           $s['date'] );
        update_post_meta( $pid, 'mec_end_date',             $e['date'] );
        update_post_meta( $pid, 'mec_start_time_hour',      $s['hour'] );
        update_post_meta( $pid, 'mec_start_time_minutes',   $s['minutes'] );
        update_post_meta( $pid, 'mec_start_time_ampm',      $s['ampm'] );
        update_post_meta( $pid, 'mec_end_time_hour',        $e['hour'] );
        update_post_meta( $pid, 'mec_end_time_minutes',     $e['minutes'] );
        update_post_meta( $pid, 'mec_end_time_ampm',        $e['ampm'] );
        update_post_meta( $pid, 'mec_date', [
            'start'         => [ 'date' => $s['date'], 'hour' => $s['hour'], 'minutes' => $s['minutes'], 'ampm' => $s['ampm'] ],
            'end'           => [ 'date' => $e['date'], 'hour' => $e['hour'], 'minutes' => $e['minutes'], 'ampm' => $e['ampm'] ],
            'repeat'        => [ 'end' => 'date', 'end_at_date' => $e['date'] ],
            'allday'        => 0,
            'hide_time'     => 0,
            'hide_end_time' => 0,
            'comment'       => '',
        ] );
    };

    // Pull shared data from the already-imported (parent) event
    $location_id    = get_post_meta( $post_id, 'mec_location_id',  true );
    $organizer_id   = get_post_meta( $post_id, 'mec_organizer_id', true );
    $thumbnail_id   = get_post_thumbnail_id( $post_id );
    $category_terms = wp_get_object_terms( $post_id, 'mec_category', [ 'fields' => 'ids' ] );
    $read_more      = 'https://www.facebook.com/events/' . $event->id . '/';

    global $wpdb;

    // Decide the parent: first IN-WINDOW occurrence.
    // The importer already created ONE post ($post_id). Reuse it for the first
    // in-window occurrence. If none are in window, the parent is removed.
    $parent_used = false;

    foreach ( $occurrences as $occ ) {
        $occ_id = $occ->id;

        // Skip out-of-window occurrences entirely (never create them).
        if ( ! $in_window( $occ->start_time ) ) continue;

        if ( ! $parent_used ) {
            $apply_dates( $post_id, $occ->start_time, isset( $occ->end_time ) ? $occ->end_time : null, $timezone );
            update_post_meta( $post_id, 'mec_advimp_facebook_event_id', $occ_id );
            update_post_meta( $post_id, 'mec_advimp_recurring', 1 );
            $p = get_post( $post_id );
            if ( $p ) {
                wp_update_post( [ 'ID' => $post_id, 'post_title' => $tag( $p->post_title ) ] );
            }
            $parent_used = true;
            continue;
        }

        // Additional in-window occurrences -> new posts
        $processing[ $occ_id ] = true;

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'mec_advimp_facebook_event_id' AND meta_value = %s LIMIT 1",
            $occ_id
        ) );
        if ( $existing ) { unset( $processing[ $occ_id ] ); continue; }

        $new_id = wp_insert_post( [
            'post_title'   => $tag( $event->name ),
            'post_content' => isset( $event->description ) ? $event->description : '',
            'post_status'  => 'publish',
            'post_type'    => 'mec-events',
        ] );
        if ( ! $new_id || is_wp_error( $new_id ) ) { unset( $processing[ $occ_id ] ); continue; }

        $apply_dates( $new_id, $occ->start_time, isset( $occ->end_time ) ? $occ->end_time : null, $timezone );
        update_post_meta( $new_id, 'mec_allday',                   0 );
        update_post_meta( $new_id, 'mec_repeat_status',            0 );
        update_post_meta( $new_id, 'mec_repeat_type',              '' );
        update_post_meta( $new_id, 'mec_source',                   'facebook-calendar' );
        update_post_meta( $new_id, 'mec_advimp_facebook_event_id', $occ_id );
        update_post_meta( $new_id, 'mec_advimp_recurring',         1 );
        update_post_meta( $new_id, 'mec_more_info',                $read_more );
        update_post_meta( $new_id, 'mec_read_more',                '' );
        if ( $location_id )  update_post_meta( $new_id, 'mec_location_id',  $location_id );
        if ( $organizer_id ) update_post_meta( $new_id, 'mec_organizer_id', $organizer_id );
        if ( ! empty( $category_terms ) ) { wp_set_object_terms( $new_id, $category_terms, 'mec_category' ); }
        if ( $thumbnail_id ) { set_post_thumbnail( $new_id, $thumbnail_id ); }

        unset( $processing[ $occ_id ] );
    }

    // If NO occurrence fell in the window, the importer-created parent is
    // out-of-window noise - remove it so nothing spurious remains.
    if ( ! $parent_used && $win_start !== null ) {
        wp_delete_post( $post_id, true );
    }

    unset( $processing[ $meta_value ] );

}, 10, 4 );
