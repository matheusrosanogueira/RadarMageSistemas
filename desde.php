<?php
/* "O que mudou desde a minha versão": todas as versões entre a do visitante e a atual, numa página só */
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/versao_bloco.php';
$minha = trim($_GET['v'] ?? '') ?: versao_visitante();
$lista = $minha !== '' ? versoes_desde($minha) : [];
$conhecida = $minha !== '' && versao_por_numero($minha) !== null;
layout_topo('Desde a minha versão', 'versoes', $minha === '' ? 'Informe sua versão na barra lateral.' : ($lista ? count($lista) . ' ' . plural(count($lista), 'versão saiu', 'versões saíram') . ' depois da ' . $minha : 'Você está na versão mais recente.'));
?>
<?php if ($minha !== '' && !$conhecida): ?><div class="aviso">A versão <?= e($minha) ?> não está no histórico do Radar; a lista abaixo considera tudo que é mais novo que ela.</div><?php endif; ?>
<?php if ($lista): ?>
  <p class="nota">Da mais antiga para a mais nova, como você vai recebê-las. Leia primeiro os avisos ⚠️ de cada uma.</p>
  <?php $totAlt = 0; foreach ($lista as $v) $totAlt += max((int)($v['alteracoes_total'] ?? 0), count(alteracoes_da_versao((int)$v['id']))); ?>
  <div class="cards cards-3">
    <span class="card"><b><?= count($lista) ?></b><span>Versões a receber</span><small><?= e($lista[0]['numero']) ?> → <?= e(end($lista)['numero']) ?></small></span>
    <span class="card"><b><?= $totAlt ?></b><span>Alterações no total</span><small>somando todas as versões</small></span>
    <span class="card"><b><?= count(array_filter($lista, fn($v) => in_array($v['homologacao'] ?? '', ['liberada', 'ressalva'], true))) ?><i>/<?= count($lista) ?></i></b><span>Liberadas pela Mage</span><small><?= count(array_filter($lista, fn($v) => ($v['homologacao'] ?? '') === 'aguarde')) ? 'há versão marcada "aguarde"' : 'veja o selo em cada uma' ?></small></span>
  </div>
  <?php foreach ($lista as $v) cartao_versao($v, true); ?>
<?php elseif ($minha !== ''): ?>
  <p class="vazio">Nada a receber: a <?= e($minha) ?> é a versão publicada mais recente.</p>
<?php endif; ?>
<?php layout_rodape();
