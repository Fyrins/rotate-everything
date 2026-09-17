# Block cases

The contract of `Rotate_Everything_Renderer::filter_render_block()`, case by
case. Each row is a `$block` array and a `$block_content` string going in, and
the markup that has to come out.

This is a manual checklist, not an automated suite. Walk it after touching the
renderer, the settings class or the `.distignore`. Defaults are assumed
throughout: supported blocks `array( 'core/image' )`, range `-359` to `359`,
step `1`, default angle `0`.

Two rules cover every row below, and the rest is their consequence:

1. A block is transformed only if its name is in the supported list **and** the
   sanitized angle is not `0` **and** the markup holds a rendering element.
2. When any of those does not hold, `$block_content` is returned **byte for
   byte** unchanged. Not re-serialised, not reformatted, not passed through the
   Tag Processor. Unchanged.

---

## Transformed

### T1. Plain supported block

```
attrs:  { "rotation": -8 }
in:     <figure class="wp-block-image size-full"><img src="a.jpg" alt=""/></figure>
out:    <figure class="wp-block-image size-full rotate-everything-rotated" style="transform:rotate(-8deg);"><img src="a.jpg" alt=""/></figure>
```

The class is appended to the existing ones. The `style` attribute is created.

### T2. Block that already carries an inline style

```
attrs:  { "rotation": 45 }
in:     <figure class="wp-block-image" style="border-radius:8px;"><img src="a.jpg" alt=""/></figure>
out:    <figure class="wp-block-image rotate-everything-rotated" style="border-radius:8px;transform:rotate(45deg);"><img src="a.jpg" alt=""/></figure>
```

`border-radius` survives. This is the case an implementation that calls
`set_attribute( 'style', … )` with a fresh value silently breaks.

### T3. Inline style with no trailing semicolon

```
attrs:  { "rotation": 45 }
in:     <figure class="wp-block-image" style="border-radius:8px"><img src="a.jpg" alt=""/></figure>
out:    <figure class="wp-block-image rotate-everything-rotated" style="border-radius:8px;transform:rotate(45deg);"><img src="a.jpg" alt=""/></figure>
```

The separator is supplied. `border-radius:8pxtransform:rotate(45deg)` is the
failure this row exists to catch.

### T4. Inline style holding a semicolon inside a value

```
attrs:  { "rotation": 10 }
in:     <figure class="wp-block-image" style="background-image:url(data:image/gif;base64,R0lGOD==)"><img src="a.jpg" alt=""/></figure>
out:    <figure class="wp-block-image rotate-everything-rotated" style="background-image:url(data:image/gif;base64,R0lGOD==);transform:rotate(10deg);"><img src="a.jpg" alt=""/></figure>
```

The old value is appended to, never parsed. An implementation that splits on
`;` to merge declarations corrupts this data URI.

### T5. Element that already carries a `transform`

```
attrs:  { "rotation": 10 }
in:     <figure class="wp-block-image" style="transform:translateX(4px);"><img src="a.jpg" alt=""/></figure>
out:    <figure class="wp-block-image rotate-everything-rotated" style="transform:translateX(4px);transform:rotate(10deg);"><img src="a.jpg" alt=""/></figure>
```

Both declarations are present and the later one wins, which is the cascade
doing its job. The plugin does not compose transforms: composing would require
parsing the existing value, which case T4 rules out.

### T6. Metadata element prepended to the block markup

```
attrs:  { "rotation": 20 }
in:     <style>.wp-container-1{gap:0}</style><figure class="wp-block-image"><img src="a.jpg" alt=""/></figure>
out:    <style>.wp-container-1{gap:0}</style><figure class="wp-block-image rotate-everything-rotated" style="transform:rotate(20deg);"><img src="a.jpg" alt=""/></figure>
```

`<style>`, `<script>`, `<link>` and `<meta>` are skipped. The `<style>` element
must come out untouched, and the selector inside it must not be mistaken for a
tag.

### T7. No class attribute at all

```
attrs:  { "rotation": 5 }
in:     <figure><img src="a.jpg" alt=""/></figure>
out:    <figure class="rotate-everything-rotated" style="transform:rotate(5deg);"><img src="a.jpg" alt=""/></figure>
```

### T8. Class already present

