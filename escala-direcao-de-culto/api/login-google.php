<?php
/**
 * api/login-google.php
 * Login real com Google (Fase 2b).
 *
 * GET  → {"client_id": "..."} — diz às páginas se o login com Google está
 *        ligado (client_id vazio = ainda não configurado, vale o login de teste).
 * POST → recebe {"credential": "<ID token do Google>"}, confere o token com
 *        o Google, exige e-mail verificado e, se o e-mail estiver em
 *        usuarios.json, devolve o mesmo token de sessão do login-teste.php.
 *
 * O Client ID fica em config.php ('google_client_id').
 */

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';
$SESSAO_SECRETA = $config['sessao_secreta'];
$SESSAO_DURACAO = $config['sessao_duracao'];
$PERMISSOES = $config['permissoes'];
$CLIENT_ID = trim($config['google_client_id'] ?? '');

function falhar($codigo, $mensagem) {
    http_response_code($codigo);
    echo json_encode(['erro' => $mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['client_id' => $CLIENT_ID], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    falhar(405, 'Método não permitido. Use POST.');
}

if ($CLIENT_ID === '') {
    falhar(503, 'Login com Google ainda não configurado (google_client_id em config.php).');
}

$corpo = json_decode(file_get_contents('php://input'), true);
$credencial = trim($corpo['credential'] ?? '');
if ($credencial === '') {
    falhar(400, 'Credencial do Google ausente.');
}

// Pede ao Google para validar o ID token (assinatura e validade).
$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credencial);
$resposta = false;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resposta = curl_exec($ch);
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $resposta = @file_get_contents($url, false, $ctx);
}

$info = $resposta ? json_decode($resposta, true) : null;
if (!is_array($info) || isset($info['error']) || isset($info['error_description'])) {
    falhar(401, 'Não foi possível confirmar o login com o Google. Tente de novo.');
}

// O token precisa ter sido emitido pelo Google para ESTE site.
$emissorOk = in_array($info['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true);
if (!$emissorOk || ($info['aud'] ?? '') !== $CLIENT_ID) {
    falhar(401, 'Login do Google inválido para este site.');
}
if ((int)($info['exp'] ?? 0) < time()) {
    falhar(401, 'Login do Google expirado. Tente de novo.');
}
$verificado = $info['email_verified'] ?? false;
if ($verificado !== true && $verificado !== 'true') {
    falhar(401, 'O e-mail desta conta Google não está verificado.');
}

$email = strtolower(trim($info['email'] ?? ''));
if ($email === '' || !isset($PERMISSOES[$email])) {
    falhar(401, 'O e-mail ' . $email . ' não tem permissão para editar nenhuma escala.');
}

// Daqui para baixo é igual ao login-teste.php.
$departamentos = $PERMISSOES[$email];
$expira = time() + $SESSAO_DURACAO;

$payload = base64_encode(json_encode([
    'email' => $email,
    'deps'  => $departamentos,
    'exp'   => $expira,
]));

$assinatura = hash_hmac('sha256', $payload, $SESSAO_SECRETA);
$token = $payload . '.' . $assinatura;

echo json_encode([
    'sucesso'       => true,
    'token'         => $token,
    'email'         => $email,
    'departamentos' => $departamentos,
    'expira_em'     => $expira,
], JSON_UNESCAPED_UNICODE);
