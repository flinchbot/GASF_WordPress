<?php
/**
 * Snippet #20: MEC Importer — Force Facebook Page Defaults
 * Scope: global | Active: Yes | Priority: 1
 * Server-side: forces importType=page and importTypeVal=GermanTampa on the FB import AJAX action; cosmetic JS reflects locked values. Replaces the old JS-disable approach that broke importType.
 */

/**
 * GASF: MEC Advanced Importer - Force Facebook Page Defaults (server-side)
 *
 * Hard-locks every manual Facebook import to:
 *   importType    = 'page'
 *   importTypeVal = 'GermanTampa'   (the GASF page, ID 156837460875)
 *
 * Enforced SERVER-SIDE on the importer's admin-ajax actions, so the request is
 * always correct regardless of what the form JS did. (The form's import-by
 * <select> defaults to 'single' and its JS var defaults to 'my', which made
 * requests hit /me/events and return nothing - this guarantees /{page}/events.)
 *
 * A small cosmetic JS also reflects the locked values in the form UI.
 *
 * Update-safe: no plugin files touched. To import by another method, deactivate.
 */

// 1) SERVER-SIDE ENFORCEMENT — runs before the importer reads $_POST.
add_action( 'admin_init', function() {
    if ( ! isset( $_POST['action'] ) ) return;
    $action = sanitize_text_field( wp_unslash( $_POST['action'] ) );

    // Only the Facebook import / schedule actions
    $fb_actions = array( 'facebook_get_events', 'mec_advimp_schedule_events' );
    if ( ! in_array( $action, $fb_actions, true ) ) return;

    // Only act when this is clearly a Facebook import (avoid clobbering others)
    $is_fb = ( isset( $_POST['importType'] ) || strpos( $action, 'facebook' ) !== false );
    if ( ! $is_fb ) return;

    $_POST['importType']    = 'page';
    $_POST['importTypeVal'] = 'GermanTampa';
}, 1 );

// 2) COSMETIC: reflect the locked values in the importer form UI.
add_action( 'admin_footer', function() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ( ! $screen || strpos( $screen->id, 'MEC-advimp' ) === false ) return;
    ?>
    <script>
    (function(){
        function lockUI(){
            var sel  = document.getElementById('mec-advimp-importby-inp');
            var page = document.getElementById('mec-advimp-importby-page-inp');
            if ( ! sel || ! page ) return false;

            // Ensure a "page" option exists and is selected (visual only)
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
            return true;
        }
        function attempt(n){ if ( lockUI() ) return; if ( n>0 ) setTimeout(function(){ attempt(n-1); }, 300); }
        if ( document.readyState !== 'loading' ) attempt(20);
        else document.addEventListener('DOMContentLoaded', function(){ attempt(20); });
    })();
    </script>
    <?php
}, 99 );
