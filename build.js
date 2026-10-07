/* Build the homepage concepts:  node build.js
   Injects the TEG wordmark (inline, so it takes its colour from CSS) and the
   frond pattern into src/*.html and writes the result to the folder root. */
const fs = require('fs');
const path = require('path');
const dir = __dirname;
const TP = path.join(dir, '..', 'teg-proposal');

const inner = f => fs.readFileSync(f, 'utf8').replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '');
const logo = '<svg viewBox="0 0 269.63 54.05" role="img" aria-label="TEG Media">'
  + inner(path.join(dir, 'assets', 'teg-logo.svg')) + '</svg>';
const pattern = fs.readFileSync(path.join(TP, 'teg-pattern.svg'), 'utf8')
  .replace(/^[\s\S]*?<g /, '<g ').replace(/<\/svg>\s*$/, '');

for (const f of fs.readdirSync(path.join(dir, 'src')).filter(f => f.endsWith('.html'))) {
  const html = fs.readFileSync(path.join(dir, 'src', f), 'utf8')
    .split('<!--LOGO-->').join(logo)
    .replace('<!--PATTERN-->', pattern);
  fs.writeFileSync(path.join(dir, f), html);
  console.log('built ' + f);
}