```
attrs:  { "rotation": 5 }
in:     <figure class="wp-block-image rotate-everything-rotated"><img src="a.jpg" alt=""/></figure>
out:    <figure class="wp-block-image rotate-everything-rotated" style="transform:rotate(5deg);"><img src="a.jpg" alt=""/></figure>
```

The class is added once. Not `rotate-everything-rotated rotate-everything-rotated`.

### T9. Supported block nested inside another block

```
post:   <!-- wp:group --><div class="wp-block-group">
          <!-- wp:image {"rotation":-15} --><figure class="wp-block-image">…</figure><!-- /wp:image -->
        </div><!-- /wp:group -->
```

The `<figure>` is rotated. The `<div class="wp-block-group">` is not: `core/group`
is not in the supported list, and `render_block` fires once per block with that
block's own attributes.

Check the editor here too. The panel has to show the **image's** angle while the
image is selected, not the group's. Reading the selected block from the data
store instead of the component props is exactly how that goes wrong.

### T10. Angle at the bounds

```
attrs:  { "rotation": 359 }   ->  transform:rotate(359deg);
attrs:  { "rotation": -359 }  ->  transform:rotate(-359deg);
```

Bounds are inclusive.

### T11. Angle as a numeric string

```
attrs:  { "rotation": "45" }  ->  transform:rotate(45deg);
```

`is_numeric()` accepts it, so it is honoured rather than discarded. A forged
value that happens to be valid is still a valid value.

### T12. Fractional angle

```
attrs:  { "rotation": 12.6 }  ->  transform:rotate(13deg);
attrs:  { "rotation": 12.4 }  ->  transform:rotate(12deg);
attrs:  { "rotation": -0.4 }  ->  no transformation (rounds to 0)
```

Angles are whole degrees. The rendered value is never `12.6deg`.

### T13. Dynamic block, once it is supported

```php
add_filter( 'rotate_everything_supported_blocks', fn( $b ) => array_merge( $b, array( 'core/latest-posts' ) ) );
```

```
attrs:  { "rotation": 6 }
in:     <ul class="wp-block-latest-posts__list wp-block-latest-posts"><li>…</li></ul>
out:    <ul class="wp-block-latest-posts__list wp-block-latest-posts rotate-everything-rotated" style="transform:rotate(6deg);"><li>…</li></ul>
```

A dynamic block reaches `render_block` with its callback's output already built,
so it is handled like any other. Its `<ul>` is the outermost rendering element
and that is where the transform lands.

---

## Refused

Every row here returns `$block_content` unchanged.

| # | Case | Input |
|---|---|---|
| R1 | Unsupported block type | `blockName: core/paragraph`, `attrs: { "rotation": 45 }` |
| R2 | Angle zero | `attrs: { "rotation": 0 }` |
| R3 | Attribute absent | `attrs: {}` |
| R4 | No attributes key | `$block` without `attrs` |
| R5 | Angle `null` | `attrs: { "rotation": null }` |
| R6 | Angle `false` / `true` | `attrs: { "rotation": false }` |
| R7 | Angle as a non-numeric string | `attrs: { "rotation": "forty-five" }` |
| R8 | Angle as an array | `attrs: { "rotation": [45] }` |
| R9 | Angle as an object | `attrs: { "rotation": { "deg": 45 } }` |
| R10 | Angle `INF` / `NAN` | `attrs: { "rotation": INF }` |
| R11 | Classic content | `blockName: null` |
| R12 | Empty markup | `$block_content` is `''` |
| R13 | Markup with no element | `$block_content` is `Just text.` |
| R14 | Markup made only of metadata elements | `<style>.a{}</style>` |
| R15 | Angle out of bounds, high | `attrs: { "rotation": 100000 }` |
| R16 | Angle out of bounds, low | `attrs: { "rotation": -100000 }` |
| R17 | CSS injection attempt | `attrs: { "rotation": "45deg);}body{display:none" }` |

R15 and R16 are refusals only in the sense that nothing crashes: they are
clamped to `359` and `-359` respectively and **are** rendered, at the bound.
The point of the row is that no `TypeError` is raised and no absurd value
reaches the stylesheet. Typing the parameter as `int` in the method signature,
as an earlier prototype did, throws on R7 through R10.

