<?php
/**
 * api/escalas.php
 * LEITURA pública — GET. Devolve as aulas (escalas.json) e, de cada
 * professor, só o nome e a foto. O telefone nunca sai por aqui.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once __DIR__ . '/_auth.php';

$config = require __DIR__ . '/config.php';
$arquivo = __DIR__ . '/../escalas.json';
if (!file_exists($arquivo)) falharAPI(404, 'Arquivo de escalas não encontrado.');

$escalas = json_decode(file_get_contents($arquivo), true);
if (!is_array($escalas)) falharAPI(500, 'Arquivo de escalas corrompido.');

$professores = [];
foreach (lerJSON(__DIR__ . '/../professores.json', []) as $id => $p) {
    $professores[$id] = ['nome' => $p['nome'] ?? '', 'foto' => $p['foto'] ?? ''];
}

echo json_encode([
    'classe'      => $config['classe'],
    'escalaData'  => (object)($escalas['escalaData'] ?? []),
    'linksFixos'  => $escalas['linksFixos'] ?? [],
    'professores' => (object)$professores,
    'updated_at'  => $escalas['updated_at'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
