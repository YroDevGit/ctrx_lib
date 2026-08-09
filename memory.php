<?php
/**
 * CTR-X SQLite Database Manager
 * Single-file database management for SQLite
 * Made by CodeYro
 * Modified date: July 24 2026
 */

// Database configuration
define('DB_PATH','app/php/db/ctrx.db');

// Check if database exists
if (!file_exists(DB_PATH)) {
    die("❌ SQLite database not found at: " . DB_PATH);
}

// Database connection function
function getDBConnection() {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
    }
}

// Helper functions
function quoteIdentifier($identifier) {
    return '"' . trim($identifier, '"') . '"';
}

function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['success' => true, 'data' => $stmt];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getTables($pdo) {
    $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name";
    $result = executeQuery($pdo, $sql);
    if (!$result['success']) return $result;
    
    $tables = [];
    while ($row = $result['data']->fetch()) {
        $tables[] = $row['name'];
    }
    return ['success' => true, 'data' => $tables];
}

function getTableInfo($pdo, $table) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $quoted = quoteIdentifier($table);
    
    $result = executeQuery($pdo, "PRAGMA table_info($quoted)");
    if (!$result['success']) return $result;
    
    $columns = [];
    while ($row = $result['data']->fetch()) {
        $columns[] = [
            'Field' => $row['name'],
            'Type' => $row['type'],
            'Null' => $row['notnull'] ? 'NO' : 'YES',
            'Key' => $row['pk'] ? 'PRI' : '',
            'Default' => $row['dflt_value'],
            'Extra' => ''
        ];
    }
    
    // Get indexes
    $indexResult = executeQuery($pdo, "PRAGMA index_list($quoted)");
    $keys = ['PRIMARY' => [], 'UNIQUE' => []];
    
    if ($indexResult['success']) {
        while ($row = $indexResult['data']->fetch()) {
            if ($row['unique']) {
                $infoResult = executeQuery($pdo, "PRAGMA index_info({$row['name']})");
                if ($infoResult['success']) {
                    $cols = [];
                    while ($info = $infoResult['data']->fetch()) {
                        $cols[] = $info['name'];
                    }
                    if (strpos($row['name'], 'sqlite_autoindex') === 0) {
                        $keys['PRIMARY'] = $cols;
                    } else {
                        $keys['UNIQUE'][$row['name']] = $cols;
                    }
                }
            }
        }
    }
    
    return ['success' => true, 'data' => ['columns' => $columns, 'keys' => $keys]];
}

function getTableData($pdo, $table, $limit = 100, $offset = 0) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $quoted = quoteIdentifier($table);
    
    $countResult = executeQuery($pdo, "SELECT COUNT(*) as total FROM $quoted");
    if (!$countResult['success']) return $countResult;
    $totalCount = $countResult['data']->fetch()['total'];
    
    if ($offset >= $totalCount) {
        return ['success' => true, 'data' => [], 'total' => $totalCount];
    }
    
    $result = executeQuery($pdo, "SELECT * FROM $quoted LIMIT $limit OFFSET $offset");
    if (!$result['success']) return $result;
    
    $data = [];
    while ($row = $result['data']->fetch()) {
        $data[] = $row;
    }
    return ['success' => true, 'data' => $data, 'total' => $totalCount];
}

function createTable($pdo, $tableName, $columnDefs) {
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $quoted = quoteIdentifier($tableName);
    $sql = "CREATE TABLE $quoted ($columnDefs)";
    return executeQuery($pdo, $sql);
}

function dropTable($pdo, $table) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $quoted = quoteIdentifier($table);
    return executeQuery($pdo, "DROP TABLE $quoted");
}

function renameTable($pdo, $oldName, $newName) {
    $oldName = preg_replace('/[^a-zA-Z0-9_]/', '', $oldName);
    $newName = preg_replace('/[^a-zA-Z0-9_]/', '', $newName);
    $quotedOld = quoteIdentifier($oldName);
    $quotedNew = quoteIdentifier($newName);
    return executeQuery($pdo, "ALTER TABLE $quotedOld RENAME TO $quotedNew");
}

function addColumn($pdo, $table, $columnDef) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $quoted = quoteIdentifier($table);
    return executeQuery($pdo, "ALTER TABLE $quoted ADD COLUMN $columnDef");
}

function removeColumn($pdo, $table, $columnName) {
    // SQLite doesn't support DROP COLUMN directly, need to recreate table
    return ['success' => false, 'message' => 'SQLite does not support DROP COLUMN directly. Please recreate the table.'];
}

function renameColumn($pdo, $table, $oldName, $newName) {
    // SQLite doesn't support RENAME COLUMN directly
    return ['success' => false, 'message' => 'SQLite does not support RENAME COLUMN directly. Please recreate the table.'];
}

function modifyColumn($pdo, $table, $columnName, $newDef) {
    // SQLite doesn't support MODIFY COLUMN directly
    return ['success' => false, 'message' => 'SQLite does not support MODIFY COLUMN directly. Please recreate the table.'];
}

function setPrimaryKey($pdo, $table, $column) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $quoted = quoteIdentifier($table);
    $quotedCol = quoteIdentifier($column);
    return executeQuery($pdo, "ALTER TABLE $quoted ADD PRIMARY KEY ($quotedCol)");
}

function setUniqueKey($pdo, $table, $column) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $quoted = quoteIdentifier($table);
    $quotedCol = quoteIdentifier($column);
    // SQLite uses CREATE UNIQUE INDEX
    $indexName = "unique_{$table}_{$column}";
    return executeQuery($pdo, "CREATE UNIQUE INDEX IF NOT EXISTS $indexName ON $quoted ($quotedCol)");
}

function dropKey($pdo, $table, $keyName) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $quoted = quoteIdentifier($table);
    
    if (strtoupper($keyName) == 'PRIMARY') {
        // SQLite requires recreating table to drop primary key
        return ['success' => false, 'message' => 'SQLite does not support dropping PRIMARY KEY directly. Please recreate the table.'];
    } else {
        $keyName = preg_replace('/[^a-zA-Z0-9_]/', '', $keyName);
        return executeQuery($pdo, "DROP INDEX IF EXISTS $keyName");
    }
}

function insertRow($pdo, $table, $data) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $quoted = quoteIdentifier($table);
    $columns = array_keys($data);
    $quotedColumns = array_map('quoteIdentifier', $columns);
    $placeholders = array_fill(0, count($columns), '?');
    $sql = "INSERT INTO $quoted (" . implode(', ', $quotedColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    return executeQuery($pdo, $sql, array_values($data));
}

function updateRow($pdo, $table, $data, $where, $whereValue) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $where = preg_replace('/[^a-zA-Z0-9_]/', '', $where);
    $quoted = quoteIdentifier($table);
    $quotedWhere = quoteIdentifier($where);
    
    $sets = [];
    $params = [];
    foreach ($data as $col => $val) {
        $col = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        $quotedCol = quoteIdentifier($col);
        $sets[] = "$quotedCol = ?";
        $params[] = $val;
    }
    $params[] = $whereValue;
    $sql = "UPDATE $quoted SET " . implode(', ', $sets) . " WHERE $quotedWhere = ?";
    return executeQuery($pdo, $sql, $params);
}

