/* Build the Heritage site mockup:  node build-site.js
   Every page in src/site/pages is wrapped in the same head, header and footer,
   with the current page marked in the navigation. Writes a self-contained
   dist/ folder (pages, css, assets) that can be opened locally or hosted.

   The first line of each page is a comment carrying its settings:
     <!-- title: Page title | page: about | nocontact -->
   page       which navigation item to mark as current
   nocontact  leave out the red "Let's talk" band (the Contact page has its own)
*/
const fs = require('fs');
const path = require('path');
const dir = __dirname;
const SRC = path.join(dir, 'src', 'site');
const DIST = process.env.TEG_DIST ? path.resolve(process.env.TEG_DIST) : path.join(dir, 'dist');
/* TEG_TOKENS=1 (build-wp-theme.js): every cover, issue name and issue link is left as a {{cal:...}}
   token instead, for WordPress to fill from its own list of issues, which updates from Calameo. */
const TOKENS = process.env.TEG_TOKENS === '1';
const TP = path.join(dir, '..', 'teg-proposal');

const inner = f => fs.readFileSync(f, 'utf8').replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '');
const logo = '<svg viewBox="0 0 269.63 54.05" role="img" aria-label="TEG Media">' + inner(path.join(dir, 'assets', 'teg-logo.svg')) + '</svg>';
/* the house pattern: its shapes' own white fills are dropped so they take the colour of wherever
   the pattern is used (white by default; each Portfolio category uses its own colour) */
const pattern = fs.readFileSync(path.join(TP, 'teg-pattern.svg'), 'utf8').replace(/^[\s\S]*?<g /, '<g ').replace(/<\/svg>\s*$/, '')
  .replace(/\sfill="#fff(fff)?"/gi, '');
const part = n => fs.readFileSync(path.join(SRC, 'partials', n + '.html'), 'utf8');
const header = part('header'), footer = part('footer'), clients = part('clients'), family = part('family');
const NAV = ['about', 'brands', 'membership', 'advertise', 'contact'];   /* MM2H goes out to mm2h.com */

/* The Expat's issues, newest first, as published on Calameo (assets/expat/covers.json).
     <!--EXPAT-STRIP:from:count-->  a row of covers, each linked to its issue
     <!--EXPAT-ARCHIVE-->           every issue, grouped by year
     {{latest-url}} {{latest-label}} the newest issue */
const covers = JSON.parse(fs.readFileSync(path.join(dir, 'assets', 'expat', 'covers.json'), 'utf8'))
  .sort((a, b) => b.slug.localeCompare(a.slug));
const issue = c => `<a class="iss" href="${c.url}" target="_blank" rel="noopener"><img src="assets/expat/${c.slug}.jpg" alt="The Expat, ${c.label}" loading="lazy" width="${c.w}" height="${c.h}"><span>${c.label}</span></a>`;
const strip = (from, n) => covers.slice(from, from + n).map(issue).join('\n      ');
const years = [...new Set(covers.map(c => c.slug.slice(0, 4)))];
const archive = years.map(y => `<div class="yr"><h3 class="serif">${y}</h3><div class="agrid">\n      ${covers.filter(c => c.slug.startsWith(y)).map(issue).join('\n      ')}\n    </div></div>`).join('\n    ');
/* the newest issue's cover: a full-resolution <slug>-hi.jpg if one exists */
const latestImg = 'assets/expat/' + covers[0].slug
  + (fs.existsSync(path.join(dir, 'assets', 'expat', covers[0].slug + '-hi.jpg')) ? '-hi' : '') + '.jpg';
/*   <!--EXPAT-STACK-->  the three newest covers as plain images, for Print's section header */
const stack = () => covers.slice(0, 3).map((c, i) => `<img class="s${i + 1}" src="${i ? 'assets/expat/' + c.slug + '.jpg' : latestImg}" alt="">`).join('');
/*   <!--EXPAT-FAN:n-->  the n newest covers, fanned (the Our work page); the
     newest sits in front, the older ones behind it to either side */
const fan = n => covers.slice(0, n).map((c, i) =>
  `<a class="fc f${i + 1}" href="${c.url}" target="_blank" rel="noopener"><img src="${i ? 'assets/expat/' + c.slug + '.jpg' : latestImg}" alt="The Expat, ${c.label}"${i ? ' loading="lazy"' : ''}></a>`)
  .join('\n        ');

/* the annual guides' editions, newest first (assets/guides/editions.json)
     <!--EWG-EDITIONS--> <!--EDU-EDITIONS-->   six recent editions of each
     {{ewg-latest-url}} {{ewg-latest-label}}  and the same for edu */
