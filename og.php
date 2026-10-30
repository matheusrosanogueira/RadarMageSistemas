<?php
/**
 * Imagem de prévia (Open Graph) gerada na hora: aparece quando um link do Radar
 * é colado no WhatsApp, Telegram etc.  Parâmetros: t (título), s (subtítulo), v (versão em destaque)
 */
require_once __DIR__ . '/inc/functions.php';
$t = mb_substr(trim($_GET['t'] ?? cfg('site_nome')), 0, 70);
$s = mb_substr(trim($_GET['s'] ?? ''), 0, 110);
$v = mb_substr(trim($_GET['v'] ?? ''), 0, 12);
$chave = md5($t . '|' . $s . '|' . $v);
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header('ETag: "' . $chave . '"');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === '"' . $chave . '"') { http_response_code(304); exit; }

$cache = RADAR_ROOT . '/data/og-' . $chave . '.png';
if (is_file($cache) && filemtime($cache) > time() - 86400 * 7) { readfile($cache); exit; }

$W = 1200; $H = 630;
$im = imagecreatetruecolor($W, $H);
$bg = imagecolorallocate($im, 10, 7, 24); imagefill($im, 0, 0, $bg);
// faixa degradê violeta → magenta no topo
for ($x = 0; $x < $W; $x++) {
    $r = (int)(124 + (226 - 124) * $x / $W); $g = (int)(92 + (58 - 92) * $x / $W); $b = (int)(255 + (157 - 255) * $x / $W);
    imageline($im, $x, 0, $x, 10, imagecolorallocate($im, $r, $g, $b));
}
$branco = imagecolorallocate($im, 236, 233, 248); $cinza = imagecolorallocate($im, 169, 163, 198); $violet = imagecolorallocate($im, 124, 92, 255); $magenta = imagecolorallocate($im, 226, 58, 157);
$fb = __DIR__ . '/assets/fontes/Inter-ExtraBold.otf'; $fr = __DIR__ . '/assets/fontes/Inter-Regular.otf';
if (is_file(__DIR__ . '/assets/logo.png')) { $logo = imagecreatefrompng(__DIR__ . '/assets/logo.png'); imagecopyresampled($im, $logo, 72, 60, 0, 0, 72, 72, imagesx($logo), imagesy($logo)); }
imagettftext($im, 30, 0, 164, 108, $branco, $fb, cfg('site_nome'));
imagettftext($im, 18, 0, 164, 140, $cinza, $fr, cfg('empresa') . ' · ' . cfg('sistema_nome'));

$y = 300;
if ($v !== '') { imagettftext($im, 150, 0, 68, 400, $branco, $fb, $v); $y = 470; }
// título com quebra de linha
$tam = $v !== '' ? 34 : 54; $largMax = $W - 140; $linhas = []; $atual = '';
foreach (preg_split('/\s+/u', $t) as $palavra) {
    $teste = trim($atual . ' ' . $palavra); $box = imagettfbbox($tam, 0, $fb, $teste);
    if ($box[2] - $box[0] > $largMax && $atual !== '') { $linhas[] = $atual; $atual = $palavra; } else $atual = $teste;
}
if ($atual !== '') $linhas[] = $atual;
foreach (array_slice($linhas, 0, 3) as $l) { imagettftext($im, $tam, 0, 70, $y, $v !== '' ? $cinza : $branco, $fb, $l); $y += $tam * 1.35; }
if ($s !== '') {
    $linhas = []; $atual = '';
    foreach (preg_split('/\s+/u', $s) as $palavra) { $teste = trim($atual . ' ' . $palavra); $box = imagettfbbox(22, 0, $fr, $teste); if ($box[2] - $box[0] > $largMax && $atual !== '') { $linhas[] = $atual; $atual = $palavra; } else $atual = $teste; }
    if ($atual !== '') $linhas[] = $atual;
    $y += 14;
    foreach (array_slice($linhas, 0, 2) as $l) { imagettftext($im, 22, 0, 70, $y, $cinza, $fr, $l); $y += 34; }
}
imagettftext($im, 16, 0, 70, $H - 44, $violet, $fr, preg_replace('#^https?://#', '', url_base()));
ob_start(); imagepng($im, null, 6); $png = ob_get_clean(); imagedestroy($im);
@file_put_contents($cache, $png);
echo $png;
