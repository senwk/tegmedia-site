/* Build the WordPress theme from the Heritage mockup:  node build-wp-theme.js
   Runs build-site.js first, then turns each built page in dist/ into a page
   template of the "TEG Media" theme, so WordPress serves the same markup, CSS,
   scripts and images as the mockup. Nothing is hand-copied: change the mockup,
   run this again, upload the new zip.

   Output: ../tegmedia-wp/theme/teg-media/  and  ../tegmedia-wp/teg-media-theme.zip

   How a page is assembled on WordPress:
     header.php   the <head> (via wp_head) and the site header, current nav item marked
     parts/<page>.html   the page's <main>, exactly as built for the mockup
     footer.php   the red "Let's talk" band (unless the page leaves it out) and the footer
   Placeholders in the parts are filled per request by teg_fill() in functions.php:
     {{assets}} where the images live (see below)   {{url:slug}}  a page on this site
     {{shop}}   /product/ on this site, for the WooCommerce buy buttons

   The images and video (18 MB) are not in the theme zip: TEG's server refuses uploads that
   big. inc/assets.php lists them; until they are copied into wp-content/uploads/teg-media/
   (Appearance > TEG Media images, one click), the pages load them from the published mockup.
*/
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const dir = __dirname;
const DIST = path.join(dir, 'dist');
const OUT = path.join(dir, '..', 'tegmedia-wp', 'theme', 'teg-media');
const VERSION = '1.3.0';

execFileSync(process.execPath, [path.join(dir, 'build-site.js')], { stdio: 'ignore' });
/* the pages again, with every cover, issue name and issue link left as a {{cal:...}} token: WordPress
   fills them from its list of issues (inc/calameo.php plus whatever it has since found on Calameo) */
const TOK = path.join(dir, '..', 'tegmedia-wp', '_dist-tokens');
execFileSync(process.execPath, [path.join(dir, 'build-site.js')], { stdio: 'ignore', env: { ...process.env, TEG_TOKENS: '1', TEG_DIST: TOK } });

/* mockup file -> WordPress page slug ('' is the front page) */
const SLUG = {
  'index.html': '', 'about.html': 'about', 'brands.html': 'portfolio', 'membership.html': 'membership',
  'advertise.html': 'advertise', 'contact.html': 'contact', 'events.html': 'events', 'clients.html': 'clients',
  'read.html': 'read', 'the-expat.html': 'the-expat', '404.html': '404', 'privacy.html': 'privacy',
};
/* the mockup's navigation keys -> slug of the page each marks */
const NAV = { about: 'about', brands: 'portfolio', membership: 'membership', advertise: 'advertise', contact: 'contact' };

