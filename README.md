# TEG Media website: source

Everything the tegmedia.my site is built from. Two outputs:

- **The design preview** (static): `node build-site.js` writes `dist/`; `npx wrangler@4 deploy`
  publishes it to https://tegmedia-site.mikedavison111.workers.dev.
- **The WordPress theme**: `node build-wp-theme.js` writes `../tegmedia-wp/theme/teg-media/` and
  `../tegmedia-wp/teg-media-theme.zip`. Upload the zip under Appearance > Themes > Add New >
  Upload and choose "Replace active with uploaded". Bump `VERSION` in `build-wp-theme.js` first.

Needs Node 18 or newer. No npm packages.

| Folder | What it holds |
|---|---|
| `src/site/pages/` | One HTML file per page; the first line sets its title and nav item |
| `src/site/partials/` | Header, footer and shared blocks |
| `css/`, `js/`, `assets/` | Styles, scripts and images, used by both outputs |
| `wp-theme/` | The hand-written WordPress PHP (`functions.php`, `issues.php`, ...) and its README |

How the theme works on WordPress, what is edited where, the contact form, security headers and
rollback are in `wp-theme/README.md`, which ships inside the theme.

Rule: never hand-edit the built theme on the server or in `tegmedia-wp/theme/`. Change the
source here, rebuild, commit, upload.
