<?php
/* Página de uma versão: link compartilhável com prévia */
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/versao_bloco.php';
$n = trim($_GET['n'] ?? '');
$v = $n !== '' ? versao_por_numero($n) : null;
if (!$v) { http_response_code(404); layout_topo('Versão não encontrada', 'versoes', 'Nenhuma versão com esse número.'); echo '<p class="vazio">Veja <a href="versoes.php">todas as versões</a>.</p>'; layout_rodape(); exit; }
$titulo = cfg('sistema_nome') . ' ' . $v['numero'] . ($v['codinome'] ? ' — ' . $v['codinome'] : '');
layout_topo($v['numero'], 'versoes', '', [
    'titulo' => $titulo, 'descricao' => $v['resumo'] ?: cfg('descricao'), 'url' => url_base() . '/v.php?n=' . rawurlencode($v['numero']), 'tipo' => 'article',
    'imagem' => http_build_query(['v' => $v['numero'], 't' => $v['codinome'] ?: ($v['publicada_em'] ? 'publicada em ' . data_br($v['publicada_em'], true) : 'em preparo'), 's' => mb_strimwidth($v['resumo'] ?? '', 0, 110, '…')]),
]);
?>
<p class="trilha mono"><a href="versoes.php">Versões</a> / <?= e($v['numero']) ?></p>
<?php if ($v['publicada_em']): cartao_versao($v, true, false); else: ?>
<section class="bloco proxima">
  <div class="bloco-cab"><h2>Vindo na próxima</h2><span class="selo">ainda não publicada</span></div>
  <div class="versao-numero"><?= e($v['numero']) ?><?php if ($v['codinome']): ?> <em><?= e($v['codinome']) ?></em><?php endif; ?></div>
  <?php if (!empty($v['nota_mage'])): ?><div class="nota-mage"><b><?= e(cfg('empresa')) ?> diz:</b> <?= md_inline($v['nota_mage']) ?></div><?php endif; ?>
  <?php if ($v['resumo']): ?><p class="uma-linha"><b>Em uma linha:</b> <?= md_inline($v['resumo']) ?></p><?php endif; ?>
  <?php $alts = alteracoes_da_versao((int)$v['id']); if ($alts) bloco_areas($alts, rotulo_areas(count($alts)), 0, true); else echo '<p class="vazio">O ciclo acabou de começar.</p>'; ?>
</section>
<?php endif; ?>
<p class="nota">Link desta página: <code><?= e(url_base()) ?>/v.php?n=<?= e($v['numero']) ?></code> — cole no WhatsApp e ele mostra a prévia.</p>
<?php layout_rodape();
