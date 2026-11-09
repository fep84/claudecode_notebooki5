<?php
/**
 * api/salvar-departamento.php
 * Endpoint de ESCRITA restrita — POST, protegido por token de sessão
 * (gerado em login-teste.php ou, futuramente, login-google.php).
 *
 * Body esperado:
 *   {
 *     "token": "...",
 *     "departamento": "Louvores",
 *     "atualizacoes": { "12/10/2026": "Fulano e Ciclana", "19/10/2026": "" }
 *   }
 *
 * Regras:
 * - O token precisa ser válido, não expirado, e autorizar o departamento
 *   informado (ou ser "*", admin).
 * - Só o campo do departamento autorizado é alterado em cada data — os
 *   demais departamentos daquela mesma data nunca são tocados.
 * - Um valor vazio ("" ou null) remove a escala daquela data/departamento.
 * - Cada gravação faz backup automático antes de sobrescrever (mesma
 *   lógica de salvar.php), mantendo os últimos 30.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_log.php';

$config = require __DIR__ . '/config.php';
$SESSAO_SECRETA = $config['sessao_secreta'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$corpo = json_decode(file_get_contents('php://input'), true);
$token = $corpo['token'] ?? '';
$departamento = $corpo['departamento'] ?? '';
$atualizacoes = $corpo['atualizacoes'] ?? null;

// ===== Verifica o token =====
$partes = explode('.', $token);
if (count($partes) !== 2) {
    http_response_code(401);
    echo json_encode(['erro' => 'Token inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

[$payloadB64, $assinaturaRecebida] = $partes;
$assinaturaEsperada = hash_hmac('sha256', $payloadB64, $SESSAO_SECRETA);

if (!hash_equals($assinaturaEsperada, $assinaturaRecebida)) {
    http_response_code(401);
    echo json_encode(['erro' => 'Token inválido ou adulterado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(base64_decode($payloadB64), true);
if (!$payload || !isset($payload['exp']) || time() > $payload['exp']) {
    http_response_code(401);
    echo json_encode(['erro' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== Verifica permissão sobre o departamento =====
$deps = $payload['deps'] ?? [];
$temAcesso = in_array('*', $deps, true) || in_array($departamento, $deps, true);

if (!$temAcesso) {
    http_response_code(403);
    echo json_encode(['erro' => 'Você não tem permissão para editar este departamento.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_array($atualizacoes) || $departamento === '') {
    http_response_code(400);
    echo json_encode(['erro' => 'Dados incompletos. Esperado: {token, departamento, atualizacoes: {"DD/MM/AAAA": valor}}'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== Carrega os dados atuais =====
$arquivo = __DIR__ . '/../escalas.json';
$atual = json_decode(file_get_contents($arquivo), true);
if (!$atual || !isset($atual['escalaData'])) {
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível ler os dados atuais.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== Backup antes de alterar =====
$pastaBackup = __DIR__ . '/../backups';
if (!is_dir($pastaBackup)) {
    mkdir($pastaBackup, 0755, true);
}
copy($arquivo, $pastaBackup . '/escalas_' . date('Y-m-d_His') . '.json');

$backups = glob($pastaBackup . '/escalas_*.json');
if ($backups !== false && count($backups) > 30) {
    sort($backups);
    foreach (array_slice($backups, 0, count($backups) - 30) as $antigo) {
        unlink($antigo);
    }
}

// ===== Aplica só as mudanças do departamento autorizado =====
// Nunca mexe em outros departamentos da mesma data.
foreach ($atualizacoes as $data => $valor) {
    if (!isset($atual['escalaData'][$data])) {
        $atual['escalaData'][$data] = [];
    }
    if ($valor === null || $valor === '') {
        unset($atual['escalaData'][$data][$departamento]);
    } else {
        $atual['escalaData'][$data][$departamento] = $valor;
    }
}

$atual['updated_at'] = gmdate('c');

$ok = file_put_contents(
    $arquivo,
    json_encode($atual, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

if ($ok === false) {
    http_response_code(500);
    echo json_encode(['erro' => 'Falha ao gravar o arquivo de escalas.'], JSON_UNESCAPED_UNICODE);
    exit;
}

registrarLog('salvar_departamento', $payload['email'] ?? 'desconhecido', [
    'departamento' => $departamento,
    'datas'        => array_keys($atualizacoes),
]);

echo json_encode([
    'sucesso'    => true,
    'mensagem'   => 'Escala atualizada com sucesso.',
    'updated_at' => $atual['updated_at'],
], JSON_UNESCAPED_UNICODE);
