/* Radar Mage — comportamento do site público */
(function () {
  var html = document.documentElement;

  // menu lateral no celular
  var btn = document.querySelector('.menu-btn'), lateral = document.getElementById('lateral'), cortina = document.getElementById('cortina');
  function fechar() { lateral.classList.remove('aberto'); cortina.classList.remove('aberto'); btn.setAttribute('aria-expanded', 'false'); }
  if (btn && lateral) {
    btn.addEventListener('click', function () {
      var aberto = lateral.classList.toggle('aberto'); cortina.classList.toggle('aberto', aberto);
      btn.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    });
    cortina.addEventListener('click', fechar);
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') fechar(); });
  }

  // tema claro / escuro (fica só neste navegador)
  var tema = document.getElementById('tema');
  function aplicarTema(t) {
    if (t === 'light') html.setAttribute('data-theme', 'light'); else html.removeAttribute('data-theme');
    if (tema) { tema.textContent = t === 'light' ? 'Tema escuro' : 'Tema claro'; tema.setAttribute('aria-pressed', t === 'light' ? 'true' : 'false'); }
  }
  var salvo = null; try { salvo = localStorage.getItem('radar_tema'); } catch (e) {}
  aplicarTema(salvo || 'dark');
  if (tema) tema.addEventListener('click', function () {
    var novo = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    aplicarTema(novo); try { localStorage.setItem('radar_tema', novo); } catch (e) {}
  });

  // "A sua instalação": grava a versão em cookie (1 ano) e recarrega
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
    if (limpar) limpar.addEventListener('click', function () { document.cookie = 'radar_versao=;path=/;max-age=0'; location.reload(); });
  }

  // ao imprimir, abre todos os detalhes e fecha os que estavam fechados depois
  var fechados = [];
  window.addEventListener('beforeprint', function () { fechados = []; document.querySelectorAll('details:not([open])').forEach(function (d) { d.open = true; fechados.push(d); }); });
  window.addEventListener('afterprint', function () { fechados.forEach(function (d) { d.open = false; }); });

  // atualiza "há X min" a cada minuto sem recarregar
  function relativo(iso) {
    var d = new Date(iso.replace(' ', 'T')); if (isNaN(d)) return null;
    var s = Math.floor((Date.now() - d.getTime()) / 1000);
    if (s < 60) return 'agora';
    var m = Math.floor(s / 60); if (m < 60) return 'há ' + m + ' min';
    var h = Math.floor(m / 60); if (h < 24) return 'há ' + h + (h > 1 ? ' horas' : ' hora');
    return null;
  }
  setInterval(function () {
    document.querySelectorAll('time[datetime]').forEach(function (t) { var r = relativo(t.getAttribute('datetime')); if (r) t.textContent = r; });
  }, 60000);
})();
