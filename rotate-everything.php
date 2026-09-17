<?php
/**
 * Plugin Name:       Rotate Everything
 * Plugin URI:        https://github.com/Fyrins/rotate-everything
 * Description:       Adds a Rotation panel to the WordPress block inspector so a supported block can be tilted by a free angle. The angle is turned into a CSS transform when the page is built, so the front end ships no JavaScript.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Alexandre Revire
 * Author URI:        https://alexandre-revire.fr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rotate-everything
 * Domain Path:       /languages
 *
 * @package RotateEverything
 */

/*
Copyright (C) 2026 Alexandre Revire

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
*/

defined( 'ABSPATH' ) || exit;

/**
 * Plugin version. Kept in step with the plugin header, the readme Stable tag
 * and the Git tag; the deploy workflow refuses to publish when they diverge.
 */
define( 'ROTATE_EVERYTHING_VERSION', '1.0.0' );

/**
 * Absolute path to this file, used to resolve asset URLs.
 */
define( 'ROTATE_EVERYTHING_FILE', __FILE__ );

/**
 * Absolute path to the plugin directory, with a trailing slash.
 */
define( 'ROTATE_EVERYTHING_PATH', plugin_dir_path( __FILE__ ) );

require_once ROTATE_EVERYTHING_PATH . 'includes/class-rotate-everything-settings.php';
require_once ROTATE_EVERYTHING_PATH . 'includes/class-rotate-everything-assets.php';
require_once ROTATE_EVERYTHING_PATH . 'includes/class-rotate-everything-renderer.php';

/*
 * Bootstrapping is deliberately two plain hook registrations. There is no
 * container, no autoloader and no Composer dependency to install: the plugin
 * either works from the files shipped in the package or it does not ship.
 *
 * The front-end stylesheet has no hook of its own. It is registered and
 * enqueued by the renderer, at the moment it actually rotates something, which
 * is both how a page without a rotated block avoids loading it and how the
 * plugin stays indifferent to whether `render_block` runs before or after
 * `wp_enqueue_scripts`.
 */
add_filter( 'render_block', array( 'Rotate_Everything_Renderer', 'filter_render_block' ), 10, 2 );
add_action( 'enqueue_block_editor_assets', array( 'Rotate_Everything_Assets', 'enqueue_editor_assets' ) );
