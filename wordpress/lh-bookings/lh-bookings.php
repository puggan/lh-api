<?php
/*
Plugin Name: LH Bokningar
Description: Admin page for the booking requests of Lokalen (pendingRequests from lh-api).
Version: 0.1.0
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
        'Bokningsförfrågningar',
        'Bokningar',
        CAPABILITY,
        'lh-bookings',
        __NAMESPACE__ . '\\render_pending_requests',
        'dashicons-calendar-alt',
        26,
    );
});

function render_pending_requests(): void
{
    if (!current_user_can(CAPABILITY)) {
        wp_die('Du har inte behörighet att se den här sidan.');
    }

    // pendingRequests.js puts its content right after its own script tag (data-tsx="pendingRequests").
    echo '<div class="wrap">';
    echo '<script src="' . esc_url(script_url('jsx.js')) . '"></script>';
    echo '<script data-tsx="pendingRequests" src="' . esc_url(script_url('pendingRequests.js')) . '"></script>';
    echo '</div>';
}

/** /ts/<file>, with the file's modification time as version so a new build is not cached. */
function script_url(string $file): string
{
    $path = dirname(ABSPATH) . '/ts/' . $file;
    $version = is_file($path) ? filemtime($path) : 0;
    return '/ts/' . $file . '?ver=' . $version;
}
