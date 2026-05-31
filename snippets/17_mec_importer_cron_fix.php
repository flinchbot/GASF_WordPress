<?php
/**
 * Snippet #17: MEC Importer Cron Fix
 * Scope: global | Active: Yes | Priority: 1
 * Patches the MEC Advanced Importer cron bug where setup_sync_cron() clears and re-registers on every init, preventing the hook from ever firing. Replaces MEC behavior with a safe register-once pattern.
 */

// MEC Advanced Importer Cron Fix
// The plugin clears and re-registers its cron hook on every init,
// which means the hook is always reset to now+60s and never fires.
// This snippet removes that behavior and replaces it with a safe,
// register-once pattern. Plugin-update-safe.

add_action("init", function() {
    // Remove MEC's buggy handler (clears cron on every page load)
    remove_action("init", ["MEC_Advanced_Importer\\MEC_Advanced_Importer_Base", "setup_sync_cron"]);
}, 1);

add_action("init", function() {
    // Register custom schedule if not present
    add_filter("cron_schedules", function($schedules) {
        if (!isset($schedules["every_minute"])) {
            $schedules["every_minute"] = [
                "interval" => 60,
                "display"  => "Every Minute",
            ];
        }
        return $schedules;
    });

    // Only register if not already scheduled — never clear existing
    if (!wp_next_scheduled("mec_advimp_sync_hook")) {
        wp_schedule_event(time(), "every_minute", "mec_advimp_sync_hook");
    }
    if (!wp_next_scheduled("mec_advimp_cleanup_hook")) {
        wp_schedule_event(time(), "every_minute", "mec_advimp_cleanup_hook");
    }
}, 20);