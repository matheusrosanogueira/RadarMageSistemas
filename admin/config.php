<?php
require_once __DIR__ . '/_admin.php';
exigir_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_checar();
    $acao = post('acao');

    if ($acao === 'geral') {
        foreach (['site_nome', 'sistema_nome', 'empresa', 'site_url', 'descricao', 'projeto_texto'] as $k) cfg_set($k, post($k));
        flash('Configurações salvas.');
        redir('config.php');
    }

    if ($acao === 'senha') {
        if (!password_verify(post('atual'), cfg('admin_senha'))) flash('A senha atual não confere.', 'erro');
        elseif (mb_strlen(post('nova')) < 8) flash('A nova senha precisa ter pelo menos 8 caracteres.', 'erro');
        elseif (post('nova') !== post('nova2')) flash('As duas senhas novas não conferem.', 'erro');
        else {
            cfg_set('admin_senha', password_hash(post('nova'), PASSWORD_DEFAULT));
            if (post('usuario')) cfg_set('admin_usuario', post('usuario'));
            flash('Senha alterada.');
        }
        redir('config.php');
    }

    if ($acao === 'sefaz') {
        cfg_set('sefaz_ativo', post('sefaz_ativo') ? '1' : '0');
        flash('Configuração da Sefaz salva.');
        redir('config.php');
    }
    if ($acao === 'sefaz_testar') {
        $ini = microtime(true);
        $s = sefaz_situacao(true);
        $r = sefaz_resumo($s);
        $ms = round((microtime(true) - $ini) * 1000);
        if ($s && $s['autorizadores']) flash("Sefaz lida em {$ms} ms: {$r['no_ar']} de {$r['total']} estados no ar, " . count($s['autorizadores']) . ' autorizadores.');
        else flash('Não consegui ler o portal: ' . ($s['erro'] ?? 'sem detalhe') . '. Verifique se a hospedagem permite conexões de saída (cURL) para nfe.fazenda.gov.br.', 'erro');
        redir('config.php');
    }
    if ($acao === 'sefaz_token') { cfg_set('sefaz_token', bin2hex(random_bytes(12))); flash('Token renovado. Atualize o cron.'); redir('config.php'); }

    if ($acao === 'restaurar' && !empty($_FILES['arquivo']['tmp_name'])) {
        $tmp = $_FILES['arquivo']['tmp_name'];
        $ok = false;
        try { $t = new PDO('sqlite:' . $tmp); $t->query("SELECT COUNT(*) FROM versoes"); $t->query("SELECT COUNT(*) FROM config"); $ok = true; } catch (Throwable) {}
        if ($ok) {
            $pdo = null; // fecha conexão
            copy(RADAR_DB, RADAR_DB . '.antes-restauracao-' . date('Ymd-His'));
            @unlink(RADAR_DB . '-wal'); @unlink(RADAR_DB . '-shm');
            move_uploaded_file($tmp, RADAR_DB);
            flash('Banco restaurado. Uma cópia do anterior ficou em data/.');
        } else flash('O arquivo não é um banco válido do Radar.', 'erro');
        redir('config.php');
    }
}

if (isset($_GET['backup'])) {
    $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="radar-' . date('Y-m-d') . '.db"');
    readfile(RADAR_DB); exit;
}

if (isset($_GET['exportar'])) {
    $dump = [];
    foreach (['versoes', 'alteracoes', 'tarefas', 'notas_tecnicas'] as $t) $dump[$t] = $pdo->query("SELECT * FROM $t")->fetchAll();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="radar-' . date('Y-m-d') . '.json"');
    echo json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); exit;
}

admin_topo('Configurações', 'config');
?>
<div class="adm-grid">
<section class="painel">
  <h2>Site</h2>
  <form method="post">
    <?= csrf_campo() ?><input type="hidden" name="acao" value="geral">
    <div class="linha-campos">
      <label>Nome do Radar<input name="site_nome" value="<?= e(cfg('site_nome')) ?>"></label>
      <label>Nome do sistema<input name="sistema_nome" value="<?= e(cfg('sistema_nome')) ?>"></label>
    </div>
    <div class="linha-campos">
      <label>Empresa<input name="empresa" value="<?= e(cfg('empresa')) ?>"></label>
      <label>Site da empresa<input name="site_url" value="<?= e(cfg('site_url')) ?>"></label>
    </div>
    <label>Descrição <small>aparece nos buscadores e ao compartilhar o link</small><textarea name="descricao" rows="2"><?= e(cfg('descricao')) ?></textarea></label>
    <label>Página "O projeto" <small>Markdown leve</small><textarea name="projeto_texto" rows="14" class="mono"><?= e(cfg('projeto_texto')) ?></textarea></label>
    <button type="submit">Salvar</button>
  </form>
