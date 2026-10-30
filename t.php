<?php
/* Página de uma tarefa: link compartilhável */
require_once __DIR__ . '/inc/layout.php';
$n = (int)($_GET['n'] ?? 0);
$t = $n ? tarefa_por_numero($n) : null;
if (!$t) { http_response_code(404); layout_topo('Tarefa não encontrada', 'planejado', 'Nenhuma tarefa com esse número.'); echo '<p class="vazio">Veja <a href="planejado.php">o planejado</a>.</p>'; layout_rodape(); exit; }
$nt = db()->prepare("SELECT * FROM notas_tecnicas WHERE tarefa_id = ?"); $nt->execute([$t['id']]); $nt = $nt->fetch() ?: null;
$situacao = $t['status'] === 'aberta' ? ($t['em_andamento'] ? 'em andamento' : 'aberta') : ($t['versao_numero'] ? 'saiu na ' . $t['versao_numero'] : 'encerrada');
layout_topo('#' . $n, 'planejado', '', [
    'titulo' => '#' . $n . ' ' . $t['titulo'], 'descricao' => ($t['descricao'] ?: $t['titulo']) . ' · ' . $situacao, 'url' => url_base() . '/t.php?n=' . $n, 'tipo' => 'article',
    'imagem' => http_build_query(['v' => '#' . $n, 't' => mb_strimwidth($t['titulo'], 0, 90, '…'), 's' => ucfirst($situacao) . ($t['area'] ? ' · ' . $t['area'] : '') . ' · ' . $t['tipo']]),
]);
?>
<p class="trilha mono"><a href="planejado.php">Planejado</a> / #<?= $n ?></p>
<section class="bloco">
  <div class="bloco-cab"><h2><span class="mono">#<?= $n ?></span> <?= e($t['titulo']) ?></h2></div>
  <?php if ($t['descricao']): ?><p class="uma-linha"><?= e($t['descricao']) ?></p><?php endif; ?>
  <p class="tags-linha">
    <?php if ($t['area']): ?><i class="tag"><?= e($t['area']) ?></i><?php endif; ?><?php if (mb_strtolower($t['area'] ?? '') !== $t['tipo']): ?><i class="tag <?= tipo_classe($t['tipo']) ?>"><?= e($t['tipo']) ?></i><?php endif; ?>
    <?php if ($t['labels'] && $t['labels'] !== $t['tipo']): ?><i class="tag"><?= e($t['labels']) ?></i><?php endif; ?>
  </p>
  <dl class="ficha">
    <div><dt>Situação</dt><dd><?= e(ucfirst($situacao)) ?></dd></div>
    <div><dt>Aberta</dt><dd><?= data_br($t['criada_em'], true) ?> (<?= tempo_relativo($t['criada_em']) ?>)</dd></div>
    <?php if ($t['encerrada_em']): ?><div><dt>Encerrada</dt><dd><?= data_br($t['encerrada_em'], true) ?></dd></div><?php endif; ?>
    <?php if ($t['versao_numero']): ?><div><dt>Versão</dt><dd><a href="v.php?n=<?= e($t['versao_numero']) ?>"><?= e($t['versao_numero']) ?></a></dd></div><?php endif; ?>
    <?php if ($t['responsaveis']): ?><div><dt>Com quem</dt><dd><?= e($t['responsaveis']) ?></dd></div><?php endif; ?>
    <?php if ($nt): ?>
      <div><dt>Nota Técnica</dt><dd><b><?= e($nt['codigo']) ?></b> <?= e($nt['assunto']) ?></dd></div>
      <div><dt>Homologação</dt><dd><?= data_br($nt['homologacao'], true) ?></dd></div>
      <div><dt>Produção</dt><dd><?= data_br($nt['producao'], true) ?></dd></div>
    <?php endif; ?>
  </dl>
</section>
<p class="nota">Link desta página: <code><?= e(url_base()) ?>/t.php?n=<?= $n ?></code></p>
<?php layout_rodape();
