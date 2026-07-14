// JetPakistan — scroll reveal, scroll cue, subtle hero parallax
(function () {
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
    });
  }, { threshold: 0.14, rootMargin: '0px 0px -8% 0px' });
  document.querySelectorAll('.reveal, .stagger').forEach(function (el) { io.observe(el); });

  var cue = document.getElementById('scrollCue');
  if (cue) {
    cue.addEventListener('click', function () {
      var b = document.querySelector('.board'); if (b) b.scrollIntoView({ behavior: 'smooth' });
    });
    window.addEventListener('scroll', function () {
      cue.style.opacity = window.scrollY > 120 ? '0' : '1';
      cue.style.pointerEvents = window.scrollY > 120 ? 'none' : 'auto';
    }, { passive: true });
  }

  var arc = document.getElementById('heroArc');
  var rm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (arc && !rm) {
    window.addEventListener('scroll', function () {
      var y = window.scrollY; if (y < 700) arc.style.transform = 'translateY(' + (y * 0.06) + 'px)';
    }, { passive: true });
  }
})();