function deleteRow($pdo, $table, $where, $value) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $where = preg_replace('/[^a-zA-Z0-9_]/', '', $where);
    $quoted = quoteIdentifier($table);
    $quotedWhere = quoteIdentifier($where);
    return executeQuery($pdo, "DELETE FROM $quoted WHERE $quotedWhere = ?", [$value]);
}

function truncateTable($pdo, $table) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $quoted = quoteIdentifier($table);
    return executeQuery($pdo, "DELETE FROM $quoted");
}

function exportDatabaseSQL($pdo, $tablesWithData = []) {
    $tablesResult = getTables($pdo);
    if (!$tablesResult['success']) {
        return ['success' => false, 'message' => 'Failed to get tables'];
    }
    
    $allTables = $tablesResult['data'];
    $sql = "-- ============================================\n";
    $sql .= "-- Database Export By CTR-X (SQLite)\n";
    $sql .= "-- Database: " . basename(DB_PATH) . "\n";
    $sql .= "-- Export Date: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- ============================================\n\n";
    
    foreach ($allTables as $table) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $quoted = quoteIdentifier($table);
        
        // Get table schema
        $schemaResult = executeQuery($pdo, "SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table]);
        if ($schemaResult['success'] && $schemaResult['data']->rowCount() > 0) {
            $row = $schemaResult['data']->fetch();
            $sql .= "-- Table structure for \"$table\"\n";
            $sql .= "DROP TABLE IF EXISTS $quoted;\n";
            $sql .= $row['sql'] . ";\n\n";
        }
        
        $includeData = in_array($table, $tablesWithData);
        
        if ($includeData) {
            $dataResult = executeQuery($pdo, "SELECT * FROM $quoted");
            if ($dataResult['success']) {
                $rows = $dataResult['data']->fetchAll();
                if (count($rows) > 0) {
                    $columns = array_keys($rows[0]);
                    $escapedColumns = array_map('quoteIdentifier', $columns);
                    $columnList = implode(', ', $escapedColumns);
                    
                    $sql .= "-- Dumping data for table \"$table\"\n";
                    $sql .= "INSERT INTO $quoted ($columnList) VALUES\n";
                    
                    $values = [];
                    foreach ($rows as $row) {
                        $escapedValues = array_map(function ($value) use ($pdo) {
                            if ($value === null) return 'NULL';
                            return $pdo->quote($value);
                        }, array_values($row));
                        $values[] = "(" . implode(', ', $escapedValues) . ")";
                    }
                    $sql .= implode(",\n", $values) . ";\n\n";
                } else {
                    $sql .= "-- No data for table \"$table\"\n\n";
                }
            }
        } else {
            $sql .= "-- Skipping data for table \"$table\" (structure only)\n\n";
        }
    }
    
    return ['success' => true, 'sql' => $sql];
}

