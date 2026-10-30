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
    $sefazSit = sefaz_ativo() ? sefaz_situacao() : null;
    $sefazFrase = $sefazSit ? sefaz_frase($sefazSit) : '';
    $sefazClasse = $sefazFrase ? (str_contains($sefazFrase, 'fora') ? 'sefaz-fora' : (str_contains($sefazFrase, 'inst') ? 'sefaz-inst' : 'sefaz-ok')) : '';
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
        <?php if ($sefazFrase): ?><a class="sefaz-frase <?= $sefazClasse ?>" href="fiscal.php#sefaz"><?= e($sefazFrase) ?></a><?php endif; ?>
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
    return falta_para_visitante($versao) ? '<span class="selo selo-falta" title="Ainda não chegou na sua instalação">novo para você</span>' : '';
}

/** Bloco "Sefaz por estado ao vivo": grade das 27 UFs com legenda */
function bloco_sefaz_estados(?array $sit, bool $compacto = false): void {
    if (!sefaz_ativo()) return;
    $estados = sefaz_estados($sit);
    $r = sefaz_resumo($sit);
    $lido = $sit['em'] ?? null;
    $fonte = ($sit['fonte'] ?? 'portal') === 'fornecedor' ? ' · pela leitura do fornecedor' : '';
    $falhas = (int)($sit['falhas_seguidas'] ?? 0);
    ?>
    <section class="bloco sefaz-bloco" id="sefaz">
      <div class="bloco-cab">
        <h2>Sefaz por estado <span class="ao-vivo">ao vivo</span></h2>
        <span class="contagem"><?php if ($lido): ?>verificou <?= data_hora_br($lido) ?><?= $fonte ?><?php else: ?>sem leitura<?php endif; ?></span>
      </div>
      <?php if (!$estados): ?>
        <div class="sefaz-erro">Ainda não foi possível ler a situação da Sefaz<?= !empty($sit['erro']) ? ': ' . e($sit['erro']) : '' ?>. A próxima visita tenta de novo.</div>
      <?php else: ?>
        <?php if ($falhas > 0): ?><div class="sefaz-erro">A leitura está parada há <?= $falhas ?> <?= plural($falhas, 'tentativa', 'tentativas') ?>: o portal e o fornecedor não respondem. O que está abaixo é a última leitura boa, de <?= data_hora_br($lido) ?>.</div><?php endif; ?>
        <p class="sefaz-titulo <?= $r['fora'] ? 'fora' : ($r['instaveis'] ? 'inst' : 'ok') ?>">
          <?php if ($r['fora']): ?><?= count($r['fora']) ?> <?= plural(count($r['fora']), 'estado fora do ar', 'estados fora do ar') ?><?= $r['instaveis'] ? ', ' . count($r['instaveis']) . ' ' . plural(count($r['instaveis']), 'instável', 'instáveis') : '' ?>
          <?php elseif ($r['instaveis']): ?><?= count($r['instaveis']) ?> <?= plural(count($r['instaveis']), 'estado instável', 'estados instáveis') ?>, <?= $r['no_ar'] ?> no ar
          <?php else: ?>Os <?= $r['no_ar'] ?> estados no ar<?php endif; ?>
        </p>
        <div class="uf-grade">
          <?php foreach ($estados as $uf => $x): ?>
            <span class="uf uf-<?= $x['situacao'] ?>" title="<?= e($uf . ' · ' . $x['autorizador'] . ' · ' . ['no_ar' => 'no ar', 'instavel' => 'instável', 'fora' => 'fora do ar'][$x['situacao']] . ($x['desde'] ? ' desde ' . data_hora_br($x['desde']) : '')) ?>"><?= $uf ?></span>
          <?php endforeach; ?>
        </div>
        <div class="sefaz-legenda"><span><i class="bola bola-verde"></i>no ar</span><span><i class="bola bola-amarela"></i>instável — some ou piora em 10 min</span><span><i class="bola bola-vermelha"></i>fora — falhas seguidas</span></div>
        <?php if ($compacto): ?><p class="sefaz-meta"><a href="fiscal.php#sefaz">ver serviço a serviço, por autorizador</a></p><?php endif; ?>
      <?php endif; ?>
    </section>
    <?php
}
