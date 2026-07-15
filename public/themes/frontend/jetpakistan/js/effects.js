// JetPakistan — loader fade-out, 3D card tilt, hero scene parallax
(function () {
  var fine = window.matchMedia('(hover:hover)').matches;
  var rm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (rm || !fine) return;

  document.querySelectorAll('.tilt').forEach(function (card) {
    card.addEventListener('mousemove', function (e) {
      var r = card.getBoundingClientRect();
      var px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
      card.style.transform = 'perspective(900px) rotateX(' + ((0.5 - py) * 9).toFixed(2) +
        'deg) rotateY(' + ((px - 0.5) * 11).toFixed(2) + 'deg) translateY(-4px)';
      card.style.setProperty('--mx', (px * 100).toFixed(1) + '%');
      card.style.setProperty('--my', (py * 100).toFixed(1) + '%');
    });
    card.addEventListener('mouseleave', function () { card.style.transform = ''; });
  });

  var scene = document.querySelector('.hero-scene');
  if (scene) window.addEventListener('mousemove', function (e) {
    var cx = e.clientX / window.innerWidth - 0.5, cy = e.clientY / window.innerHeight - 0.5;
    scene.style.transform = 'translate3d(' + (cx * -20).toFixed(1) + 'px,' + (cy * -14).toFixed(1) + 'px,0)';
  }, { passive: true });
})();
