/* TEG Card partner offers, live from the card's public feed (see cardHOOKREADME.md).
   Fills any <div class="pof-app" data-feed="..."> with a small wall of partner logos, each on the
   colour it is designed to sit on, in the feed's own order. Hovering or tapping a logo shows the
   partner and its deal. The new card programme has not launched yet (Oct 2026), so the page
   presents these as the partners signed up so far, and fills in their number.
   The feed is cached for five minutes at its end, so fetching on every page load is fine. If it
   fails, the section keeps its plain note that the offers arrive with the new card. */
(function () {
  var app = document.querySelector('.pof-app[data-feed]');
  if (!app || !window.fetch) return;

  var https = function (u) { return typeof u === 'string' && /^https:\/\//.test(u) ? u : null; };
  var el = function (tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  };

  function tile(o) {
    var t = el('div', 'pol');
    t.tabIndex = 0;
    t.setAttribute('role', 'listitem');
    t.setAttribute('aria-label', o.partner + ': ' + o.headline);
    t.style.background = /^#[0-9a-f]{3,8}$/i.test(o.brand_color || '') ? o.brand_color : '#1d1d20';
    var logo = https(o.logo_url);
    if (logo) {
      var img = el('img'); img.src = logo; img.alt = ''; img.loading = 'lazy'; img.decoding = 'async';
      t.appendChild(img);
    } else {
      t.appendChild(el('span', 'pol-name', o.partner));
    }
    var tip = el('span', 'pol-tip');
    tip.setAttribute('aria-hidden', 'true');
    tip.appendChild(el('b', null, o.partner));
    tip.appendChild(document.createTextNode(o.headline));
    t.appendChild(tip);
    return t;
  }

  fetch(app.dataset.feed).then(function (r) {
    if (!r.ok) throw new Error('feed ' + r.status);
    return r.json();
  }).then(function (data) {
    var offers = (data && data.offers) || [];
    if (!offers.length) return;
    var wall = el('div', 'pof-logos');
    wall.setAttribute('role', 'list');
    offers.forEach(function (o) { wall.appendChild(tile(o)); });

    app.textContent = '';
    app.appendChild(wall);
    app.appendChild(el('p', 'pof-hint', 'Hover over or tap a logo to see the offer.'));
    app.classList.add('is-live');
    var n = document.querySelector('[data-offer-count]');
    if (n) n.textContent = offers.length + ' partners signed up so far. ';
  }).catch(function () { /* keep the plain note already in the section */ });
})();
