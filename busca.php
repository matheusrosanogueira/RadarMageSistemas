<?php
require_once __DIR__ . '/inc/layout.php';
$q = trim($_GET['q'] ?? '');
$like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
$res = ['versoes' => [], 'alteracoes' => [], 'tarefas' => [], 'nts' => []];
if (mb_strlen($q) >= 2) {
    $pdo = db(); $p = produto();
    $st = $pdo->prepare("SELECT * FROM versoes WHERE produto=? AND (numero LIKE ? OR codinome LIKE ? OR resumo LIKE ? OR notas LIKE ?) ORDER BY publicada_em DESC LIMIT 20"); $st->execute([$p, $like, $like, $like, $like]); $res['versoes'] = $st->fetchAll();
    $st = $pdo->prepare("SELECT a.*, v.numero AS versao_numero FROM alteracoes a LEFT JOIN versoes v ON v.id=a.versao_id WHERE a.produto=? AND a.interno=0 AND (a.titulo LIKE ? OR a.area LIKE ? OR a.ref LIKE ?) ORDER BY a.criada_em DESC LIMIT 100"); $st->execute([$p, $like, $like, $like]); $res['alteracoes'] = $st->fetchAll();
    $st = $pdo->prepare("SELECT t.*, v.numero AS versao_numero FROM tarefas t LEFT JOIN versoes v ON v.id=t.versao_id WHERE t.produto=? AND (t.titulo LIKE ? OR t.descricao LIKE ? OR t.area LIKE ? OR CAST(t.numero AS TEXT) = ?) ORDER BY t.status='aberta' DESC, t.criada_em DESC LIMIT 60"); $st->execute([$p, $like, $like, $like, ltrim($q, '#')]); $res['tarefas'] = $st->fetchAll();
    $st = $pdo->prepare("SELECT n.*, t.numero AS tarefa_numero FROM notas_tecnicas n LEFT JOIN tarefas t ON t.id=n.tarefa_id WHERE n.produto=? AND (n.codigo LIKE ? OR n.assunto LIKE ?) ORDER BY n.producao LIMIT 30"); $st->execute([$p, $like, $like]); $res['nts'] = $st->fetchAll();
}
$total = array_sum(array_map('count', $res));
function realce(string $texto, string $q): string {
    $h = e($texto); if ($q === '') return $h;
    return preg_replace('/(' . preg_quote(e($q), '/') . ')/iu', '<mark>$1</mark>', $h);
}
layout_topo('Busca', 'busca');
?>
<div class="cabecalho"><h1>Busca</h1><p><?= $q === '' ? 'Procure uma alteração, versão, tarefa ou Nota Técnica.' : ($total ? "$total " . plural($total, 'resultado', 'resultados') . " para “" . e($q) . "”" : "Nada encontrado para “" . e($q) . "”.") ?></p></div>
<?php if ($q !== '' && mb_strlen($q) < 2): ?><p class="vazio">Digite pelo menos 2 caracteres.</p><?php endif; ?>

<?php if ($res['versoes']): ?>
<section class="bloco"><div class="bloco-cab"><h2>Versões</h2><span class="contagem"><?= count($res['versoes']) ?></span></div>
  <ul class="lista">
    <?php foreach ($res['versoes'] as $v): ?><li><span><a href="versoes.php#v<?= $v['id'] ?>"><b><?= realce($v['numero'], $q) ?></b><?= $v['codinome'] ? ' — ' . realce($v['codinome'], $q) : '' ?></a><?php if ($v['resumo']): ?><small class="sub"><?= realce(mb_strimwidth($v['resumo'], 0, 160, '…'), $q) ?></small><?php endif; ?></span><time><?= $v['publicada_em'] ? data_br($v['publicada_em']) : 'em preparo' ?></time></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($res['tarefas']): ?>
<section class="bloco"><div class="bloco-cab"><h2>Tarefas</h2><span class="contagem"><?= count($res['tarefas']) ?></span></div>
  <ul class="lista tarefas">
    <?php foreach ($res['tarefas'] as $t): ?><li class="<?= $t['em_andamento'] ? 'andamento' : '' ?>"><span class="num mono">#<?= (int)$t['numero'] ?></span><span class="tit"><?= realce($t['titulo'], $q) ?><?php if ($t['descricao']): ?> <small>— <?= realce($t['descricao'], $q) ?></small><?php endif; ?></span><span class="tags"><?php if ($t['area']): ?><i class="tag"><?= e($t['area']) ?></i><?php endif; ?><i class="tag <?= tipo_classe($t['tipo']) ?>"><?= e($t['tipo']) ?></i></span><time><?= $t['status'] === 'aberta' ? 'aberta ' . tempo_relativo($t['criada_em']) : ($t['versao_numero'] ? 'saiu na ' . e($t['versao_numero']) : 'encerrada') ?></time></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($res['nts']): ?>
<section class="bloco"><div class="bloco-cab"><h2>Notas Técnicas</h2><span class="contagem"><?= count($res['nts']) ?></span></div>
  <ul class="lista">
    <?php foreach ($res['nts'] as $n): ?><li><span><b><?= realce($n['codigo'], $q) ?></b> <?= realce($n['assunto'], $q) ?></span><time><?= $n['status'] === 'aberta' ? ($n['producao'] ? 'produção ' . data_br($n['producao']) : 'sem data') : 'atendida' ?></time></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($res['alteracoes']): ?>
<section class="bloco"><div class="bloco-cab"><h2>Alterações</h2><span class="contagem"><?= count($res['alteracoes']) ?></span></div>
  <ul class="lista feed">
    <?php foreach ($res['alteracoes'] as $a): ?><li><span><?= realce($a['titulo'], $q) ?><?php if ($a['area']): ?> <i class="area-mini"><?= e($a['area']) ?></i><?php endif; ?></span><?php if ($a['versao_numero']): ?><code><?= e($a['versao_numero']) ?></code><?php endif; ?><time datetime="<?= e($a['criada_em']) ?>"><?= tempo_relativo($a['criada_em']) ?></time></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php layout_rodape();
