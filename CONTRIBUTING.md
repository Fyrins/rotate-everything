# Contributing

Thanks for looking. This is a small plugin with a narrow scope, and most of what follows exists to keep it that way.

## Before you open a pull request

Read the **Design decisions** section of [README.md](README.md). Five of them are load-bearing, and a change that breaks one will be declined regardless of how well it is written:

1. **Nothing is written to the saved markup.** No `blocks.getSaveElement`, no `blocks.getSaveContent.extraProps`, no `save` override. The angle stays in the block comment and the transform is applied at render time.
2. **No JavaScript on the front end.** Ever. The build exists for the editor script and nothing else.
3. **The toolchain is `@wordpress/scripts` and nothing else.** No second bundler, no Babel config of our own, no PostCSS pipeline. The front-end stylesheet stays hand-written: two rules do not need a preprocessor.
4. **Nothing from `node_modules` ships.** The `@wordpress/*` packages are devDependencies, externalised by webpack to the `wp.*` globals. If a change makes the bundle grow past a few KB, something is being bundled that should not be.
5. **No runtime Composer dependency.** `composer.json` exists for PHPCS and is excluded from the release.

Also declined by default: a settings page, an option in the database, an admin notice, a credit in the front-end output, an outgoing request of any kind, and a bundled third-party library where WordPress already ships one.

Widening `rotate_everything_supported_blocks`' default is a separate conversation. The single-block default is a readability decision, not an accident.

## Setting up

```bash
git clone git@github.com:Fyrins/rotate-everything.git
cd rotate-everything
composer install          # PHPCS
npm install               # @wordpress/scripts
npm run build             # produces build/editor.js
```

`npm run build` is not optional. `build/` is not in the repository, and without it the plugin loads but the Rotation panel never appears: `enqueue_editor_assets()` finds no asset file and returns. There is no admin notice telling you so, by design.

Use `npm start` while working on `src/editor.js`; it rebuilds on save.

Symlink or copy the directory into a WordPress install at `wp-content/plugins/rotate-everything`. Keep that exact directory name.

A disposable testbed is enough, and a separate directory from this repository is the point:

```bash
mkdir ~/rotate-everything-testbed && cd ~/rotate-everything-testbed
ddev config --project-type=wordpress --project-name=rotate-everything-testbed
ddev start
ddev wp core download
ddev wp core install --url=https://rotate-everything-testbed.ddev.site \
  --title="Rotate Everything testbed" --admin_user=admin \
  --admin_password=admin --admin_email=admin@example.test
ddev wp theme install twentytwentyfive --activate
ddev wp plugin install plugin-check --activate
```

Sync the plugin in by applying `.distignore`, so you are looking at what ships:

```bash
( cd /path/to/rotate-everything && npm run build )
rsync -a --delete --delete-excluded --exclude-from=.distignore \
  /path/to/rotate-everything/ web/wp-content/plugins/rotate-everything/
```

`--delete-excluded` is not optional. Without it rsync protects excluded files from deletion, and the target directory stays polluted by whatever the previous sync left there.

## What has to pass

### JavaScript

```bash
npm run lint:js           # ESLint with the WordPress config, plus Prettier
npm run format            # applies the formatting
npm run build
```

Nothing reported by the linter. `.prettierrc.js` re-exports `@wordpress/prettier-config`; without it Prettier falls back to its own defaults and `format` and `lint-js` reformat the same file two different ways, each undoing the other.

Watch the bundle size in the build output. It sits under 3 KB. A jump means a package stopped being externalised and is now being bundled, which is a bug, not a size problem.

### PHPCS

```bash
composer lint
```

Nothing reported. Not "a few warnings we live with": nothing. The ruleset is `phpcs.xml.dist` (WordPress plus PHPCompatibilityWP, `testVersion` 7.4 and up, `minimum_wp_version` 6.2). `composer lint:fix` handles the mechanical part.

### Plugin Check

On the built package, in a directory named exactly `rotate-everything`, across all five categories:

```bash
npm run build
rsync -a --delete --delete-excluded --exclude-from=.distignore ./ dist/rotate-everything/
# with the package synced into the testbed:
ddev wp plugin check rotate-everything \
  --categories=general,plugin_repo,security,performance,accessibility \
  --include-experimental
```

