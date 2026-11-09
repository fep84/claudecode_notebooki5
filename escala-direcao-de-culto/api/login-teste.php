<?php
/**
 * api/login-teste.php
 * Login de TESTE — POST, sem senha, sem verificação real de identidade.
 * Recebe {"email": "..."} e, se o e-mail estiver na lista de permissões
 * (config.php), devolve um token de sessão assinado, válido por algumas
 * horas, com a lista de departamentos que aquele e-mail pode editar.
 *
 * Este endpoint será substituído por login-google.php na Fase 2b, quando
 * o login real do Google estiver configurado. A lógica de permissões e o
 * formato do token continuam os mesmos — só a forma de confirmar quem é
 * a pessoa muda.
 */

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';
$SESSAO_SECRETA = $config['sessao_secreta'];
$SESSAO_DURACAO = $config['sessao_duracao'];
$PERMISSOES = $config['permissoes'];

// Com o login do Google configurado, o login de teste fica desligado.
if (trim($config['google_client_id'] ?? '') !== '') {
    http_response_code(410);
    echo json_encode(['erro' => 'Login de teste desativado. Use o login com Google.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$corpo = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($corpo['email'] ?? ''));

if ($email === '' || !isset($PERMISSOES[$email])) {
    http_response_code(401);
    echo json_encode(['erro' => 'E-mail não autorizado a editar nenhuma escala.'], JSON_UNESCAPED_UNICODE);
    exit;
}

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