function importSQL($pdo, $sql, $isFile = false) {
    if ($isFile) {
        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload failed'];
        }
        $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
        if ($sql === false) {
            return ['success' => false, 'message' => 'Failed to read file content'];
        }
    }
    
    $sql = trim($sql);
    if (empty($sql)) {
        return ['success' => false, 'message' => 'SQL is empty'];
    }
    
    // Split SQL statements
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        if ($inString) {
            if ($char == '\\' && $i + 1 < $len) {
                $current .= $char . $sql[++$i];
                continue;
            }
            if ($char == $stringChar) {
                $inString = false;
                $stringChar = '';
            }
            $current .= $char;
            continue;
        }
        if ($char == "'" || $char == '"') {
            $inString = true;
            $stringChar = $char;
            $current .= $char;
            continue;
        }
        if ($char == ';') {
            $stmt = trim($current);
            if (!empty($stmt)) {
                $statements[] = $stmt;
            }
            $current = '';
            continue;
        }
        $current .= $char;
    }
    $stmt = trim($current);
    if (!empty($stmt)) {
        $statements[] = $stmt;
    }
    
    if (empty($statements)) {
        return ['success' => false, 'message' => 'No valid SQL statements found'];
    }
    
    $errors = [];
    $successCount = 0;
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;
        
        try {
            $pdo->exec($stmt);
            $successCount++;
        } catch (PDOException $e) {
            $errors[] = "Error in statement: " . substr($stmt, 0, 100) . "... - " . $e->getMessage();
        }
    }
    
    if ($successCount > 0 && empty($errors)) {
        return ['success' => true, 'message' => "Import completed successfully. $successCount statements executed."];
    } elseif ($successCount > 0 && !empty($errors)) {
        return ['success' => true, 'message' => "Import completed with some errors. $successCount statements executed successfully. Errors: " . implode('; ', $errors)];
    } else {
        return ['success' => false, 'message' => 'Import failed. Errors: ' . implode('; ', $errors)];
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $pdo = getDBConnection();
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    try {
        switch ($action) {
            case 'getTables':
                $response = getTables($pdo);
                break;
            case 'getTableInfo':
                $response = getTableInfo($pdo, $_POST['table'] ?? '');
                break;
            case 'getTableData':
                $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
                $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
                $response = getTableData($pdo, $_POST['table'] ?? '', $limit, $offset);
                break;
            case 'createTable':
                $response = createTable($pdo, $_POST['tableName'] ?? '', $_POST['columns'] ?? '');
                break;
            case 'dropTable':
                $response = dropTable($pdo, $_POST['table'] ?? '');
                break;
            case 'renameTable':
                $response = renameTable($pdo, $_POST['oldName'] ?? '', $_POST['newName'] ?? '');
                break;
            case 'addColumn':
                $response = addColumn($pdo, $_POST['table'] ?? '', $_POST['columnDef'] ?? '');
                break;
            case 'removeColumn':
                $response = removeColumn($pdo, $_POST['table'] ?? '', $_POST['columnName'] ?? '');
                break;
            case 'renameColumn':
                $response = renameColumn($pdo, $_POST['table'] ?? '', $_POST['oldName'] ?? '', $_POST['newName'] ?? '');
                break;
            case 'modifyColumn':
                $response = modifyColumn($pdo, $_POST['table'] ?? '', $_POST['columnName'] ?? '', $_POST['newDef'] ?? '');
                break;
            case 'setPrimaryKey':
                $response = setPrimaryKey($pdo, $_POST['table'] ?? '', $_POST['column'] ?? '');
                break;
            case 'setUniqueKey':
                $response = setUniqueKey($pdo, $_POST['table'] ?? '', $_POST['column'] ?? '');
                break;
            case 'dropKey':
                $response = dropKey($pdo, $_POST['table'] ?? '', $_POST['keyName'] ?? '');
                break;
            case 'insertRow':
                $data = [];
                $skipColumns = isset($_POST['skip_columns']) ? json_decode($_POST['skip_columns'], true) : [];
                $skipColumns = $skipColumns ?? [];
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'col_') === 0) {
                        $colName = substr($key, 4);
                        if (!in_array($colName, $skipColumns)) {
                            $data[$colName] = $value;
                        }
                    }
                }
                $response = insertRow($pdo, $_POST['table'] ?? '', $data);
                break;
            case 'updateRow':
                $data = [];
                $skipColumns = isset($_POST['skip_columns']) ? json_decode($_POST['skip_columns'] ?? [], true) : [];
                $skipColumns = $skipColumns ?? [];
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'col_') === 0) {
                        $colName = substr($key, 4);
                        if (!in_array($colName, $skipColumns)) {
                            $data[$colName] = $value;
                        }
                    }
                }
                $response = updateRow($pdo, $_POST['table'] ?? '', $data, $_POST['whereCol'] ?? '', $_POST['whereVal'] ?? '');
                break;
            case 'deleteRow':
                $response = deleteRow($pdo, $_POST['table'] ?? '', $_POST['whereCol'] ?? '', $_POST['whereVal'] ?? '');
                break;
            case 'truncateTable':
                $response = truncateTable($pdo, $_POST['table'] ?? '');
                break;
            case 'exportSQL':
                $tablesWithData = isset($_POST['tables_with_data']) ? json_decode($_POST['tables_with_data'], true) : [];
                $response = exportDatabaseSQL($pdo, $tablesWithData);
                break;
            case 'importSQL':
                $isFile = isset($_POST['import_type']) && $_POST['import_type'] === 'file';
                if ($isFile) {
                    $response = importSQL($pdo, '', true);
                } else {
                    $sqlQuery = $_POST['sql_query'] ?? '';
                    $response = importSQL($pdo, $sqlQuery, false);
                }
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    if ($action === 'exportSQL' && $response['success']) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename(DB_PATH, '.db') . '_backup_' . date('Y-m-d_H-i-s') . '.sql"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $response['sql'];
        exit;
    }
    
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQLite Database Manager</title>
    <style>
        /* [Same CSS as original but simplified] */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f0f2f5; padding: 20px; color: #333; }
        .container { max-width: 1400px; margin: 0 auto; }
        .row { display: flex; flex-wrap: wrap; gap: 20px; }
        .col-md-3 { flex: 0 0 calc(25% - 15px); min-width: 250px; }
        .col-md-9 { flex: 1; min-width: 300px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .db-header { border-left: 4px solid #0d6efd; padding-left: 15px; display: flex; align-items: center; gap: 10px; }
        .db-header h2 { font-size: 24px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .db-header h2 .text-primary { color: #0d6efd; }
        .db-header small { color: #6c757d; }
        .btn { display: inline-block; padding: 6px 12px; font-size: 14px; font-weight: 500; text-align: center; border: 1px solid transparent; border-radius: 4px; cursor: pointer; transition: all 0.15s; line-height: 1.5; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .btn-primary { background: #0d6efd; color: white; border-color: #0d6efd; }
        .btn-primary:hover { background: #0b5ed7; }
        .btn-secondary { background: #6c757d; color: white; border-color: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-success { background: #198754; color: white; border-color: #198754; }
        .btn-success:hover { background: #157347; }
        .btn-danger { background: #dc3545; color: white; border-color: #dc3545; }
        .btn-danger:hover { background: #bb2d3b; }
        .btn-warning { background: #ffc107; color: #212529; border-color: #ffc107; }
        .btn-warning:hover { background: #ffca2c; }
        .btn-info { background: #0dcaf0; color: #212529; border-color: #0dcaf0; }
        .btn-info:hover { background: #31d2f2; }
        .btn-outline-primary { background: transparent; color: #0d6efd; border-color: #0d6efd; }
        .btn-outline-primary:hover { background: #0d6efd; color: white; }
        .btn-outline-secondary { background: transparent; color: #6c757d; border-color: #6c757d; }
        .btn-outline-secondary:hover { background: #6c757d; color: white; }
        .btn-outline-danger { background: transparent; color: #dc3545; border-color: #dc3545; }
        .btn-outline-danger:hover { background: #dc3545; color: white; }
        .w-100 { width: 100%; }
        .mt-2 { margin-top: 10px; }
        .mb-2 { margin-bottom: 10px; }
        .mb-3 { margin-bottom: 15px; }
        .mb-4 { margin-bottom: 20px; }
        .mt-3 { margin-top: 15px; }
        .mt-4 { margin-top: 20px; }
        .me-2 { margin-right: 10px; }
        .me-1 { margin-right: 5px; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e9ecef; }
        .card-header h5 { font-size: 16px; font-weight: 600; }
        .table-list { list-style: none; padding: 0; margin: 0; }
        .table-list-item { display: block; padding: 8px 12px; border-radius: 4px; cursor: pointer; transition: background 0.15s; }
        .table-list-item:hover { background: #f0f2f5; }
        .table-list-item.active { background: #0d6efd; color: white; }
        .table-responsive { overflow-x: auto; max-height: 500px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table th { background: #f8f9fa; position: sticky; top: 0; z-index: 10; padding: 8px 10px; text-align: left; border: 1px solid #dee2e6; font-weight: 600; }
        table td { padding: 6px 10px; border: 1px solid #dee2e6; }
        table tbody tr:hover { background: #f8f9fa; }
        table tbody tr:nth-child(even) { background: #f8f9fa; }
        .badge-key { display: inline-block; padding: 2px 6px; font-size: 10px; font-weight: 700; border-radius: 3px; margin-left: 4px; }
        .badge-key.bg-primary { background: #0d6efd; color: white; }
        .badge-key.bg-info { background: #0dcaf0; color: #212529; }
        .text-muted { color: #6c757d; }
        .text-center { text-align: center; }
        .text-danger { color: #dc3545; }
        .py-3 { padding-top: 15px; padding-bottom: 15px; }
        .py-5 { padding-top: 40px; padding-bottom: 40px; }
        .display-4 { font-size: 48px; font-weight: 300; }
        .alert { padding: 12px 20px; border-radius: 4px; margin-bottom: 15px; border: 1px solid transparent; }
        .alert-success { background: #d1e7dd; border-color: #badbcc; color: #0f5132; }
        .alert-danger { background: #f8d7da; border-color: #f5c2c7; color: #842029; }
        .alert-warning { background: #fff3cd; border-color: #ffecb5; color: #664d03; }
        .alert-info { background: #cff4fc; border-color: #b6effb; color: #055160; }
        .alert-dismissible { position: relative; padding-right: 40px; }
        .alert-dismissible .btn-close { position: absolute; top: 8px; right: 12px; background: none; border: none; font-size: 20px; cursor: pointer; color: inherit; opacity: 0.6; }
        .alert-dismissible .btn-close:hover { opacity: 1; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: white; border-radius: 8px; max-width: 700px; width: 95%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .modal-lg { max-width: 800px; }
        .modal-header { padding: 15px 20px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: white; z-index: 10; }
        .modal-header h5 { font-size: 18px; font-weight: 600; }
        .modal-header .btn-close { background: none; border: none; font-size: 24px; cursor: pointer; opacity: 0.6; padding: 0 8px; }
        .modal-header .btn-close:hover { opacity: 1; }
        .modal-body { padding: 20px; max-height: 60vh; overflow-y: auto; }
        .modal-footer { padding: 15px 20px; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 10px; position: sticky; bottom: 0; background: white; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-control { display: block; width: 100%; padding: 8px 12px; font-size: 14px; border: 1px solid #ced4da; border-radius: 4px; transition: border-color 0.15s; }
        .form-control:focus { border-color: #0d6efd; outline: 0; box-shadow: 0 0 0 3px rgba(13,110,253,0.15); }
        textarea.form-control { min-height: 100px; font-family: monospace; resize: vertical; }
        .input-group { display: flex; gap: 5px; }
        .input-group .form-control { flex: 1; }
        .checkbox-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; margin: 10px 0 15px 0; max-height: 300px; overflow-y: auto; padding: 10px; background: #f8f9fa; border-radius: 4px; border: 1px solid #e9ecef; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 4px; transition: background 0.15s; cursor: pointer; }
        .checkbox-item:hover { background: #e9ecef; }
        .checkbox-item input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: #0d6efd; }
        .checkbox-item label { cursor: pointer; font-size: 13px; color: #333; margin: 0; flex: 1; }
        .select-all-container { display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #e9ecef; border-radius: 4px; margin-bottom: 10px; }
        .select-all-container input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: #0d6efd; }
        .select-all-container label { cursor: pointer; font-weight: 500; font-size: 14px; margin: 0; color: #333; }
        .spinner-border { display: inline-block; width: 16px; height: 16px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: spinner 0.75s linear infinite; }
        @keyframes spinner { to { transform: rotate(360deg); } }
        .loading { opacity: 0.6; pointer-events: none; }
        .btn-group { display: flex; flex-wrap: wrap; gap: 4px; }
        .gap-2 { gap: 8px; }
        .icon { display: inline-block; width: 16px; text-align: center; margin-right: 4px; }
        .filter-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 12px; background: #f8f9fa; padding: 10px 14px; border-radius: 6px; border: 1px solid #e9ecef; }
        .filter-bar select, .filter-bar input { padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 13px; background: white; }
        .filter-bar select { min-width: 140px; }
        .filter-bar input { min-width: 180px; }
        .filter-bar .filter-label { font-weight: 500; font-size: 13px; color: #495057; margin-right: 2px; }
        .import-tabs { display: flex; gap: 8px; margin-bottom: 15px; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; }
        .import-tab { padding: 8px 20px; border-radius: 6px 6px 0 0; cursor: pointer; font-weight: 500; color: #6c757d; border: 1px solid transparent; transition: all 0.2s; }
        .import-tab:hover { background: #f0f2f5; }
        .import-tab.active { color: #0d6efd; border-bottom: 3px solid #0d6efd; background: transparent; }
        .import-panel { display: none; }
        .import-panel.active { display: block; }
        .file-upload-area { border: 2px dashed #ced4da; border-radius: 8px; padding: 30px 20px; text-align: center; transition: all 0.2s; background: #fafbfc; }
        .file-upload-area:hover { border-color: #0d6efd; background: #f8f9fa; }
        .file-upload-area input[type="file"] { display: none; }
        .file-upload-area .file-label { cursor: pointer; color: #0d6efd; font-weight: 500; }
        .file-upload-area .file-label:hover { text-decoration: underline; }
        .file-upload-area .file-info { margin-top: 8px; font-size: 13px; color: #6c757d; }
        .file-upload-area .file-selected { margin-top: 10px; padding: 8px 12px; background: #e9ecef; border-radius: 4px; font-size: 13px; display: none; }
        .file-upload-area .file-selected.show { display: block; }
        .field-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .field-row .form-label { margin-bottom: 0; min-width: 120px; flex-shrink: 0; }
        .field-row .form-control { flex: 1; }
        .field-row .skip-checkbox { display: flex; align-items: center; gap: 5px; flex-shrink: 0; }
        .field-row .skip-checkbox input[type="checkbox"] { width: 16px; height: 16px; accent-color: #dc3545; cursor: pointer; }
        .field-row .skip-checkbox label { font-size: 12px; color: #6c757d; cursor: pointer; margin: 0; }
        .pagination-bar { display: flex; justify-content: flex-end; align-items: center; gap: 10px; padding: 10px 0; border-top: 1px solid #e9ecef; margin-top: 10px; }
        .pagination-bar .info { font-size: 13px; color: #6c757d; }
        .db-header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        @media (max-width: 768px) {
            .col-md-3, .col-md-9 { flex: 0 0 100%; min-width: 0; }
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .db-header-actions { width: 100%; }
            .db-header-actions button { flex: 1; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar select, .filter-bar input { min-width: auto; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container" id="app">
        <div class="header">
            <div class="db-header">
                <h2>
                    <span class="icon">🗄️</span>
                    <span class="text-primary">SQLite</span> Database Manager
                    <small><?= basename(DB_PATH) ?></small>
                </h2>
            </div>
            <div class="db-header-actions">
                <button class="btn btn-success btn-sm" onclick="showExportModal()">
                    <span class="icon">💾</span> Export
                </button>
                <button class="btn btn-info btn-sm" onclick="showImportModal()">
                    <span class="icon">📥</span> Import
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="refreshAll()">
                    <span class="icon">🔄</span> Refresh
                </button>
            </div>
        </div>

        <div id="alertContainer"></div>

        <div class="row">
            <div class="col-md-3">
                <div class="card sidebar">
                    <div class="card-header">
                        <h5><span class="icon">📋</span> Tables</h5>
                        <button class="btn btn-primary btn-sm" onclick="showCreateTableModal()">
                            <span class="icon">➕</span> New
                        </button>
                    </div>
                    <div id="tableList">
                        <div class="text-center text-muted py-3">Loading tables...</div>
                    </div>
                    <hr style="margin: 15px 0;">
                    <button class="btn btn-outline-danger btn-sm w-100" onclick="dropTable()">
                        <span class="icon">🗑️</span> Drop Table
                    </button>
                    <button class="btn btn-outline-secondary btn-sm w-100 mt-2" onclick="showRenameTableModal()">
                        <span class="icon">✏️</span> Rename Table
                    </button>
                </div>
            </div>

            <div class="col-md-9">
                <div class="card main-content">
                    <div id="tableContent">
                        <div class="text-center text-muted py-5">
                            <div style="font-size: 48px; margin-bottom: 15px;">🗄️</div>
                            <p>Select a table from the left to manage it</p>
                            <p><small class="text-muted">SQLite Database: <?= basename(DB_PATH) ?></small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals (same as original but simplified) -->
    <div class="modal-overlay" id="exportModal">
        <div class="modal">
            <div class="modal-header">
                <h5><span class="icon">💾</span> Export Database</h5>
                <button class="btn-close" onclick="closeModal('exportModal')">×</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 12px; color: #6c757d; font-size: 14px;">
                    Select which tables should include data in the export.
                </p>
                <div class="select-all-container">
                    <input type="checkbox" id="selectAllTables" onchange="toggleAllTables()">
                    <label for="selectAllTables">Select All Tables</label>
                    <span class="hint">Include data for all tables</span>
                </div>
                <div id="tableCheckboxList" class="checkbox-list"></div>
                <div style="margin-top: 10px; font-size: 13px; color: #6c757d;">
                    <span id="selectedCount">0</span> tables selected
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('exportModal')">Cancel</button>
                <button class="btn btn-success" onclick="exportSQL()">Export</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="importModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h5><span class="icon">📥</span> Import SQL</h5>
                <button class="btn-close" onclick="closeModal('importModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="import-tabs">
                    <div class="import-tab active" data-tab="file" onclick="switchImportTab('file')">📁 Upload File</div>
                    <div class="import-tab" data-tab="paste" onclick="switchImportTab('paste')">📝 Paste Query</div>
                </div>
                <div class="import-panel active" id="importPanelFile">
                    <div class="file-upload-area">
                        <div style="font-size: 48px; margin-bottom: 10px;">📄</div>
                        <p><span class="file-label" onclick="document.getElementById('sqlFileInput').click()">Click to select SQL file</span></p>
                        <input type="file" id="sqlFileInput" accept=".sql,.txt" onchange="handleFileSelect(event)">
                        <div class="file-selected" id="fileSelectedInfo">📎 <span id="selectedFileName"></span></div>
                    </div>
                </div>
                <div class="import-panel" id="importPanelPaste">
                    <textarea id="sqlPasteInput" class="form-control" rows="10" placeholder="-- Paste your SQL here"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('importModal')">Cancel</button>
                <button class="btn btn-primary" onclick="importSQL()">Import</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="createTableModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h5><span class="icon">➕</span> Create Table</h5>
                <button class="btn-close" onclick="closeModal('createTableModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Table Name</label>
                    <input id="newTableName" class="form-control" placeholder="e.g., products">
                </div>
                <div class="mb-3">
                    <label class="form-label">Column Definitions</label>
                    <textarea id="newTableColumns" class="form-control" rows="6" placeholder="id INTEGER PRIMARY KEY AUTOINCREMENT,&#10;name TEXT NOT NULL,&#10;price REAL,&#10;created_at DATETIME DEFAULT CURRENT_TIMESTAMP"></textarea>
                    <small class="text-muted">Separate columns with commas. Use standard SQLite column definitions.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('createTableModal')">Cancel</button>
                <button class="btn btn-primary" onclick="createTable()">Create</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="addColumnModal">
        <div class="modal">
            <div class="modal-header">
                <h5><span class="icon">➕</span> Add Column</h5>
                <button class="btn-close" onclick="closeModal('addColumnModal')">×</button>
            </div>
            <div class="modal-body">
                <label class="form-label">Column Definition</label>
                <input id="addColumnDef" class="form-control" placeholder="email TEXT NOT NULL">
                <small class="text-muted">SQLite supports: TEXT, INTEGER, REAL, BLOB, etc.</small>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('addColumnModal')">Cancel</button>
                <button class="btn btn-primary" onclick="addColumn()">Add</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="renameTableModal">
        <div class="modal">
            <div class="modal-header">
                <h5><span class="icon">✏️</span> Rename Table</h5>
                <button class="btn-close" onclick="closeModal('renameTableModal')">×</button>
            </div>
            <div class="modal-body">
                <label class="form-label">New Table Name</label>
                <input id="renameTableName" class="form-control" placeholder="new_table_name">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('renameTableModal')">Cancel</button>
                <button class="btn btn-warning" onclick="renameTable()">Rename</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="insertRowModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h5><span class="icon">➕</span> Insert Row</h5>
                <button class="btn-close" onclick="closeModal('insertRowModal')">×</button>
            </div>
            <div class="modal-body" id="insertRowFields"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('insertRowModal')">Cancel</button>
                <button class="btn btn-success" onclick="insertRow()">Insert</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="editRowModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h5><span class="icon">✏️</span> Edit Row</h5>
                <button class="btn-close" onclick="closeModal('editRowModal')">×</button>
            </div>
            <div class="modal-body" id="editRowFields"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('editRowModal')">Cancel</button>
                <button class="btn btn-primary" onclick="updateRow()">Update</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="keyModal">
        <div class="modal">
            <div class="modal-header">
                <h5><span class="icon">🔑</span> Manage Keys</h5>
                <button class="btn-close" onclick="closeModal('keyModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Set UNIQUE KEY</label>
                    <div class="input-group">
                        <input id="uniqueColumn" class="form-control" placeholder="column_name">
                        <button class="btn btn-info" onclick="setUniqueKey()">Set</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Drop Key</label>
                    <div class="input-group">
                        <input id="dropKeyName" class="form-control" placeholder="key_name">
                        <button class="btn btn-danger" onclick="dropKey()">Drop</button>
                    </div>
                </div>
                <small class="text-muted">Note: PRIMARY KEY must be set during table creation in SQLite.</small>
            </div>
        </div>
    </div>

    <script>
        // Same JavaScript as original, keeping it compact
        let currentTable = null;
        let currentColumns = [];
        let tableData = [];
        let allTableNames = [];
        let selectedFile = null;
        let currentPage = 0;
        let totalRecords = 0;
        let filteredData = [];

        function openModal(id) { document.getElementById(id).classList.add('show'); }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('show');
        });

        function showAlert(message, type = 'info') {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible`;
            alert.innerHTML = `${message} <button class="btn-close" onclick="this.parentElement.remove()">×</button>`;
            container.appendChild(alert);
            setTimeout(() => { if (alert.parentElement) alert.remove(); }, 5000);
        }

        function showLoading(element) {
            if (!element) return;
            element.classList.add('loading');
            element.innerHTML = '<div class="text-center py-3"><span class="spinner-border"></span> Loading...</div>';
        }

        function hideLoading(element) {
            if (!element) return;
            element.classList.remove('loading');
        }

        async function apiRequest(action, data = {}) {
            data.action = action;
            const formData = new FormData();
            for (const key in data) formData.append(key, data[key]);
            try {
                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                const contentType = response.headers.get('Content-Type');
                if (contentType && contentType.includes('application/sql')) {
                    const blob = await response.blob();
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'sqlite_backup_' + new Date().toISOString().slice(0,19).replace(/[:-]/g, '_') + '.sql';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                    return { success: true, message: 'Export started' };
                }
                const result = await response.json();
                if (!result.success) showAlert(result.message || 'Operation failed', 'danger');
                return result;
            } catch (error) {
                showAlert('Network error: ' + error.message, 'danger');
                return { success: false, message: error.message };
            }
        }

        // Table management
        async function loadTables() {
            const container = document.getElementById('tableList');
            if (!container) return;
            showLoading(container);
            const result = await apiRequest('getTables');
            hideLoading(container);
            if (result.success && result.data) {
                allTableNames = result.data;
                if (result.data.length === 0) {
                    container.innerHTML = '<div class="text-muted text-center py-3">No tables found</div>';
                } else {
                    container.innerHTML = result.data.map(table => 
                        `<div class="table-list-item ${currentTable === table ? 'active' : ''}" onclick="selectTable('${table}')">
                            <span>📊 ${table}</span>
                        </div>`
                    ).join('');
                }
            } else {
                container.innerHTML = '<div class="text-danger text-center py-3">Failed to load tables</div>';
            }
        }

        async function selectTable(table) {
            currentTable = table;
            currentPage = 0;
            totalRecords = 0;
            filteredData = [];
            await loadTables();
            await loadTableInfo();
            await loadTableData();
        }

        async function loadTableInfo() {
            if (!currentTable) return;
            const result = await apiRequest('getTableInfo', { table: currentTable });
            if (result.success && result.data) {
                currentColumns = result.data.columns || [];
                renderTableInfo();
            }
        }

        function renderTableInfo() {
            const container = document.getElementById('tableContent');
            if (!container) return;
            if (!currentColumns.length) {
                container.innerHTML = '<div class="text-center text-muted py-5">No columns found</div>';
                return;
            }

            let html = `
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:8px;">
                    <h5 style="font-size:16px; font-weight:600;">
                        <span class="icon">📋</span> Table: <strong>${currentTable}</strong>
                    </h5>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary btn-sm" onclick="showAddColumnModal()">➕ Column</button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="showRenameTableModal()">✏️ Rename</button>
                        <button class="btn btn-outline-info btn-sm" onclick="showKeyModal()">🔑 Keys</button>
                        <button class="btn btn-success btn-sm" onclick="showInsertRowModal()">➕ Insert</button>
                        <button class="btn btn-danger btn-sm" onclick="truncateTable()">🗑️ Truncate</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead>
                        <tbody>
            `;

            currentColumns.forEach(col => {
                let keyBadges = '';
                if (col.Key === 'PRI') keyBadges = '<span class="badge-key bg-primary">PRI</span>';
                html += `
                    <tr>
                        <td><strong>${col.Field}</strong></td>
                        <td>${col.Type}</td>
                        <td>${col.Null}</td>
                        <td>${keyBadges || '—'}</td>
                        <td>${col.Default !== null && col.Default !== undefined ? col.Default : 'NULL'}</td>
                        <td>${col.Extra || ''}</td>
                    </tr>
                `;
            });

            html += `</tbody></table></div><div class="mt-4" id="tableDataContainer"><div class="text-center text-muted py-3">Loading data...</div></div>`;
            container.innerHTML = html;
            loadTableData();
        }

        async function loadTableData() {
            if (!currentTable) return;
            const container = document.getElementById('tableDataContainer');
            if (!container) return;
            showLoading(container);
            const offset = currentPage * 100;
            const result = await apiRequest('getTableData', { table: currentTable, limit: 100, offset });
            hideLoading(container);
            if (result.success && result.data) {
                tableData = result.data;
                totalRecords = result.total || 0;
                renderTableData();
            } else {
                container.innerHTML = '<div class="text-center text-danger py-3">Failed to load data</div>';
            }
        }

        function renderTableData() {
            const container = document.getElementById('tableDataContainer');
            if (!container) return;
            const dataToShow = filteredData.length ? filteredData : tableData;

            if (!dataToShow.length) {
                container.innerHTML = `<div class="text-center text-muted py-3">${filteredData.length ? 'No matching rows' : 'No rows found'}</div>`;
                return;
            }

            const columns = Object.keys(dataToShow[0]);
            const primaryKey = currentColumns.find(c => c.Key === 'PRI')?.Field || columns[0];
            const totalPages = Math.ceil(totalRecords / 100);

            let html = `
                <h6 style="margin-bottom:10px;">📊 Data (${dataToShow.length} of ${totalRecords} rows)</h6>
                <div class="filter-bar">
                    <span class="filter-label">🔍 Search:</span>
                    <select id="filterColumnSelect" class="form-control" style="width:auto;display:inline-block;">
                        <option value="all">All Columns</option>
                        ${columns.map(col => `<option value="${col}">${col}</option>`).join('')}
                    </select>
                    <input type="text" id="filterInputValue" class="form-control" placeholder="Enter value..." style="width:auto;display:inline-block;">
                    <button class="btn btn-primary btn-sm" onclick="applyFilter()">Search</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="clearFilter()">Clear</button>
                </div>
                <div class="table-responsive" style="max-height:400px;">
                    <table>
                        <thead><tr>${columns.map(col => `<th>${col}</th>`).join('')}<th style="min-width:100px;">Actions</th></tr></thead>
                        <tbody>
            `;

            dataToShow.forEach(row => {
                html += '<tr>';
                columns.forEach(col => {
                    html += `<td>${row[col] !== null ? row[col] : '<span class="text-muted">NULL</span>'}</td>`;
                });
                const pkValue = row[primaryKey];
                html += `
                    <td>
                        <button class="btn btn-outline-primary btn-sm" onclick="showEditRowModal('${pkValue}')" style="margin:2px;">✏️</button>
                        <button class="btn btn-outline-danger btn-sm" onclick="deleteRow('${pkValue}')" style="margin:2px;">🗑️</button>
                    </td>
                </tr>`;
            });

            html += `</tbody></table></div>`;
            if (totalPages > 1 || currentPage > 0) {
                html += `
                    <div class="pagination-bar">
                        <span class="info">Page ${currentPage + 1} of ${totalPages}</span>
                        <div>
                            <button class="btn btn-outline-secondary btn-sm" onclick="goToPage(${currentPage - 1})" ${currentPage === 0 ? 'disabled' : ''}>← Prev</button>
                            <button class="btn btn-outline-primary btn-sm" onclick="goToPage(${currentPage + 1})" ${currentPage >= totalPages - 1 ? 'disabled' : ''}>Next →</button>
                        </div>
                    </div>
                `;
            }
            container.innerHTML = html;
        }

        function goToPage(page) {
            const totalPages = Math.ceil(totalRecords / 100);
            if (page < 0 || page >= totalPages) return;
            currentPage = page;
            loadTableData();
        }

        function applyFilter() {
            const column = document.getElementById('filterColumnSelect').value;
            const value = document.getElementById('filterInputValue').value.trim();
            if (!value) { filteredData = []; renderTableData(); return; }
            if (column === 'all') {
                filteredData = tableData.filter(row => {
                    for (let key in row) {
                        if (row[key] !== null && String(row[key]).toLowerCase().includes(value.toLowerCase())) return true;
                    }
                    return false;
                });
            } else {
                filteredData = tableData.filter(row => {
                    if (row[column] === null) return false;
                    return String(row[column]).toLowerCase().includes(value.toLowerCase());
                });
            }
            renderTableData();
        }

        function clearFilter() {
            document.getElementById('filterInputValue').value = '';
            filteredData = [];
            renderTableData();
        }

        // CRUD Operations
        async function createTable() {
            const tableName = document.getElementById('newTableName').value.trim();
            const columns = document.getElementById('newTableColumns').value.trim();
            if (!tableName || !columns) { showAlert('Table name and columns required', 'warning'); return; }
            const result = await apiRequest('createTable', { tableName, columns });
            if (result.success) {
                showAlert(`Table "${tableName}" created`, 'success');
                closeModal('createTableModal');
                document.getElementById('newTableName').value = '';
                document.getElementById('newTableColumns').value = '';
                await loadTables();
                currentTable = tableName;
                await selectTable(tableName);
            }
        }

        async function dropTable() {
            if (!currentTable) { showAlert('Select a table first', 'warning'); return; }
            if (!confirm(`Drop table "${currentTable}"?`)) return;
            const result = await apiRequest('dropTable', { table: currentTable });
            if (result.success) {
                showAlert(`Table "${currentTable}" dropped`, 'warning');
                currentTable = null;
                document.getElementById('tableContent').innerHTML = `
                    <div class="text-center text-muted py-5">
                        <div style="font-size:48px; margin-bottom:15px;">🗄️</div>
                        <p>Select a table from the left to manage it</p>
                    </div>
                `;
                await loadTables();
            }
        }

        function showRenameTableModal() {
            if (!currentTable) { showAlert('Select a table first', 'warning'); return; }
            document.getElementById('renameTableName').value = currentTable;
            openModal('renameTableModal');
        }

        async function renameTable() {
            const newName = document.getElementById('renameTableName').value.trim();
            if (!newName) { showAlert('New name required', 'warning'); return; }
            if (newName === currentTable) { closeModal('renameTableModal'); return; }
            const result = await apiRequest('renameTable', { oldName: currentTable, newName });
            if (result.success) {
                showAlert(`Table renamed to "${newName}"`, 'success');
                closeModal('renameTableModal');
                currentTable = newName;
                await loadTables();
                await selectTable(newName);
            }
        }

        async function truncateTable() {
            if (!currentTable) { showAlert('Select a table first', 'warning'); return; }
            if (!confirm(`Truncate all data from "${currentTable}"?`)) return;
            const result = await apiRequest('truncateTable', { table: currentTable });
            if (result.success) {
                showAlert(`Table "${currentTable}" truncated`, 'warning');
                currentPage = 0;
                await loadTableData();
            }
        }

        function showAddColumnModal() {
            if (!currentTable) { showAlert('Select a table first', 'warning'); return; }
            document.getElementById('addColumnDef').value = '';
            openModal('addColumnModal');
        }

        async function addColumn() {
            const columnDef = document.getElementById('addColumnDef').value.trim();
            if (!columnDef) { showAlert('Column definition required', 'warning'); return; }
            const result = await apiRequest('addColumn', { table: currentTable, columnDef });
            if (result.success) {
                showAlert('Column added', 'success');
                closeModal('addColumnModal');
                await loadTableInfo();
            }
        }

        function showKeyModal() {
            if (!currentTable) { showAlert('Select a table first', 'warning'); return; }
            document.getElementById('uniqueColumn').value = '';
            document.getElementById('dropKeyName').value = '';
            openModal('keyModal');
        }

        async function setUniqueKey() {
            const column = document.getElementById('uniqueColumn').value.trim();
            if (!column) { showAlert('Column name required', 'warning'); return; }
            const result = await apiRequest('setUniqueKey', { table: currentTable, column });
            if (result.success) {
                showAlert(`UNIQUE KEY set on "${column}"`, 'success');
                document.getElementById('uniqueColumn').value = '';
                await loadTableInfo();
            }
        }

        async function dropKey() {
            const keyName = document.getElementById('dropKeyName').value.trim();
            if (!keyName) { showAlert('Key name required', 'warning'); return; }
            const result = await apiRequest('dropKey', { table: currentTable, keyName });
            if (result.success) {
                showAlert(`Key "${keyName}" dropped`, 'info');
                document.getElementById('dropKeyName').value = '';
                await loadTableInfo();
            }
        }

        function showInsertRowModal() {
            if (!currentTable || !currentColumns.length) {
                showAlert('Select a valid table', 'warning');
                return;
            }
            const container = document.getElementById('insertRowFields');
            let html = `<input type="hidden" name="table" value="${currentTable}">`;
            html += `<input type="hidden" id="insertSkipColumns" name="skip_columns" value="">`;
            currentColumns.forEach(col => {
                if (col.Extra && col.Extra.includes('auto_increment')) {
                    html += `<div class="field-row text-muted" style="padding:6px 0;">
                        <span style="min-width:120px;">${col.Field}</span>
                        <span style="flex:1;font-size:13px;">(auto-increment)</span>
                    </div>`;
                } else {
                    html += `
                        <label class="form-label">${col.Field} <small class="text-muted">(${col.Type})</small></label>
                        <div class="field-row">
                            <input class="form-control" name="col_${col.Field}" placeholder="Enter value" id="input_${col.Field}">
                            <div class="skip-checkbox" id="skip_${col.Field}">
                                <input type="checkbox" id="skip_check_${col.Field}" onchange="toggleSkip('${col.Field}')">
                                <label for="skip_check_${col.Field}">Skip</label>
                            </div>
                        </div>
                    `;
                }
            });
            container.innerHTML = html;
            openModal('insertRowModal');
            updateSkipColumnsList();
        }

        function toggleSkip(columnName) {
            const checkbox = document.getElementById(`skip_check_${columnName}`);
            const input = document.getElementById(`input_${columnName}`);
            if (checkbox.checked) {
                input.disabled = true;
                input.style.opacity = '0.5';
                input.style.background = '#f0f0f0';
            } else {
                input.disabled = false;
                input.style.opacity = '1';
                input.style.background = '';
            }
            updateSkipColumnsList();
        }

        function updateSkipColumnsList() {
            const skipInput = document.getElementById('insertSkipColumns');
            const checkboxes = document.querySelectorAll('#insertRowFields .skip-checkbox input[type="checkbox"]');
            const skipped = [];
            checkboxes.forEach(cb => { if (cb.checked) skipped.push(cb.id.replace('skip_check_', '')); });
            skipInput.value = JSON.stringify(skipped);
        }

        async function insertRow() {
            const form = document.getElementById('insertRowFields');
            const inputs = form.querySelectorAll('input');
            const data = { table: currentTable };
            inputs.forEach(input => {
                if (input.name.startsWith('col_')) data[input.name] = input.value;
                else if (input.name === 'skip_columns') data.skip_columns = input.value;
            });
            const result = await apiRequest('insertRow', data);
            if (result.success) {
                showAlert('Row inserted', 'success');
                closeModal('insertRowModal');
                currentPage = 0;
                await loadTableData();
            }
        }

        function showEditRowModal(primaryKeyValue) {
            if (!currentTable || !currentColumns.length) {
                showAlert('Select a valid table', 'warning');
                return;
            }
            const primaryKey = currentColumns.find(c => c.Key === 'PRI')?.Field || 'id';
            const row = tableData.find(r => String(r[primaryKey]) === String(primaryKeyValue));
            if (!row) { showAlert('Row not found', 'danger'); return; }

            const container = document.getElementById('editRowFields');
            let html = `
                <input type="hidden" name="table" value="${currentTable}">
                <input type="hidden" name="whereCol" value="${primaryKey}">
                <input type="hidden" name="whereVal" value="${primaryKeyValue}">
                <input type="hidden" id="editSkipColumns" name="skip_columns" value="">
            `;
            currentColumns.forEach(col => {
                const value = row[col.Field] !== null ? row[col.Field] : '';
                if (col.Extra && col.Extra.includes('auto_increment')) {
                    html += `<div class="field-row text-muted" style="padding:6px 0;">
                        <span style="min-width:120px;">${col.Field}</span>
                        <span style="flex:1;font-size:13px;">(auto-increment) value: ${value}</span>
                    </div>`;
                } else {
                    html += `
                        <label class="form-label">${col.Field} <small class="text-muted">(${col.Type})</small></label>
                        <div class="field-row">
                            <input class="form-control" name="col_${col.Field}" value="${value}" id="edit_input_${col.Field}">
                            <div class="skip-checkbox" id="edit_skip_${col.Field}">
                                <input type="checkbox" id="edit_skip_check_${col.Field}" onchange="toggleEditSkip('${col.Field}')">
                                <label for="edit_skip_check_${col.Field}">Skip</label>
                            </div>
                        </div>
                    `;
                }
            });
            container.innerHTML = html;
            openModal('editRowModal');
            updateEditSkipColumnsList();
        }

        function toggleEditSkip(columnName) {
            const checkbox = document.getElementById(`edit_skip_check_${columnName}`);
            const input = document.getElementById(`edit_input_${columnName}`);
            if (checkbox.checked) {
                input.disabled = true;
                input.style.opacity = '0.5';
                input.style.background = '#f0f0f0';
            } else {
                input.disabled = false;
                input.style.opacity = '1';
                input.style.background = '';
            }
            updateEditSkipColumnsList();
        }

        function updateEditSkipColumnsList() {
            const skipInput = document.getElementById('editSkipColumns');
            const checkboxes = document.querySelectorAll('#editRowFields .skip-checkbox input[type="checkbox"]');
            const skipped = [];
            checkboxes.forEach(cb => { if (cb.checked) skipped.push(cb.id.replace('edit_skip_check_', '')); });
            skipInput.value = JSON.stringify(skipped);
        }

        async function updateRow() {
            const form = document.getElementById('editRowFields');
            const inputs = form.querySelectorAll('input');
            const data = { table: currentTable };
            inputs.forEach(input => {
                if (input.name.startsWith('col_')) data[input.name] = input.value;
                else if (['whereCol', 'whereVal', 'skip_columns'].includes(input.name)) data[input.name] = input.value;
            });
            const result = await apiRequest('updateRow', data);
            if (result.success) {
                showAlert('Row updated', 'success');
                closeModal('editRowModal');
                currentPage = 0;
                await loadTableData();
            }
        }

        async function deleteRow(primaryKeyValue) {
            if (!currentTable) { showAlert('Select a table first', 'warning'); return; }
            if (!confirm('Delete this row?')) return;
            const primaryKey = currentColumns.find(c => c.Key === 'PRI')?.Field || 'id';
            const result = await apiRequest('deleteRow', { table: currentTable, whereCol: primaryKey, whereVal: primaryKeyValue });
            if (result.success) {
                showAlert('Row deleted', 'danger');
                currentPage = 0;
                await loadTableData();
            }
        }

        // Export/Import
        function showCreateTableModal() {
            document.getElementById('newTableName').value = '';
            document.getElementById('newTableColumns').value = 'id INTEGER PRIMARY KEY AUTOINCREMENT,\nname TEXT NOT NULL';
            openModal('createTableModal');
        }

        async function showExportModal() {
            if (allTableNames.length === 0) {
                const result = await apiRequest('getTables');
                if (result.success && result.data) allTableNames = result.data;
                else { showAlert('Failed to load tables', 'danger'); return; }
            }
            const container = document.getElementById('tableCheckboxList');
            container.innerHTML = allTableNames.map(table => `
                <div class="checkbox-item">
                    <input type="checkbox" id="table_${table}" value="${table}" onchange="updateSelectedCount()">
                    <label for="table_${table}">${table}</label>
                </div>
            `).join('');
            document.getElementById('selectAllTables').checked = false;
            updateSelectedCount();
            openModal('exportModal');
        }

        function toggleAllTables() {
            const checked = document.getElementById('selectAllTables').checked;
            document.querySelectorAll('#tableCheckboxList input[type="checkbox"]').forEach(cb => cb.checked = checked);
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checked = document.querySelectorAll('#tableCheckboxList input[type="checkbox"]:checked').length;
            document.getElementById('selectedCount').textContent = checked;
        }

        async function exportSQL() {
            const selectedTables = [];
            document.querySelectorAll('#tableCheckboxList input[type="checkbox"]:checked').forEach(cb => selectedTables.push(cb.value));
            if (selectedTables.length === 0) { showAlert('Select at least one table', 'warning'); return; }
            closeModal('exportModal');
            const result = await apiRequest('exportSQL', { tables_with_data: JSON.stringify(selectedTables) });
            if (result.success) showAlert('Export started!', 'success');
        }

        function showImportModal() {
            document.getElementById('sqlFileInput').value = '';
            document.getElementById('fileSelectedInfo').classList.remove('show');
            document.getElementById('selectedFileName').textContent = '';
            document.getElementById('sqlPasteInput').value = '';
            selectedFile = null;
            openModal('importModal');
        }

        function switchImportTab(tab) {
            document.querySelectorAll('.import-tab').forEach(el => el.classList.remove('active'));
            document.querySelector(`.import-tab[data-tab="${tab}"]`).classList.add('active');
            document.querySelectorAll('.import-panel').forEach(el => el.classList.remove('active'));
            document.getElementById(`importPanel${tab.charAt(0).toUpperCase() + tab.slice(1)}`).classList.add('active');
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                selectedFile = file;
                document.getElementById('selectedFileName').textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                document.getElementById('fileSelectedInfo').classList.add('show');
            }
        }

        async function importSQL() {
            const activeTab = document.querySelector('.import-tab.active');
            const tabType = activeTab ? activeTab.dataset.tab : 'file';
            const btn = document.querySelector('#importModal .modal-footer .btn-primary');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Importing...';
            btn.disabled = true;

            try {
                let result;
                if (tabType === 'file') {
                    if (!selectedFile) { showAlert('Select a SQL file', 'warning'); return; }
                    const formData = new FormData();
                    formData.append('action', 'importSQL');
                    formData.append('import_type', 'file');
                    formData.append('sql_file', selectedFile);
                    const response = await fetch(window.location.href, { method: 'POST', body: formData });
                    result = await response.json();
                } else {
                    const query = document.getElementById('sqlPasteInput').value.trim();
                    if (!query) { showAlert('Paste a SQL query', 'warning'); return; }
                    result = await apiRequest('importSQL', { import_type: 'paste', sql_query: query });
                }
                if (result.success) {
                    showAlert(result.message || 'Import completed!', 'success');
                    closeModal('importModal');
                    await loadTables();
                    if (currentTable) { await loadTableInfo(); await loadTableData(); }
                } else {
                    showAlert(result.message || 'Import failed', 'danger');
                }
            } catch (error) {
                showAlert('Import error: ' + error.message, 'danger');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        async function refreshAll() {
            await loadTables();
            if (currentTable) {
                currentPage = 0;
                await loadTableInfo();
                await loadTableData();
            }
            showAlert('Refreshed', 'info');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', async function() {
            await loadTables();
            if (allTableNames.length > 0) {
                currentTable = allTableNames[0];
                await selectTable(currentTable);
            }
        });
    </script>
</body>
</html>