Build first. A package assembled without `build/` is a package whose editor script is missing, and Plugin Check will happily pass it.

Zero errors, zero warnings. Running it on the source tree instead reports the development files; running it under a different directory name makes it infer the wrong text domain and bury everything real.

### Manual verification

`tests/block-cases.md` lists the block structures that must be transformed and those that must be refused. Walk it. In particular, with `WP_DEBUG` and `WP_DEBUG_LOG` on, front end and editor, in English and in French, `debug.log` has to stay empty. Prove the log works first by triggering a notice on purpose, otherwise an empty log proves nothing.

## Translations

```bash
npm run i18n
```

That runs four steps: `make-pot` over `src/` (not over `build/`, whose strings are minified), `update-po`, `make-mo`, and `make-json` with a path map.

The map is the part that bites. `wp_set_script_translations()` names the JSON file after the md5 of the **registered** script path, which is `build/editor.js`, while the POT references `src/editor.js`. Without `--use-map={"src/editor.js":"build/editor.js"}` the JSON gets the wrong name and the editor silently falls back to English, with no error anywhere. CI checks the hash on every run.

If the entry point ever moves, the hash changes and the translations have to be regenerated.

Regenerate the `.pot` from the code rather than editing it. If you add a string:

- Literal only. No variable, no concatenation inside `__()`.
- Numbered `printf` placeholders as soon as there is more than one.
- A `/* translators: */` comment above any string with a placeholder, explaining what each one holds.
- `_x()` with a context for anything ambiguous. "Rotation" and "Reset" both are.
- Nothing translated before `init`: since WordPress 6.7 that raises a `_doing_it_wrong()` notice.

## Releasing

Maintainers only.

1. Bump the version in **four** places: the `Version:` header in `rotate-everything.php`, `ROTATE_EVERYTHING_VERSION` in the same file, `Stable tag:` in `readme.txt`, and the heading in `CHANGELOG.md`.
2. Add the release notes to `readme.txt`'s `== Changelog ==` and to `CHANGELOG.md`. Both are checked by the deploy workflow.
3. Validate `readme.txt` at https://wordpress.org/plugins/developers/readme-validator/ (needs a logged-in wordpress.org session). Two minutes here against several days of waiting if it is rejected.
4. Merge to `main` and wait for CI to come back green.
5. Tag and push:
   ```bash
   git tag v1.0.1
   git push origin v1.0.1
   ```

`deploy.yml` fires on `v*`. Before touching SVN it checks the tag against the plugin header, the PHP constant and the readme's stable tag, refuses a stable tag of `trunk`, and requires a changelog section in both `CHANGELOG.md` and `readme.txt`. Any of those failing stops the run before anything is published. It then builds the editor script, refuses to publish if the bundle or its translation JSON is missing, and only after the directory has the release does it open the GitHub release with the notes taken from `CHANGELOG.md`.

Every action in both workflows is pinned to a commit SHA rather than a tag. A tag is a movable reference, and the deploy job holds the SVN credentials: whatever that tag pointed at tomorrow would publish under the maintainer's wordpress.org account. Bump a pin by editing the SHA and the comment together.

### The two secrets

`deploy.yml` needs `SVN_USERNAME` and `SVN_PASSWORD` in the repository's Actions secrets. They are the wordpress.org account credentials with commit access to the plugin's SVN repository, and they are created by hand:

1. GitHub, repository **Settings**, **Secrets and variables**, **Actions**, **New repository secret**.
2. `SVN_USERNAME`: the wordpress.org username.
3. `SVN_PASSWORD`: that account's password.

Nothing in this repository generates them and nothing should. Until both exist, `deploy.yml` fails at the publish step, which is the correct outcome.

The wordpress.org assets (banners, icon, screenshots) live in `.wordpress-org/` and are pushed to SVN's `assets/` directory by the same action.

## Reporting a bug

Open an issue on GitHub with the WordPress version, the PHP version, the theme, the block type, the angle, and the markup the front end actually produced. "The rotation does not work" is not reproducible; a `wp post get --field=content` and a `curl` of the rendered page are.

For a security issue, do not open a public issue. Use GitHub's private vulnerability reporting on this repository.
