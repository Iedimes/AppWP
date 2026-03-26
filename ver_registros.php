<?php
// Ver registros en la base de datos

$config = include 'config.php';

$dbhost = $config['db_host'] ?? 'localhost';
$dbuser = $config['db_user'] ?? 'root';
$dbpass = $config['db_pass'] ?? '';
$dbname = $config['db_name'] ?? 'field_data';

$db = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

if ($db->connect_error) {
    die("Error de conexión: " . $db->connect_error);
}

$result = $db->query("SELECT * FROM registros ORDER BY fecha DESC");

echo "<h1>Field Data - Registros</h1>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Tipo</th><th>Cultivo/Animal/Item</th><th>Cantidad</th><th>Monto</th><th>Lugar</th><th>Fecha</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['tipo'] . "</td>";
    echo "<td>" . ($row['cultivo'] ?? $row['animal'] ?? $row['item'] ?? '-') . "</td>";
    echo "<td>" . ($row['cantidad'] ?? '-') . "</td>";
    echo "<td>" . ($row['monto'] ? '$' . number_format($row['monto']) : '-') . "</td>";
    echo "<td>" . ($row['lugar'] ?? '-') . "</td>";
    echo "<td>" . $row['fecha'] . "</td>";
    echo "</tr>";
}

echo "</table>";

$db->close();