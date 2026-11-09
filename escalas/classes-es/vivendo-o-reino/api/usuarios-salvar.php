<?php
/**
 * api/usuarios-salvar.php
 * Adiciona, edita ou remove um usuário e suas permissões — POST, protegido
 * por token de sessão com acesso admin ("*"). Usado pelo Painel Administrativo.
 *
 * Body esperado:
 *   {
 *     "token": "...",
 *     "acao": "salvar" | "remover",
 *     "email": "vanessa@iasdgloria",
 *     "departamentos": ["Anúncios"]   // obrigatório quando acao = "salvar"
 *   }
 *
 * Regras:
 * - "departamentos": ["*"] concede acesso admin (todos os departamentos).
 * - Um e-mail já existente é sobrescrito com os novos departamentos.
 * - Cada gravação faz backup do usuarios.json antes de sobrescrever.
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
$acao = $corpo['acao'] ?? '';
$emailAlvo = strtolower(trim($corpo['email'] ?? ''));
$departamentos = $corpo['departamentos'] ?? null;

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

// ===== Só admin ("*") pode gerenciar usuários =====
$deps = $payload['deps'] ?? [];
if (!in_array('*', $deps, true)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Só administradores podem gerenciar usuários.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($emailAlvo === '' || !in_array($acao, ['salvar', 'remover'], true)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Dados incompletos. Esperado: {token, acao: "salvar"|"remover", email, departamentos?}'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($acao === 'salvar' && (!is_array($departamentos) || count($departamentos) === 0)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Informe ao menos um departamento (ou ["*"] para admin).'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Proteção: impede remover o próprio usuário logado, pra não trancar o
// admin pra fora sem querer.
if ($acao === 'remover' && $emailAlvo === ($payload['email'] ?? '')) {
    http_response_code(400);
    echo json_encode(['erro' => 'Você não pode remover o próprio usuário enquanto estiver logado com ele.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$arquivo = __DIR__ . '/../usuarios.json';
$usuarios = $config['permissoes'];

// ===== Backup antes de alterar =====
if (file_exists($arquivo)) {
    $pastaBackup = __DIR__ . '/../backups';
    if (!is_dir($pastaBackup)) {
        mkdir($pastaBackup, 0755, true);
    }
    copy($arquivo, $pastaBackup . '/usuarios_' . date('Y-m-d_His') . '.json');
}

if ($acao === 'salvar') {
    $usuarios[$emailAlvo] = array_values($departamentos);
    $acaoLog = isset($config['permissoes'][$emailAlvo]) ? 'editar_usuario' : 'criar_usuario';
} else {
    unset($usuarios[$emailAlvo]);
    $acaoLog = 'remover_usuario';
}

$ok = file_put_contents(
    $arquivo,
    json_encode($usuarios, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

if ($ok === false) {
    http_response_code(500);
    echo json_encode(['erro' => 'Falha ao gravar o arquivo de usuários.'], JSON_UNESCAPED_UNICODE);
    exit;
}

registrarLog($acaoLog, $payload['email'] ?? 'desconhecido', [
    'email_alvo'    => $emailAlvo,
    'departamentos' => $departamentos,
]);

echo json_encode([
    'sucesso'  => true,
    'mensagem' => 'Usuários atualizados com sucesso.',
    'usuarios' => $usuarios,
], JSON_UNESCAPED_UNICODE);
