<?php
/**
 * api/_backup.php
 * Funções auxiliares dos backups diário e manual.
 * Incluído por outros arquivos da API — não é um endpoint, não deve ser
 * acessado diretamente pelo navegador.
 *
 * Tipos de backup na pasta backups/ (todos são cópias de escalas.json):
 * - escalas_AAAA-MM-DD_HHMMSS.json  antes de cada alteração pelo site
 *                                   (regra original, mantém os 30 últimos)
 * - diario_AAAA-MM-DD.json          um por dia, feito automaticamente no
 *                                   primeiro acesso do dia à escala
 *                                   (mantém os 60 últimos)
 * - manual_AAAA-MM-DD_HHMMSS.json   feito pelo botão "Fazer backup agora"
 *                                   do Painel Administrativo
 *                                   (mantém os 30 últimos)
 *
 * Cada tipo tem um prefixo próprio, então a limpeza de um tipo nunca
 * apaga backups de outro.
 */

define('BACKUP_PASTA', __DIR__ . '/../backups');
define('BACKUP_ESCALAS', __DIR__ . '/../escalas.json');
define('BACKUP_DIARIOS_MAX', 60);
define('BACKUP_MANUAIS_MAX', 30);

function backupGarantirPasta() {
    if (is_dir(BACKUP_PASTA)) return true;
    return @mkdir(BACKUP_PASTA, 0755, true);
}

/**
 * Mantém só os $max backups mais recentes do tipo indicado pelo prefixo.
 */
function backupAparar($prefixo, $max) {
    $lista = glob(BACKUP_PASTA . '/' . $prefixo . '_*.json');
    if ($lista === false || count($lista) <= $max) return;
    sort($lista);
    foreach (array_slice($lista, 0, count($lista) - $max) as $antigo) {
        @unlink($antigo);
    }
}

/**
 * Backup diário automático: se ainda não existe o backup de hoje, copia o
 * escalas.json atual. Chamado a cada leitura da escala (api/escalas.php),
 * então roda sozinho no primeiro acesso de cada dia, sem precisar de
 * agendamento no servidor. Nunca interrompe a página se algo falhar.
 */
function backupDiarioSeNecessario() {
    if (!file_exists(BACKUP_ESCALAS) || !backupGarantirPasta()) return;
    $destino = BACKUP_PASTA . '/diario_' . date('Y-m-d') . '.json';
    if (file_exists($destino)) return;
    if (@copy(BACKUP_ESCALAS, $destino)) {
        backupAparar('diario', BACKUP_DIARIOS_MAX);
    }
}

/**
 * Backup manual: copia o escalas.json atual na hora.
 * Devolve o nome do arquivo criado, ou false se falhar.
 */
function backupManual() {
    if (!file_exists(BACKUP_ESCALAS) || !backupGarantirPasta()) return false;
    $nome = 'manual_' . date('Y-m-d_His') . '.json';
    if (!@copy(BACKUP_ESCALAS, BACKUP_PASTA . '/' . $nome)) return false;
    backupAparar('manual', BACKUP_MANUAIS_MAX);
    return $nome;
}
