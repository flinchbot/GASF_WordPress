<?php
/**
 * Snippet #19: MEC Facebook Window Response Filter
 * Scope: global | Active: Yes | Priority: 10
 * Strips out-of-window events from the Graph API response before the importer parses them. Date-based, inclusive. Manual syncs only. No trashing.
 */

/**
 * GASF: MEC Facebook Import - Window Response Filter
 *
 * The MEC Advanced Importer appends time_filter=upcoming to its Graph API call,
 * which overrides the chosen End Date, so Facebook returns ALL upcoming events
 * (the page returns ~83, newest-first) regardless of the window. This strips
 * events whose start date is outside the selected window FROM THE API RESPONSE,
 * before the importer parses it - so out-of-window events are never created.
 *
 * Comparison is done on the event's LOCAL calendar date (the date a human sees
 * in the start_time), inclusive of both window endpoints. Facebook returns
 * start_time like 2026-05-29T18:30:00-0400; we take the Y-m-d part directly.
 *
 * Recurring series (event_times arrays) are kept if ANY occurrence's date is in
 * window, so Snippet #18 can expand the in-window occurrences. On this page all
 * events currently come back as singles, but this stays correct either way.
 *
 * Active only on a manual date-range sync (POST start_date + end_date present);
 * scheduled cron syncs pass through untouched. Update-safe: no plugin files.
 */
add_filter( 'http_response', function( $response, $args, $url ) {

    if ( strpos( $url, 'graph.facebook.com' ) === false ) return $response;
    if ( empty( $_POST['start_date'] ) || empty( $_POST['end_date'] ) ) return $response;

    // Window as plain Y-m-d date strings (inclusive). Compared as strings,
    // which is safe for the YYYY-MM-DD format (lexical order == chronological).
    $win_start = sanitize_text_field( wp_unslash( $_POST['start_date'] ) );
    $win_end   = sanitize_text_field( wp_unslash( $_POST['end_date'] ) );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $win_start ) ) return $response;
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $win_end ) )   return $response;

    $body = wp_remote_retrieve_body( $response );
    if ( ! $body ) return $response;

    $data = json_decode( $body );
    if ( ! $data || ! isset( $data->data ) || ! is_array( $data->data ) ) return $response;

    // Take the local Y-m-d date from a Facebook timestamp like 2026-05-29T18:30:00-0400
    $local_date = function( $ts ) {
        if ( ! is_string( $ts ) || strlen( $ts ) < 10 ) return null;
        return substr( $ts, 0, 10 );
    };

    $kept = array();
    foreach ( $data->data as $event ) {

        // Recurring series: keep if any occurrence date is in window.
        if ( ! empty( $event->event_times ) && is_array( $event->event_times ) ) {
            $any_in = false;
            foreach ( $event->event_times as $occ ) {
                if ( isset( $occ->start_time ) ) {
                    $d = $local_date( $occ->start_time );
                    if ( $d !== null && $d >= $win_start && $d <= $win_end ) { $any_in = true; break; }
                }
            }
            if ( $any_in ) { $kept[] = $event; }
            continue;
        }

        // Single event: keep only if its local start date is in window.
        if ( isset( $event->start_time ) ) {
            $d = $local_date( $event->start_time );
            if ( $d !== null && $d >= $win_start && $d <= $win_end ) {
                $kept[] = $event;
            }
            continue;
        }

        // No date info at all - keep it (let the importer/plugin decide).
        $kept[] = $event;
    }

    $data->data = array_values( $kept );
    if ( isset( $data->total_records ) ) { $data->total_records = count( $data->data ); }

    $new_body = wp_json_encode( $data );
    if ( $new_body !== false && is_array( $response ) ) {
        $response['body'] = $new_body;
    }

    return $response;

}, 10, 3 );
