<?php
require_once __DIR__ . '/_admin.php';
exigir_login();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_checar();
    $acao = post('acao');
    if ($acao === 'config') {
        cfg_set('aviso_ativo', post('aviso_ativo') ? '1' : '0');
        cfg_set('aviso_versao_gatilho', post('aviso_versao_gatilho') === 'publicada' ? 'publicada' : 'liberada');
        foreach (['aviso_email_de', 'whats_url', 'whats_metodo', 'whats_corpo', 'whats_cabecalhos'] as $k) cfg_set($k, post($k));
        flash('Configuração de avisos salva.'); redir('avisos.php');
    }
    if ($acao === 'testar') {
        $canal = post('canal'); $dest = post('destino'); $e = null;
        $msg = 'Teste do ' . cfg('site_nome') . ' em ' . date('d/m/Y H:i') . '. Se chegou, os avisos estão funcionando.';
        $ok = $canal === 'email' ? aviso_email($dest, 'Teste do ' . cfg('site_nome'), $msg, $e) : aviso_whatsapp($dest, $msg, $e);
        flash($ok ? "Teste enviado por $canal para $dest." : "Falhou: $e", $ok ? 'ok' : 'erro'); redir('avisos.php');
    }
    if ($acao === 'reenviar') { $pdo->prepare("UPDATE avisos SET status='pendente', erro='' WHERE status='erro'")->execute(); flash('Avisos com erro voltaram para a fila.'); redir('avisos.php'); }
    if ($acao === 'enviar_agora') { set_time_limit(120); $g = avisos_gerar_versoes() + avisos_gerar_sefaz(); [$ok, $erro] = avisos_enviar(50); flash("$g enfileirados, $ok enviados" . ($erro ? ", $erro com erro" : '') . '.'); redir('avisos.php'); }
    if ($acao === 'limpar') { $pdo->exec("DELETE FROM avisos WHERE status <> 'pendente' AND criado_em < datetime('now','localtime','-30 days')"); flash('Histórico antigo apagado.'); redir('avisos.php'); }
}
$pend = (int)$pdo->query("SELECT COUNT(*) FROM avisos WHERE status='pendente'")->fetchColumn();
$lista = $pdo->query("SELECT a.*, c.nome FROM avisos a LEFT JOIN clientes c ON c.id=a.cliente_id ORDER BY a.id DESC LIMIT 150")->fetchAll();
admin_topo('Avisos', 'avisos');
?>
<div class="adm-grid">
<section class="painel">
  <h2>Como avisar</h2>
  <p class="ajuda">Quem recebe: os clientes com "avisar" marcado (Clientes). O cron gera e envia a cada 10 minutos. O aviso de versão vai só para quem está numa versão mais antiga.</p>
  <form method="post">
    <?= csrf_campo() ?><input type="hidden" name="acao" value="config">
    <label class="check"><input type="checkbox" name="aviso_ativo" value="1" <?= avisos_ativos() ? 'checked' : '' ?>> Avisos ligados</label>
    <label>Avisar versão nova quando<select name="aviso_versao_gatilho">
      <option value="liberada" <?= cfg('aviso_versao_gatilho', 'liberada') === 'liberada' ? 'selected' : '' ?>>a Mage marcar como liberada (ou com ressalva)</option>
      <option value="publicada" <?= cfg('aviso_versao_gatilho') === 'publicada' ? 'selected' : '' ?>>o fornecedor publicar</option>
    </select></label>
    <label>E-mail remetente <small>um endereço do seu domínio, ex.: radar@magesistemas.com.br</small><input type="email" name="aviso_email_de" value="<?= e(cfg('aviso_email_de')) ?>"></label>
    <h3 style="margin-top:14px">WhatsApp por webhook</h3>
    <p class="ajuda">Encaixa em qualquer API de WhatsApp (Evolution, Z-API, a do seu MageZap…). Substituições: <code>{telefone}</code>, <code>{mensagem}</code>, <code>{telefone_url}</code>, <code>{mensagem_url}</code>, <code>{mensagem_json}</code>.</p>
    <label>URL<input name="whats_url" value="<?= e(cfg('whats_url')) ?>" placeholder="https://api.exemplo.com/message/sendText/INSTANCIA"></label>
    <div class="linha-campos">
      <label>Método<select name="whats_metodo"><option <?= cfg('whats_metodo', 'POST') === 'POST' ? 'selected' : '' ?>>POST</option><option <?= cfg('whats_metodo') === 'GET' ? 'selected' : '' ?>>GET</option></select></label>
      <label>Cabeçalhos <small>um por linha</small><input name="whats_cabecalhos" value="<?= e(cfg('whats_cabecalhos')) ?>" placeholder="apikey: XXXX"></label>
    </div>
    <label>Corpo (POST, JSON)<textarea name="whats_corpo" rows="2" class="mono"><?= e(cfg('whats_corpo', '{"number":"55{telefone}","text":"{mensagem_json}"}')) ?></textarea></label>
    <button type="submit">Salvar</button>
  </form>
  <div class="destaque">
    <h3>Testar</h3>
    <form method="post" class="linha-campos"><?= csrf_campo() ?><input type="hidden" name="acao" value="testar">
      <label>Canal<select name="canal"><option value="email">e-mail</option><option value="whatsapp">WhatsApp</option></select></label>
      <label>Destino<input name="destino" required placeholder="email ou 61999999999"></label>
      <button type="submit" class="btn-sec">Enviar teste</button>
    </form>
  </div>
</section>
<section class="painel">
  <h2>Fila <span class="ajuda"><?= $pend ?> <?= plural($pend, 'pendente', 'pendentes') ?></span></h2>
  <div class="botoes">
    <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="enviar_agora"><button class="btn-sec">Gerar e enviar agora</button></form>
    <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="reenviar"><button class="btn-mini">reenviar os com erro</button></form>
    <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="limpar"><button class="btn-mini">limpar antigos</button></form>
  </div>
  <table class="adm-tabela" style="margin-top:12px">
    <thead><tr><th>Quando</th><th>Cliente</th><th>Tipo</th><th>Canal</th><th>Situação</th></tr></thead>
    <tbody>
      <?php if (!$lista): ?><tr><td colspan="5" class="ajuda">Nenhum aviso ainda.</td></tr><?php endif; ?>
      <?php foreach ($lista as $a): ?>
        <tr><td class="nowrap"><?= data_hora_br($a['criado_em']) ?></td><td><?= e($a['nome'] ?? $a['destino']) ?></td><td><?= e($a['tipo']) ?> <small><?= e($a['referencia']) ?></small></td><td><?= e($a['canal']) ?></td>
          <td><?= $a['status'] === 'enviado' ? '<i class="tag tag-melhoria">enviado</i>' : ($a['status'] === 'erro' ? '<i class="tag tag-bug" title="' . e($a['erro']) . '">erro</i> <small>' . e(mb_strimwidth($a['erro'], 0, 60, '…')) . '</small>' : '<i class="tag">pendente</i>') ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
</div>
<?php admin_rodape();
