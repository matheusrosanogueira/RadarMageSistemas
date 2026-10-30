<?php
require_once __DIR__ . '/inc/layout.php';

$proxima = versao_proxima();
$ultimaPub = versao_ultima_publicada();
$prontas = alteracoes_proxima();
$internas = alteracoes_internas_proxima();
$publicadas = array_values(array_filter(versoes_todas(), fn($v) => $v['publicada_em']));

function rotulo_areas(int $n): string {
    $m = min(50, $n);
    return $m === 1 ? 'Ver a alteração, por área' : "Ver as $m alterações, agrupadas por área";
}

function bloco_areas(array $alteracoes, string $rotulo): void {
    if (!$alteracoes) return;
    $grupos = agrupar_por_area($alteracoes);
    $limite = 50;
    $mostradas = 0;
    ?>
    <details class="areas">
      <summary><?= e($rotulo) ?></summary>
      <?php foreach ($grupos as $nome => $lista): if ($mostradas >= $limite) break; ?>
        <h4><?= e($nome) ?><b><?= count($lista) ?></b></h4>
        <ul class="lista compacta">
          <?php foreach ($lista as $a): if ($mostradas++ >= $limite) break; ?>
            <li><span><?= e($a['titulo']) ?></span><time><?= tempo_relativo($a['criada_em']) ?></time></li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
      <?php if (count($alteracoes) > $limite): ?>
        <p class="nota">E mais <?= count($alteracoes) - $limite ?> alterações neste ciclo, não listadas aqui.</p>
      <?php endif; ?>
    </details>
    <?php
}

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
    <?php if ($proxima && $proxima['resumo']): ?><p class="uma-linha"><b>Em uma linha:</b> <?= md_inline($proxima['resumo']) ?></p><?php endif; ?>
    <?php if ($proxima && $proxima['notas']): ?><div class="notas"><?= md($proxima['notas']) ?></div><?php endif; ?>
    <?php bloco_areas($prontas, rotulo_areas(count($prontas))); ?>
    <?php if ($internas): ?><p class="nota">Mais <b><?= $internas ?> <?= plural($internas, 'alteração', 'alterações') ?></b> no próprio Radar ou em ferramentas internas — não vão no pacote do sistema.</p><?php endif; ?>
  <?php else: ?>
    <p class="vazio">Nenhuma alteração pronta ainda para o próximo ciclo.</p>
  <?php endif; ?>
</section>

<h2 class="secao-titulo">Já entregue</h2>
<p class="nota">Cada versão com o que ela levou até você. Diga a sua no alto da página e o Radar marca o que ainda não chegou.</p>

<?php if (!$publicadas): ?>
  <p class="vazio">Nenhuma versão publicada ainda.</p>
<?php endif; ?>

<?php foreach ($publicadas as $v):
    $alts = alteracoes_da_versao((int)$v['id']);
    $issues = tarefas_da_versao((int)$v['id']);
    $falta = falta_para_visitante($v);
?>
<article class="versao <?= $falta ? 'versao-falta' : '' ?>" id="v<?= $v['id'] ?>">
  <header class="versao-cab">
    <h2><span class="versao-numero"><?= e($v['numero']) ?></span><?php if ($v['codinome']): ?><em><?= e($v['codinome']) ?></em><?php endif; ?></h2>
    <p class="versao-meta">
      <?php if ($issues): ?><?= count($issues) ?> <?= plural(count($issues), 'assunto', 'assuntos') ?> · <?php endif; ?>
      <?= count($alts) ?> <?= plural(count($alts), 'alteração', 'alterações') ?> · publicada em <?= data_br($v['publicada_em']) ?>
      <?= selo_falta($v) ?>
    </p>
  </header>

  <?php if ($v['resumo']): ?><p class="uma-linha"><b>Em uma linha:</b> <?= md_inline($v['resumo']) ?></p><?php endif; ?>

  <?php if ($v['notas']): ?>
    <details class="notas-detalhe" <?= $v === $publicadas[0] ? 'open' : '' ?>>
      <summary>Ver o que mudou na <?= e($v['numero']) ?></summary>
      <div class="notas"><?= md($v['notas']) ?></div>
      <?php if ($v['como_atualizar']): ?>
        <h3 class="md-secao">📥 Como atualizar</h3>
        <div class="notas"><?= md($v['como_atualizar']) ?></div>
      <?php endif; ?>
    </details>
  <?php elseif ($v['como_atualizar']): ?>
    <details class="notas-detalhe">
      <summary>Como atualizar para a <?= e($v['numero']) ?></summary>
      <div class="notas"><?= md($v['como_atualizar']) ?></div>
    </details>
  <?php endif; ?>

  <?php if ($issues): ?>
    <details class="issues">
      <summary>Ver <?= count($issues) === 1 ? 'a issue que fechou' : 'as ' . count($issues) . ' issues que fecharam' ?> nesta versão</summary>
      <ul class="lista tarefas">
        <?php foreach ($issues as $t): ?>
          <li>
            <span class="num">#<?= (int)$t['numero'] ?></span>
            <span class="tit"><?= e($t['titulo']) ?></span>
            <span class="tags"><?php if ($t['area']): ?><i class="tag"><?= e($t['area']) ?></i><?php endif; ?><i class="tag <?= tipo_classe($t['tipo']) ?>"><?= e($t['tipo']) ?></i></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>

  <?php bloco_areas($alts, rotulo_areas(count($alts))); ?>
</article>
<?php endforeach; ?>

<?php layout_rodape();
