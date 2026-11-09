<?php
/**
 * api/professores-listar.php
 * Cadastro completo dos professores (com telefone) — POST, token de editor.
 * Body: { "token": "..." }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_auth.php';

$config = require __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') falharAPI(405, 'Método não permitido. Use POST.');

$corpo = json_decode(file_get_contents('php://input'), true);
exigirEditor($config, $corpo['token'] ?? '');

responder(200, [
    'sucesso'     => true,
    'professores' => (object)lerJSON(__DIR__ . '/../professores.json', []),
]);
