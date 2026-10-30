/* Radar Mage — comportamento do site público */
(function () {
  // menu mobile
  var btn = document.querySelector('.menu-btn'), nav = document.getElementById('nav');
  if (btn && nav) {
    btn.addEventListener('click', function () {
      var aberto = nav.classList.toggle('aberto');
      btn.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    });
  }

  // "A sua instalação": grava a versão em cookie (1 ano) e recarrega para o servidor marcar
  var form = document.getElementById('form-versao');
  if (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var v = form.querySelector('input').value.trim();
      if (!/^\d+(\.\d+){0,3}$/.test(v)) { form.querySelector('input').focus(); return; }
      document.cookie = 'radar_versao=' + encodeURIComponent(v) + ';path=/;max-age=31536000;SameSite=Lax';
      location.reload();
    });
    var limpar = document.getElementById('limpar-versao');
    if (limpar) limpar.addEventListener('click', function () {
      document.cookie = 'radar_versao=;path=/;max-age=0';
      location.reload();
    });
  }

  // atualiza "há X min" a cada minuto sem recarregar
  function relativo(iso) {
    var d = new Date(iso.replace(' ', 'T')); if (isNaN(d)) return null;
    var s = Math.floor((Date.now() - d.getTime()) / 1000);
    if (s < 60) return 'agora';
    var m = Math.floor(s / 60); if (m < 60) return 'há ' + m + ' min';
    var h = Math.floor(m / 60); if (h < 24) return 'há ' + h + (h > 1 ? ' horas' : ' hora');
    return null; // acima disso o servidor já escreveu certo
  }
  setInterval(function () {
    document.querySelectorAll('time[datetime]').forEach(function (t) {
      var r = relativo(t.getAttribute('datetime')); if (r) t.textContent = r;
    });
  }, 60000);
})();
