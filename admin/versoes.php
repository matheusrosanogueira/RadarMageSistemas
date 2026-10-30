<?php
require_once __DIR__ . '/_admin.php';
exigir_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_checar();
    $acao = post('acao');

    if ($acao === 'salvar') {
        $id = post_int('id');
        $numero = post('numero');
        if (!preg_match('/^\d+(\.\d+){0,3}$/', $numero)) { flash('Número de versão inválido. Use o formato 1.2.3.', 'erro'); redir('versoes.php' . ($id ? "?editar=$id" : '')); }
        $pub = post('publicada') ? normaliza_dt(post('publicada_em'), true) : null;
        $dados = [$numero, post('codinome'), post('resumo'), post('notas'), post('como_atualizar'), $pub];
        try {
            if ($id) {
                $pdo->prepare("UPDATE versoes SET numero=?, codinome=?, resumo=?, notas=?, como_atualizar=?, publicada_em=? WHERE id=?")->execute([...$dados, $id]);
                flash("Versão $numero salva.");
            } else {
                $pdo->prepare("INSERT INTO versoes(numero, codinome, resumo, notas, como_atualizar, publicada_em, produto) VALUES (?,?,?,?,?,?,?)")->execute([...$dados, produto()]);
                $id = (int)$pdo->lastInsertId();
                flash("Versão $numero criada.");
            }
        } catch (PDOException $ex) {
            flash("Já existe uma versão $numero.", 'erro');
        }
        redir("versoes.php?editar=$id");
    }

    if ($acao === 'publicar') {
        $id = post_int('id');
        $v = versao_por_id($id);
        if ($v) {
            $pdo->beginTransaction();
            // tudo que estava sem versão passa para esta
            $pdo->prepare("UPDATE alteracoes SET versao_id=? WHERE produto=? AND versao_id IS NULL")->execute([$id, produto()]);
            $pdo->prepare("UPDATE tarefas SET versao_id=? WHERE produto=? AND status='encerrada' AND versao_id IS NULL AND encerrada_em >= ?")
                ->execute([$id, produto(), $v['criada_em']]);
            $pdo->prepare("UPDATE versoes SET publicada_em=? WHERE id=?")->execute([date('Y-m-d H:i:s'), $id]);
            // cria a próxima automaticamente, se pedido
            $prox = post('proxima_numero');
            if ($prox && preg_match('/^\d+(\.\d+){0,3}$/', $prox)) {
                $pdo->prepare("INSERT OR IGNORE INTO versoes(numero, produto) VALUES (?,?)")->execute([$prox, produto()]);
            }
            $pdo->commit();
            flash("Versão {$v['numero']} publicada. O ciclo seguinte já está aberto.");
        }
        redir('versoes.php');
    }

    if ($acao === 'excluir') {
        $id = post_int('id');
        $pdo->prepare("DELETE FROM versoes WHERE id=?")->execute([$id]);
        flash('Versão excluída. As alterações dela voltaram para o próximo ciclo.');
        redir('versoes.php');
    }
}

$editar = null;
if (!empty($_GET['editar'])) $editar = versao_por_id((int)$_GET['editar']);
$nova = isset($_GET['nova']);

// sugestão de próximo número
function sugerir_proxima(): string {
    $u = versao_atual(); if (!$u) return '1.0.0';
    $p = explode('.', $u['numero']); $p[count($p) - 1] = (string)((int)end($p) + 1);
    return implode('.', $p);
}

