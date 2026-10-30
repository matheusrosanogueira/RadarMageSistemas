<?php
/**
 * Radar Mage — camada de dados (SQLite via PDO)
 * O banco é criado sozinho na primeira execução em data/radar.db
 */
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
// fallbacks caso a hospedagem não tenha mbstring
if (!function_exists('mb_strlen')) { function mb_strlen(string $s): int { return strlen(utf8_decode($s)); } }
if (!function_exists('mb_strpos')) { function mb_strpos(string $h, string $n): int|false { return strpos($h, $n); } }
if (!function_exists('mb_strimwidth')) { function mb_strimwidth(string $s, int $a, int $w, string $t = ''): string { return strlen($s) > $w ? substr($s, $a, $w - strlen($t)) . $t : $s; } }

define('RADAR_ROOT', dirname(__DIR__));
define('RADAR_DB', RADAR_ROOT . '/data/radar.db');

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!extension_loaded('pdo_sqlite')) radar_parar('A extensão pdo_sqlite do PHP não está ativa. No cPanel, abra "Selecionar versão do PHP" e marque pdo_sqlite e sqlite3.');
    if (!is_dir(dirname(RADAR_DB)) && !@mkdir(dirname(RADAR_DB), 0755, true)) radar_parar('Não consegui criar a pasta data/. Crie-a dentro da pasta do site e dê permissão 775.');
    if (!is_writable(dirname(RADAR_DB))) radar_parar('A pasta data/ não permite gravação. No Gerenciador de arquivos, botão direito em data/ > Alterar permissões > 775.');

    $novo = !file_exists(RADAR_DB);
    if (!is_dir(dirname(RADAR_DB))) mkdir(dirname(RADAR_DB), 0755, true);

    $pdo = new PDO('sqlite:' . RADAR_DB);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    db_schema($pdo);
    if ($novo) db_seed($pdo);
    return $pdo;
}

/** Para com uma página legível (em vez de um erro 500 em branco) */
function radar_parar(string $motivo): never {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Radar — configuração</title><body style="font-family:system-ui;background:#0A0718;color:#ECE9F8;padding:40px;max-width:640px;margin:auto"><h1 style="font-size:22px">O Radar ainda não consegue abrir</h1><p style="color:#A9A3C6;line-height:1.6">' . htmlspecialchars($motivo) . '</p><p style="color:#6E6890;font-size:13px">Depois de corrigir, recarregue a página. Para ver tudo de uma vez, abra <code>diagnostico.php</code>.</p></body>';
    exit;
}

