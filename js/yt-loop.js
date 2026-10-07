/* Background YouTube video: muted, no controls, starts at data-start and loops
   back there before the end.
     <span class="yt" data-yt="VIDEO_ID" data-start="10"></span>
     <span class="yt" data-yt="VIDEO_ID" data-start="75" data-end="102"></span>  (a segment)
   Put it after the still photograph it covers. YouTube draws its own play and
   pause icon inside the player whenever playback starts or jumps, and the page
   cannot style inside the player, so the video is only shown once it has been
   playing smoothly for a moment, and at each loop it fades back to the
   photograph, jumps, and fades in again. Loads YouTube only when the element
   is near the screen, and not at all for anyone who has asked for reduced
   motion. (Once the original file is in assets/whiskyplus.mp4, the build uses
   a plain <video> instead and none of this runs.) */
(function () {
  var els = [].slice.call(document.querySelectorAll('[data-yt]'));
  if (!els.length || !('IntersectionObserver' in window)) return;
  if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  /* ms of playback before the video is shown: YouTube draws its title, logo and a
     pause button for about 6 seconds after every start and every seek (measured in
     Chrome, 30 Sep 2026), so the still stays in front until they have gone */
  var SETTLE = 6500;
  var ready = false, loading = false, queue = [];
  function load() {
    if (ready || loading) return;
    loading = true;
    window.onYouTubeIframeAPIReady = function () { ready = true; flush(); };
    var s = document.createElement('script');
    s.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(s);
  }
  function flush() { queue.splice(0).forEach(make); }
  function make(el) {
    var start = Number(el.getAttribute('data-start')) || 0;
    var end = Number(el.getAttribute('data-end')) || 0;   /* optional: loop a segment, start to end */
    var holder = document.createElement('div');
    el.appendChild(holder);
    var shown = null, jumping = false;
    function hide() { clearTimeout(shown); el.classList.remove('on'); }
    /* YouTube turns on auto-captions for some viewers (their own account setting):
       unload the captions module, every time playback starts */
    function noCaptions() {
      try { p.unloadModule('captions'); p.unloadModule('cc'); p.setOption('captions', 'track', {}); } catch (_) {}
    }
    var p = new YT.Player(holder, {
      videoId: el.getAttribute('data-yt'),
      host: 'https://www.youtube-nocookie.com',
      playerVars: { autoplay: 1, mute: 1, controls: 0, disablekb: 1, fs: 0, iv_load_policy: 3,
                    modestbranding: 1, playsinline: 1, rel: 0, start: start,
                    cc_load_policy: 0, cc_lang_pref: 'none' },
      events: {
        onReady: function (e) {
          e.target.mute();
          noCaptions();
          /* nudge only if autoplay did not start by itself */
          setTimeout(function () { if (p.getPlayerState() !== YT.PlayerState.PLAYING) p.playVideo(); }, 1500);
        },
        onStateChange: function (e) {
          if (e.data === YT.PlayerState.PLAYING) {
            noCaptions(); setTimeout(noCaptions, 1200); setTimeout(noCaptions, 4000);
            jumping = false;
            clearTimeout(shown);
            shown = setTimeout(function () { el.classList.add('on'); }, SETTLE);
          } else {
            hide();
            if (e.data === YT.PlayerState.ENDED) { p.seekTo(start, true); p.playVideo(); }
          }
        }
      }
    });
    /* near the end (of the segment, or of the film): fade to the photograph,
       jump back, fade in when playing again */
    setInterval(function () {
      try {
        var d = p.getDuration(), t = p.getCurrentTime();
        var stop = end && end < d ? end - 0.7 : d - 2;
        if (!jumping && d && t > stop) {
          jumping = true;
          hide();
          setTimeout(function () {
            p.seekTo(start, true);
            /* a jump inside the buffered part may not report a new state: show it again anyway */
            setTimeout(function () {
              if (p.getPlayerState() === YT.PlayerState.PLAYING && !el.classList.contains('on')) { jumping = false; el.classList.add('on'); }
            }, 700 + SETTLE);
          }, 700);
        }
      } catch (_) {}
    }, 300);
  }
  var io = new IntersectionObserver(function (list) {
    list.forEach(function (en) {
      if (!en.isIntersecting) return;
      io.unobserve(en.target);
      queue.push(en.target);
      if (ready) flush(); else load();
    });
  }, { rootMargin: '300px' });
  els.forEach(function (e) { io.observe(e); });
})();
