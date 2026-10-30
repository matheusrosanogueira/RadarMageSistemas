<?php
require_once __DIR__ . '/inc/layout.php';
layout_topo('O projeto', 'projeto');
titulo_pagina('O projeto', 'Como este Radar funciona');
?>
<section class="bloco">
  <div class="notas prosa"><?= md(cfg('projeto_texto')) ?></div>
</section>
<?php layout_rodape();
