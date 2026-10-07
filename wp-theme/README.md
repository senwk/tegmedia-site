# TEG Media theme: how it works and how to maintain it

A classic WordPress theme in plain PHP, HTML and CSS. No page builder, no framework, no build step
needed on the server. It uses only standard WordPress and WooCommerce functions.

## What is where

| Part | Where it lives | Who edits it |
|---|---|---|
| Designed pages (Home, About, Portfolio, Membership, Advertise, Events, Past clients, Read, The Expat, Contact, Privacy, 404) | `parts/<page>.html`, generated from the source project | The source project (see below) |
| Shared header and footer | `parts/header.html`, `parts/footer.html` | The source project |
| Page titles and descriptions | `inc/pages.php` (generated) | The source project |
| Styles | `css/*.css` | The source project (`css/`), or `css/wp.css` for WordPress-only rules |
| PHP: page serving, shop pages, contact form, redirects, images, security headers | `functions.php`, `issues.php`, `woocommerce.php`, `page.php`, `header.php`, `footer.php` | Directly, in the source project's `wp-theme/` folder |
| Shop, cart, checkout, account, iPay88 | WooCommerce and its plugins, untouched | WordPress admin as normal |
| Products, prices, the TEG Card form fields | WooCommerce products + Advanced Product Fields | WordPress admin as normal |
| Any other page or post | WordPress editor, shown in the theme's header and footer | WordPress admin as normal |

**Do not edit files inside this theme folder on the server.** They are overwritten the next time
the theme is uploaded. Make the change in the source project and rebuild.

## The source project

`tegmedia-site/` (kept under version control with git):

- `src/site/pages/*.html`, `src/site/partials/*.html`: the page content
- `css/`, `js/`, `assets/`: styles, scripts, images
- `wp-theme/`: the hand-written WordPress files (this README, `functions.php`, etc.)
- `build-site.js`: builds the static preview into `dist/`
- `build-wp-theme.js`: builds this theme into `../tegmedia-wp/theme/teg-media/` and the zip

To change a designed page: edit `src/site/pages/<page>.html`, run `node build-wp-theme.js`
(Node 18+), upload `teg-media-theme.zip` under Appearance > Themes > Add New > Upload, and choose
"Replace active with uploaded". Bump `VERSION` in `build-wp-theme.js` each time.

## Things that update themselves

- **Magazine covers and issue links** come from Calameo twice a day (`issues.php`; status under
  Appearance > TEG Media issues). If Calameo changes, the site keeps the issues it has.
- **TEG Card partner offers** load in the visitor's browser from the card programme's public feed.
  If the feed is down, the section shows a plain note.

## Images

Images are not in the theme zip (the server's upload limit). Appearance > TEG Media images >
"Copy images to this site" copies them into `wp-content/uploads/teg-media/assets/`. Any image
already there is always served from this site; only an image the site does not have yet is
loaded from the design preview. Once the copy finishes, nothing loads from the preview.
They can also be uploaded by hand: extract the images zip into `wp-content/uploads/`.

## Contact form

Posts to `admin-post.php` (action `teg_contact`). Every enquiry is saved under **Enquiries** in
the admin menu before it is emailed, and an admin notice appears if any email failed.
Spam checks: hidden honeypot field, a 3-second minimum, 5 per hour per IP address, and optional
Cloudflare Turnstile. To switch Turnstile on, add to `wp-config.php`:

    define( 'TEG_TURNSTILE_SITE_KEY', '...' );
    define( 'TEG_TURNSTILE_SECRET', '...' );

Change the recipient with the `teg_contact_to` filter (default: the site admin email).

## Plugins the theme works around

- **ShopLentor (WooLentor)** replaces product templates at `template_include` priority 999; the
  theme takes product pages back at 1000. Cart and checkout are left alone.
- **Ultimate Addons for Elementor (Header Footer)**: its header/footer override is switched off
  with its own filters.
- On designed pages only the theme's styles and scripts load (plus jQuery and the admin bar), so
  plugins built for the old Elementor pages cannot restyle them. Pop-ups that plugins print after the
  footer are hidden on those pages.

Tested 7 October 2026 on WordPress 7.1.3, PHP 8.4, WooCommerce 11.1.2, Elementor 4.3.4, UAE 2.9.5,
ShopLentor 3.5.4, Advanced Product Fields 1.7.1, LiteSpeed Cache 7.9.1: no errors or warnings from
the theme.

## Security headers

Sent on public pages: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
`Permissions-Policy`, a baseline `Content-Security-Policy` (framing, base-uri, object-src,
upgrade-insecure-requests) and `Strict-Transport-Security` over https. Adjust or remove with the
`teg_security_headers` filter. If the server (LiteSpeed/.htaccess) sets the same headers, remove
them from one side. A stricter CSP with a script allow-list should be set at server level and
tested against checkout and iPay88.

## Rolling back

Keep the previous theme (Metrobank Child) installed. Appearance > Themes > Activate it to go back
in one click. Nothing in this theme changes the database except its own options
(`teg_assets_set`, `teg_cal_*`) and the Enquiries post type.

## Redirects

Old page addresses forward to the new pages (302 during staging). Switch to permanent with:

    add_filter( 'teg_redirect_status', function () { return 301; } );
