<?php
require_once __DIR__ . '/_admin.php';
exigir_login();
$pdo = db();
$ufs = ufs_brasil();
$areas = areas_conhecidas();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_checar();
    $acao = post('acao');
    if ($acao === 'salvar') {
        $id = post_int('id');
        $mods = implode(', ', array_map('trim', (array)($_POST['modulos'] ?? [])));
        $uf = in_array(strtoupper(post('uf')), $ufs, true) ? strtoupper(post('uf')) : '';
        $versao = preg_match('/^\d+(\.\d+){0,3}$/', post('versao')) ? post('versao') : '';
        $dados = [post('nome'), $versao, $uf, $mods, post('email'), preg_replace('/\D+/', '', post('telefone')), post('avisar_versao') ? 1 : 0, post('avisar_sefaz') ? 1 : 0, post('observacao')];
        if (post('nome') === '') { flash('Informe o nome da loja.', 'erro'); redir('clientes.php'); }
        if ($id) { $pdo->prepare("UPDATE clientes SET nome=?, versao=?, uf=?, modulos=?, email=?, telefone=?, avisar_versao=?, avisar_sefaz=?, observacao=? WHERE id=?")->execute([...$dados, $id]); flash('Cliente salvo.'); }
        else { $chave = gerar_chave(); $pdo->prepare("INSERT INTO clientes(nome, versao, uf, modulos, email, telefone, avisar_versao, avisar_sefaz, observacao, chave) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([...$dados, $chave]); flash("Cliente cadastrado com a chave $chave."); }
        redir('clientes.php');
    }
    if ($acao === 'excluir') { $pdo->prepare("DELETE FROM clientes WHERE id=?")->execute([post_int('id')]); flash('Cliente excluído.'); redir('clientes.php'); }
    if ($acao === 'nova_chave') { $pdo->prepare("UPDATE clientes SET chave=? WHERE id=?")->execute([gerar_chave(), post_int('id')]); flash('Chave renovada. O link antigo deixou de funcionar.'); redir('clientes.php?editar=' . post_int('id')); }
    if ($acao === 'importar') {
        $n = 0;
        foreach (preg_split('/\r?\n/', post('linhas')) as $l) {
            $c = array_map('trim', str_getcsv($l, ';'));
            if (count($c) < 1 || $c[0] === '') continue;
            $uf = in_array(strtoupper($c[2] ?? ''), $ufs, true) ? strtoupper($c[2]) : '';
            $pdo->prepare("INSERT INTO clientes(nome, versao, uf, email, telefone, chave) VALUES (?,?,?,?,?,?)")->execute([$c[0], preg_match('/^\d+(\.\d+){0,3}$/', $c[1] ?? '') ? $c[1] : '', $uf, $c[3] ?? '', preg_replace('/\D+/', '', $c[4] ?? ''), gerar_chave()]);
            $n++;
        }
        flash("$n " . plural($n, 'cliente importado', 'clientes importados') . '.');
        redir('clientes.php');
    }
}

$editar = null;
if (!empty($_GET['editar'])) { $st = $pdo->prepare("SELECT * FROM clientes WHERE id=?"); $st->execute([(int)$_GET['editar']]); $editar = $st->fetch() ?: null; }
$busca = trim($_GET['q'] ?? '');
$st = $pdo->prepare("SELECT * FROM clientes WHERE nome LIKE ? OR chave LIKE ? OR uf LIKE ? ORDER BY nome"); $st->execute(["%$busca%", "%$busca%", "%$busca%"]); $lista = $st->fetchAll();
$total = (int)$pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$dist = distribuicao_versoes();
$atual = versao_ultima_publicada();
$base = url_base();
$modsEditar = cliente_modulos($editar);

admin_topo('Clientes', 'clientes');
?>
<div class="adm-grid">
<section class="painel">
  <h2><?= $editar ? 'Editar ' . e($editar['nome']) : 'Cadastrar cliente' ?></h2>
  <p class="ajuda">Cada cliente ganha uma chave e um link próprio. Abrindo o link, o Radar já sabe a loja, a versão e o estado dele, e marca o que ainda não chegou.</p>
  <form method="post">
    <?= csrf_campo() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= $editar['id'] ?? '' ?>">
    <label>Nome da loja<input name="nome" required value="<?= e($editar['nome'] ?? '') ?>"></label>
    <div class="linha-campos">
      <label>Versão instalada<input name="versao" pattern="[0-9]+(\.[0-9]+)*" value="<?= e($editar['versao'] ?? '') ?>" placeholder="<?= $atual ? e($atual['numero']) : '' ?>"></label>
      <label>UF<select name="uf"><option value="">—</option><?php foreach ($ufs as $u): ?><option <?= ($editar['uf'] ?? '') === $u ? 'selected' : '' ?>><?= $u ?></option><?php endforeach; ?></select></label>
    </div>
    <div class="linha-campos">
      <label>E-mail<input type="email" name="email" value="<?= e($editar['email'] ?? '') ?>"></label>
      <label>WhatsApp <small>com DDD</small><input name="telefone" value="<?= e($editar['telefone'] ?? '') ?>" placeholder="61999999999"></label>
    </div>
    <label>Módulos que a loja usa <small>o filtro "só o que me afeta" usa isto</small></label>
    <div class="caixas">
      <?php foreach ($areas as $a): ?><label class="check"><input type="checkbox" name="modulos[]" value="<?= e($a) ?>" <?= in_array($a, $modsEditar, true) ? 'checked' : '' ?>> <?= e($a) ?></label><?php endforeach; ?>
      <?php if (!$areas): ?><span class="ajuda">As áreas aparecem aqui depois da primeira sincronização.</span><?php endif; ?>
    </div>
    <div class="linha-campos">
      <label class="check"><input type="checkbox" name="avisar_versao" value="1" <?= !isset($editar) || !empty($editar['avisar_versao']) ? 'checked' : '' ?>> Avisar quando sair versão</label>
      <label class="check"><input type="checkbox" name="avisar_sefaz" value="1" <?= !empty($editar['avisar_sefaz']) ? 'checked' : '' ?>> Avisar quando a Sefaz do estado cair</label>
    </div>
    <label>Observação<input name="observacao" value="<?= e($editar['observacao'] ?? '') ?>"></label>
    <div class="botoes"><button type="submit"><?= $editar ? 'Salvar' : 'Cadastrar' ?></button><?php if ($editar): ?><a class="btn-sec" href="clientes.php">Cancelar</a><?php endif; ?></div>
  </form>
  <?php if ($editar): ?>
    <div class="destaque">
      <h3>Link da loja</h3>
      <textarea readonly rows="1" class="mono" onclick="this.select()"><?= e($base) ?>/c.php?k=<?= e($editar['chave']) ?></textarea>
      <p class="ajuda">Para o Mage Lite informar a versão sozinho, chame <code><?= e($base) ?>/c.php?k=<?= e($editar['chave']) ?>&amp;v=VERSAO</code> ou use a API (Configurações).<?php if ($editar['visto_em']): ?> Último acesso <?= tempo_relativo($editar['visto_em']) ?>.<?php endif; ?></p>
      <form method="post" data-confirmar="Gerar outra chave? O link atual deixa de funcionar."><?= csrf_campo() ?><input type="hidden" name="acao" value="nova_chave"><input type="hidden" name="id" value="<?= $editar['id'] ?>"><button class="btn-mini">renovar chave</button></form>
    </div>
    <form method="post" class="excluir" data-confirmar="Excluir <?= e($editar['nome']) ?>?"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $editar['id'] ?>"><button type="submit" class="btn-perigo">Excluir</button></form>
  <?php endif; ?>
</section>

<div>
<section class="painel">
  <h2>Distribuição por versão <span class="ajuda"><?= $total ?> <?= plural($total, 'cliente', 'clientes') ?></span></h2>
  <?php if (!$dist): ?><p class="ajuda">Nenhum cliente com versão informada.</p><?php else: ?>
    <?php $max = max(array_column($dist, 'n')); $comVersao = array_sum(array_column($dist, 'n')); ?>
    <ul class="dist">
      <?php foreach ($dist as $d): $atras = $atual && version_compare($d['versao'], $atual['numero'], '<'); ?>
        <li><span class="mono"><?= e($d['versao']) ?></span><i style="--w:<?= round($d['n'] / $max * 100) ?>%" class="<?= $atras ? 'atras' : '' ?>"></i><b><?= $d['n'] ?> <small>(<?= round($d['n'] / $comVersao * 100) ?>%)</small></b></li>
      <?php endforeach; ?>
    </ul>
    <?php $desat = array_sum(array_map(fn($d) => $atual && version_compare($d['versao'], $atual['numero'], '<') ? (int)$d['n'] : 0, $dist)); ?>
    <p class="ajuda"><?= $desat ?> de <?= $comVersao ?> ainda não estão na <?= $atual ? e($atual['numero']) : 'última' ?>. <?= $total - $comVersao ?> sem versão informada.</p>
  <?php endif; ?>
</section>

<section class="painel">
  <h2>Importar vários</h2>
  <p class="ajuda">Uma loja por linha: <code>nome;versão;UF;email;whatsapp</code> (só o nome é obrigatório). Cada uma recebe uma chave.</p>
  <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="importar"><textarea name="linhas" rows="4" placeholder="Mercado Silva;7.5.5;GO;contato@silva.com;62999990000"></textarea><button type="submit" class="btn-sec">Importar</button></form>
</section>
</div>
</div>

<section class="painel">
  <form method="get" class="filtros"><input name="q" value="<?= e($busca) ?>" placeholder="Buscar por nome, chave ou UF"><button class="btn-sec">Filtrar</button><span class="ajuda"><?= count($lista) ?></span></form>
  <table class="adm-tabela">
    <thead><tr><th>Loja</th><th>Chave</th><th>Versão</th><th>UF</th><th>Módulos</th><th>Avisos</th><th>Último acesso</th><th></th></tr></thead>
    <tbody>
      <?php if (!$lista): ?><tr><td colspan="8" class="ajuda">Nenhum cliente.</td></tr><?php endif; ?>
      <?php foreach ($lista as $c): $atras = $atual && $c['versao'] && version_compare($c['versao'], $atual['numero'], '<'); ?>
        <tr>
          <td><a href="?editar=<?= $c['id'] ?>"><?= e($c['nome']) ?></a></td>
          <td class="nowrap"><code><?= e($c['chave']) ?></code> <a href="../c.php?k=<?= e($c['chave']) ?>" target="_blank" title="abrir como o cliente">↗</a></td>
          <td class="nowrap"><?= $c['versao'] ? e($c['versao']) : '<span class="ajuda">—</span>' ?><?= $atras ? ' <i class="tag tag-bug">atrás</i>' : '' ?></td>
          <td><?= e($c['uf']) ?></td>
          <td><small><?= e($c['modulos']) ?></small></td>
          <td class="nowrap"><?= $c['avisar_versao'] ? '<i class="tag">versão</i>' : '' ?> <?= $c['avisar_sefaz'] ? '<i class="tag">sefaz</i>' : '' ?></td>
          <td class="nowrap"><?= $c['visto_em'] ? tempo_relativo($c['visto_em']) : 'nunca' ?></td>
          <td class="nowrap"><a href="?editar=<?= $c['id'] ?>">editar</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php admin_rodape();
