<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/functions.php';

session_name('radar_admin');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

/* ---------- primeiro acesso: define a senha ---------- */
if (cfg('admin_senha') === '' && basename($_SERVER['SCRIPT_NAME']) !== 'login.php') {
    header('Location: login.php'); exit;
}

function logado(): bool { return !empty($_SESSION['radar_admin_ok']); }

function exigir_login(): void {
    if (!logado()) { header('Location: login.php'); exit; }
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_campo(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function csrf_checar(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf(), $_POST['csrf'] ?? '')) {
        http_response_code(403); exit('Sessão expirada. Volte e tente de novo.');
    }
}

function flash(string $msg, string $tipo = 'ok'): void { $_SESSION['flash'] = [$msg, $tipo]; }
function flash_pegar(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

function redir(string $url): never { header('Location: ' . $url); exit; }

function post(string $k, string $padrao = ''): string { return trim((string)($_POST[$k] ?? $padrao)); }
function post_int(string $k): ?int { $v = post($k); return $v === '' ? null : (int)$v; }
function post_data(string $k): ?string { $v = post($k); return $v === '' ? null : $v; }

/** data vinda de <input type=date> ou datetime-local -> 'Y-m-d H:i:s' */
function normaliza_dt(?string $v, bool $agora_se_vazio = false): ?string {
    if (!$v) return $agora_se_vazio ? date('Y-m-d H:i:s') : null;
    $d = dt(str_replace('T', ' ', $v));
    return $d ? $d->format('Y-m-d H:i:s') : null;
}

/* ---------- layout ---------- */
function admin_topo(string $titulo, string $pagina): void {
    $menu = [
        'inicio'     => ['Início', 'index.php'],
        'alteracoes' => ['Alterações', 'alteracoes.php'],
        'versoes'    => ['Versões', 'versoes.php'],
        'tarefas'    => ['Tarefas', 'tarefas.php'],
        'fiscal'     => ['Fiscal', 'fiscal.php'],
        'config'     => ['Configurações', 'config.php'],
    ];
    $flash = flash_pegar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> · Admin · <?= e(cfg('site_nome')) ?></title>
<meta name="robots" content="noindex">
<link rel="icon" href="../assets/favicon.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/admin.css?v=1">
</head>
<body>
<header class="adm-topo">
  <a class="adm-marca" href="index.php"><img src="../assets/logo.png" alt="" width="26" height="26"><span><?= e(cfg('site_nome')) ?></span><small>admin</small></a>
  <nav class="adm-nav">
    <?php foreach ($menu as $k => [$r, $h]): ?><a href="<?= $h ?>" <?= $pagina === $k ? 'aria-current="page"' : '' ?>><?= $r ?></a><?php endforeach; ?>
  </nav>
  <div class="adm-acoes"><a href="../" target="_blank">Ver o site</a><a href="logout.php">Sair</a></div>
</header>
<main class="adm-main">
<?php if ($flash): ?><div class="flash flash-<?= e($flash[1]) ?>"><?= e($flash[0]) ?></div><?php endif; ?>
<h1><?= e($titulo) ?></h1>
<?php
}

function admin_rodape(): void {
?>
</main>
<script src="../assets/admin.js?v=1"></script>
</body>
</html>
<?php
}

function opcoes_versoes(?int $sel, bool $com_vazio = true, string $vazio = 'Próximo ciclo (sem versão)'): string {
    $h = $com_vazio ? '<option value="">' . e($vazio) . '</option>' : '';
    foreach (versoes_todas() as $v) {
        $h .= '<option value="' . $v['id'] . '"' . ($sel === (int)$v['id'] ? ' selected' : '') . '>' . e($v['numero']) . ($v['publicada_em'] ? '' : ' (próxima)') . '</option>';
    }
    return $h;
}

function datalist_areas(): string {
    $h = '<datalist id="areas">';
    foreach (areas_conhecidas() as $a) $h .= '<option value="' . e($a) . '">';
    return $h . '</datalist>';
}