const missing = new Set();
const rw = html => html
  .replace(/(href|action)="([a-z0-9-]+)\.html([?#][^"]*)?"/g, (m, attr, name, rest) => {
    const f = name + '.html';
    if (!(f in SLUG)) { missing.add(f); return m; }
    return `${attr}="{{url:${SLUG[f] === '404' ? '' : SLUG[f]}}}${rest || ''}"`;
  })
  .replace(/(["'(]|,\s*)assets\//g, '$1{{assets}}/')
  .split('https://www.tegmedia.my/product/').join('{{shop}}');

const read = f => fs.readFileSync(path.join(TOK, f), 'utf8');
const between = (s, a, b) => { const i = s.indexOf(a), j = s.indexOf(b, i + a.length); return i < 0 || j < 0 ? null : s.slice(i + a.length, j); };
const decode = s => s.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, "'");

fs.rmSync(OUT, { recursive: true, force: true });
fs.mkdirSync(path.join(OUT, 'parts'), { recursive: true });
fs.mkdirSync(path.join(OUT, 'inc'), { recursive: true });

/* the shared header and footer, taken from the built homepage */
const home = read('index.html');
const body = home.slice(home.indexOf('>', home.indexOf('<body')) + 1, home.lastIndexOf('</body>'));
let headerHtml = body.slice(0, body.indexOf('<main>'));
const footerHtml = body.slice(body.indexOf('</main>') + '</main>'.length);
/* mark where the current page's class="on" goes: the first link to each page is the desktop nav */
for (const [key, slug] of Object.entries(NAV)) {
  const a = `<a href="${key}.html">`;
  if (!headerHtml.includes(a)) throw new Error('nav link not found: ' + a);
  headerHtml = headerHtml.replace(a, `<a href="${key}.html"{{on:${slug}}}>`);
}
fs.writeFileSync(path.join(OUT, 'parts', 'header.html'), rw(headerHtml).trim() + '\n');
fs.writeFileSync(path.join(OUT, 'parts', 'footer.html'), rw(footerHtml).trim() + '\n');
fs.writeFileSync(path.join(OUT, 'parts', 'footer-nocontact.html'),
  rw(footerHtml.replace(/<section class="contact">[\s\S]*?<\/section>\s*/, '')).trim() + '\n');

/* each page's <main>, title, description, body class, marked nav item and contact band */
const pages = {};
for (const f of Object.keys(SLUG)) {
  const html = read(f);
  const slug = SLUG[f];
  const key = slug === '' ? 'front' : slug;
  let main = between(html, '<main>', '</main>');
  if (main === null) throw new Error('no <main> in ' + f);
  if (f === 'contact.html') main = contactForm(main);
  fs.writeFileSync(path.join(OUT, 'parts', key + '.html'), rw(main).trim() + '\n');
  const bodyClass = (html.match(/<body class="([^"]*)"/) || [, 'a'])[1];
  const pg = (bodyClass.match(/pg-([a-z0-9-]+)/) || [, 'home'])[1];
  pages[key] = {
    title: decode((html.match(/<title>([^<]*)<\/title>/) || [, 'TEG Media'])[1]),
    description: decode((html.match(/<meta name="description" content="([^"]*)"/) || [, ''])[1]),
    body: 'pg-' + pg,
    on: NAV[pg] || '',
    contact: html.includes('<section class="contact">'),
  };
}
if (missing.size) throw new Error('links to pages the theme does not know: ' + [...missing].join(', '));

const php = v => typeof v === 'boolean' ? (v ? 'true' : 'false') : `'${String(v).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
fs.writeFileSync(path.join(OUT, 'inc', 'pages.php'), `<?php
/* Generated by build-wp-theme.js from the mockup. Do not edit: change the mockup and rebuild. */
return array(
${Object.entries(pages).map(([k, p]) => `\t${php(k)} => array( ${Object.entries(p).map(([a, b]) => `${php(a)} => ${php(b)}`).join(', ')} ),`).join('\n')}
);
`);

/* one small template per designed page; WordPress picks page-<slug>.php by the page's slug */
for (const key of Object.keys(pages)) {
  if (key === 'front' || key === '404') continue;
  fs.writeFileSync(path.join(OUT, `page-${key}.php`), `<?php\n/* ${pages[key].title} (built from the mockup) */\nteg_designed_page( '${key}' );\n`);
}
fs.writeFileSync(path.join(OUT, 'front-page.php'), `<?php\n/* The homepage (built from the mockup) */\nteg_designed_page( 'front' );\n`);
fs.writeFileSync(path.join(OUT, '404.php'), `<?php\n/* Page not found (built from the mockup) */\nteg_designed_page( '404' );\n`);

/* the hand-written theme files, CSS, scripts and every asset the pages use */
const copy = (a, b) => {
  fs.mkdirSync(b, { recursive: true });
  for (const e of fs.readdirSync(a, { withFileTypes: true })) {
    const s = path.join(a, e.name), d = path.join(b, e.name);
    e.isDirectory() ? copy(s, d) : fs.copyFileSync(s, d);
  }
};
copy(path.join(dir, 'wp-theme'), OUT);
copy(path.join(DIST, 'css'), path.join(OUT, 'css'));
copy(path.join(DIST, 'js'), path.join(OUT, 'js'));
/* only the images the stylesheets point at travel inside the theme; the rest are listed for copying */
const cssAssets = new Set();
for (const c of fs.readdirSync(path.join(DIST, 'css'))) {
  (fs.readFileSync(path.join(DIST, 'css', c), 'utf8').match(/\.\.\/assets\/[^"')\s]+/g) || []).forEach(r => cssAssets.add(r.slice(3)));
}
for (const r of cssAssets) {
  fs.mkdirSync(path.dirname(path.join(OUT, r)), { recursive: true });
  fs.copyFileSync(path.join(DIST, r), path.join(OUT, r));
}
const list = [];
const scan = d => {
  for (const e of fs.readdirSync(path.join(DIST, d), { withFileTypes: true })) {
    const r = d + '/' + e.name;
    e.isDirectory() ? scan(r) : list.push([r.slice('assets/'.length), fs.statSync(path.join(DIST, r)).size]);
  }
};
scan('assets');
list.sort((a, b) => a[0].localeCompare(b[0]));
const hash = require('crypto').createHash('md5').update(list.map(([p, s]) => p + ':' + s).join('\n')).digest('hex').slice(0, 12);
fs.writeFileSync(path.join(OUT, 'inc', 'assets.php'), `<?php
/* Generated by build-wp-theme.js: every image the pages use, with its size in bytes. */
return array(
\t'hash'  => '${hash}',
\t'files' => array(
${list.map(([p, s]) => `\t\t'${p}' => ${s},`).join('\n')}
\t),
);
`);
console.log(`images listed for copying: ${list.length} (${(list.reduce((a, [, s]) => a + s, 0) / 1048576).toFixed(1)} MB), set ${hash}; inside the theme: ${[...cssAssets].join(', ')}`);
['favicon.svg', 'favicon-32.png', 'apple-touch-icon.png'].forEach(f => fs.copyFileSync(path.join(DIST, f), path.join(OUT, f)));
fs.writeFileSync(path.join(OUT, 'style.css'), fs.readFileSync(path.join(OUT, 'style.css'), 'utf8').replace('{{version}}', VERSION));

/* the issues known at build time, newest first: what the {{cal:...}} tokens show until WordPress finds
   newer ones on Calameo. Their covers are in the copied images (expat/<slug>.jpg, guides/<slug>.jpg). */
const seed = {
  expat: JSON.parse(fs.readFileSync(path.join(dir, 'assets', 'expat', 'covers.json'), 'utf8')),
  ...JSON.parse(fs.readFileSync(path.join(dir, 'assets', 'guides', 'editions.json'), 'utf8')),
};
const phpList = l => l.slice().sort((a, b) => b.slug.localeCompare(a.slug))
  .map(c => `\t\tarray( 'slug' => ${php(c.slug)}, 'label' => ${php(c.label)}, 'id' => ${php(c.id)}, 'url' => ${php(c.url)}, 'w' => ${+c.w}, 'h' => ${+c.h} ),`).join('\n');
fs.writeFileSync(path.join(OUT, 'inc', 'calameo.php'), `<?php
/* Generated by build-wp-theme.js: the issues known when the theme was built, newest first. */
return array(
${['expat', 'welcome', 'education'].map(k => `\t${php(k)} => array(\n${phpList(seed[k])}\n\t),`).join('\n')}
);
`);
const left = fs.readdirSync(path.join(OUT, 'parts')).flatMap(p => (fs.readFileSync(path.join(OUT, 'parts', p), 'utf8').match(/\{\{cal:[^}]*\}\}/g) || []));
console.log('calameo: seed ' + seed.expat.length + ' Expat issues, ' + seed.welcome.length + ' Welcome, ' + seed.education.length + ' Education; tokens in parts: ' + [...new Set(left)].join(' '));

/* the contact form posts to WordPress, which emails it (see teg_contact_send in functions.php) */
function contactForm(main) {
  const before = main;
  main = main
    .replace('<form class="enq" id="message">', '<form class="enq" id="message" method="post" action="{{admin-post}}">\n      <input type="hidden" name="action" value="teg_contact">\n      <label class="hp" aria-hidden="true">Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label>\n      <input type="hidden" name="t" value="">')
    .replace('<label>Name<input type="text">', '<label>Name<input type="text" name="name" required autocomplete="name">')
    .replace('<label>Company<input type="text">', '<label>Company<input type="text" name="company" autocomplete="organization">')
    .replace('<label>Email<input type="email">', '<label>Email<input type="email" name="email" required autocomplete="email">')
    .replace('<label>Phone<input type="tel">', '<label>Phone<input type="tel" name="phone" autocomplete="tel">')
    .replace('<label>Message<textarea rows="5">', '<label>Message<textarea name="message" rows="5" required>')
    .replace(/<p class="sent" role="status" hidden>[\s\S]*?<\/p>/,
      '<p class="sent" role="status" hidden>Thank you. Your message has been sent, and we will be in touch soon.</p>\n      <p class="sent err" role="alert" hidden>Sorry, your message could not be sent. Please email info@tegmedia.my or call +603 2094 9664.</p>')
    .replace("f.addEventListener('submit',function(e){e.preventDefault();f.querySelector('.sent').hidden=false;});",
      "var r=new URLSearchParams(location.search).get('sent');if(r!==null){f.querySelector(r==='1'?'.sent:not(.err)':'.sent.err').hidden=false;}var t=f.querySelector('input[name=t]');if(t)t.value=Date.now();");
  main = main.replace('<!--CAPTCHA-->', '{{captcha}}');
  const checks = ['{{captcha}}', 'name="t"', 'name="action"', 'name="name"', 'name="company"', 'name="email"', 'name="phone"', 'name="message"', '.sent.err', "get('sent')"];
  for (const c of checks) if (!main.includes(c)) throw new Error('contact form: could not wire ' + c + (main === before ? ' (nothing matched)' : ''));
  return main;
}

/* what was made */
let files = 0, bytes = 0;
const walk = d => { for (const e of fs.readdirSync(d, { withFileTypes: true })) { const p = path.join(d, e.name); if (e.isDirectory()) walk(p); else { files++; bytes += fs.statSync(p).size; } } };
walk(OUT);
console.log(`theme built: ${Object.keys(pages).length} designed pages, ${files} files, ${(bytes / 1048576).toFixed(1)} MB -> ${OUT}`);
