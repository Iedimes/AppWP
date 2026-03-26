<?php
// Test de conexión a MariaDB

header('Content-Type: text/plain');

$config = include 'config.php';

echo "=== CONFIGURACIÓN ===\n";
echo "Host: " . ($config['db_host'] ?? 'localhost') . "\n";
echo "User: " . ($config['db_user'] ?? 'root') . "\n";
echo "DB Name: " . ($config['db_name'] ?? 'field_data') . "\n";
echo "Groq Key: " . (empty($config['groq_api_key']) ? 'VACÍA' : 'CONFIGURADA') . "\n\n";

$dbhost = $config['db_host'] ?? 'localhost';
$dbuser = $config['db_user'] ?? 'root';
$dbpass = $config['db_pass'] ?? '';
$dbname = $config['db_name'] ?? 'field_data';

echo "=== CONEXIÓN ===\n";

$db = @new mysqli($dbhost, $dbuser, $dbpass);

if ($db->connect_error) {
    echo "ERROR: " . $db->connect_error . "\n";
} else {
    echo "✅ CONEXIÓN EXITOSA\n\n";
    
    // Crear base de datos si no existe
    $db->query("CREATE DATABASE IF NOT EXISTS $dbname");
    $db->select_db($dbname);
    
    echo "=== TABLAS ===\n";
    $result = $db->query("SHOW TABLES");
    if ($result) {
        while ($row = $result->fetch_array()) {
            echo "- " . $row[0] . "\n";
        }
    }
    
    echo "\n=== REGISTROS ===\n";
    $result = $db->query("SELECT COUNT(*) as total FROM registros");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Total registros: " . $row['total'] . "\n";
    }
    
    if (!$db->connect_error) {
        $db->close();
    }
}