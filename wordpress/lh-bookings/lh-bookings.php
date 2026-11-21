<?php
/*
Plugin Name: LH Bokningar
Description: Admin page for the bookings of Lokalen: login check, pending requests, upcoming periods, adding periods, the terms and calendar feeds (scripts from lh-api).
Version: 0.5.0
Author: "Puggan Sundragon" <puggan@sundragon.se>
License: MIT
*/

/*
 * Lives in the lh-api repository (wordpress/lh-bookings) and is symlinked into wp-content/plugins:
 *   ln -s /var/www/lh-api/wordpress/lh-bookings /var/www/lh/wp/wp-content/plugins/lh-bookings
 *
 * The scripts are the ones built by `npm run build` in lh-api, served from /ts/ and /less/
 * (symlinks in the site root to lh-api/public/ts and lh-api/public/less).
 */

namespace Puggan\LhBookings;

if (!defined('ABSPATH')) {
    exit;
}

/** Who may see and answer booking requests. */
const CAPABILITY = 'edit_pages';

add_action('admin_menu', static function (): void {
    add_menu_page(
        'Bokningar',
        'Bokningar',
        CAPABILITY,
        'lh-bookings',
        __NAMESPACE__ . '\\render_page',
        'dashicons-calendar-alt',
        26,
    );
});

/**
 * One section per script; each script puts its content right after its own script tag
 * (data-tsx="<name>") and renders its own heading.
 */
const SECTIONS = [
    'whoAmI',           // does the API recognise the WordPress login?
    'pendingRequests',  // requests to approve or decline
    'upcomingBookings', // upcoming periods and their bookings
    'addPeriods',       // add bookable periods, one or many
    'editTerms',        // the booking terms: publish a new version
    'calendarFeeds',    // calendar feeds to subscribe to (Google Calendar)
];

function render_page(): void
{
    if (!current_user_can(CAPABILITY)) {
        wp_die('Du har inte behörighet att se den här sidan.');
    }

    echo '<div class="wrap">';
    echo '<h1>Bokningar</h1>';
    echo '<script src="' . esc_url(script_url('jsx.js')) . '"></script>';
    foreach (SECTIONS as $name) {
        echo '<section class="lh-bookings-section">';
        echo '<script data-tsx="' . esc_attr($name) . '" src="' . esc_url(script_url($name . '.js')) . '"></script>';
        echo '</section>';
    }
    echo '</div>';
    echo '<style>.lh-bookings-section { margin-top: 2em; padding-top: 1em; border-top: 1px solid #c3c4c7; }</style>';
}

/** /ts/<file>, with the file's modification time as version so a new build is not cached. */
function script_url(string $file): string
{
    $path = dirname(ABSPATH) . '/ts/' . $file;
    $version = is_file($path) ? filemtime($path) : 0;
    return '/ts/' . $file . '?ver=' . $version;
}
