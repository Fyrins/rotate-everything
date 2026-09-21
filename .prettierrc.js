/**
 * Prettier picks up its defaults unless a config file exists at the root, and
 * those defaults disagree with the WordPress style on tabs and on spacing
 * inside parentheses. Without this file, `wp-scripts format` and
 * `wp-scripts lint-js` format the same file two different ways and fight.
 */
module.exports = require( '@wordpress/prettier-config' );
