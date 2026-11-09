<?php
/**
 * api/_log.php
 * Funções auxiliares de registro de histórico (log de alterações).
 * Incluído por outros arquivos da API — não é um endpoint, não deve ser
 * acessado diretamente pelo navegador (o nome começa com "_" de propósito,
 * pra deixar isso claro).
 *
 * O log fica em log.ndjson (uma pasta acima desta), no formato "JSON Lines"
 * — um objeto JSON por linha, o que permite adicionar registros sem precisar
 * reescrever o arquivo inteiro toda vez.
 */

define('LOG_ARQUIVO', __DIR__ . '/../log.ndjson');
define('LOG_MAX_LINHAS', 500);

/**
 * Adiciona um registro ao histórico.
 *
 * @param string $acao       Ex: "salvar_departamento", "salvar_completo",
 *                            "restaurar_backup", "criar_usuario", etc.
 * @param string $ator       Quem fez a ação (e-mail, ou "admin (chave mestra)").
 * @param array  $detalhes   Dados extras relevantes (departamento, datas, etc).
 */
function registrarLog($acao, $ator, $detalhes = []) {
    $registro = [
        'data'      => gmdate('c'),
        'acao'      => $acao,
        'ator'      => $ator,
        'detalhes'  => $detalhes,
    ];

    $linha = json_encode($registro, JSON_UNESCAPED_UNICODE) . "\n";
    file_put_contents(LOG_ARQUIVO, $linha, FILE_APPEND | LOCK_EX);

    aparaLogSeNecessario();
}

/**
 * Mantém o arquivo de log com no máximo LOG_MAX_LINHAS registros,
 * removendo os mais antigos quando passa disso. Roda a cada gravação,
 * mas só reescreve o arquivo quando realmente excede o limite.
 */
function aparaLogSeNecessario() {
    if (!file_exists(LOG_ARQUIVO)) return;

    $linhas = file(LOG_ARQUIVO, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($linhas === false || count($linhas) <= LOG_MAX_LINHAS) return;

    $linhasRecentes = array_slice($linhas, -LOG_MAX_LINHAS);
    file_put_contents(LOG_ARQUIVO, implode("\n", $linhasRecentes) . "\n", LOCK_EX);
}

/**
 * Lê os últimos $quantidade registros do log, do mais recente pro mais antigo.
 */
function lerLog($quantidade = 100) {
    if (!file_exists(LOG_ARQUIVO)) return [];

    $linhas = file(LOG_ARQUIVO, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($linhas === false) return [];

    $linhas = array_slice($linhas, -$quantidade);
    $registros = array_map(function($linha) {
        return json_decode($linha, true);
    }, $linhas);

    // Mais recente primeiro
    return array_reverse(array_filter($registros));
}
