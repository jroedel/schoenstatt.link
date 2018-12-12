<?php
$config = include_once '../config/autoload/local.php';
$dbConfig = $config['db'];
// Configuration:
define('DBDRIVER', 'mysql');  // E.g. 'mysql', 'pgsql', 'sqlite'
if (!extension_loaded('pdo')) {
    exit('PDO extension required.');
}
try {
    // Setup connection to database.
    $pdo = new PDO($dbConfig['dsn'], $dbConfig['username'], $dbConfig['password'], $dbConfig['driver_options']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (DBDRIVER === 'mysql') {
        // Make use of emulated prepared statement in PDO if on old mysql server version.
        define('MYSQL_VERSION_NATIVE_PREPARE_CACHE_SUPPORT', '5.1.17');
        $emulate_preparedstmts = version_compare($pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            MYSQL_VERSION_NATIVE_PREPARE_CACHE_SUPPORT,
            '<');
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate_preparedstmts);
    }
} catch (PDOException $pdo_exc) {
    error_log($pdo_exc->getMessage());
    exit('Database connection error.');
}
// Get the raw POST data
$data = file_get_contents('php://input');
// Only continue if it’s valid JSON that is not just `null`, `0`, `false` or an
// empty string, i.e. if it could be a CSP violation report.
if ($data = json_decode($data, true)) {
    $full_report = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    $document_uri = '';  // NULL not allowed by orginal schema.
    if (isset($data['csp-report']['document-uri'])) {
        $document_uri = $data['csp-report']['document-uri'];
    }
    $referrer = '';
    if (isset($data['csp-report']['referrer'])) {
        $referrer = $data['csp-report']['referrer'];
    }
    $violated_directive = '';
    if (isset($data['csp-report']['violated-directive'])) {
        $violated_directive = $data['csp-report']['violated-directive'];
    }
    $original_policy = '';
    if (isset($data['csp-report']['original-policy'])) {
        $original_policy = $data['csp-report']['original-policy'];
    }
    $blocked_uri = '';
    if (isset($data['csp-report']['blocked-uri'])) {
        $blocked_uri = $data['csp-report']['blocked-uri'];
    }
    $source_file = '';
    if (isset($data['csp-report']['source-file'])) {
        $source_file = $data['csp-report']['source-file'];
    }
    // Legacy table uses MEDIUMTEXT for line-number, column-number and status-code. Should be INT.
    $line_number = '';
    if (isset($data['csp-report']['line-number'])) {
        $line_number = $data['csp-report']['line-number'];
    }
    $column_number = '';
    if (isset($data['csp-report']['column-number'])) {
        $column_number = $data['csp-report']['column-number'];
    }
    $status_code = '';
    if (isset($data['csp-report']['status-code'])) {
        $status_code = $data['csp-report']['status-code'];
    }
    // Add to database:
    $stmtaddcspviolation = $pdo->prepare('INSERT INTO `csp_reports`
 (document_uri, full_report, referrer, violated_directive, original_policy, blocked_uri,
 source_file, line_number, column_number, status_code) VALUES (:document_uri, :full_report,
 :referrer, :violated_directive, :original_policy, :blocked_uri, :source_file, :line_number,
 :column_number, :status_code);');
    $stmtaddcspviolation->bindParam(':document_uri', $document_uri, PDO::PARAM_STR);
    $stmtaddcspviolation->bindParam(':full_report', $full_report, PDO::PARAM_STR);
    $stmtaddcspviolation->bindParam(':referrer', $referrer, PDO::PARAM_STR);
    $stmtaddcspviolation->bindParam(':violated_directive', $violated_directive, PDO::PARAM_STR);
    $stmtaddcspviolation->bindParam(':original_policy', $original_policy, PDO::PARAM_STR);
    $stmtaddcspviolation->bindParam(':blocked_uri', $blocked_uri, PDO::PARAM_STR);
    $stmtaddcspviolation->bindParam(':source_file', $source_file, PDO::PARAM_STR);
    $stmtaddcspviolation->bindParam(':line_number', $line_number, PDO::PARAM_STR);
    $stmtaddcspviolation->bindParam(':column_number', $column_number, PDO::PARAM_STR);
    $stmtaddcspviolation->bindParam(':status_code', $status_code, PDO::PARAM_STR);
    if (!$stmtaddcspviolation->execute()) {
        exit('Could not add CSP violation report.');
    }
}
