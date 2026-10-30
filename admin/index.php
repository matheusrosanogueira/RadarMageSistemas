<?php
require_once __DIR__ . '/_admin.php';
exigir_login();

$proxima = versao_proxima();
$prontas = alteracoes_proxima(true);
$abertas = tarefas_abertas();
$nts = nts_abertas();
$ultimas = alteracoes_recentes(10);

admin_topo('Início', 'inicio');
?>
<div class="adm-cards cinco">
  <a href="versoes.php"><b><?= $proxima ? e($proxima['numero']) : '—' ?></b><span>Próxima versão</span><small><?= $proxima ? 'em preparo' : 'crie uma em Versões' ?></small></a>
  <a href="alteracoes.php"><b><?= count($prontas) ?></b><span>Alterações no ciclo</span><small>vão na próxima</small></a>
  <a href="tarefas.php"><b><?= count($abertas) ?></b><span>Tarefas abertas</span><small><?= count(array_filter($abertas, fn($t) => $t['em_andamento'])) ?> em andamento</small></a>
  <a href="fiscal.php"><b><?= count($nts) ?></b><span>NTs em aberto</span><small>prazos da Sefaz</small></a>
  <?php $rel = lojadev_relatorio(); ?>
  <a href="config.php"><b><?= $rel && !empty($rel['em']) ? e(tempo_relativo($rel['em'])) : '—' ?></b><span>Fornecedor</span><small><?= !lojadev_ativo() ? 'sincronização desligada' : ($rel ? ($rel['ok'] ? 'sincronizado' : 'última falhou') : 'nunca sincronizou') ?></small></a>
</div>

<div class="adm-grid">
  <section class="painel">
    <h2>Lançar alterações</h2>
    <p class="ajuda">Uma por linha. É o jeito mais rápido de registrar o que foi feito hoje. Cada linha vira uma alteração com data e hora de agora. Você pode prefixar a área com dois-pontos: <code>NF-e 55: a devolução volta a numerar certo</code>.</p>
    <form method="post" action="alteracoes.php">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="lote">
      <textarea name="linhas" rows="7" placeholder="PDV: o cupom leva o ICMS retido&#10;Relatórios: a lista de produtos imprime o valor do estoque no alto&#10;Corrigido o erro ao cancelar O.S." required></textarea>
      <div class="linha-campos">
        <label>Área padrão (quando a linha não tiver)<input name="area" list="areas" placeholder="Outros assuntos"></label>
        <label>Vai na<select name="versao_id"><?= opcoes_versoes($proxima ? (int)$proxima['id'] : null) ?></select></label>
      </div>
      <?= datalist_areas() ?>
      <button type="submit">Registrar alterações</button>
    </form>
  </section>

  <section class="painel">
    <h2>Últimas registradas</h2>
    <?php if (!$ultimas): ?><p class="ajuda">Nada ainda.</p><?php endif; ?>
    <ul class="adm-lista">
      <?php foreach ($ultimas as $a): ?>
        <li><a href="alteracoes.php?editar=<?= $a['id'] ?>"><?= e($a['titulo']) ?></a><small><?= e($a['area'] ?: 'sem área') ?> · <?= tempo_relativo($a['criada_em']) ?></small></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<section class="painel">
  <h2>Como usar o Radar no dia a dia</h2>
  <ol class="passos">
    <li><b>Terminou algo no Mage Lite?</b> Lance a alteração aqui (uma linha) — ela aparece na hora em <i>Agora</i> e em <i>Vindo na próxima</i>.</li>
    <li><b>Assunto grande, que leva dias?</b> Abra uma <a href="tarefas.php">tarefa</a> numerada. Marque "em andamento" quando começar e encerre quando sair, dizendo em qual versão.</li>
    <li><b>Sefaz publicou uma NT?</b> Cadastre em <a href="fiscal.php">Fiscal</a> com as datas; o site calcula quantos dias faltam.</li>
    <li><b>Fechou o pacote?</b> Em <a href="versoes.php">Versões</a>, escreva as notas da versão e clique em <i>Publicar</i>: todas as alterações do ciclo passam para ela e o próximo ciclo começa vazio.</li>
  </ol>
</section>
<?php admin_rodape();
