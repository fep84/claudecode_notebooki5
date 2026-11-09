<?php
/**
 * api/escalas.php
 * Endpoint de LEITURA das escalas — GET, público.
 * Devolve o conteúdo de escalas.json (um nível acima desta pasta).
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$arquivo = __DIR__ . '/../escalas.json';

// Backup diário automático (só faz algo no primeiro acesso do dia)
require_once __DIR__ . '/_backup.php';
backupDiarioSeNecessario();

if (!file_exists($arquivo)) {
    http_response_code(404);
    echo json_encode(['erro' => 'Arquivo de escalas não encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conteudo = file_get_contents($arquivo);

if ($conteudo === false) {
    http_response_code(500);
    echo json_encode(['erro' => 'Falha ao ler o arquivo de escalas.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Valida se é um JSON íntegro antes de devolver
$dados = json_decode($conteudo, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['erro' => 'Arquivo de escalas corrompido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo $conteudo;
