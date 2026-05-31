<?php
/**
 * Snippet #18: MEC Facebook Recurring Event Expander
 * Scope: global | Active: Yes | Priority: 5
 * Intercepts MEC Facebook imports and expands recurring events (event_times) into individual MEC events. MEC natively ignores event_times. Plugin-update-safe.
 */

/**
 * GASF: MEC Facebook Recurring Event Expander
 *
 * MEC's Facebook importer fetches event_times from the Graph API but
 * silently ignores it, storing recurring events as one spanning MEC event.
 * This snippet intercepts the post_meta write and expands each occurrence
 * into its own MEC event — one per Facebook event_times entry.
 *
 * Hooks into: added_post_meta (mec_advimp_facebook_event_id on mec-events)
 * Safe against: recursion, re-syncs, duplicate imports
 * Plugin-update-safe: touches no plugin files
 */
add_action( 'added_post_meta', function( $mid, $post_id, $meta_key, $meta_value ) {

    // Only handle the Facebook event ID meta being added to MEC events
    if ( $meta_key !== 'mec_advimp_facebook_event_id' ) return;
    if ( get_post_type( $post_id ) !== 'mec-events' ) return;

    // Static registry prevents recursion when we create additional occurrences
    static $processing = [];
    if ( isset( $processing[ $meta_value ] ) ) return;
    $processing[ $meta_value ] = true;

    // ── Get the live Facebook token ───────────────────────────
    $config = get_option( 'mec_advimp_auth_facebook', [] );
    $token  = null;
    foreach ( $config as $account ) {
        // Use last stored account — most recently authenticated
        if ( ! empty( $account['access_token'] ) ) {
            $token = $account['access_token'];
        }
    }
    if ( ! $token ) { unset( $processing[ $meta_value ] ); return; }

    // ── Query Facebook for event_times ────────────────────────
    $url  = 'https://graph.facebook.com/v18.0/' . rawurlencode( $meta_value )
          . '?fields=id,name,description,start_time,end_time,event_times,timezone,cover,place'
          . '&access_token=' . $token;
    $resp = wp_remote_get( $url, [ 'timeout' => 15 ] );

    if ( is_wp_error( $resp ) ) { unset( $processing[ $meta_value ] ); return; }

    $event = json_decode( wp_remote_retrieve_body( $resp ) );

    // Not a recurring event or only one occurrence — nothing to do
    if ( ! $event || isset( $event->error ) || empty( $event->event_times ) || count( $event->event_times ) <= 1 ) {
        unset( $processing[ $meta_value ] ); return;
    }

    // Sort occurrences chronologically (Facebook returns newest first)
    $occurrences = $event->event_times;
    usort( $occurrences, fn( $a, $b ) => strtotime( $a->start_time ) <=> strtotime( $b->start_time ) );

    $timezone = $event->timezone ?? get_option( 'timezone_string', 'America/New_York' );

    // ── Helper: parse FB timestamp → MEC time fields ─────────
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

    // ── Helper: write date meta to an MEC event post ─────────
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

    // ── Fix existing event → use FIRST occurrence dates ───────
    $first = $occurrences[0];
    $apply_dates( $post_id, $first->start_time, $first->end_time ?? null, $timezone );
    // Update its FB ID to the occurrence ID so deduplication works
    update_post_meta( $post_id, 'mec_advimp_facebook_event_id', $first->id );

    // Pull shared data from the already-imported event
    $location_id    = get_post_meta( $post_id, 'mec_location_id',  true );
    $organizer_id   = get_post_meta( $post_id, 'mec_organizer_id', true );
    $thumbnail_id   = get_post_thumbnail_id( $post_id );
    $category_terms = wp_get_object_terms( $post_id, 'mec_category', [ 'fields' => 'ids' ] );
    $read_more      = 'https://www.facebook.com/events/' . $event->id . '/';

    // ── Create one MEC event per remaining occurrence ─────────
    global $wpdb;

    for ( $i = 1; $i < count( $occurrences ); $i++ ) {
        $occ    = $occurrences[ $i ];
        $occ_id = $occ->id;

        $processing[ $occ_id ] = true; // prevent recursion for this occurrence

        // Deduplication — skip if already in MEC
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = 'mec_advimp_facebook_event_id' AND meta_value = %s LIMIT 1",
            $occ_id
        ) );
        if ( $existing ) { unset( $processing[ $occ_id ] ); continue; }

        // Create the MEC event post
        $new_id = wp_insert_post( [
            'post_title'   => $event->name,
            'post_content' => $event->description ?? '',
            'post_status'  => 'publish',
            'post_type'    => 'mec-events',
        ] );

        if ( ! $new_id || is_wp_error( $new_id ) ) {
            unset( $processing[ $occ_id ] ); continue;
        }

        // Dates
        $apply_dates( $new_id, $occ->start_time, $occ->end_time ?? null, $timezone );

        // Core MEC meta
        update_post_meta( $new_id, 'mec_allday',                   0 );
        update_post_meta( $new_id, 'mec_repeat_status',            0 );
        update_post_meta( $new_id, 'mec_repeat_type',              '' );
        update_post_meta( $new_id, 'mec_source',                   'facebook-calendar' );
        update_post_meta( $new_id, 'mec_advimp_facebook_event_id', $occ_id );
        update_post_meta( $new_id, 'mec_more_info',                $read_more );
        update_post_meta( $new_id, 'mec_read_more',                '' );

        if ( $location_id )  update_post_meta( $new_id, 'mec_location_id',  $location_id );
        if ( $organizer_id ) update_post_meta( $new_id, 'mec_organizer_id', $organizer_id );

        // Taxonomy and thumbnail
        if ( ! empty( $category_terms ) ) {
            wp_set_object_terms( $new_id, $category_terms, 'mec_category' );
        }
        if ( $thumbnail_id ) {
            set_post_thumbnail( $new_id, $thumbnail_id );
        }

        unset( $processing[ $occ_id ] );
    }

    unset( $processing[ $meta_value ] );

}, 10, 4 );