const eds = JSON.parse(fs.readFileSync(path.join(dir, 'assets', 'guides', 'editions.json'), 'utf8'));
const edition = (c, name) => `<a class="iss" href="${c.url}" target="_blank" rel="noopener"><img src="assets/guides/${c.slug}.jpg" alt="${name} ${c.label}" loading="lazy" width="${c.w}" height="${c.h}"><span>${c.label}</span></a>`;
const edRow = (list, name) => list.slice(0, 6).map(c => edition(c, name)).join('\n      ');
/*   <!--PRINT-FLOW-->  Print's section header: every title in a Cover Flow, the newest Expat facing
     and the others angled to either side, turning slowly through all ten (js/cats.js). Titles
     alternate so no two of a kind sit together. data-pos is each cover's place, 0 in front;
     the build sets the opening arrangement, so it reads the same with no script. */
const flow = () => {
  const ex = i => 'assets/expat/' + covers[i].slug + '.jpg', g = (k, i) => 'assets/guides/' + eds[k][i].slug + '.jpg';
  const list = [latestImg, g('welcome', 0), g('education', 0), 'assets/circular-cover.jpg', ex(1),
    g('welcome', 1), g('education', 1), ex(2), g('welcome', 2), g('education', 2)];
  return list.map((s, i) => `<img src="${s}" alt="" data-pos="${i <= 4 ? i : i - list.length}">`).join('');
};

/* the homepage hero: a looping video if assets/hero.mp4 exists, the still
   photograph otherwise (the video opens on its own first frame, assets/hero-poster.jpg, while it loads) */
const heroMedia = fs.existsSync(path.join(dir, 'assets', 'hero.mp4'))
  ? '<video class="bg" autoplay muted loop playsinline preload="auto" poster="assets/hero-poster.jpg"><source src="assets/hero.mp4" type="video/mp4"></video>'
  : '<img class="bg" src="assets/kl-clouds.jpg" alt="">';

/* Event films. A page marks where one plays with
     <span class="yt" data-yt="YOUTUBE_ID" data-start="10"></span>
   over a still photograph, and js/yt-loop.js plays it from YouTube. Mike chose
   YouTube over storing the files (30 Sep 2026), accepting that YouTube briefly
   shows its own pause icon at each start and loop. If a file is ever saved
   under assets/ (already cut to the segment), the build puts a plain looping
   <video> there instead, with no YouTube player at all. */
/* The shop. Buy buttons are written {{shop:<product>}}: the TEG Card, The Expat,
   the two guides and the MM2H subscription are on sale today as WooCommerce
   products on the live tegmedia.my (prices checked 30 Sep 2026: RM100, RM150,
   RM20, RM20, RM125). On the WordPress rebuild the same pages live at
   /product/<slug>/, so SHOP becomes '/product/' there. */
const SHOP = process.env.SHOP || 'https://www.tegmedia.my/product/';

const FILMS = {
  Qes1Irj81CA: 'whiskyplus.mp4',    /* WhiskyPLUS 2026, from 0:10 to the end */
  wSeKGDZ1txA: 'expressions.mp4',   /* Expressions 2026, 1:15 to 1:42 */
};
const films = html => html.replace(/\s*<span class="yt" data-yt="([\w-]+)"[^>]*><\/span>/g, (m, id) => {
  const file = FILMS[id];
  return file && fs.existsSync(path.join(dir, 'assets', file))
    ? `<video class="ytv" autoplay muted loop playsinline preload="metadata"><source src="assets/${file}" type="video/mp4"></video>`
    : m;
});

/*   <!--WALL:set:n-->  n small past covers from assets/walls/<set>-NN.jpg, for
     the tilted background walls on the Our work cards. Taken seven at a time
     through the set, so a repeat never sits beside itself. */
const wall = (set, n) => {
  const files = fs.readdirSync(path.join(dir, 'assets', 'walls')).filter(f => new RegExp('^' + set + '-\\d+\\.jpg$').test(f)).sort();
  if (!files.length) return '';
  const step = files.length % 7 ? 7 : 5;
  return Array.from({ length: n }, (_, i) => `<img src="assets/walls/${files[(i * step) % files.length]}" alt="" loading="lazy" decoding="async">`).join('');
};

const tokens = html => html
  .replace(/<!--EXPAT-STRIP:(\d+):(\d+)-->/g, '{{cal:strip:$1:$2}}')
  .replace(/<!--EXPAT-FAN:(\d+)-->/g, '{{cal:fan:$1}}')
  .split('<!--PRINT-FLOW-->').join('{{cal:flow}}')
  .split('<!--EXPAT-ARCHIVE-->').join('{{cal:archive}}')
  .split('<!--EWG-EDITIONS-->').join('{{cal:ewg-editions}}')
  .split('<!--EDU-EDITIONS-->').join('{{cal:edu-editions}}')
  .replace(/\{\{((ewg-|edu-)?latest-(url|label|img|thumb)|issue-count)\}\}/g, '{{cal:$1}}');
