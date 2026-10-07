/* Portfolio's four categories, read like chapters (Mike, 1 Oct 2026: the tab bar went).
   - Each category's header pins at the top while its content slides up over it; the header
     darkens and recedes as it is covered (the CSS reads --cover, 0 to 1).
   - A 3px line under the site header, in four segments in the categories' colours, fills as
     you read through each one.
   - When you stop scrolling near the start of a category, the page eases onto it, slowly. It
     never moves while you are reading the middle of a section, and any wheel, touch, key or
     click stops it at once. Nothing moves for anyone who has asked for reduced motion. */
(function () {
  var cats = [].slice.call(document.querySelectorAll('.catwrap'));
  if (!cats.length) return;
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var header = document.querySelector('.top');
  var rail = document.querySelector('.catrail');
  var fills = rail ? [].slice.call(rail.querySelectorAll('b')) : [];
  var openers = cats.map(function (c) { return c.querySelector('.opener'); });

  /* a header taller than the screen pins by its bottom edge, so all of it is seen first */
  function pinTops() {
    openers.forEach(function (o) { o.style.top = Math.min(0, innerHeight - o.offsetHeight) + 'px'; });
  }

  var queued = false;
  function frame() {
    queued = false;
    var vh = innerHeight, mark = vh * 0.3, inside = false;
    cats.forEach(function (c, i) {
      var r = c.getBoundingClientRect(), o = openers[i];
      if (!reduce) o.style.setProperty('--cover', Math.max(0, Math.min(1, -r.top / o.offsetHeight)).toFixed(3));
      var p = Math.max(0, Math.min(1, (mark - r.top) / r.height));
      if (fills[i]) fills[i].style.transform = 'scaleX(' + p.toFixed(4) + ')';
      if (r.top <= mark && r.bottom > mark) inside = true;
    });
    if (rail) {
      rail.style.top = (header ? Math.max(0, header.getBoundingClientRect().bottom) : 0) + 'px';
      rail.classList.toggle('on', inside);
    }
  }
  function queue() { if (!queued) { queued = true; requestAnimationFrame(frame); } }

  /* the soft snap */
  var timer = null, anim = null;
  function stop() { if (anim) { cancelAnimationFrame(anim); anim = null; } }
  ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach(function (e) { addEventListener(e, stop, { passive: true }); });

  function settle() {
    if (anim || reduce) return;
    var vh = innerHeight, gap = null;
    cats.forEach(function (c) {
      var t = c.getBoundingClientRect().top;
      /* only near a category's start: up to 38% of the screen below the top, or 18% past it */
      if (t > -vh * 0.18 && t < vh * 0.38 && Math.abs(t) > 2 && (gap === null || Math.abs(t) < Math.abs(gap))) gap = t;
    });
    if (gap === null) return;
    var from = scrollY, to = scrollY + gap, start = null, dur = Math.min(1150, 600 + Math.abs(gap) * 0.9);
    function step(ts) {
      if (start === null) start = ts;
      var k = Math.min(1, (ts - start) / dur);
      var e = k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2;   /* ease in and out */
      scrollTo({ top: from + (to - from) * e, behavior: 'instant' });
      anim = k < 1 ? requestAnimationFrame(step) : null;
    }
    anim = requestAnimationFrame(step);
  }

  addEventListener('scroll', function () {
    queue();
    if (!anim) { clearTimeout(timer); timer = setTimeout(settle, 200); }
  }, { passive: true });
  addEventListener('resize', function () { pinTops(); queue(); });
  addEventListener('load', function () { pinTops(); queue(); });
  pinTops();
  queue();
})();

/* Print's header: the Cover Flow turns one place every few seconds, only while it is on screen.
   Each cover's data-pos is its place (0 in front); the CSS moves it there. The cover leaving at the
   far left comes back, hidden, at the far right, without travelling across. */
(function () {
  var flow = document.querySelector('.obj.flow');
  if (!flow || (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
  var covers = [].slice.call(flow.querySelectorAll('img[data-pos]'));
  var lo = -Math.floor(covers.length / 2), hi = lo + covers.length - 1, timer = null, seen = false;
  function turn() {
    covers.forEach(function (c) {
      var p = +c.dataset.pos - 1;
      c.style.transition = p < lo ? 'none' : '';
      c.dataset.pos = p < lo ? hi : p;
    });
  }
  function run() {
    var go = seen && !document.hidden;
    if (go && !timer) timer = setInterval(turn, 3800);
    if (!go && timer) { clearInterval(timer); timer = null; }
  }
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (e) { seen = e[0].isIntersecting; run(); }).observe(flow.closest('.opener') || flow);
  } else { seen = true; }
  document.addEventListener('visibilitychange', run);
  run();
})();
