<?php
require_once __DIR__ . '/_admin.php';

$primeiro = cfg('admin_senha') === '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_checar();
    if ($primeiro) {
        $u = post('usuario') ?: 'admin'; $s = post('senha'); $s2 = post('senha2');
        if (mb_strlen($s) < 8) $erro = 'A senha precisa ter pelo menos 8 caracteres.';
        elseif ($s !== $s2) $erro = 'As duas senhas não conferem.';
        else {
            cfg_set('admin_usuario', $u);
            cfg_set('admin_senha', password_hash($s, PASSWORD_DEFAULT));
            $_SESSION['radar_admin_ok'] = true;
            flash('Senha definida. Bem-vindo ao Radar.');
            redir('index.php');
        }
    } else {
        // atraso simples contra força bruta
        usleep(400000);
        if (hash_equals(cfg('admin_usuario'), post('usuario')) && password_verify(post('senha'), cfg('admin_senha'))) {
            session_regenerate_id(true);
            $_SESSION['radar_admin_ok'] = true;
            redir('index.php');
        }
        $erro = 'Usuário ou senha incorretos.';
    }
}
if (logado() && !$primeiro) redir('index.php');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · <?= e(cfg('site_nome')) ?></title>
<meta name="robots" content="noindex">
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/admin.css?v=1">
</head>
<body class="login-body">
<form method="post" class="login">
  <?= csrf_campo() ?>
  <img src="../assets/logo.svg" alt="" width="44" height="44">
  <h1><?= $primeiro ? 'Primeiro acesso' : 'Entrar no painel' ?></h1>
  <?php if ($primeiro): ?><p class="ajuda">Defina o usuário e a senha que vão administrar o Radar. Isso só acontece uma vez.</p><?php endif; ?>
  <?php if ($erro): ?><div class="flash flash-erro"><?= e($erro) ?></div><?php endif; ?>
  <label>Usuário<input name="usuario" value="<?= $primeiro ? 'admin' : e(post('usuario')) ?>" autocomplete="username" required></label>
  <label>Senha<input type="password" name="senha" autocomplete="<?= $primeiro ? 'new-password' : 'current-password' ?>" required minlength="<?= $primeiro ? 8 : 1 ?>"></label>
  <?php if ($primeiro): ?><label>Repita a senha<input type="password" name="senha2" autocomplete="new-password" required minlength="8"></label><?php endif; ?>
  <button type="submit"><?= $primeiro ? 'Definir senha e entrar' : 'Entrar' ?></button>
  <a class="voltar" href="../">← voltar ao site</a>
</form>
</body>
</html>
