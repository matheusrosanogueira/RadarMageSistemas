<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

function layout_topo(string $titulo, string $pagina): void {
    $atual = versao_atual();
    $abertas = count(tarefas_abertas());
    $nts = count(nts_abertas());
    $minha = versao_visitante();
    $site = cfg('site_nome');
    $nav = [
        'index'     => ['Visão geral', 'index.php', null],
        'agora'     => ['Agora', 'agora.php', null],
        'versoes'   => ['Versões', 'versoes.php', null],
        'planejado' => ['Planejado', 'planejado.php', $abertas],
        'fiscal'    => ['Fiscal', 'fiscal.php', $nts],
        'projeto'   => ['O projeto', 'projeto.php', null],
    ];
    $ultimaAlt = db()->prepare("SELECT MAX(criada_em) FROM alteracoes WHERE produto = ?");
    $ultimaAlt->execute([produto()]);
    $atualizadoEm = $ultimaAlt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titulo) ?> · <?= e($site) ?></title>
<meta name="description" content="<?= e(cfg('descricao')) ?>">
<meta name="theme-color" content="#0A0718">
<meta property="og:title" content="<?= e($titulo) ?> · <?= e($site) ?>">
<meta property="og:description" content="<?= e(cfg('descricao')) ?>">
<meta property="og:site_name" content="<?= e($site) ?>">
<meta property="og:locale" content="pt_BR">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=1">
</head>
<body data-pagina="<?= e($pagina) ?>">
<a class="pular" href="#conteudo">Ir para o conteúdo</a>

<header class="topo">
  <div class="topo-linha">
    <a class="marca" href="index.php" aria-label="<?= e($site) ?>">
      <img src="assets/logo.svg" alt="" width="34" height="34">
      <span><?= e($site) ?></span>
    </a>
    <button class="menu-btn" aria-expanded="false" aria-controls="nav" aria-label="Abrir menu">
      <span></span><span></span><span></span>
    </button>
    <nav class="nav" id="nav">
      <?php foreach ($nav as $chave => [$rotulo, $href, $badge]): ?>
        <a href="<?= $href ?>" <?= $pagina === $chave ? 'aria-current="page"' : '' ?>><?= $rotulo ?><?php if ($badge): ?><b><?= $badge ?></b><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="instalacao">
    <div class="instalacao-texto">
      <strong>A sua instalação</strong>
      <span>Diga sua versão e o Radar passa a marcar o que ainda não chegou até você.</span>
    </div>
    <form class="instalacao-form" id="form-versao" autocomplete="off">
      <label class="visually-hidden" for="minha-versao">Sua versão do <?= e(cfg('sistema_nome')) ?></label>
      <input id="minha-versao" name="v" inputmode="decimal" pattern="[0-9]+(\.[0-9]+)*" placeholder="<?= $atual ? e($atual['numero']) : '1.0.0' ?>" value="<?= e($minha) ?>">
      <button type="submit"><?= $minha ? 'Trocar' : 'Marcar' ?></button>
      <?php if ($minha): ?><button type="button" class="limpar" id="limpar-versao" title="Esquecer minha versão">esquecer</button><?php endif; ?>
    </form>
    <div class="instalacao-atual">
      <?php if ($atual): ?>
        <b><?= e($atual['numero']) ?></b> é a versão atual
        <?php if ($atualizadoEm): ?><small>atualizado <?= data_hora_br($atualizadoEm) ?></small><?php endif; ?>
      <?php else: ?>
        <small>nenhuma versão cadastrada ainda</small>
      <?php endif; ?>
    </div>
  </div>
</header>

<main id="conteudo" class="conteudo">
<?php
}

function layout_rodape(): void {
?>
</main>
<footer class="rodape">
  <div>
    <?= e(cfg('site_nome')) ?> · <?= e(cfg('empresa')) ?>
    <?php if (cfg('site_url')): ?> · <a href="<?= e(cfg('site_url')) ?>"><?= e(preg_replace('#^https?://#', '', cfg('site_url'))) ?></a><?php endif; ?>
  </div>
  <div><a href="admin/">Área administrativa</a></div>
</footer>
<script src="assets/app.js?v=1"></script>
</body>
</html>
<?php
}

/** Título de página com subtítulo */
function titulo_pagina(string $t, string $sub = ''): void {
    echo '<div class="cabecalho"><h1>' . e($t) . '</h1>';
    if ($sub) echo '<p>' . e($sub) . '</p>';
    echo '</div>';
}

/** Selo "ainda não chegou" para versão */
function selo_falta(array $versao): string {
    return falta_para_visitante($versao) ? '<span class="selo selo-falta" title="Ainda não chegou na sua instalação">ainda não chegou</span>' : '';
}