const expat = html => films(TOKENS ? tokens(html) : html.split('<!--CAPTCHA-->').join(''))
  .replace(/\{\{shop:([\w-]+)\}\}/g, (_, s) => SHOP + s + '/')
  .replace(/<!--WALL:(\w+):(\d+)-->/g, (_, s, n) => wall(s, Number(n)))
  .split('<!--HERO-MEDIA-->').join(heroMedia)
  .replace(/<!--EXPAT-STRIP:(\d+):(\d+)-->/g, (_, a, b) => strip(Number(a), Number(b)))
  .replace(/<!--EXPAT-FAN:(\d+)-->/g, (_, n) => fan(Number(n)))
  .split('<!--EXPAT-STACK-->').join(stack())
  .split('<!--PRINT-FLOW-->').join(flow())
  .split('<!--EXPAT-ARCHIVE-->').join(archive)
  .split('<!--EWG-EDITIONS-->').join(edRow(eds.welcome, 'The Expat Welcome Guide'))
  .split('<!--EDU-EDITIONS-->').join(edRow(eds.education, 'The Expat Education Guide'))
  .split('{{ewg-latest-img}}').join('assets/guides/ewg.jpg')   /* a sharper copy of the newest edition */
  .split('{{edu-latest-img}}').join('assets/guides/edu.jpg')
  .split('{{ewg-latest-url}}').join(eds.welcome[0].url)
  .split('{{ewg-latest-label}}').join(eds.welcome[0].label)
  .split('{{edu-latest-url}}').join(eds.education[0].url)
  .split('{{edu-latest-label}}').join(eds.education[0].label)
  .split('{{latest-img}}').join(latestImg)
  .split('{{latest-thumb}}').join('assets/expat/' + covers[0].slug + '.jpg')
  .split('{{latest-url}}').join(covers[0].url)
  .split('{{latest-label}}').join(covers[0].label)
  .split('{{issue-count}}').join(String(covers.length));

/* copy a folder, recursively */
const copy = (a, b) => {
  fs.mkdirSync(b, { recursive: true });
  for (const e of fs.readdirSync(a, { withFileTypes: true })) {
    if (e.name.startsWith('_')) continue;
    const s = path.join(a, e.name), d = path.join(b, e.name);
    e.isDirectory() ? copy(s, d) : fs.copyFileSync(s, d);
  }
};

/* where the site lives (the link previews need absolute addresses), and the
   one-line description each page gives search engines and link previews */
