<?php
require_once __DIR__ . '/_admin.php';
exigir_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_checar();
    $acao = post('acao');
    if ($acao === 'salvar') {
        $id = post_int('id');
        $status = in_array(post('status'), ['aberta', 'atendida', 'nao_aplica'], true) ? post('status') : 'aberta';
        $dados = [post('codigo'), post('assunto'), post_data('homologacao'), post_data('producao'), post_int('tarefa_id'), $status, post('observacao')];
        if ($id) { $pdo->prepare("UPDATE notas_tecnicas SET codigo=?, assunto=?, homologacao=?, producao=?, tarefa_id=?, status=?, observacao=? WHERE id=?")->execute([...$dados, $id]); flash('Nota Técnica salva.'); }
        else { $pdo->prepare("INSERT INTO notas_tecnicas(codigo, assunto, homologacao, producao, tarefa_id, status, observacao, produto) VALUES (?,?,?,?,?,?,?,?)")->execute([...$dados, produto()]); flash('Nota Técnica cadastrada.'); }
        redir('fiscal.php');
    }
    if ($acao === 'excluir') { $pdo->prepare("DELETE FROM notas_tecnicas WHERE id=?")->execute([post_int('id')]); flash('Nota Técnica excluída.'); redir('fiscal.php'); }
}

$editar = null;
if (!empty($_GET['editar'])) { $st = $pdo->prepare("SELECT * FROM notas_tecnicas WHERE id=?"); $st->execute([(int)$_GET['editar']]); $editar = $st->fetch() ?: null; }
$todas = $pdo->query("SELECT n.*, t.numero AS tarefa_numero FROM notas_tecnicas n LEFT JOIN tarefas t ON t.id=n.tarefa_id WHERE n.produto='" . produto() . "' ORDER BY n.status='aberta' DESC, n.producao")->fetchAll();
$tarefas = $pdo->query("SELECT id, numero, titulo FROM tarefas WHERE produto='" . produto() . "' ORDER BY numero DESC")->fetchAll();

admin_topo('Fiscal', 'fiscal');
?>
<section class="painel">
  <h2><?= $editar ? 'Editar ' . e($editar['codigo']) : 'Cadastrar Nota Técnica' ?></h2>
  <p class="ajuda">Consulte as publicações no Portal da NF-e (nfe.fazenda.gov.br) e cadastre as que podem exigir mudança no sistema. As datas em branco aparecem como "sem data".</p>
  <form method="post">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="id" value="<?= $editar['id'] ?? '' ?>">
    <div class="linha-campos">
      <label>Código<input name="codigo" required value="<?= e($editar['codigo'] ?? '') ?>" placeholder="NT 2026.006 v1.00"></label>
      <label>Assunto<input name="assunto" value="<?= e($editar['assunto'] ?? '') ?>" placeholder="vinculação de NF-e e NFC-e com pagamento do DF-e"></label>
    </div>
    <div class="linha-campos">
      <label>Homologação<input type="date" name="homologacao" value="<?= e($editar['homologacao'] ?? '') ?>"></label>
      <label>Produção<input type="date" name="producao" value="<?= e($editar['producao'] ?? '') ?>"></label>
      <label>Situação<select name="status">
        <option value="aberta" <?= ($editar['status'] ?? 'aberta') === 'aberta' ? 'selected' : '' ?>>em aberto</option>
        <option value="atendida" <?= ($editar['status'] ?? '') === 'atendida' ? 'selected' : '' ?>>atendida</option>
        <option value="nao_aplica" <?= ($editar['status'] ?? '') === 'nao_aplica' ? 'selected' : '' ?>>não exige mudança</option>
      </select></label>
      <label>Tarefa ligada<select name="tarefa_id"><option value="">— nenhuma —</option><?php foreach ($tarefas as $t): ?><option value="<?= $t['id'] ?>" <?= ($editar['tarefa_id'] ?? null) == $t['id'] ? 'selected' : '' ?>>#<?= (int)$t['numero'] ?> <?= e(mb_strimwidth($t['titulo'], 0, 50, '…')) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Observação <small>opcional, aparece abaixo do assunto</small><input name="observacao" value="<?= e($editar['observacao'] ?? '') ?>"></label>
    <div class="botoes">
      <button type="submit"><?= $editar ? 'Salvar' : 'Cadastrar' ?></button>
      <?php if ($editar): ?><a class="btn-sec" href="fiscal.php">Cancelar</a><?php endif; ?>
    </div>
  </form>
  <?php if ($editar): ?>
    <form method="post" class="excluir" data-confirmar="Excluir <?= e($editar['codigo']) ?>?">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $editar['id'] ?>">
      <button type="submit" class="btn-perigo">Excluir</button>
    </form>
  <?php endif; ?>
</section>

<section class="painel">
  <h2>Todas as Notas Técnicas</h2>
  <table class="adm-tabela">
    <thead><tr><th>Código</th><th>Assunto</th><th>Homolog.</th><th>Produção</th><th>Falta</th><th>Situação</th><th></th></tr></thead>
    <tbody>
      <?php if (!$todas): ?><tr><td colspan="7" class="ajuda">Nenhuma NT cadastrada.</td></tr><?php endif; ?>
      <?php foreach ($todas as $nt): $p = nt_prazo($nt); ?>
        <tr>
          <td class="nowrap"><b><?= e($nt['codigo']) ?></b><?php if ($nt['tarefa_numero']): ?> <small>#<?= (int)$nt['tarefa_numero'] ?></small><?php endif; ?></td>
          <td><?= e($nt['assunto']) ?></td>
          <td class="nowrap"><?= data_br($nt['homologacao']) ?></td>
          <td class="nowrap"><?= data_br($nt['producao']) ?></td>
          <td class="nowrap"><?= $p ? $p['etapa'] . ' · ' . ($p['dias'] < 0 ? 'venceu' : $p['dias'] . ' dias') : 'sem data' ?></td>
          <td class="nowrap"><?= ['aberta' => 'em aberto', 'atendida' => 'atendida', 'nao_aplica' => 'não exige'][$nt['status']] ?? $nt['status'] ?></td>
          <td class="nowrap"><a href="?editar=<?= $nt['id'] ?>">editar</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php admin_rodape();