function db_schema(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS config (
        chave TEXT PRIMARY KEY,
        valor TEXT
    );
    CREATE TABLE IF NOT EXISTS versoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        produto TEXT NOT NULL DEFAULT 'lite',
        numero TEXT NOT NULL,
        codinome TEXT DEFAULT '',
        resumo TEXT DEFAULT '',
        notas TEXT DEFAULT '',
        como_atualizar TEXT DEFAULT '',
        publicada_em TEXT,
        criada_em TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        UNIQUE(produto, numero)
    );
    CREATE TABLE IF NOT EXISTS alteracoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        produto TEXT NOT NULL DEFAULT 'lite',
        versao_id INTEGER REFERENCES versoes(id) ON DELETE SET NULL,
        titulo TEXT NOT NULL,
        area TEXT DEFAULT '',
        ref TEXT DEFAULT '',
        interno INTEGER NOT NULL DEFAULT 0,
        criada_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    );
    CREATE TABLE IF NOT EXISTS tarefas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        produto TEXT NOT NULL DEFAULT 'lite',
        numero INTEGER NOT NULL,
        titulo TEXT NOT NULL,
        descricao TEXT DEFAULT '',
        area TEXT DEFAULT '',
        tipo TEXT NOT NULL DEFAULT 'melhoria',
        em_andamento INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'aberta',
        versao_id INTEGER REFERENCES versoes(id) ON DELETE SET NULL,
        criada_em TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        encerrada_em TEXT,
        UNIQUE(produto, numero)
    );
    CREATE TABLE IF NOT EXISTS notas_tecnicas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        produto TEXT NOT NULL DEFAULT 'lite',
        codigo TEXT NOT NULL,
        assunto TEXT DEFAULT '',
        homologacao TEXT,
        producao TEXT,
        tarefa_id INTEGER REFERENCES tarefas(id) ON DELETE SET NULL,
        status TEXT NOT NULL DEFAULT 'aberta',
        observacao TEXT DEFAULT '',
        criada_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    );
    CREATE TABLE IF NOT EXISTS sefaz_historico (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        em TEXT NOT NULL,
        fonte TEXT NOT NULL DEFAULT 'portal',
        estados TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS sefaz_quedas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uf TEXT NOT NULL,
        autorizador TEXT NOT NULL DEFAULT '',
        inicio TEXT NOT NULL,
        fim TEXT,
        pior TEXT NOT NULL DEFAULT 'amarela'
    );
    CREATE INDEX IF NOT EXISTS ix_alt_versao ON alteracoes(versao_id);
    CREATE INDEX IF NOT EXISTS ix_alt_data ON alteracoes(criada_em);
    CREATE INDEX IF NOT EXISTS ix_tar_status ON tarefas(status);
    CREATE INDEX IF NOT EXISTS ix_sh_em ON sefaz_historico(em);
    CREATE INDEX IF NOT EXISTS ix_sq_uf ON sefaz_quedas(uf, fim);
    ");

    // migrações: colunas acrescentadas depois da primeira versão
    $novas = [
        'versoes'         => ['origem TEXT NOT NULL DEFAULT \'manual\'', 'editado INTEGER NOT NULL DEFAULT 0', 'alteracoes_total INTEGER', 'assuntos_total INTEGER'],
        'alteracoes'      => ['origem TEXT NOT NULL DEFAULT \'manual\'', 'editado INTEGER NOT NULL DEFAULT 0'],
        'tarefas'         => ['origem TEXT NOT NULL DEFAULT \'manual\'', 'editado INTEGER NOT NULL DEFAULT 0', 'labels TEXT DEFAULT \'\'', 'responsaveis TEXT DEFAULT \'\'', 'comentarios INTEGER DEFAULT 0'],
        'notas_tecnicas'  => ['origem TEXT NOT NULL DEFAULT \'manual\'', 'editado INTEGER NOT NULL DEFAULT 0'],
    ];
    foreach ($novas as $tabela => $colunas) {
        $existentes = array_column($pdo->query("PRAGMA table_info($tabela)")->fetchAll(), 'name');
        foreach ($colunas as $def) {
            $nome = explode(' ', $def)[0];
            if (!in_array($nome, $existentes, true)) $pdo->exec("ALTER TABLE $tabela ADD COLUMN $def");
        }
    }
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_alt_ref ON alteracoes(produto, ref) WHERE ref <> ''");
}

function db_seed(PDO $pdo): void {
    $defaults = [
        'site_nome'       => 'Radar Mage',
        'sistema_nome'    => 'Mage Lite',
        'produto'         => 'lite',
        'empresa'         => 'Mage Sistemas',
        'site_url'        => 'https://magesistemas.com.br',
        'descricao'       => 'O painel do cliente sobre o Mage Lite: o que está sendo feito agora, o que vem na próxima versão, os prazos da Sefaz e o histórico por versão.',
        'projeto_texto'   => "## Por que o Radar existe\n\nQuem usa o Mage Lite precisa saber três coisas: o que já saiu, o que está para sair e o que a Sefaz vai exigir em breve. O Radar responde às três num lugar só, sem precisar ligar para o suporte.\n\n## Como ler\n\n- **Agora** é o trabalho conforme entra, alteração por alteração.\n- **Versões** é onde cada alteração aparece já organizada no pacote em que sai, com as notas de leitura obrigatória no alto.\n- **Planejado** são as tarefas grandes, numeradas, do momento em que entram até fecharem.\n- **Fiscal** acompanha as Notas Técnicas da Sefaz e quanto tempo falta para cada prazo.\n\n## Sua versão\n\nInforme a versão instalada no seu cliente no alto da página. A partir daí o Radar marca o que ainda não chegou até você.",
        'fonte_url'       => 'https://radar.lojadev.com.br',
        'sinc_ativo'      => '1',
        'sinc_intervalo'  => '30',
        'sinc_substituicoes' => "Radar LojaDev=>Radar Mage",
        'admin_usuario'   => 'admin',
        'admin_senha'     => '', // definido no primeiro acesso ao /admin
    ];
    $st = $pdo->prepare("INSERT OR IGNORE INTO config(chave, valor) VALUES (?, ?)");
    foreach ($defaults as $k => $v) $st->execute([$k, $v]);
}

function cfg(string $chave, string $padrao = ''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query("SELECT chave, valor FROM config") as $r) $cache[$r['chave']] = (string)$r['valor'];
    }
    return $cache[$chave] ?? $padrao;
}

function cfg_set(string $chave, string $valor): void {
    db()->prepare("INSERT INTO config(chave, valor) VALUES (?, ?) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor")
        ->execute([$chave, $valor]);
}

function produto(): string { return cfg('produto', 'lite'); }
