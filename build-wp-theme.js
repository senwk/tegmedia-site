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

   The images and the homepage film travel inside the theme (assets/), so the site loads them only
   from itself. That makes the zip about 20 MB: if WordPress refuses the upload ("The link you
   followed has expired"), raise upload_max_filesize and post_max_size to 64M in cPanel's MultiPHP
   INI Editor, or unpack the zip into wp-content/themes/ with cPanel's File Manager.

   node build-wp-theme.js --slim  leaves the images out (a zip of about 1 MB) for a server that
   cannot take the full one. The pages then use the copy in wp-content/uploads/teg-media/assets/,
   which must already hold every image: it also writes teg-media-images.zip, the images pack to
   unpack into wp-content/uploads/ with cPanel's File Manager whenever the images change.
*/
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const dir = __dirname;
const DIST = path.join(dir, 'dist');
const OUT = path.join(dir, '..', 'tegmedia-wp', 'theme', 'teg-media');
const VERSION = '1.3.4';
const SLIM = process.argv.includes('--slim');
const ZIP = path.join(dir, '..', 'tegmedia-wp', SLIM ? 'teg-media-theme-slim.zip' : 'teg-media-theme.zip');
const IMAGES_ZIP = path.join(dir, '..', 'tegmedia-wp', 'teg-media-images.zip');

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
/* every image the pages use (build-site.js ships only those into dist/assets): inside the theme,
   or with --slim only the few the stylesheets point at, the rest read from the uploads folder */
const images = [];
(function list(d, rel) {
  for (const e of fs.readdirSync(d, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
    const p = path.join(d, e.name), r = rel ? rel + '/' + e.name : e.name;
    e.isDirectory() ? list(p, r) : images.push([r, fs.statSync(p).size]);
  }
})(path.join(DIST, 'assets'), '');
if (!SLIM) {
  copy(path.join(DIST, 'assets'), path.join(OUT, 'assets'));
} else {
  for (const c of fs.readdirSync(path.join(DIST, 'css'))) {
    for (const r of fs.readFileSync(path.join(DIST, 'css', c), 'utf8').match(/\.\.\/assets\/[^"')\s]+/g) || []) {
      fs.mkdirSync(path.dirname(path.join(OUT, r.slice(3))), { recursive: true });
      fs.copyFileSync(path.join(DIST, r.slice(3)), path.join(OUT, r.slice(3)));
    }
  }
}
fs.writeFileSync(path.join(OUT, 'inc', 'assets.php'), `<?php
/* Generated by build-wp-theme.js. 'inside': the images travel in this theme's assets/ folder; if
   false (a --slim build) the pages read them from wp-content/uploads/teg-media/assets/.
   'files': every image the pages use, with its size in bytes. */
return array(
\t'inside' => ${SLIM ? 'false' : 'true'},
\t'files'  => array(
${images.map(([p, s]) => `\t\t'${p}' => ${s},`).join('\n')}
\t),
);
`);
['favicon.svg', 'favicon-32.png', 'apple-touch-icon.png'].forEach(f => fs.copyFileSync(path.join(DIST, f), path.join(OUT, f)));
fs.writeFileSync(path.join(OUT, 'style.css'), fs.readFileSync(path.join(OUT, 'style.css'), 'utf8').replace('{{version}}', VERSION));

/* the issues known at build time, newest first: what the {{cal:...}} tokens show until WordPress finds
   newer ones on Calameo. Their covers are in the theme's images (expat/<slug>.jpg, guides/<slug>.jpg). */
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

/* the zip WordPress takes under Appearance > Themes > Add New > Upload: everything inside a
   teg-media/ folder. Written here with Node's own zlib, so the build needs nothing installed. */
writeZip(OUT, 'teg-media', ZIP);
console.log(`zip${SLIM ? ' (slim, images not inside)' : ''}: ${(fs.statSync(ZIP).size / 1048576).toFixed(1)} MB -> ${ZIP}`);
if (SLIM) {
  /* the images pack: unpacked into wp-content/uploads/ it fills teg-media/assets/ */
  writeZip(path.join(DIST, 'assets'), 'teg-media/assets', IMAGES_ZIP);
  console.log(`images pack: ${images.length} files, ${(fs.statSync(IMAGES_ZIP).size / 1048576).toFixed(1)} MB -> ${IMAGES_ZIP}`);
}

function writeZip(srcDir, top, file) {
  const zlib = require('zlib');
  const crcTable = Array.from({ length: 256 }, (_, n) => { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; return c >>> 0; });
  const crc32 = buf => { let c = 0xFFFFFFFF; for (const b of buf) c = crcTable[(c ^ b) & 0xFF] ^ (c >>> 8); return (c ^ 0xFFFFFFFF) >>> 0; };
  const entries = [];
  const add = (abs, rel) => {
    for (const e of fs.readdirSync(abs, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
      const p = path.join(abs, e.name), r = rel + '/' + e.name;
      if (e.isDirectory()) add(p, r); else entries.push([r, fs.readFileSync(p)]);
    }
  };
  add(srcDir, top);
  const d = new Date(), dosTime = (d.getHours() << 11) | (d.getMinutes() << 5) | (d.getSeconds() >> 1);
  const dosDate = ((d.getFullYear() - 1980) << 9) | ((d.getMonth() + 1) << 5) | d.getDate();
  const locals = [], centrals = [];
  let offset = 0;
  for (const [name, data] of entries) {
    const packed = zlib.deflateRawSync(data, { level: 9 });
    const stored = packed.length >= data.length;          // photos and the film do not shrink: store them
    const body = stored ? data : packed;
    const nameBuf = Buffer.from(name, 'utf8'), crc = crc32(data);
    const head = Buffer.alloc(30);
    head.writeUInt32LE(0x04034b50, 0); head.writeUInt16LE(20, 4); head.writeUInt16LE(0x0800, 6);
    head.writeUInt16LE(stored ? 0 : 8, 8); head.writeUInt16LE(dosTime, 10); head.writeUInt16LE(dosDate, 12);
    head.writeUInt32LE(crc, 14); head.writeUInt32LE(body.length, 18); head.writeUInt32LE(data.length, 22);
    head.writeUInt16LE(nameBuf.length, 26); head.writeUInt16LE(0, 28);
    const cen = Buffer.alloc(46);
    cen.writeUInt32LE(0x02014b50, 0); cen.writeUInt16LE(20, 4); cen.writeUInt16LE(20, 6); cen.writeUInt16LE(0x0800, 8);
    cen.writeUInt16LE(stored ? 0 : 8, 10); cen.writeUInt16LE(dosTime, 12); cen.writeUInt16LE(dosDate, 14);
    cen.writeUInt32LE(crc, 16); cen.writeUInt32LE(body.length, 20); cen.writeUInt32LE(data.length, 24);
    cen.writeUInt16LE(nameBuf.length, 28); cen.writeUInt32LE(offset, 42);
    locals.push(head, nameBuf, body);
    centrals.push(cen, nameBuf);
    offset += 30 + nameBuf.length + body.length;
  }
  const cenSize = centrals.reduce((a, b) => a + b.length, 0);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0); end.writeUInt16LE(entries.length, 8); end.writeUInt16LE(entries.length, 10);
  end.writeUInt32LE(cenSize, 12); end.writeUInt32LE(offset, 16);
  fs.writeFileSync(file, Buffer.concat([...locals, ...centrals, end]));
}
