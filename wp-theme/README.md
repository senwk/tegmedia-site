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

To change a designed page: edit `src/site/pages/<page>.html`, bump `VERSION` in
`build-wp-theme.js`, run `node build-wp-theme.js` (Node 18+, nothing to install), then upload
`../tegmedia-wp/teg-media-theme.zip` under Appearance > Themes > Add New > Upload and choose
"Replace active with uploaded". Try it on the staging site first.

## Things that update themselves

- **Magazine covers and issue links** come from Calameo twice a day (`issues.php`; status under
  Appearance > TEG Media issues). If Calameo changes, the site keeps the issues it has.
- **TEG Card partner offers** load in the visitor's browser from the card programme's public feed.
  If the feed is down, the section shows a plain note.

## Images

The site loads its photographs, covers and homepage film only from itself. There are two builds:

- **Standard** (`node build-wp-theme.js`): the images travel inside the theme, in `assets/`, so
  each update brings its own. The zip is about 20 MB. If WordPress refuses the upload ("The link
  you followed has expired"), raise `upload_max_filesize` and `post_max_size` to 64M in cPanel >
  MultiPHP INI Editor, or unpack the zip into `wp-content/themes/` with cPanel's File Manager.
- **Slim** (`node build-wp-theme.js --slim`, about 0.5 MB): the images stay out of the theme and the
  pages read them from `wp-content/uploads/teg-media/assets/`. That folder must already hold
  every image: the 1.3.0 theme's Copy images button filled it, and the slim build also writes
  `teg-media-images.zip`, which fills it when unpacked into `wp-content/uploads/` (cPanel File
  Manager). Unpack the new images pack whenever the images change. If any image is missing,
  administrators see a notice naming it.

`inc/assets.php` (generated) records which build is installed and lists every image with its size.

## Google Analytics

The theme adds the site's Google Analytics tag (G-LKT9ZPPN4P, the property the old site reports
to) on every page, but only on the public site: while Settings > Reading > "Discourage search
engines" is ticked (staging), nothing is sent. If a plugin or snippet already adds Google's tag or
Tag Manager, the theme's stands down so no visit is counted twice. Change or switch it off in
`wp-config.php`:

    define( 'TEG_GA_ID', 'G-XXXXXXXXXX' );   // a different property
    define( 'TEG_GA_ID', '' );               // off

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
  plugins built for the old Elementor pages cannot restyle them. Kept as well: analytics and tag
  managers, cookie and consent tools, and WooCommerce's order attribution (matched by handle; see
  `teg_keep_asset()` and the `teg_keep_assets` filter). Pop-ups that plugins print after the footer
  are hidden on those pages.

Tested 7 October 2026 on WordPress 7.1.3, PHP 8.4, WooCommerce 11.1.2, Elementor 4.3.4, UAE 2.9.5,
ShopLentor 3.5.4, Advanced Product Fields 1.7.1, LiteSpeed Cache 7.9.1: no errors or warnings from
the theme. Version 1.3.2 re-checked 13 October 2026 on WordPress 7.1.3 and PHP 8.4, standard and slim builds:
every designed page identical to the design at desktop and phone widths, no errors or warnings.

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
(`teg_cal_*`), the Enquiries post type, and any pages it was asked to create (see below).

## Redirects

Old page addresses forward to the new pages: permanent (301) on the public site, temporary (302)
while "Discourage search engines" is ticked on staging. Override with the `teg_redirect_status`
filter.

## The pages WordPress needs

Each designed page is shown through `page-<slug>.php`, so WordPress needs a published, empty page
at each address: /about/, /portfolio/, /membership/, /advertise/, /contact/, /events/,
/clients/, /read/ and /the-expat/. The homepage, /privacy/ and the 404 page need none. If any is
missing, administrators see a notice with a "Create the missing pages" button.

## Before go-live

1. Make a fresh staging copy of the live site (cPanel > WP Toolkit or Softaculous), with
   "Discourage search engines" ticked and customer emails and payments in test mode.
2. Install this theme on staging and activate it; create any missing pages from the notice.
3. Run the test list: every page on desktop and phone, the contact form (check Enquiries and the
   inbox), a TEG Card and a subscription purchase through iPay88 in test mode, cart, checkout and
   account, JavaScript console and the PHP error log, page speed, redirects, 404, sitemap
   (/wp-sitemap.xml) and robots.txt.
4. Back up the live site (files and database) and confirm a restore works.
5. On live: upload and activate the theme, create any missing pages, purge LiteSpeed Cache, and
   make one small real purchase, refunded afterwards.
6. Check Google Analytics Realtime shows visits, and that old addresses answer with 301.
7. Rolling back: activate Metrobank Child (see above).
