<?php
require_once __DIR__ . '/_admin.php';
exigir_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_checar();
    $acao = post('acao');

    if ($acao === 'salvar') {
        $id = post_int('id');
        $status = post('status') === 'encerrada' ? 'encerrada' : 'aberta';
        $encerrada = $status === 'encerrada' ? normaliza_dt(post('encerrada_em'), true) : null;
        $dados = [post_int('numero'), post('titulo'), post('descricao'), post('area'), post('tipo') ?: 'melhoria', post('em_andamento') ? 1 : 0, $status, post_int('versao_id'), normaliza_dt(post('criada_em'), true), $encerrada];
        try {
            if ($id) {
                $pdo->prepare("UPDATE tarefas SET numero=?, titulo=?, descricao=?, area=?, tipo=?, em_andamento=?, status=?, versao_id=?, criada_em=?, encerrada_em=? WHERE id=?")->execute([...$dados, $id]);
                flash('Tarefa salva.');
            } else {
                $pdo->prepare("INSERT INTO tarefas(numero, titulo, descricao, area, tipo, em_andamento, status, versao_id, criada_em, encerrada_em, produto) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([...$dados, produto()]);
                flash('Tarefa #' . $dados[0] . ' aberta.');
            }
        } catch (PDOException $ex) { flash('Já existe uma tarefa com esse número.', 'erro'); }
        redir('tarefas.php');
    }

    if ($acao === 'andamento') {
        $pdo->prepare("UPDATE tarefas SET em_andamento = 1 - em_andamento WHERE id=?")->execute([post_int('id')]);
        redir('tarefas.php');
    }

    if ($acao === 'encerrar') {
        $pdo->prepare("UPDATE tarefas SET status='encerrada', em_andamento=0, encerrada_em=datetime('now','localtime'), versao_id=? WHERE id=?")->execute([post_int('versao_id'), post_int('id')]);
        flash('Tarefa encerrada.');
        redir('tarefas.php');
    }

    if ($acao === 'reabrir') {
        $pdo->prepare("UPDATE tarefas SET status='aberta', encerrada_em=NULL, versao_id=NULL WHERE id=?")->execute([post_int('id')]);
        redir('tarefas.php');
    }

    if ($acao === 'excluir') {
        $pdo->prepare("DELETE FROM tarefas WHERE id=?")->execute([post_int('id')]);
        flash('Tarefa excluída.');
        redir('tarefas.php');
    }
}

$editar = null;
if (!empty($_GET['editar'])) { $st = $pdo->prepare("SELECT * FROM tarefas WHERE id=?"); $st->execute([(int)$_GET['editar']]); $editar = $st->fetch() ?: null; }
$proxNum = (int)$pdo->query("SELECT COALESCE(MAX(numero),0)+1 FROM tarefas WHERE produto='" . produto() . "'")->fetchColumn();
$proxima = versao_proxima();
$abertas = tarefas_abertas();
$encerradas = tarefas_encerradas(200);
$tipos = ['bug' => 'bug', 'melhoria' => 'melhoria', 'fiscal' => 'fiscal', 'geral' => 'geral'];

