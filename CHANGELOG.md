# Changelog

All notable changes to this plugin are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The user-facing version of this list lives in `readme.txt`, which is what the
plugin directory reads.

## [Unreleased]

## [1.0.0] - 2026-09-18

First release.

### Added

- `Rotation` panel in the block inspector: a free angle, a live preview in the
  editor canvas, and a reset button.
- Server-side rendering through `render_block` and `WP_HTML_Tag_Processor`. The
  transform lands on the block's outermost rendering element and the existing
  `style` attribute is merged rather than replaced.
- Front-end stylesheet enqueued only from `render_block`, so a page without a
  rotated block carries no reference to it.
- Transition neutralised under `@media (prefers-reduced-motion: reduce)`.
- Filters for every configurable value: `rotate_everything_supported_blocks`,
  `rotate_everything_min_angle`, `rotate_everything_max_angle`,
  `rotate_everything_step`, `rotate_everything_default_angle` and
  `rotate_everything_transition_duration`.
- French translation.

### Notes

- Nothing is written to the saved markup. The `rotation` attribute has no
  `source`, so it lives in the block comment and block validation ignores it.
  Deactivating the plugin invalidates no block.
- No JavaScript on the front end, no runtime Composer dependency, no option in
  the database, no settings page and no outgoing request.
- The editor script is built from `src/editor.js` with `@wordpress/scripts`.
  The front-end stylesheet is not compiled. Nothing from `node_modules` is
  shipped: the `@wordpress/*` packages are externalised to the globals
  WordPress already serves, and the bundle stays under 3 KB. Sources and build
  instructions are in the repository, linked from `readme.txt`.
- `core/image` is the only block type supported by default. Rotating running
  text hurts legibility, so widening the list is an explicit decision made
  through a filter.

[Unreleased]: https://github.com/Fyrins/rotate-everything/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/Fyrins/rotate-everything/releases/tag/v1.0.0
