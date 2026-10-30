<?php
require_once __DIR__ . '/_admin.php';
exigir_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_checar();
    $acao = post('acao');

    if ($acao === 'lote') {
        $versao = post_int('versao_id');
        $areaPadrao = post('area');
        $n = 0;
        $st = $pdo->prepare("INSERT INTO alteracoes(produto, versao_id, titulo, area, ref, interno, criada_em) VALUES (?,?,?,?,?,?,datetime('now','localtime'))");
        foreach (preg_split('/\r?\n/', post('linhas')) as $linha) {
            $linha = trim($linha); if ($linha === '') continue;
            $area = $areaPadrao; $titulo = $linha; $interno = 0;
            if (preg_match('/^([^:]{2,40}):\s+(.+)$/u', $linha, $m)) { $area = trim($m[1]); $titulo = trim($m[2]); }
            if (preg_match('/^\[(radar|interno)\]\s*/i', $titulo, $m)) { $interno = 1; $titulo = trim(substr($titulo, strlen($m[0]))); }
            $st->execute([produto(), $versao, $titulo, $area, '', $interno]);
            $n++;
        }
        flash("$n " . plural($n, 'alteração registrada', 'alterações registradas') . '.');
        redir('alteracoes.php');
    }

    if ($acao === 'salvar') {
        $id = post_int('id');
        $dados = [post('titulo'), post('area'), post('ref'), post('interno') ? 1 : 0, post_int('versao_id'), normaliza_dt(post('criada_em'), true)];
        if ($id) {
            $pdo->prepare("UPDATE alteracoes SET titulo=?, area=?, ref=?, interno=?, versao_id=?, criada_em=? WHERE id=?")->execute([...$dados, $id]);
            flash('Alteração salva.');
        } else {
            $pdo->prepare("INSERT INTO alteracoes(titulo, area, ref, interno, versao_id, criada_em, produto) VALUES (?,?,?,?,?,?,?)")->execute([...$dados, produto()]);
            flash('Alteração registrada.');
        }
        redir('alteracoes.php');
    }

    if ($acao === 'excluir') {
        $pdo->prepare("DELETE FROM alteracoes WHERE id=?")->execute([post_int('id')]);
        flash('Alteração excluída.');
        redir('alteracoes.php');
    }

    if ($acao === 'mover') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE alteracoes SET versao_id=? WHERE id IN ($in)")->execute([post_int('versao_id'), ...$ids]);
            flash(count($ids) . ' alterações movidas.');
        }
        redir('alteracoes.php?' . http_build_query(['versao' => $_GET['versao'] ?? '']));
    }
}

