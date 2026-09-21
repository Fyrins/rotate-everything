# Rotate Everything

Rotate any block by a free angle from the editor sidebar. Server-rendered CSS transform, no JavaScript on the front end.

[![CI](https://github.com/Fyrins/rotate-everything/actions/workflows/ci.yml/badge.svg)](https://github.com/Fyrins/rotate-everything/actions/workflows/ci.yml)

Plugin directory page: https://wordpress.org/plugins/rotate-everything/

- **Requires WordPress** 6.2 or later
- **Requires PHP** 7.4 or later
- **License** GPL-2.0-or-later

---

## What it does

Adds a `Rotation` panel to the block inspector. The angle is stored as a block attribute, previewed live in the editor, and turned into a `transform: rotate()` declaration when the page is rendered.

By default the panel appears on `core/image` and on nothing else. Rotating running text hurts legibility, so opening the list up is a decision the site owner makes explicitly through a filter.

## Design decisions

These are the constraints the plugin was built under. They explain most of the code, so they are worth reading before changing any of it.

### Nothing is written to the saved markup

The `rotation` attribute is declared without a `source`, which means it lives in the block comment rather than in the block's HTML:

```html
<!-- wp:image {"id":42,"rotation":-8} -->
<figure class="wp-block-image size-full"><img src="…" alt="" class="wp-image-42"/></figure>
<!-- /wp:image -->
```

Block validation reads the HTML, not the comment, so the attribute is invisible to it. The consequence is the one that matters: deactivating the plugin invalidates nothing. The markup in the database is still exactly what `core/image`'s save function produces, so the editor compares identical strings and every block stays valid.

Writing the transform into the saved markup, as an earlier prototype of this plugin did through `blocks.getSaveElement`, breaks that property in both directions. It creates a second source of truth alongside `render_block`, which risks applying the rotation twice, and it puts a `style` attribute in the database that the regenerated markup no longer produces once the plugin is gone, invalidating every affected block at deactivation.

For the same reason there is no `blocks.getSaveContent.extraProps` filter and no `blocks.getSaveElement` filter in this codebase, and there should not be one.

### The transform is applied on the server, through the Tag Processor

`render_block` hands us the rendered HTML of one block. We open it with `WP_HTML_Tag_Processor`, move to the target element, and write two attributes. No `DOMDocument`, which would reserialise markup it does not fully understand, and no regular expression over HTML.

The Tag Processor can only read and write attributes, which is all this needs.

### Where the transform lands, and why

On the block's **outermost rendering element**: the first tag that is not `<style>`, `<script>`, `<link>` or `<meta>`. In document order that element is the outermost one, so it is the element whose layout box the browser reserves for the block. Rotating it rotates the block and nothing else.

The alternative was to look for the first element carrying a `wp-block-` class. That is the more fragile rule: a dynamic block rendering its own markup without `get_block_wrapper_attributes()` has no such class on its outer element, and the search would then descend into an inner block and tilt a fragment of the content. The metadata elements are skipped because block supports and third-party filters may prepend a `<style>` or a `<link>` to a block's markup.

If the block content holds no rendering element at all, nothing is transformed. Guessing is worse than doing nothing.

### The `style` attribute is merged, never replaced

A block may already carry an inline `style` written by core or by another plugin. The existing value is read, kept whole, and the new declaration is appended after it, with a missing trailing semicolon supplied:

```
style="border-radius:8px"           ->  style="border-radius:8px;transform:rotate(-8deg);"
style="border-radius:8px;"          ->  style="border-radius:8px;transform:rotate(-8deg);"
```

The old value is deliberately **not** parsed. Splitting it on `;` would corrupt a legitimate `url(data:image/png;base64,…)` whose own semicolon means nothing to the cascade. Appending also settles the case where the element already carries a `transform`: the later declaration wins, which is plain CSS cascade rather than a rule of the plugin's own invention.

### Every value that reaches CSS is validated first

`$block['attrs']` is parsed out of a block comment and never validated by WordPress. The angle can arrive as a string, an array, a boolean, `null`, `INF`, or a number far outside the range. `Rotate_Everything_Settings::sanitize_angle()` rejects anything that is not a finite number, rounds what is left, and confines it to the configured bounds. Typing the parameter as `int` instead, as an earlier prototype did, raises a `TypeError` on a forged value.

The same applies to filtered values. `rotate_everything_transition_duration` ends up concatenated into a stylesheet, so it is matched against `/^[0-9]*\.?[0-9]+m?s$/` and replaced by the default if it does not match. Nothing reaches a CSS declaration through escaping alone.

### Effects of `transform` that the plugin cannot remove

A rotation is purely visual. It does not change DOM order and it does not change the accessibility tree, so there are no ARIA roles to restore and the plugin sets none. What it does change is real, and it is on you to design around it:

- **Containing block and stacking context.** `transform` makes the element a containing block for absolutely positioned descendants and creates a stacking context. A descendant with `position: fixed` stops being fixed to the viewport, and `z-index` inside the block is resolved against the block instead of against the page.
- **Overflow past the layout box.** The layout box does not rotate, only the paint does. A tilted element's corners reach outside their box and can overlap neighbouring content or push the page into horizontal scrolling. See the measurements below: this is the one effect that can turn into a WCAG failure, and it depends on your content rather than on the plugin.
- **Motion.** The front-end transition is neutralised under `@media (prefers-reduced-motion: reduce)` (WCAG 2.3.3). A rotation that animates is a vestibular disorder trigger.

#### Reflow: what was measured, and what to do about it

WCAG 2.2 1.4.10 asks that content not require scrolling on two axes at 320 CSS pixels wide, and 1.4.4 asks the same at 400% zoom (which on a 1280-pixel window comes to the same 320 pixels). Measured on the testbed, Chromium, Twenty Twenty-Five, reading `window.scrollX` after scrolling to the far right rather than reading `scrollWidth`, which reports content extent and not the ability to scroll:

| Page | 320 x 640 | 320 x 256 (400% zoom) | 1280 x 900 |
|---|---|---|---|
| No rotated block (control) | 0 px | 0 px | 0 px |
| Image at -8° | 0 px | 0 px | 0 px |
| Images at -8°, 15° and 45° | 0 px | 0 px | 0 px |
| **Full-width image at 45°** | **27 px** | **27 px** | **110 px** |

So a rotated block at the content width the Image block uses by default introduces no horizontal scrolling, at any of those sizes, even at 45°. A block set to full-width alignment and a large angle does, and that combination is a two-axis scroll failure.

The plugin does not try to patch it, and that decision was tested rather than assumed. `overflow-x: clip` and `overflow-x: hidden` on `body`, and `overflow-x: clip` on `html`, were each measured against that page: none of them reduced the scrollable width, because the overflow comes from the negative margin of full-width alignment carrying the rotated paint past the viewport's inline start. The remedies that would work, capping the element's width or reducing the angle, silently override what the author asked for.

What is left is to say so where the decision is made. The angle control's help text warns that a full-width block at a large angle can push the page sideways, and the readme says it again. The conduct to adopt: keep rotation for blocks inside the content width, or keep the angle small on a block that spans the viewport.

Three things the plugin deliberately does not do:

- No `transform: inherit` on descendants. An earlier prototype shipped `.rotate-everything-rotated * { transform: inherit; }`, which re-applies the transformation to every child, including children of third-party blocks.
- No permanent `will-change: transform`. It is a promise of compositing that the browser keeps in memory forever, at a GPU cost, for no gain on a static rotation.
- No change to the focus indicator. It follows the rotated box, which is the expected behaviour.

### Conditional asset loading

The stylesheet is registered **and** enqueued from `render_block`, at the moment a block is actually rotated. A page with no rotated block carries no reference to it, and a page with three rotated blocks carries exactly one.

Registering it lazily rather than on a hook of its own is deliberate, and it is the one design decision here that came out of measurement rather than reasoning. The relative order of `render_block` and `wp_enqueue_scripts` is not fixed: with a block theme on WordPress 7.x the template is rendered before `wp_head`, so `render_block` runs first and a style registered on `wp_enqueue_scripts` does not exist yet, while with a classic theme the content is rendered from inside the template, long after. The first implementation registered on `wp_enqueue_scripts` and silently enqueued nothing on a block theme. Registering on demand makes the plugin indifferent to which order it is dealing with.

Measured on WordPress 7.1 with Twenty Twenty-Five (block theme) and Twenty Twenty-One (classic theme): the link lands in `<head>` in both cases, because WordPress hoists late-printed styles through its template output buffer. Nothing depends on that, though. The rotation is an inline declaration, so a block stays tilted even if the stylesheet never arrives.

### Why those minimum versions

**WordPress 6.2** is the release `WP_HTML_Tag_Processor` shipped in. The five methods this plugin calls are all `@since 6.2.0`, checked in the WordPress 7.1.1 source rather than taken from memory: `next_tag()`, `get_tag()`, `get_attribute()`, `set_attribute()` and `get_updated_html()`.

`has_class()` and `add_class()` would be more natural for adding a class, but they are `@since 6.4.0`, so the class is added by hand through `get_attribute()` and `set_attribute()` instead of raising the floor by two releases for nothing.

**PHP 7.4** is WordPress's own floor, read from `https://api.wordpress.org/core/version-check/1.7/`, which reports `php_version: 7.4` for 7.1.1. The code uses no syntax newer than PHP 7.0, so the declared value is a support commitment aligned with core rather than a technical constraint. The CI matrix covers 7.4, 8.0, 8.1, 8.2, 8.3 and 8.4.

One consequence worth knowing: `str_contains()` is available despite the PHP 7.4 floor, because WordPress has polyfilled it since 5.9, which is below the 6.2 floor.

### The build, and what it is allowed to touch

The editor script is JSX in `src/editor.js`, compiled by [`@wordpress/scripts`](https://www.npmjs.com/package/@wordpress/scripts) to `build/editor.js`. That is the only compiled artefact. The front-end stylesheet is written by hand and shipped as it reads, and the front end still runs no JavaScript at all.

Three consequences worth knowing before touching any of it.

**The bundle is not in the repository.** `build/` is in `.gitignore`. CI builds it before assembling the package, and the deploy workflow builds it before publishing and refuses to publish without it. A fresh clone has no `build/`, so `Rotate_Everything_Assets::enqueue_editor_assets()` bails out quietly and the Rotation panel does not appear until you run `npm run build`. That silence is deliberate: the plugin adds no admin notice.

**Dependencies come from the asset file, not from a hand-written list.** `build/editor.asset.php` is generated by webpack and declares both the script handles and a content hash used for cache busting. Writing that list by hand is how a plugin ends up loading before a package it needs; the first version of this plugin listed `wp-blocks` and `wp-element`, which are not used, and missed `react-jsx-runtime`, which is.

**The `@wordpress/*` packages are devDependencies, not dependencies.** Webpack externalises every one of them to the `wp.*` globals WordPress already serves, so none of them are bundled: `build/editor.js` is under 3 KB. They are declared so that ESLint, the editor and anyone reading the imports can resolve them, and for no other reason. Nothing from `node_modules` reaches the published package.

Guideline 4 requires the source and the toolchain to be available. They are: the repository is public, `readme.txt` says where, and the whole chain is one npm package maintained by the WordPress project.

## Filters

All configuration is code. There is no settings page and no option is written to the database.

### `rotate_everything_supported_blocks`

Block types offered the Rotation panel. Default `array( 'core/image' )`.

```php
add_filter(
	'rotate_everything_supported_blocks',
	function ( $blocks ) {
		$blocks[] = 'core/group';
		$blocks[] = 'core/gallery';

		return $blocks;
	}
);
```

Returning an empty array switches the plugin off without deactivating it. Non-string entries are dropped, values are trimmed, and duplicates are removed. This list is read by the renderer and handed to the editor script through `wp_add_inline_script()`, so there is one list and it cannot drift.

### `rotate_everything_min_angle` / `rotate_everything_max_angle`

Inclusive bounds of the angle control, in degrees. Defaults `-359` and `359`.

```php
add_filter( 'rotate_everything_min_angle', fn() => -45 );
add_filter( 'rotate_everything_max_angle', fn() => 45 );
```

Both are rounded to whole degrees. If the range comes out inverted or empty, both bounds fall back to their defaults rather than leaving a control with no valid value.

### `rotate_everything_step`

Increment of the angle control, in degrees. Default `1`, minimum `1`.

```php
add_filter( 'rotate_everything_step', fn() => 15 );
```

Angles are whole degrees throughout the plugin. A fractional step is rounded up to 1.

### `rotate_everything_default_angle`

Angle used by a block that carries no usable value. Default `0`.

```php
add_filter( 'rotate_everything_default_angle', fn() => -3 );
```

Worth understanding before you use it: this is the attribute default, so every supported block without an explicit angle is rendered at this angle, including blocks saved before the filter existed. At the default of `0` the renderer returns the markup untouched.

### `rotate_everything_transition_duration`

Duration of the front-end `transform` transition, as a CSS time value. Default `'0.2s'`.

```php
// Slower.
add_filter( 'rotate_everything_transition_duration', fn() => '400ms' );

// Off for everybody. It is already off under prefers-reduced-motion.
add_filter( 'rotate_everything_transition_duration', '__return_empty_string' );
```

The value is validated against `/^[0-9]*\.?[0-9]+m?s$/`. Anything else, including `calc()` and scientific notation, falls back to the default. A non-default value is emitted as a custom property rather than as a `transition` override, so it cannot outrank the reduced-motion rule.

## Installing from this repository

The release on wordpress.org is the supported install path. To run the development copy:

```bash
git clone git@github.com:Fyrins/rotate-everything.git
cd rotate-everything
composer install          # PHPCS only; the plugin has no runtime PHP dependency
npm install               # @wordpress/scripts
npm run build             # produces build/editor.js
```

Then symlink or copy the directory into `wp-content/plugins/rotate-everything`. The directory name matters: Plugin Check infers the text domain from it.

`npm run build` is required. `build/` is not in the repository, so without it the plugin activates but the Rotation panel never appears, silently. Use `npm start` to rebuild on save while working on `src/editor.js`.

`assets/css/rotate-everything.css` is not compiled and ships as it reads.

## Checks

```bash
composer lint             # PHPCS, WordPress ruleset plus PHPCompatibilityWP
composer lint:fix         # phpcbf
composer lint:php         # php -l over every PHP file
npm run lint:js           # ESLint with the WordPress config, plus Prettier
npm run format            # applies the formatting
```

Plugin Check has to run against the **built package**, in a directory named exactly `rotate-everything`. Run on the source tree it reports the development files, and under a different directory name it infers the wrong text domain and buries the real findings:

```bash
npm run build
rsync -a --delete --delete-excluded --exclude-from=.distignore ./ dist/rotate-everything/
# then, in a WordPress install with the plugin-check plugin active:
wp plugin check rotate-everything \
  --categories=general,plugin_repo,security,performance,accessibility \
  --include-experimental
```

Two directories, easy to confuse: `build/` is webpack's output and goes into the package, `dist/` is where the package is assembled and does not.

`tests/block-cases.md` lists the block structures that must be transformed and those that must be refused, with the expected behaviour for each. It is a checklist for manual verification, not an automated suite.

## Translating

The text domain matches the slug and is declared in the plugin header. There is no `load_plugin_textdomain()` call: just-in-time loading covers a plugin hosted on wordpress.org, and the call would be dead weight.

```bash
npm run i18n
```

That is `make-pot` over `src/`, then `update-po`, `make-mo`, and `make-json` with a path map.

The map is what makes it work. `wp_set_script_translations()` reads a JSON file whose name carries the MD5 of the **registered** script path, `build/editor.js`, while the POT references `src/editor.js`. Without `--use-map={"src/editor.js":"build/editor.js"}` the file is named after a path WordPress never looks for, and the editor falls back to English with no error anywhere. CI checks the hash on every run.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Short version: PHPCS has to pass with nothing reported, Plugin Check has to pass on the built package across all five categories, and a pull request that adds a build step or writes to the saved markup will be declined on principle.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for the full text.