const SITE = process.env.SITE || 'https://tegmedia-site.mikedavison111.workers.dev';
const DESC = {
  'index.html': 'Malaysia’s English-language media group for the international community since 1996: The Expat, ExpatGo, our guides, events and MM2H.',
  'about.html': 'Thirty years of publishing for Malaysia’s international community: our story, our four companies and the team behind TEG Media.',
  'brands.html': 'Print, digital, events and MM2H: The Expat, the Welcome and Education Guides, The Circular, ExpatGo, WhiskyPLUS, Expressions and our MM2H agency.',
  'the-expat.html': 'The Expat, Malaysia’s magazine for the international community since 1996, in print and free to read online every month.',
  'events.html': 'WhiskyPLUS, Expressions, The Mingle and our Wine Dinners, and private events for brands that want to meet Malaysia’s international community.',
  'advertise.html': 'Reach the international community in Malaysia with one campaign across print, ExpatGo, newsletters, social and events.',
  'clients.html': 'Hotels, schools, banks, airlines, developers and brands that have worked with TEG Media since 1996, by sector.',
  'read.html': 'Every issue of The Expat, free to read online, with the Welcome and Education Guides. Subscribe to print or join the TEG Card.',
  'membership.html': 'The TEG Card: RM100 a year. Members save at every Mingle and Wine Dinner, with member prices at WhiskyPLUS and offers from our hotel and restaurant partners.',
  'privacy.html': 'How TEG Media collects, uses and protects your personal data, under Malaysia’s Personal Data Protection Act 2010.',
  'contact.html': 'Talk to TEG Media about advertising, events, MM2H or subscriptions. 5th Floor, Syed Kechik Foundation Building, Bangsar, Kuala Lumpur.',
};
const esc = s => s.replace(/&/g, '&amp;').replace(/"/g, '&quot;');

/* start clean, so files that are no longer used do not linger in dist/ */
fs.rmSync(DIST, { recursive: true, force: true });
fs.mkdirSync(DIST, { recursive: true });
for (const f of fs.readdirSync(path.join(SRC, 'pages')).filter(f => f.endsWith('.html'))) {
  let src = expat(fs.readFileSync(path.join(SRC, 'pages', f), 'utf8'));
  const meta = (src.match(/^<!--([\s\S]*?)-->/) || [, ''])[1];
  src = src.replace(/^<!--[\s\S]*?-->\s*/, '');
  const title = (meta.match(/title:\s*([^|]*?)\s*(\||$)/) || [, 'TEG Media'])[1]
    + (/\|\s*[^|:]+\|/.test(meta) ? '' : '');
  const fullTitle = meta.split('|').filter(s => !/^\s*(page:|nocontact)/.test(s)).map(s => s.replace('title:', '').trim()).join(' | ');
  const page = (meta.match(/page:\s*([a-z0-9-]+)/) || [, 'home'])[1];
  const noContact = /nocontact/.test(meta);

  let head = header;
  NAV.forEach(n => { head = head.split('{{' + n + '}}').join(n === page ? ' class="on"' : ''); });

  const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>${fullTitle}</title>
<meta name="description" content="${esc(DESC[f] || DESC['index.html'])}">
<meta name="theme-color" content="#0B0B0D">
<link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="icon" href="favicon-32.png" sizes="32x32" type="image/png"><link rel="apple-touch-icon" href="apple-touch-icon.png">
<meta property="og:type" content="website"><meta property="og:site_name" content="TEG Media">
<meta property="og:title" content="${esc(fullTitle)}"><meta property="og:description" content="${esc(DESC[f] || DESC['index.html'])}">
<meta property="og:image" content="${SITE}/assets/og.jpg"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">
<meta property="og:url" content="${SITE}/${f === 'index.html' ? '' : f}"><meta name="twitter:card" content="summary_large_image">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cabin:wght@400;500;700&family=Libre+Baskerville:ital,wght@0,400;1,400&family=Montserrat:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/a.css">
<link rel="stylesheet" href="css/pages.css">
<link rel="stylesheet" href="css/polish.css">
<link rel="stylesheet" href="css/work.css">
<link rel="stylesheet" href="css/motion.css">
<script>document.documentElement.className+=' js'</script>
<script src="js/motion.js" defer></script>
<script src="js/yt-loop.js" defer></script>
<script src="js/offers.js" defer></script>
<script src="js/cats.js" defer></script>
</head>
<body class="a pg-${page}">
${head}
<main>
${src}
</main>
${noContact ? footer.replace(/<section class="contact">[\s\S]*?<\/section>\s*/, '') : footer}
</body>
</html>
`.split('<!--CLIENTS-->').join(clients)
   .split('<!--EXPAT-FAMILY-->').join(expat(family))
   .split('<!--LOGO-->').join(logo)
   .replace('<!--PATTERN-->', pattern);

  fs.writeFileSync(path.join(DIST, f), html);
  console.log('built ' + f.padEnd(18) + fullTitle);
}
copy(path.join(dir, 'css'), path.join(DIST, 'css'));
copy(path.join(dir, 'js'), path.join(DIST, 'js'));
/* only the assets the pages, styles and scripts actually use: the folder also
   holds earlier concepts' images, which do not need to be published */
const used = new Set();
for (const d of ['', 'css', 'js']) {
  for (const f of fs.readdirSync(path.join(DIST, d)).filter(f => /\.(html|css|js)$/.test(f))) {
    (fs.readFileSync(path.join(DIST, d, f), 'utf8').match(/assets\/[^"')\s>]+/g) || []).forEach(r => used.add(r.replace(/[?#].*$/, '')));
  }
}
let shipped = 0;
for (const r of used) {
  const s = path.join(dir, r);
  if (!fs.existsSync(s) || !fs.statSync(s).isFile()) continue;
  fs.mkdirSync(path.dirname(path.join(DIST, r)), { recursive: true });
  fs.copyFileSync(s, path.join(DIST, r)); shipped++;
}
console.log('assets: ' + shipped + ' files used by the pages');
['favicon.svg', 'favicon-32.png', 'apple-touch-icon.png'].forEach(f => fs.copyFileSync(path.join(dir, f), path.join(DIST, f)));
/* the old MM2H page now forwards to mm2h.com, for anyone holding the link */
fs.writeFileSync(path.join(DIST, 'mm2h.html'), '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0; url=https://mm2h.com"><link rel="canonical" href="https://mm2h.com"><title>mm2h.com</title><a href="https://mm2h.com">mm2h.com</a>');
fs.writeFileSync(path.join(DIST, 'robots.txt'), 'User-agent: *\nDisallow: /\n');
fs.writeFileSync(path.join(DIST, '_headers'), '/*\n  X-Robots-Tag: noindex, nofollow\n');
console.log('dist/ ready');
