<?php
/* Diagnóstico do Radar Mage — escrito para rodar em qualquer versão do PHP (sem recursos novos). */
header('Content-Type: text/plain; charset=utf-8');
echo "RADAR MAGE — DIAGNOSTICO\n========================\n\n";
echo "PHP: " . PHP_VERSION . (version_compare(PHP_VERSION, '8.1.0', '>=') ? "  OK" : "  <<< PRECISA SER 8.1 OU SUPERIOR (cPanel > Selecionar versao do PHP)") . "\n";
foreach (array('pdo_sqlite', 'sqlite3', 'mbstring', 'curl', 'json') as $ext) {
    echo "extensao $ext: " . (extension_loaded($ext) ? "OK" : "<<< FALTANDO (marque em Selecionar versao do PHP > Extensoes)") . "\n";
}
$data = __DIR__ . '/data';
echo "\npasta data/: " . (is_dir($data) ? "existe" : "<<< NAO EXISTE") . "\n";
if (is_dir($data)) {
    echo "  gravavel: " . (is_writable($data) ? "OK" : "<<< NAO (Gerenciador de arquivos > data > Alterar permissoes > 775)") . "\n";
    echo "  permissao: " . substr(sprintf('%o', fileperms($data)), -4) . "\n";
    $t = @fopen($data . '/.teste', 'w'); if ($t) { fclose($t); @unlink($data . '/.teste'); echo "  teste de escrita: OK\n"; } else echo "  teste de escrita: <<< FALHOU\n";
}
echo "\narquivos:\n";
foreach (array('index.php', 'inc/db.php', 'inc/functions.php', 'inc/sefaz.php', 'inc/lojadev.php', 'inc/layout.php', 'assets/style.css', '.htaccess', 'data/.htaccess') as $f) {
    echo "  $f: " . (file_exists(__DIR__ . '/' . $f) ? "OK" : "<<< FALTANDO") . "\n";
}
echo "\nusuario do processo: " . (function_exists('posix_geteuid') ? posix_geteuid() : '?') . "  pasta: " . __DIR__ . "\n";
echo "\n--- tentando carregar o Radar ---\n";
error_reporting(E_ALL); ini_set('display_errors', '1');
if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
    try { require __DIR__ . '/inc/functions.php'; $v = versao_atual(); echo "banco OK; versoes cadastradas: " . count(versoes_todas()) . "\n"; }
    catch (Throwable $e) { echo "<<< ERRO: " . get_class($e) . ": " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine() . "\n"; }
} else echo "(pulado: versao do PHP antiga)\n";
echo "\nSe tudo acima esta OK e o site ainda da erro 500, o problema e o .htaccess: renomeie-o para .htaccess.off e teste de novo.\n";
