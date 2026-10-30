<?php
/* Diário da Sefaz: quedas do mês por autorizador, tempo fora e piores horários */
require_once __DIR__ . '/inc/layout.php';
$mes = preg_match('/^\d{4}-\d{2}$/', $_GET['m'] ?? '') ? $_GET['m'] : date('Y-m');
$d = sefaz_diario($mes);
$meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$nomeMes = $meses[(int)substr($mes, 5, 2) - 1] . ' de ' . substr($mes, 0, 4);
$ant = date('Y-m', strtotime($mes . '-01 -1 month')); $prox = date('Y-m', strtotime($mes . '-01 +1 month'));
$primeiro = db()->query("SELECT MIN(inicio) FROM sefaz_quedas")->fetchColumn();
layout_topo('Diário da Sefaz', 'fiscal', 'Quedas, tempo fora e horários — ' . $nomeMes);
$maxH = max(1, max($d['horas']));
?>
<p class="trilha mono"><a href="fiscal.php#sefaz">Fiscal</a> / Diário · <a href="?m=<?= $ant ?>">← <?= $ant ?></a> · <?php if ($prox <= date('Y-m')): ?><a href="?m=<?= $prox ?>"><?= $prox ?> →</a><?php endif; ?> · <a href="#" onclick="window.print();return false" class="so-tela">imprimir</a></p>
<div class="cards cards-3">
  <span class="card"><b><?= $d['total_quedas'] ?></b><span>Quedas no mês</span><small>por autorizador</small></span>
  <span class="card"><b><?= count($d['por']) ?></b><span>Autorizadores afetados</span><small>de 14 acompanhados</small></span>
  <span class="card"><b><?= duracao_seg($d['total_segundos']) ?></b><span>Tempo fora somado</span><small>somando os autorizadores</small></span>
</div>
<?php if (!$d['por']): ?>
  <section class="bloco"><p class="vazio">Nenhuma queda registrada em <?= $nomeMes ?><?= $primeiro ? ' (o registro começou em ' . data_br($primeiro, true) . ')' : ' — o histórico começa a ser gravado pelo cron' ?>.</p></section>
<?php else: ?>
<section class="bloco">
  <div class="bloco-cab"><h2>Por autorizador</h2></div>
  <div class="tabela-rolagem"><table class="tabela">
    <thead><tr><th>Autorizador</th><th>Estados</th><th>Quedas</th><th>Fora do ar</th><th>Tempo fora</th><th>Maior queda</th></tr></thead>
    <tbody>
      <?php foreach ($d['por'] as $a => $x): ?>
        <tr><td class="mono"><b><?= e($a) ?></b></td><td><small><?= e(implode(' ', array_keys($x['ufs']))) ?></small></td><td><?= $x['quedas'] ?></td><td><?= $x['vermelhas'] ?></td><td class="nowrap"><?= duracao_seg($x['segundos']) ?></td><td class="nowrap"><?= duracao_seg($x['maior']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<section class="bloco">
  <div class="bloco-cab"><h2>Em que horas a Sefaz cai</h2><span class="contagem">horas com algum autorizador fora, no mês</span></div>
  <div class="barras horas">
    <?php foreach ($d['horas'] as $h => $n): ?><div class="barra"><b><?= $n ?: '' ?></b><i style="--h:<?= round($n / $maxH * 100) ?>%" class="<?= $n === $maxH && $n ? 'barra-magenta' : 'barra-violet' ?>"></i><span><?= $h ?>h</span></div><?php endforeach; ?>
  </div>
  <p class="nota">Serve para o suporte: se a loja relata recusa de nota num horário em que o autorizador dela estava fora, não foi o sistema.</p>
</section>
<?php endif; ?>
<?php layout_rodape();
