<?php
require_once __DIR__ . '/inc/layout.php';

$st = projeto_stats();
$meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$rotulos = [];
if ($st) {
    $n = count($st['commits'] ?? []);
    $fim = dt($st['atualizado'] ?? null) ?? new DateTime();
    for ($i = $n - 1; $i >= 0; $i--) { $d = (clone $fim)->modify('first day of this month')->modify("-$i months"); $rotulos[] = $meses[(int)$d->format('n') - 1]; }
}
function fmt_n(int|float $n): string { return number_format($n, 0, ',', '.'); }

function barras(array $valores, array $rotulos, string $cor = 'violet'): void {
    $max = max(1, max($valores ?: [0]));
    echo '<div class="barras">';
    foreach ($valores as $i => $v) {
        $h = round($v / $max * 100);
        echo '<div class="barra"><b>' . fmt_n($v) . '</b><i style="--h:' . $h . '%" class="barra-' . $cor . '"></i><span>' . e($rotulos[$i] ?? '') . '</span></div>';
    }
    echo '</div>';
}

layout_topo('O projeto', 'projeto');
titulo_pagina('O projeto', $st ? 'Ritmo e tamanho' : 'Como este Radar funciona');
?>
<?php if ($st): ?>
<div class="duas-colunas iguais">
  <section class="bloco">
    <div class="bloco-cab"><h2>Commits por mês</h2><span class="contagem"><?= fmt_n(array_sum($st['commits'])) ?> no período</span></div>
    <?php barras($st['commits'], $rotulos); ?>
  </section>
  <section class="bloco">
    <div class="bloco-cab"><h2>Módulos novos</h2><span class="contagem"><?= fmt_n(array_sum($st['modulos'])) ?> no período</span></div>
    <?php barras($st['modulos'], $rotulos, 'magenta'); ?>
  </section>
</div>

<section class="bloco">
  <?php $ent = array_sum($st['linhas_entraram']); $sai = array_sum($st['linhas_sairam']); $saldo = $ent - $sai; ?>
  <div class="bloco-cab"><h2>Linhas trocadas</h2><span class="contagem">entraram <?= fmt_n($ent) ?> · saíram <?= fmt_n($sai) ?> · saldo <?= $saldo >= 0 ? '+' : '−' ?><?= fmt_n(abs($saldo)) ?></span></div>
  <div class="sefaz-legenda"><span><i class="bola bola-violet"></i>entraram</span><span><i class="bola bola-cinza"></i>saíram</span></div>
  <?php $max = max(1, max(array_merge($st['linhas_entraram'], $st['linhas_sairam']))); ?>
  <div class="barras duplas">
    <?php foreach ($st['linhas_entraram'] as $i => $v): $s = $st['linhas_sairam'][$i] ?? 0; ?>
      <div class="barra">
        <b><?= fmt_n($v) ?><small><?= fmt_n($s) ?></small></b>
        <em><i style="--h:<?= round($v / $max * 100) ?>%" class="barra-violet"></i><i style="--h:<?= round($s / $max * 100) ?>%" class="barra-cinza"></i></em>
        <span><?= e($rotulos[$i] ?? '') ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="duas-colunas iguais">
  <section class="bloco">
    <div class="bloco-cab"><h2>Onde o esforço foi parar</h2><span class="contagem">menções no título das alterações</span></div>
    <?php $maxF = max(1, (int)($st['frentes'][0][1] ?? 1)); ?>
    <ul class="frentes">
      <?php foreach (array_slice($st['frentes'], 0, 14) as [$nome, $qtd]): ?>
        <li><span><?= e($nome) ?></span><i style="--w:<?= round($qtd / $maxF * 100) ?>%"></i><b><?= fmt_n($qtd) ?></b></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <section class="bloco">
    <div class="bloco-cab"><h2>O tamanho da base</h2></div>
    <?php $sis = $st['sistema']; ?>
    <dl class="tamanho">
      <div><dt>Units de código .pas</dt><dd><?= fmt_n($sis['units'] ?? 0) ?></dd></div>
      <div><dt>Formulários .dfm</dt><dd><?= fmt_n($sis['forms'] ?? 0) ?></dd></div>
      <div><dt>Linhas de Pascal</dt><dd><?= fmt_n($sis['linhas'] ?? 0) ?></dd></div>
      <div><dt>Arquivos em View/Model/Service</dt><dd><?= fmt_n($sis['autorais'] ?? 0) ?></dd></div>
      <div><dt>Linhas nesses arquivos</dt><dd><?= fmt_n($sis['linhas_autorais'] ?? 0) ?></dd></div>
      <div><dt>Migrações de banco</dt><dd><?= fmt_n($sis['migracoes'] ?? 0) ?></dd></div>
    </dl>
    <p class="nota">Os três executáveis — Retaguarda, PDV e Pré-Venda — saem destes mesmos fontes.</p>
  </section>
</div>
<?php endif; ?>

<section class="bloco">
  <div class="notas prosa"><?= md(cfg('projeto_texto')) ?></div>
</section>
<?php layout_rodape();
