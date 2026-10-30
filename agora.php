<?php
require_once __DIR__ . '/inc/layout.php';

$stats = projeto_stats();
$pulso = $stats ? array_slice($stats['pulso'], -56) : pulso_diario(56);
$dias = count($pulso);
$total = array_sum(array_column($pulso, 1));
$max = max(1, max(array_column($pulso, 1) ?: [0]));
$semanas = (int)ceil($dias / 7);

$st = db()->prepare("SELECT a.*, v.numero AS versao_numero FROM alteracoes a LEFT JOIN versoes v ON v.id = a.versao_id WHERE a.produto = ? AND a.interno = 0 ORDER BY a.criada_em DESC, a.id DESC LIMIT 300");
$st->execute([produto()]);
$porDia = [];
foreach (filtrar_alteracoes($st->fetchAll()) as $a) $porDia[substr($a['criada_em'], 0, 10)][] = $a;

layout_topo('Agora', 'agora');
titulo_pagina('Agora', 'O trabalho ao vivo');
?>
<section class="bloco">
  <div class="bloco-cab"><h2>O pulso ao vivo</h2></div>
  <p class="resumo-num"><b><?= $total ?></b> <?= plural($total, 'alteração', 'alterações') ?> em <?= $semanas ?> semanas</p>
  <div class="pulso" role="img" aria-label="Alterações por dia nos últimos <?= $dias ?> dias">
    <?php foreach ($pulso as [$dia, $n]): ?>
      <div class="pulso-barra" style="--h:<?= round($n / $max * 100) ?>%" title="<?= $n ?> em <?= data_br($dia) ?>"><i></i></div>
    <?php endforeach; ?>
  </div>
  <div class="pulso-eixo"><span><?= $semanas ?> semanas atrás</span><span>hoje</span></div>
</section>

<section class="bloco">
  <div class="bloco-cab"><h2>Cada alteração, conforme entra</h2></div>
  <p class="nota">O título é o mesmo que fica no histórico do projeto. Se tem linha nova aí em cima, alguém está mexendo no sistema neste momento.<?php if (filtro_areas()): ?> <b>Mostrando só as áreas da sua loja</b> (e o que não tem área).<?php endif; ?></p>

  <?php if (!$porDia): ?>
    <p class="vazio">Nenhuma alteração registrada ainda.</p>
  <?php endif; ?>

  <?php foreach ($porDia as $dia => $lista): ?>
    <h3 class="dia"><?= rotulo_dia($dia) ?></h3>
    <ul class="lista feed">
      <?php foreach ($lista as $a): ?>
        <li>
          <span><?= e($a['titulo']) ?><?php if ($a['area']): ?> <i class="area-mini"><?= e($a['area']) ?></i><?php endif; ?></span>
          <?php if ($a['ref']): ?><code><?= e($a['ref']) ?></code><?php endif; ?>
          <time datetime="<?= e($a['criada_em']) ?>"><?= tempo_relativo($a['criada_em']) ?></time>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>
</section>
<?php layout_rodape();
