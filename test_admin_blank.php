<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function for debugging
function debug_log($message) {
    echo "<pre style='background:#f5f5f5;padding:10px;margin:5px;border:1px solid #ddd;'>";
    echo date('Y-m-d H:i:s') . " - " . $message;
    echo "</pre>";
}

debug_log("Iniciando teste do painel admin");

try {
    // 1. Teste de conexão com o banco
    debug_log("Testando conexão com o banco de dados...");
    $test_query = $db->getRecFrmQry("SELECT 1");
    debug_log("Conexão com banco OK");

    // 2. Teste da query principal do admin
    debug_log("\nTestando query principal do admin:");
    $main_query = "SELECT * FROM " . DB_TBLPREFIX . "_mbrs AS mbr 
                  LEFT JOIN " . DB_TBLPREFIX . "_mbrplans AS plan ON id = idmbr 
                  WHERE 1 
                  ORDER BY reg_utctime DESC";
    
    debug_log("Query executada: " . $main_query);
    
    $userData = $db->getRecFrmQry($main_query);
    debug_log("Total de registros encontrados: " . count($userData));

    // 3. Teste de estrutura das tabelas
    debug_log("\nVerificando estrutura das tabelas:");
    
    // Verifica tabela de membros
    $mbrs_structure = $db->getRecFrmQry("DESCRIBE " . DB_TBLPREFIX . "_mbrs");
    debug_log("Campos na tabela _mbrs:");
    foreach ($mbrs_structure as $field) {
        debug_log("- " . $field['Field'] . " (" . $field['Type'] . ")");
    }
    
    // Verifica tabela de planos
    $plans_structure = $db->getRecFrmQry("DESCRIBE " . DB_TBLPREFIX . "_mbrplans");
    debug_log("\nCampos na tabela _mbrplans:");
    foreach ($plans_structure as $field) {
        debug_log("- " . $field['Field'] . " (" . $field['Type'] . ")");
    }

    // 4. Teste de dados específicos
    debug_log("\nVerificando dados específicos:");
    
    // Verifica se existem registros
    $count_mbrs = $db->getRecFrmQry("SELECT COUNT(*) as total FROM " . DB_TBLPREFIX . "_mbrs");
    debug_log("Total de membros: " . $count_mbrs[0]['total']);
    
    $count_plans = $db->getRecFrmQry("SELECT COUNT(*) as total FROM " . DB_TBLPREFIX . "_mbrplans");
    debug_log("Total de planos: " . $count_plans[0]['total']);

    // 5. Teste de JOIN
    debug_log("\nTestando JOIN entre as tabelas:");
    $join_test = $db->getRecFrmQry("SELECT mbr.id, mbr.username, mbr.mpid, plan.mppid 
                                   FROM " . DB_TBLPREFIX . "_mbrs AS mbr 
                                   LEFT JOIN " . DB_TBLPREFIX . "_mbrplans AS plan ON mbr.id = plan.idmbr 
                                   LIMIT 5");
    
    if (count($join_test) > 0) {
        foreach ($join_test as $row) {
            debug_log("Membro: " . $row['username'] . 
                     " (ID: " . $row['id'] . 
                     ", MPID: " . $row['mpid'] . 
                     ", Plano: " . $row['mppid'] . ")");
        }
    } else {
        debug_log("Nenhum resultado encontrado no JOIN");
    }

    // 6. Verifica configurações do sistema
    debug_log("\nVerificando configurações do sistema:");
    $cfgrow = $db->getRecFrmQry("SELECT * FROM " . DB_TBLPREFIX . "_configs LIMIT 1");
    if (!empty($cfgrow)) {
        debug_log("Configurações encontradas");
        foreach ($cfgrow[0] as $key => $value) {
            if (!is_array($value)) {
                debug_log("- " . $key . ": " . $value);
            }
        }
    } else {
        debug_log("Nenhuma configuração encontrada");
    }

} catch (Exception $e) {
    debug_log("ERRO: " . $e->getMessage());
    debug_log("Stack trace: " . $e->getTraceAsString());
} 