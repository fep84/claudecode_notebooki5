<?php
/**
 * api/salvar.php
 * Endpoint de ESCRITA das escalas — POST, protegido por chave secreta.
 *
 * Como usar:
 *   Envie um POST para este endpoint com:
 *     Header:  Authorization: Bearer SUA_CHAVE_SECRETA
 *     Body:    JSON no formato {"escalaData": {...}, "diasData": {...}}
 *
 * A chave secreta abaixo é a que o Claude gerou para você.
 * NÃO compartilhe este arquivo nem a chave publicamente.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_log.php';

// ===== CHAVE SECRETA (não compartilhe) =====
define('CHAVE_SECRETA', 'T32zlWDXQCm4eWoUSlWbdMQWZbkxasMxoKvw6KCK');

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== Verifica a chave enviada no header Authorization =====
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$chaveEnviada = trim(str_replace('Bearer', '', $authHeader));

if (!hash_equals(CHAVE_SECRETA, $chaveEnviada)) {
    http_response_code(401);
    echo json_encode(['erro' => 'Chave de acesso inválida ou ausente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== Lê e valida o corpo da requisição =====
$corpo = file_get_contents('php://input');
$dados = json_decode($corpo, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['erro' => 'JSON inválido: ' . json_last_error_msg()], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($dados['escalaData']) || !isset($dados['diasData'])
    || !is_array($dados['escalaData']) || !is_array($dados['diasData'])) {
    http_response_code(400);
    echo json_encode(['erro' => 'Formato inválido. Esperado: {"escalaData": {...}, "diasData": {...}}'], JSON_UNESCAPED_UNICODE);
    exit;
}

$arquivo = __DIR__ . '/../escalas.json';

// ===== Faz backup do arquivo atual antes de sobrescrever =====
if (file_exists($arquivo)) {
    $pastaBackup = __DIR__ . '/../backups';
    if (!is_dir($pastaBackup)) {
        mkdir($pastaBackup, 0755, true);
    }
    $nomeBackup = $pastaBackup . '/escalas_' . date('Y-m-d_His') . '.json';
    copy($arquivo, $nomeBackup);

    // Mantém só os 30 backups mais recentes, para não acumular arquivos indefinidamente
    $backups = glob($pastaBackup . '/escalas_*.json');
    if ($backups !== false && count($backups) > 30) {
        sort($backups);
        $excedentes = array_slice($backups, 0, count($backups) - 30);
        foreach ($excedentes as $antigo) {
            unlink($antigo);
        }
    }
}

// ===== Monta o payload final e salva =====
$payload = [
    'escalaData' => $dados['escalaData'],
    'diasData'   => $dados['diasData'],
    'updated_at' => gmdate('c'),
];

$jsonFinal = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($jsonFinal === false) {
    http_response_code(500);
    echo json_encode(['erro' => 'Falha ao converter os dados para JSON.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ok = file_put_contents($arquivo, $jsonFinal, LOCK_EX);

if ($ok === false) {
    http_response_code(500);
    echo json_encode(['erro' => 'Falha ao gravar o arquivo de escalas no servidor.'], JSON_UNESCAPED_UNICODE);
    exit;
}

registrarLog('salvar_completo', 'admin (chave mestra)', [
    'total_datas' => count($dados['escalaData']),
]);

echo json_encode([
    'sucesso'    => true,
    'mensagem'   => 'Escalas atualizadas com sucesso.',
    'updated_at' => $payload['updated_at'],
], JSON_UNESCAPED_UNICODE);