$editar = null;
if (!empty($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM alteracoes WHERE id=?"); $st->execute([(int)$_GET['editar']]); $editar = $st->fetch() ?: null;
}

$filtroVersao = $_GET['versao'] ?? 'ciclo';
$busca = trim($_GET['q'] ?? '');
$sql = "SELECT a.*, v.numero AS versao_numero FROM alteracoes a LEFT JOIN versoes v ON v.id=a.versao_id WHERE a.produto=?";
$par = [produto()];
$proxima = versao_proxima();
if ($filtroVersao === 'ciclo') { $sql .= " AND (a.versao_id IS NULL" . ($proxima ? " OR a.versao_id=" . (int)$proxima['id'] : "") . ")"; }
elseif ($filtroVersao !== 'todas') { $sql .= " AND a.versao_id=?"; $par[] = (int)$filtroVersao; }
if ($busca !== '') { $sql .= " AND (a.titulo LIKE ? OR a.area LIKE ?)"; $par[] = "%$busca%"; $par[] = "%$busca%"; }
$sql .= " ORDER BY a.criada_em DESC, a.id DESC LIMIT 500";
$st = $pdo->prepare($sql); $st->execute($par); $lista = $st->fetchAll();

admin_topo('Alterações', 'alteracoes');
?>
<div class="adm-grid">
  <section class="painel">
    <h2><?= $editar ? 'Editar alteração' : 'Nova alteração' ?></h2>
    <form method="post">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar">
      <input type="hidden" name="id" value="<?= $editar['id'] ?? '' ?>">
      <label>Título <small>o que mudou, na linguagem do cliente</small><input name="titulo" required value="<?= e($editar['titulo'] ?? '') ?>"></label>
      <div class="linha-campos">
        <label>Área<input name="area" list="areas" value="<?= e($editar['area'] ?? '') ?>" placeholder="NF-e 55, PDV, Relatórios…"></label>
        <label>Referência <small>commit, ticket, opcional</small><input name="ref" value="<?= e($editar['ref'] ?? '') ?>" placeholder="a1b2c3d"></label>
      </div>
      <div class="linha-campos">
        <label>Vai na<select name="versao_id"><?= opcoes_versoes(isset($editar['versao_id']) ? (int)$editar['versao_id'] : ($proxima ? (int)$proxima['id'] : null)) ?></select></label>
        <label>Data e hora<input type="datetime-local" name="criada_em" value="<?= $editar ? e(str_replace(' ', 'T', substr($editar['criada_em'], 0, 16))) : date('Y-m-d\TH:i') ?>"></label>
      </div>
      <label class="check"><input type="checkbox" name="interno" value="1" <?= !empty($editar['interno']) ? 'checked' : '' ?>> Interna (Radar/ferramentas) — não vai no pacote do sistema</label>
      <?= datalist_areas() ?>
      <div class="botoes">
        <button type="submit"><?= $editar ? 'Salvar' : 'Registrar' ?></button>
        <?php if ($editar): ?><a class="btn-sec" href="alteracoes.php">Cancelar</a><?php endif; ?>
      </div>
    </form>
    <?php if ($editar): ?>
      <form method="post" class="excluir" data-confirmar="Excluir esta alteração?">
        <?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $editar['id'] ?>">
        <button type="submit" class="btn-perigo">Excluir</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="painel">
    <h2>Lançar várias de uma vez</h2>
    <p class="ajuda">Uma por linha. Prefixe a área com dois-pontos (<code>PDV: …</code>). Prefixe <code>[radar]</code> para marcar como interna.</p>
    <form method="post">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="lote">
      <textarea name="linhas" rows="8" required></textarea>
      <div class="linha-campos">
        <label>Área padrão<input name="area" list="areas"></label>
        <label>Vai na<select name="versao_id"><?= opcoes_versoes($proxima ? (int)$proxima['id'] : null) ?></select></label>
      </div>
      <button type="submit">Registrar todas</button>
    </form>
  </section>
</div>

<section class="painel">
  <form method="get" class="filtros">
    <select name="versao">
      <option value="ciclo" <?= $filtroVersao === 'ciclo' ? 'selected' : '' ?>>Ciclo atual (próxima)</option>
      <option value="todas" <?= $filtroVersao === 'todas' ? 'selected' : '' ?>>Todas</option>
      <?php foreach (versoes_todas() as $v): ?><option value="<?= $v['id'] ?>" <?= $filtroVersao == $v['id'] ? 'selected' : '' ?>><?= e($v['numero']) ?></option><?php endforeach; ?>
    </select>
    <input name="q" value="<?= e($busca) ?>" placeholder="Buscar no título ou área">
    <button type="submit" class="btn-sec">Filtrar</button>
    <span class="ajuda"><?= count($lista) ?> <?= plural(count($lista), 'alteração', 'alterações') ?></span>
  </form>

  <form method="post" id="form-mover">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="mover">
    <table class="adm-tabela">
      <thead><tr><th><input type="checkbox" data-todos></th><th>Título</th><th>Área</th><th>Versão</th><th>Quando</th><th></th></tr></thead>
      <tbody>
        <?php if (!$lista): ?><tr><td colspan="6" class="ajuda">Nenhuma alteração neste filtro.</td></tr><?php endif; ?>
        <?php foreach ($lista as $a): ?>
          <tr class="<?= $a['interno'] ? 'interna' : '' ?>">
            <td><input type="checkbox" name="ids[]" value="<?= $a['id'] ?>"></td>
            <td><?= e($a['titulo']) ?><?php if ($a['interno']): ?> <i class="tag">interna</i><?php endif; ?><?php if ($a['ref']): ?> <code><?= e($a['ref']) ?></code><?php endif; ?></td>
            <td><?= e($a['area']) ?></td>
            <td><?= $a['versao_numero'] ? e($a['versao_numero']) : '<span class="ajuda">próximo ciclo</span>' ?></td>
            <td class="nowrap"><?= data_hora_br($a['criada_em']) ?></td>
            <td class="nowrap"><a href="?editar=<?= $a['id'] ?>">editar</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="acoes-lote">
      Com as selecionadas: mover para
      <select name="versao_id"><?= opcoes_versoes(null) ?></select>
      <button type="submit" class="btn-sec">Mover</button>
    </div>
  </form>
</section>
<?php admin_rodape();