R17 has to produce nothing at all: `is_numeric( "45deg);}body{display:none" )`
is `false`, so the value never reaches a CSS declaration. Confirm by reading
the rendered HTML, not by reasoning about it.

---

## Angle sanitizing, in one table

`Rotate_Everything_Settings::sanitize_angle()` with the default range.

| Input | Result | Rendered |
|---|---|---|
| `45` | `45` | yes |
| `-45` | `-45` | yes |
| `"45"` | `45` | yes |
| `" 45"` | `45` | yes |
| `45.0` | `45` | yes |
| `12.6` | `13` | yes |
| `0` | `0` | no |
| `"0"` | `0` | no |
| `-0.4` | `0` | no |
| `100000` | `359` | yes, at the bound |
| `-100000` | `-359` | yes, at the bound |
| `"1e3"` | `359` | yes, at the bound |
| `null` | `0` | no |
| `true` | `0` | no |
| `false` | `0` | no |
| `""` | `0` | no |
| `"abc"` | `0` | no |
| `array( 45 )` | `0` | no |
| `INF` | `0` | no |
| `NAN` | `0` | no |

---

## Filtered values

| # | Filter | Return | Expected |
|---|---|---|---|
| F1 | `rotate_everything_supported_blocks` | `array()` | Panel offered nowhere, nothing rendered, plugin effectively idle |
| F2 | `rotate_everything_supported_blocks` | `'core/image'` (a string) | Falls back to the default array |
| F3 | `rotate_everything_supported_blocks` | `array( 'core/image', 42, '', ' core/group ' )` | `array( 'core/image', 'core/group' )` |
| F4 | `rotate_everything_min_angle` / `max` | `-45` / `45` | Control clamps to that range; a saved `90` renders as `45deg` |
| F5 | `rotate_everything_min_angle` / `max` | `90` / `-90` (inverted) | Both fall back to `-359` / `359` |
| F6 | `rotate_everything_step` | `0` | Step is `1` |
| F7 | `rotate_everything_step` | `0.5` | Step is `1`; angles stay whole degrees |
| F8 | `rotate_everything_default_angle` | `-3` | Supported blocks with no explicit angle render at `-3deg` |
| F9 | `rotate_everything_transition_duration` | `'400ms'` | Custom property emitted, `--rotate-everything-duration:400ms` |
| F10 | `rotate_everything_transition_duration` | `''` or `'0'` | `--rotate-everything-duration:0s` |
| F11 | `rotate_everything_transition_duration` | `'1s;}body{display:none'` | Falls back to `0.2s`, nothing injected |
| F12 | `rotate_everything_transition_duration` | `'calc(1s * 2)'` | Falls back to `0.2s` |
| F13 | `rotate_everything_transition_duration` | `array()` | Falls back to `0.2s` |

F11 is the one to read in the page source. A non-default duration is emitted as
a custom property, never as a `transition` override, so it cannot outrank the
`prefers-reduced-motion` rule and reinstate motion for someone who asked for
none.

---

## Assets

| # | Case | Expected |
|---|---|---|
| A1 | Page with a rotated block | `rotate-everything.css` referenced once |
| A2 | Page with a supported block at angle `0` | Stylesheet not referenced at all |
| A3 | Page with no supported block | Stylesheet not referenced at all |
| A4 | Page with three rotated blocks | Stylesheet referenced once |
| A5 | Any front-end page | No script from this plugin, in `<head>` or in the footer |
| A6 | Editor | `editor.js` enqueued, preceded by `window.rotateEverythingSettings` |

Check A1 through A4 with `curl | grep`, not with the browser's network panel: a
cache plugin or a service worker makes the panel lie.

---

## Editor

| # | Case | Expected |
|---|---|---|
| E1 | Select an image | Rotation panel present, angle control and reset button |
| E2 | Drag the angle control | Block tilts in the canvas straight away |
| E3 | Reset | Angle back to `0`, block upright, block still valid |
| E4 | Image inside a group, image selected | Panel shows the image's angle |
| E5 | Select a paragraph | No Rotation panel |
| E6 | Resize handles on a rotated image | Present and usable; they follow the rotated box |
| E7 | Selection outline on a rotated image | Present; follows the rotated box |
| E8 | Focus indicator | Untouched, follows the rotated box |
| E9 | Save, reload, reopen | `isValid: true` on every block |
| E10 | Deactivate the plugin, reopen the post | `isValid: true` on every block, no recovery prompt |
| E11 | Reactivate, reopen | Angle still there, rotation back |

