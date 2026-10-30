<?php
require_once __DIR__ . '/inc/layout.php';

$proxima = versao_proxima();
$ultimaPub = versao_ultima_publicada();
$prontas = filtrar_alteracoes(alteracoes_proxima());
$internas = alteracoes_internas_proxima();
$publicadas = array_values(array_filter(versoes_todas(), fn($v) => $v['publicada_em']));

require_once __DIR__ . '/inc/versao_bloco.php';

layout_topo('Versões', 'versoes');
titulo_pagina('Versões', 'O que vem e o que saiu');
?>

<section class="bloco proxima">
  <div class="bloco-cab">
    <h2>Vindo na próxima</h2>
    <span class="selo">ainda não publicada</span>
  </div>
  <?php if ($proxima): ?>
    <div class="versao-numero"><?= e($proxima['numero']) ?><?php if ($proxima['codinome']): ?> <em><?= e($proxima['codinome']) ?></em><?php endif; ?></div>
  <?php endif; ?>
  <?php if ($prontas): ?>
    <p>São <b><?= count($prontas) ?> <?= plural(count($prontas), 'alteração', 'alterações') ?></b><?php if ($ultimaPub): ?> desde o fechamento anterior, em <b><?= data_br($ultimaPub['publicada_em']) ?></b><?php endif; ?>. Tudo isto sai junto na próxima atualização.</p>
    <?php if ($proxima && !empty($proxima['nota_mage'])): ?><div class="nota-mage"><b><?= e(cfg('empresa')) ?> diz:</b> <?= md_inline($proxima['nota_mage']) ?></div><?php endif; ?>
    <?php if ($proxima && $proxima['resumo']): ?><p class="uma-linha"><b>Em uma linha:</b> <?= md_inline($proxima['resumo']) ?></p><?php endif; ?>
    <?php if ($proxima && $proxima['notas']): ?><div class="notas"><?= md($proxima['notas']) ?></div><?php endif; ?>
    <?php bloco_areas($prontas, rotulo_areas(count($prontas))); ?>
    <?php if ($internas): ?><p class="nota">Mais <b><?= $internas ?> <?= plural($internas, 'alteração', 'alterações') ?></b> no próprio Radar ou em ferramentas internas — não vão no pacote do sistema.</p><?php endif; ?>
  <?php else: ?>
    <p class="vazio">Nenhuma alteração pronta ainda para o próximo ciclo.</p>
  <?php endif; ?>
</section>

<h2 class="secao-titulo">Já entregue</h2>
<p class="nota">Cada versão com o que ela levou até você. Diga a sua na barra lateral e o Radar marca o que ainda não chegou.</p>

<?php if (!$publicadas): ?>
  <p class="vazio">Nenhuma versão publicada ainda.</p>
<?php endif; ?>

<?php $todas = isset($_GET['todas']); $limiteV = 6; $recentes = $todas ? $publicadas : array_slice($publicadas, 0, $limiteV); $antigas = $todas ? [] : array_slice($publicadas, $limiteV); ?>
<?php foreach ($recentes as $v) cartao_versao($v, $v === $publicadas[0]); ?>
<?php if ($antigas): ?>
<section class="bloco">
  <div class="bloco-cab"><h2>Versões anteriores</h2><a href="?todas=1">mostrar todas por completo</a></div>
  <ul class="lista">
    <?php foreach ($antigas as $v): ?>
      <li><span><a href="v.php?n=<?= e($v['numero']) ?>"><b><?= e($v['numero']) ?></b><?= $v['codinome'] ? ' — ' . e($v['codinome']) : '' ?></a><?= selo_homologacao($v) ?><?= selo_falta($v) ?><?php if ($v['resumo']): ?><small class="sub"><?= e(mb_strimwidth($v['resumo'], 0, 140, '…')) ?></small><?php endif; ?></span><time><?= data_br($v['publicada_em'], true) ?></time></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php layout_rodape();
