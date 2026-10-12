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

## Optional: the Claude connector (MCP)

The website does not need this to run. Nothing in the theme calls it, and the site works the same
with it switched off or removed.

It is two plugins: the official WordPress **MCP Adapter** and **TEG Systems Tools** (source in
`tools/teg-systems-tools/`). With both active and an application password set up, Claude, an AI
assistant, can work on the site through WordPress itself:

- **Audit the site:** read the pages, posts, menus, media library and recent changes, and report
  problems.
- **Edit content:** create or update pages and posts, and publish, unpublish or make private pages,
  posts and products. New pages and posts are saved as drafts, and every save keeps a revision that
  can be rolled back from the editor.
- **Add and update products** through WooCommerce's own tools (WooCommerce 11 and later).

It cannot delete anything, see orders or customer details, install plugins or themes, or change
this theme's code. The designed pages (Home, About, Portfolio and the rest) live in this theme, so
their wording is changed here in the source, and goes through git and the staging site like any
other change.

Every change made through the connector is listed under **Tools > TEG Systems activity**.

To remove it: deactivate and delete both plugins, then revoke the application password under
Users > Profile > Application Passwords.
