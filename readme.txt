=== Rotate Everything ===
Contributors: fyrins
Tags: block editor, gutenberg, rotate, transform, image
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tilt a block by any angle from the editor sidebar. The transform is rendered by the server, so the front end ships no JavaScript.

== Description ==

Rotate Everything adds a Rotation panel to the block inspector. Pick an angle, watch the block tilt in the editor, and that is the whole feature. There is no settings page, no account, no dashboard notice and no outgoing request.

Out of the box the panel is offered on the Image block and nothing else. That restriction is the point rather than an oversight: a tilted paragraph is a paragraph people have to tilt their head to read. If your design needs another block type, one filter opens it up.

**What it does not touch**

The angle is kept in the block comment, not in the saved markup. Your post content stays byte for byte what the block itself produced, and the transform is added while the page is being built. Two things follow from that:

* Deactivating the plugin does not invalidate a single block. The tilt stops, the content is intact, and the editor raises no "this block contains unexpected content" warning.
* Nothing is written twice. The rotation exists in one place and is applied once, on the server.

**Things worth knowing before you tilt something**

A CSS transform has consequences that have nothing to do with this plugin, and they are worth saying out loud:

* A rotated element still occupies its original, unrotated layout box. The visible corners stick out of it and can overlap whatever sits next to the block. Give the block some breathing room rather than expecting the page to make room for it.
* A transform creates a containing block and a stacking context. A descendant with `position: fixed` stops being fixed to the viewport, and `z-index` inside the block is measured against the block instead of the page.
* A block sitting inside the normal content width does not push the page sideways, even at 45 degrees. A block set to full-width alignment at a large angle does, and that is worth avoiding: a page that scrolls both up and down and left to right is hard to use and fails an accessibility requirement. Keep rotation for blocks inside the content width, or keep the angle small on a block that spans the whole viewport.

**Front end**

No JavaScript. One small stylesheet, requested only on pages that actually contain a rotated block, carrying a transform origin and a transition. The transition switches itself off for anyone whose system asks for reduced motion.

**Filters**

Everything is configured in code. `rotate_everything_supported_blocks` sets the block types, `rotate_everything_min_angle` and `rotate_everything_max_angle` set the range, `rotate_everything_step` sets the increment, `rotate_everything_default_angle` sets the starting value, and `rotate_everything_transition_duration` sets or removes the transition. The development README documents each one with an example.

**Source code and build**

The editor script shipped in `build/` is compiled from `src/editor.js`. The uncompiled source, the build configuration and the full history live in the public repository:

https://github.com/Fyrins/rotate-everything

The toolchain is [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts), the build tool maintained by the WordPress project, and nothing else. To reproduce the shipped file:

`git clone https://github.com/Fyrins/rotate-everything.git
cd rotate-everything
npm install
npm run build`

The front-end stylesheet is not compiled: `assets/css/rotate-everything.css` is written by hand and shipped as it reads.

== Installation ==

1. In your admin, go to Plugins, then Add New Plugin, and search for Rotate Everything.
2. Install and activate it.
3. Edit a post or a template, select an Image block, and open Rotation in the block settings sidebar.

There is nothing to configure afterwards. A manual install works the same way: upload the folder to `wp-content/plugins` and activate it.

== Frequently Asked Questions ==

= Why is only the Image block supported? =

Because rotating text is a readability problem rather than a design feature. Anyone who genuinely needs another block type can say so in code, and that decision then lives in their theme instead of being the plugin's default.

= How do I make another block type rotatable? =

Add this to your theme's `functions.php` or to a small plugin of your own:

`add_filter( 'rotate_everything_supported_blocks', function ( $blocks ) {
	$blocks[] = 'core/group';
	return $blocks;
} );`

= Does it change my saved post content? =

No. The angle lives in the block comment, which block validation ignores. The markup stored in your database is exactly what the block's own save function wrote.

= What happens to my rotated blocks if I deactivate the plugin? =

They stop being rotated, and that is all. No block is invalidated, no content is lost, and there is no cleanup to do.

= My rotated image overlaps the text below it. Is that a bug? =

No, that is how CSS transforms work. The layout box stays where it was and only the paint is rotated, so the corners of a tilted element reach outside their box. Add spacing around the block, or use a smaller angle.

= My page scrolls sideways now. =

Check whether the rotated block is set to full-width alignment. A block that already spans the viewport has nowhere to put its rotated corners, so they end up past the edge of the page and the browser offers a horizontal scrollbar. A block inside the normal content width does not do this, even at 45 degrees. Either drop the full-width alignment or use a smaller angle.

= Something inside my rotated block used to be sticky or fixed and now it is not. =

A transform turns the element into a containing block, so `position: fixed` inside it becomes relative to the block rather than to the viewport. That is a browser rule and the plugin cannot opt out of it. Rotate the inner element instead of the container, or drop the fixed positioning.

= Can I turn the transition off? =

Yes, and it is already off for anyone who has asked their operating system for reduced motion. To remove it for everybody:

`add_filter( 'rotate_everything_transition_duration', '__return_empty_string' );`

= Does it phone home? =

No. There is no telemetry, no external asset and no outgoing request of any kind, and no option is written to your database.

== Screenshots ==

1. The Rotation panel in the block inspector, with the angle control and the reset button.
2. Dragging the angle control tilts the block in the editor straight away.
3. The same block on the front end, rotated by a server-rendered CSS transform.

== Changelog ==

= 1.0.0 =
* First release.
* Rotation panel in the block inspector, with a free angle, a live preview and a reset button.
* Server-side rendering through `render_block` and the HTML Tag Processor, with nothing written to the saved markup.
* Stylesheet loaded only on pages that contain a rotated block.
* Transition neutralised under `prefers-reduced-motion`.
* French translation included.

== Upgrade Notice ==

= 1.0.0 =
First release.
