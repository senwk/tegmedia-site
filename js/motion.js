/* Heritage motion: the header that follows you, sections that rise into place,
   figures that count up, and a hero that eases away as you scroll.
   Does nothing for anyone who has asked for reduced motion. */
(function () {
  var still = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var top = document.querySelector('.top');
  var hero = document.querySelector('.hero .in');

  /* the header: fixed and dark once the hero has scrolled away */
  var chaps = [].slice.call(document.querySelectorAll('.chap[id], .opener[id]'));
  var tabs = [].slice.call(document.querySelectorAll('.chapnav a'));
  var stuck = false, ticking = false;
  function onScroll() {
    var y = window.scrollY || window.pageYOffset;
    var s = y > 160;
    if (top && s !== stuck) { stuck = s; top.classList.toggle('stuck', s); }
    if (hero && !still && y < 900) {
      hero.style.transform = 'translateY(' + (y * 0.28) + 'px)';
      hero.style.opacity = Math.max(0, 1 - y / 620);
    }
    /* Our work page: the chapter bar marks the chapter you are reading */
    if (chaps.length) {
      var cur = null;
      chaps.forEach(function (c) { if (c.getBoundingClientRect().top < innerHeight * 0.45) cur = c.id; });
      tabs.forEach(function (a) { a.classList.toggle('on', a.getAttribute('href') === '#' + cur); });
    }
    ticking = false;
  }
  addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();

  if (still) {
    var v = document.querySelector('.hero video');
    if (v) v.pause();
    return;
  }
  if (!('IntersectionObserver' in window)) return;

  /* sections rise into place; siblings follow one another */
  var SEL = [
    'main .h-sec', 'main .lede', 'main .eyebrow', '.card', '.circ', '.iss', '.wall-logos > div',
    '.figs .row > div', '.brow', '.cos > div', '.tl li', '.people > div', '.ladder li',
    '.views li', '.offer li', '.rgrid > div', '.sectors > div', '.cgrid .sec', '.fam .fc',
    '.founder', '.thirty', '.mag .now', '.mag .big', '.tiers > *', '.evrows > *', '.shop .p',
    '.pkcta', '.allc', '.cols4 > .col', '.chap',
    /* Our work and Events, third pass */
    '.opener .n', '.opener h2', '.opener p', '.opener .stats li', '.opener .marks',
    '.xfeat', '.tcard', '.dfeat', '.dcard', '.ecard', '.mini', '.mfeat .txt', '.schemes li',
    '.flag', '.mcard', '.psteps li'
  ].join(',');
  var els = [].filter.call(document.querySelectorAll(SEL), function (e) {
    return !e.closest('.hero, .phero, .menu, .top') && !e.parentNode.closest('.rv');
  });
  els.forEach(function (e) {
    var sibs = [].filter.call(e.parentNode.children, function (s) { return els.indexOf(s) > -1; });
    var i = sibs.indexOf(e);
    e.style.setProperty('--d', Math.min(i, 8) * 0.07 + 's');
    e.classList.add('rv');
  });
  var io = new IntersectionObserver(function (list) {
    list.forEach(function (en) {
      if (!en.isIntersecting) return;
      var e = en.target;
      io.unobserve(e);
      e.classList.add('in');
      /* once it has arrived, hand the element back to its own hover styles */
      var d = parseFloat(getComputedStyle(e).getPropertyValue('--d')) || 0;
      setTimeout(function () { e.classList.remove('rv', 'in'); e.style.removeProperty('--d'); }, 1100 + d * 1000);
    });
  }, { rootMargin: '0px 0px -60px 0px', threshold: 0 });
  els.forEach(function (e) { io.observe(e); });

  /* figures count up the first time they are seen; years stay as they are */
  var nums = document.querySelectorAll('.figs b, .rgrid b, .views b, .opener .stats b');
  var co = new IntersectionObserver(function (list) {
    list.forEach(function (en) {
      if (!en.isIntersecting) return;
      co.unobserve(en.target);
      var b = en.target, t = b.textContent.trim(), m = t.match(/^([\d,]+)(.*)$/);
      if (!m) return;
      var n = parseInt(m[1].replace(/,/g, ''), 10), comma = m[1].indexOf(',') > -1 || n >= 10000;
      if (!comma && n >= 1900 && n <= 2100) return;
      var t0 = null, dur = 1800;
      var fmt = function (v) { return (comma ? v.toLocaleString('en-US') : String(v)) + m[2]; };
      (function step(ts) {
        if (!t0) t0 = ts;
        var p = Math.min(1, (ts - t0) / dur), e = 1 - Math.pow(1 - p, 3);
        b.textContent = fmt(Math.round(n * e));
        if (p < 1) requestAnimationFrame(step); else b.textContent = t;
      })(performance.now());
    });
  }, { threshold: 0.6 });
  [].forEach.call(nums, function (b) { co.observe(b); });
})();
