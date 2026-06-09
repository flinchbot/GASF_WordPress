<?php
/**
 * Snippet #20: MEC Importer — Force Facebook Page Defaults
 * Scope: global | Active: No | Priority: 1
 * Server-side: forces importType=page and importTypeVal=GermanTampa on the FB import AJAX action; cosmetic JS reflects locked values. Replaces the old JS-disable approach that broke importType.
 */

/**
 * GASF: MEC Advanced Importer - Force Facebook Page Defaults (server-side)
 *
 * Hard-locks every manual Facebook import to:
 *   importType    = 'page'
 *   importTypeVal = 'GermanTampa'   (the GASF page, ID 156837460875)
 *
 * Also sets sensible default dates in the form UI:
 *   Start Date = today
 *   End Date   = today + 60 days
 * (The plugin's own defaults were first-of-prior-month / +3 months.)
 *
 * importType/importTypeVal are enforced SERVER-SIDE on the importer's
 * admin-ajax actions, so the request is always correct regardless of the form
 * JS state. The date defaults are UI conveniences (user can still change them).
 *
 * Update-safe: no plugin files touched. To import by another method, deactivate.
 */

// 1) SERVER-SIDE ENFORCEMENT - runs before the importer reads $_POST.
add_action( 'admin_init', function() {
    if ( ! isset( $_POST['action'] ) ) return;
    $action = sanitize_text_field( wp_unslash( $_POST['action'] ) );

    $fb_actions = array( 'facebook_get_events', 'mec_advimp_schedule_events' );
    if ( ! in_array( $action, $fb_actions, true ) ) return;

    $is_fb = ( isset( $_POST['importType'] ) || strpos( $action, 'facebook' ) !== false );
    if ( ! $is_fb ) return;

    $_POST['importType']    = 'page';
    $_POST['importTypeVal'] = 'GermanTampa';
}, 1 );

// 2) FORM UI: lock import-by/page, and default the date window.
add_action( 'admin_footer', function() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ( ! $screen || strpos( $screen->id, 'MEC-advimp' ) === false ) return;

    // Compute defaults server-side using WP local time.
    $today = date_i18n( 'Y-m-d' );
    $plus60 = date_i18n( 'Y-m-d', strtotime( '+60 days' ) );
    ?>
    <script>
    (function(){
        var GASF_SDATE = <?php echo wp_json_encode( $today ); ?>;
        var GASF_EDATE = <?php echo wp_json_encode( $plus60 ); ?>;

        function lockUI(){
            var sel  = document.getElementById('mec-advimp-importby-inp');
            var page = document.getElementById('mec-advimp-importby-page-inp');
            if ( ! sel || ! page ) return false;

            var hasPage = false;
            for ( var i = 0; i < sel.options.length; i++ ) {
                if ( sel.options[i].value === 'page' ) { hasPage = true; break; }
            }
            if ( hasPage ) {
                sel.value = 'page';
                if ( window.jQuery ) { jQuery(sel).trigger('change'); }
                else { sel.dispatchEvent( new Event('change', { bubbles: true }) ); }
            }
            sel.setAttribute('disabled','disabled');
            sel.style.opacity = '0.7';

            page.value = 'GermanTampa';
            page.setAttribute('readonly','readonly');
            page.style.backgroundColor = '#f0f0f0';
            page.style.cursor = 'not-allowed';

            var row = document.getElementById('mec-advimp-importby-page');
            if ( row ) { row.style.display = 'block'; }

            // Default the date window: start = today, end = today + 60 days.
            var sd = document.getElementById('mec-advimp-import-sdate');
            var ed = document.getElementById('mec-advimp-import-edate');
            if ( sd && ! sd.getAttribute('data-gasf-set') ) {
                sd.value = GASF_SDATE;
                sd.setAttribute('data-gasf-set','1');
                if ( window.jQuery ) { jQuery(sd).trigger('change'); }
            }
            if ( ed && ! ed.getAttribute('data-gasf-set') ) {
                ed.value = GASF_EDATE;
                ed.setAttribute('data-gasf-set','1');
                if ( window.jQuery ) { jQuery(ed).trigger('change'); }
            }

            return true;
        }
        function attempt(n){ if ( lockUI() ) return; if ( n>0 ) setTimeout(function(){ attempt(n-1); }, 300); }
        if ( document.readyState !== 'loading' ) attempt(20);
        else document.addEventListener('DOMContentLoaded', function(){ attempt(20); });
    })();
    </script>
    <?php
}, 99 );