</section>

<div>
<section class="painel">
  <h2>Acesso</h2>
  <form method="post">
    <?= csrf_campo() ?><input type="hidden" name="acao" value="senha">
    <label>Usuário<input name="usuario" value="<?= e(cfg('admin_usuario')) ?>" autocomplete="username"></label>
    <label>Senha atual<input type="password" name="atual" required autocomplete="current-password"></label>
    <label>Nova senha<input type="password" name="nova" required minlength="8" autocomplete="new-password"></label>
    <label>Repita a nova senha<input type="password" name="nova2" required minlength="8" autocomplete="new-password"></label>
    <button type="submit">Alterar senha</button>
  </form>
</section>

<section class="painel">
  <h2>Sefaz</h2>
  <?php $sc = sefaz_cache(); $host = preg_replace('#^https?://#', '', rtrim(cfg('site_url'), '/')); $urlCron = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'radar.magesistemas.com.br') . dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/sefaz-atualizar.php?token=' . sefaz_token(); $urlCron = str_replace('//sefaz', '/sefaz', $urlCron); ?>
  <p class="ajuda">O Radar lê a página de disponibilidade do Portal da NF-e e mostra quais autorizadores estão no ar. A leitura é renovada quando alguém visita o site e o cache tem mais de <?= SEFAZ_CACHE_MIN ?> minutos<?php if ($sc && !empty($sc['em'])): ?> — última leitura <?= tempo_relativo($sc['em']) ?><?= !empty($sc['erro']) ? ' (última tentativa falhou: ' . e($sc['erro']) . ')' : '' ?><?php endif; ?>.</p>
  <form method="post" class="botoes">
    <?= csrf_campo() ?><input type="hidden" name="acao" value="sefaz">
    <label class="check" style="margin:0"><input type="checkbox" name="sefaz_ativo" value="1" <?= sefaz_ativo() ? 'checked' : '' ?>> Mostrar a situação da Sefaz no site</label>
    <button type="submit" class="btn-sec">Salvar</button>
  </form>
  <form method="post" style="margin-top:12px">
    <?= csrf_campo() ?><input type="hidden" name="acao" value="sefaz_testar">
    <button type="submit">Ler a Sefaz agora</button>
  </form>
  <p class="ajuda" style="margin-top:14px"><b>Para não depender das visitas</b>, agende no CPanel (Cron Jobs) a cada 10 minutos:</p>
  <textarea readonly rows="2" class="mono" onclick="this.select()">curl -s "<?= e($urlCron) ?>" > /dev/null</textarea>
  <form method="post" data-confirmar="Gerar um token novo? O cron atual vai parar de funcionar até ser atualizado." style="margin-top:8px">
    <?= csrf_campo() ?><input type="hidden" name="acao" value="sefaz_token">
    <button type="submit" class="btn-mini">renovar token</button>
  </form>
</section>

<section class="painel">
  <h2>Backup</h2>
  <p class="ajuda">Todo o Radar mora em um arquivo: <code>data/radar.db</code>. Baixe uma cópia de vez em quando.</p>
  <div class="botoes">
    <a class="btn" href="?backup=1">Baixar banco (.db)</a>
    <a class="btn-sec" href="?exportar=1">Exportar dados (.json)</a>
  </div>
  <form method="post" enctype="multipart/form-data" class="restaurar" data-confirmar="Substituir o banco atual pelo arquivo enviado?">
    <?= csrf_campo() ?><input type="hidden" name="acao" value="restaurar">
    <label>Restaurar a partir de um .db<input type="file" name="arquivo" accept=".db" required></label>
    <button type="submit" class="btn-sec">Restaurar</button>
  </form>
</section>
</div>
</div>
<?php admin_rodape();