$versoes = versoes_todas();
admin_topo('Versões', 'versoes');
?>
<?php if ($editar || $nova): ?>
<section class="painel">
  <h2><?= $editar ? 'Editar versão ' . e($editar['numero']) : 'Nova versão' ?></h2>
  <form method="post">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="id" value="<?= $editar['id'] ?? '' ?>">
    <div class="linha-campos">
      <label>Número<input name="numero" required pattern="[0-9]+(\.[0-9]+)*" value="<?= e($editar['numero'] ?? sugerir_proxima()) ?>"></label>
      <label>Codinome <small>opcional, ex.: "DRE e Curva ABC"</small><input name="codinome" value="<?= e($editar['codinome'] ?? '') ?>"></label>
    </div>
    <label>Em uma linha <small>o resumo que abre a versão</small><input name="resumo" value="<?= e($editar['resumo'] ?? '') ?>" placeholder="o sistema passou a responder duas perguntas que não respondia…"></label>
    <label>Notas da versão <small>Markdown leve: <code>###</code> título, <code>**negrito**</code>, <code>*itálico*</code>, <code>- item</code>, <code>`código`</code>. Título com ⚠️ vira aviso destacado; com 🆕 🧾 🔧 ⚡ vira seção.</small>
      <textarea name="notas" rows="18" class="mono"><?= e($editar['notas'] ?? "### ⚠️ Leia antes de atualizar\n\n\n\n### 🆕 Novo\n\n- \n\n### 🧾 Fiscal\n\n- \n\n### 🔧 Corrigido\n\n- \n\n### ⚡ Desempenho\n\n- ") ?></textarea>
    </label>
    <label>Como atualizar <small>instruções de instalação, pré-requisitos</small><textarea name="como_atualizar" rows="4" class="mono"><?= e($editar['como_atualizar'] ?? '') ?></textarea></label>
    <div class="linha-campos">
      <label class="check"><input type="checkbox" name="publicada" value="1" <?= !empty($editar['publicada_em']) ? 'checked' : '' ?>> Publicada</label>
      <label>Data de publicação<input type="datetime-local" name="publicada_em" value="<?= !empty($editar['publicada_em']) ? e(str_replace(' ', 'T', substr($editar['publicada_em'], 0, 16))) : '' ?>"></label>
    </div>
    <div class="botoes">
      <button type="submit">Salvar</button>
      <a class="btn-sec" href="versoes.php">Voltar</a>
      <?php if ($editar): ?><a class="btn-sec" href="../versoes.php#v<?= $editar['id'] ?>" target="_blank">Ver no site</a><?php endif; ?>
    </div>
  </form>

  <?php if ($editar && !$editar['publicada_em']): ?>
    <div class="destaque">
      <h3>Publicar a <?= e($editar['numero']) ?></h3>
      <p class="ajuda">Fecha o ciclo: toda alteração ainda sem versão passa para a <?= e($editar['numero']) ?>, as tarefas encerradas desde a criação dela ganham "saiu na <?= e($editar['numero']) ?>", e a data de publicação vira agora. Salve as notas antes.</p>
      <form method="post" data-confirmar="Publicar a <?= e($editar['numero']) ?> e fechar o ciclo?">
        <?= csrf_campo() ?><input type="hidden" name="acao" value="publicar"><input type="hidden" name="id" value="<?= $editar['id'] ?>">
        <div class="linha-campos">
          <label>Já abrir a próxima como<input name="proxima_numero" value="<?= e(sugerir_proxima()) ?>" pattern="[0-9]+(\.[0-9]+)*"></label>
          <button type="submit">Publicar agora</button>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($editar): ?>
    <form method="post" class="excluir" data-confirmar="Excluir a versão <?= e($editar['numero']) ?>? As alterações dela voltam para o próximo ciclo.">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $editar['id'] ?>">
      <button type="submit" class="btn-perigo">Excluir versão</button>
    </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="painel">
  <div class="painel-cab"><h2>Todas as versões</h2><a class="btn" href="?nova">Nova versão</a></div>
  <table class="adm-tabela">
    <thead><tr><th>Versão</th><th>Codinome</th><th>Alterações</th><th>Situação</th><th></th></tr></thead>
    <tbody>
      <?php if (!$versoes): ?><tr><td colspan="5" class="ajuda">Nenhuma versão. Crie a primeira — normalmente a que está em preparo, sem marcar como publicada.</td></tr><?php endif; ?>
      <?php foreach ($versoes as $v):
        $n = $pdo->prepare("SELECT COUNT(*) FROM alteracoes WHERE versao_id=?"); $n->execute([$v['id']]); $n = (int)$n->fetchColumn(); ?>
        <tr>
          <td><b><?= e($v['numero']) ?></b></td>
          <td><?= e($v['codinome']) ?></td>
          <td><?= $n ?></td>
          <td><?= $v['publicada_em'] ? 'publicada em ' . data_br($v['publicada_em'], true) : '<i class="tag tag-prox">próxima</i>' ?></td>
          <td class="nowrap"><a href="?editar=<?= $v['id'] ?>">editar</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php admin_rodape();