E9 through E11 are the reason the attribute has no `source`. Check them with
`wp.data.select( 'core/block-editor' ).getBlocks().map( b => b.isValid )` in the
editor console, not by eye.

---

## Accessibility and layout

| # | Case | Expected |
|---|---|---|
| X1 | Block at `45deg` inside the content width, viewport 320px | No horizontal scrolling (WCAG 2.2, 1.4.10) |
| X2 | Same, at 400% zoom (320 x 256) | No horizontal scrolling (WCAG 1.4.4) |
| X3 | **Full-width** block at `45deg`, viewport 320px | Scrolls horizontally. Known, documented, not patched |
| X4 | `prefers-reduced-motion: reduce` | Computed `transition` is `none 0s`; the block is still rotated |
| X5 | Descendants of a rotated block | Computed `transform: none`; `transform: inherit` appears nowhere |
| X6 | Computed style of a rotated element | `will-change: auto` |
| X7 | Accessibility tree | Same rotated and upright; DOM order unchanged; no ARIA attribute added |
| X8 | Screen reader on a rotated image | Reads the `alt` text as usual |

**Measure the right thing.** `documentElement.scrollWidth` reports content
extent, not whether the page can be scrolled, and it reads 347 on a page that
cannot scroll at all. Scroll to the far right and read `window.scrollX`:

```js
window.scrollTo( 9999, 0 ); const maxScrollX = window.scrollX; window.scrollTo( 0, 0 );
```

Values measured on WordPress 7.1, Chromium, Twenty Twenty-Five:

| Page | 320 x 640 | 320 x 256 | 1280 x 900 |
|---|---|---|---|
| Control, no rotated block | 0 | 0 | 0 |
| Image at -8° | 0 | 0 | 0 |
| Images at -8°, 15°, 45° | 0 | 0 | 0 |
| Full-width image at 45° | 27 | 27 | 110 |

X3 is a real two-axis scroll failure and it is the author's to avoid, not the
plugin's to hide. Three remedies were measured on that page and none of them
reduced `maxScrollX` below 27: `body { overflow-x: clip }`,
`html { overflow-x: clip }` and `body { overflow-x: hidden }`. The overflow
comes from the negative margin of full-width alignment carrying the rotated
paint past the viewport's inline start, which an ancestor's overflow does not
reach. Anything that would work, capping the width or reducing the angle,
overrides what the author asked for.

Re-measure X1 through X3 after any change to the stylesheet, and do not replace
the `maxScrollX` check with a `scrollWidth` check.

---

## Package

| # | Case | Expected |
|---|---|---|
| P1 | `rsync --exclude-from=.distignore` | No hidden file or directory in `build/rotate-everything/` |
| P2 | Same | No `vendor/`, `docs/`, `tests/`, `composer.json`, `composer.lock`, `phpcs.xml.dist`, `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md` |
| P3 | Same | `readme.txt`, `LICENSE`, `languages/`, `assets/`, `includes/`, `rotate-everything.php` all present |
| P4 | A log directory dropped at the root by some tool | Excluded by the `.*` rule on the first line |
| P5 | Re-sync after deleting a file from the source | The file is gone from the target, which is what `--delete-excluded` buys |
| P6 | Version consistency | Plugin header, `ROTATE_EVERYTHING_VERSION`, readme `Stable tag` and the Git tag all agree |

---

## Logs

| # | Case | Expected |
|---|---|---|
| L1 | `WP_DEBUG` and `WP_DEBUG_LOG` on, a deliberate notice triggered | The notice appears in `debug.log` |
| L2 | Front end with a rotated block, locale `en_US` | Nothing added to `debug.log` |
| L3 | Front end with a rotated block, locale `fr_FR` | Nothing added |
| L4 | Editor, locale `en_US` | Nothing added |
| L5 | Editor, locale `fr_FR` | Nothing added |

L1 comes first. Without it, an empty log proves that logging is off, not that
the plugin is clean.
