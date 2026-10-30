(function () {
  document.querySelectorAll('form[data-confirmar]').forEach(function (f) {
    f.addEventListener('submit', function (ev) { if (!confirm(f.getAttribute('data-confirmar'))) ev.preventDefault(); });
  });
  var todos = document.querySelector('[data-todos]');
  if (todos) todos.addEventListener('change', function () {
    document.querySelectorAll('input[name="ids[]"]').forEach(function (c) { c.checked = todos.checked; });
  });
})();