admin_topo('Tarefas', 'tarefas');
?>
<section class="painel">
  <h2><?= $editar ? 'Editar tarefa #' . (int)$editar['numero'] : 'Abrir tarefa' ?></h2>
  <form method="post">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="id" value="<?= $editar['id'] ?? '' ?>">
    <div class="linha-campos">
      <label>Número<input type="number" name="numero" required value="<?= (int)($editar['numero'] ?? $proxNum) ?>" style="max-width:110px"></label>
      <label>Título<input name="titulo" required value="<?= e($editar['titulo'] ?? '') ?>" placeholder="Migração do Firebird 3.0 para o 5.0"></label>
    </div>
    <label>Descrição curta <small>uma frase depois do travessão</small><input name="descricao" value="<?= e($editar['descricao'] ?? '') ?>" placeholder="terminal que continua vendendo com o servidor fora do ar"></label>
    <div class="linha-campos">
      <label>Área<input name="area" list="areas" value="<?= e($editar['area'] ?? '') ?>" placeholder="Fiscal, PDV, Estoque…"></label>
      <label>Tipo<select name="tipo"><?php foreach ($tipos as $k => $r): ?><option value="<?= $k ?>" <?= ($editar['tipo'] ?? 'melhoria') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></label>
      <label>Aberta em<input type="datetime-local" name="criada_em" value="<?= $editar ? e(str_replace(' ', 'T', substr($editar['criada_em'], 0, 16))) : date('Y-m-d\TH:i') ?>"></label>
    </div>
    <div class="linha-campos">
      <label class="check"><input type="checkbox" name="em_andamento" value="1" <?= !empty($editar['em_andamento']) ? 'checked' : '' ?>> Alguém já está trabalhando</label>
      <label>Situação<select name="status"><option value="aberta" <?= ($editar['status'] ?? '') !== 'encerrada' ? 'selected' : '' ?>>aberta</option><option value="encerrada" <?= ($editar['status'] ?? '') === 'encerrada' ? 'selected' : '' ?>>encerrada</option></select></label>
      <label>Encerrada em<input type="datetime-local" name="encerrada_em" value="<?= !empty($editar['encerrada_em']) ? e(str_replace(' ', 'T', substr($editar['encerrada_em'], 0, 16))) : '' ?>"></label>
      <label>Saiu na versão<select name="versao_id"><?= opcoes_versoes(isset($editar['versao_id']) ? (int)$editar['versao_id'] : null, true, '— nenhuma —') ?></select></label>
    </div>
    <?= datalist_areas() ?>
    <div class="botoes">
      <button type="submit"><?= $editar ? 'Salvar' : 'Abrir tarefa' ?></button>
      <?php if ($editar): ?><a class="btn-sec" href="tarefas.php">Cancelar</a><?php endif; ?>
    </div>
  </form>
  <?php if ($editar): ?>
    <form method="post" class="excluir" data-confirmar="Excluir a tarefa #<?= (int)$editar['numero'] ?>?">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $editar['id'] ?>">
      <button type="submit" class="btn-perigo">Excluir</button>
    </form>
  <?php endif; ?>
</section>

<section class="painel">
  <h2>Abertas <span class="ajuda"><?= count($abertas) ?></span></h2>
  <table class="adm-tabela">
    <thead><tr><th>#</th><th>Título</th><th>Tipo</th><th>Aberta</th><th>Ações</th></tr></thead>
    <tbody>
      <?php if (!$abertas): ?><tr><td colspan="5" class="ajuda">Nenhuma tarefa aberta.</td></tr><?php endif; ?>
      <?php foreach ($abertas as $t): ?>
        <tr>
          <td class="nowrap"><?= $t['em_andamento'] ? '<i class="ponto"></i>' : '' ?>#<?= (int)$t['numero'] ?></td>
          <td><a href="?editar=<?= $t['id'] ?>"><?= e($t['titulo']) ?></a><?php if ($t['area']): ?> <i class="tag"><?= e($t['area']) ?></i><?php endif; ?></td>
          <td><i class="tag <?= tipo_classe($t['tipo']) ?>"><?= e($t['tipo']) ?></i></td>
          <td class="nowrap"><?= tempo_relativo($t['criada_em']) ?></td>
          <td class="nowrap acoes-inline">
            <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="andamento"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="btn-mini"><?= $t['em_andamento'] ? 'pausar' : 'iniciar' ?></button></form>
            <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="encerrar"><input type="hidden" name="id" value="<?= $t['id'] ?>">
              <select name="versao_id" class="mini"><?= opcoes_versoes($proxima ? (int)$proxima['id'] : null, true, 'sem versão') ?></select>
              <button class="btn-mini">encerrar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section class="painel">
  <h2>Encerradas <span class="ajuda"><?= count($encerradas) ?></span></h2>
  <table class="adm-tabela">
    <thead><tr><th>#</th><th>Título</th><th>Tipo</th><th>Saiu na</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($encerradas as $t): ?>
        <tr>
          <td class="nowrap">#<?= (int)$t['numero'] ?></td>
          <td><a href="?editar=<?= $t['id'] ?>"><?= e($t['titulo']) ?></a></td>
          <td><i class="tag <?= tipo_classe($t['tipo']) ?>"><?= e($t['tipo']) ?></i></td>
          <td class="nowrap"><?= $t['versao_numero'] ? e($t['versao_numero']) : '<span class="ajuda">—</span>' ?></td>
          <td class="nowrap"><form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="reabrir"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="btn-mini">reabrir</button></form></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php admin_rodape();
