<?php
/** Radar Mage — clientes identificados por chave */
declare(strict_types=1);

function cliente_por_chave(string $chave): ?array {
    $chave = strtoupper(trim($chave));
    if (!preg_match('/^[A-Z0-9]{6,24}$/', $chave)) return null;
    $st = db()->prepare("SELECT * FROM clientes WHERE chave = ?"); $st->execute([$chave]);
    return $st->fetch() ?: null;
}

/** Cliente do visitante atual (cookie radar_cliente), ou null */
function cliente_atual(): ?array {
    static $c = false;
    if ($c !== false) return $c;
    $c = cliente_por_chave($_COOKIE['radar_cliente'] ?? '');
    return $c;
}

function gerar_chave(): string {
    $alf = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s = '';
    for ($i = 0; $i < 8; $i++) $s .= $alf[random_int(0, strlen($alf) - 1)];
    return $s;
}

function cliente_modulos(?array $c): array {
    if (!$c || trim((string)$c['modulos']) === '') return [];
    return array_values(array_filter(array_map('trim', explode(',', $c['modulos']))));
}

/** Áreas do filtro "só o que me afeta" (cookie radar_filtro=1 e cliente com módulos), ou null = sem filtro */
function filtro_areas(): ?array {
    if (($_COOKIE['radar_filtro'] ?? '') !== '1') return null;
    $m = cliente_modulos(cliente_atual());
    return $m ?: null;
}

/** Mantém só as alterações das áreas do cliente (e as sem área, que podem afetar qualquer um) */
function filtrar_alteracoes(array $alts): array {
    $areas = filtro_areas(); if ($areas === null) return $alts;
    $set = array_flip(array_map('mb_strtolower', $areas));
    return array_values(array_filter($alts, fn($a) => trim((string)$a['area']) === '' || isset($set[mb_strtolower($a['area'])])));
}

/** Distribuição dos clientes por versão informada */
function distribuicao_versoes(): array {
    $rows = db()->query("SELECT versao, COUNT(*) n FROM clientes WHERE versao <> '' GROUP BY versao")->fetchAll();
    usort($rows, fn($a, $b) => version_compare($b['versao'], $a['versao']));
    return $rows;
}

function ufs_brasil(): array {
    return ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];
}
