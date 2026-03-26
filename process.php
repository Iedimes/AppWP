<?php

header('Content-Type: application/json');

$config = include 'config.php';
$apiKey = $config['openai_api_key'] ?? '';
$groqKey = $config['groq_api_key'] ?? '';

// Configuración MariaDB/MySQL desde config.php
$dbhost = $config['db_host'] ?? 'localhost';
$dbuser = $config['db_user'] ?? 'root';
$dbpass = $config['db_pass'] ?? '';
$dbname = $config['db_name'] ?? 'field_data';

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$user = trim($input['user'] ?? 'anonimo');

$db = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

if ($db->connect_error) {
    die(json_encode(['response' => 'Error de conexión a la base de datos']));
}

// Tabla de usuarios
$db->query("CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telefono VARCHAR(20) UNIQUE,
    nombre VARCHAR(100),
    rol VARCHAR(20) DEFAULT 'user',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Insertar usuarios ficticios si no existen
$db->query("INSERT IGNORE INTO usuarios (telefono, nombre, rol) VALUES 
    ('0981517309', 'Juan Chavez', 'admin'),
    ('0972123456', 'Maria Lopez', 'user'),
    ('0983765432', 'Pedro Gomez', 'user')
");

$db->query("CREATE TABLE IF NOT EXISTS registros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    descripcion TEXT,
    cantidad REAL,
    unidad VARCHAR(20),
    lugar VARCHAR(100),
    monto REAL,
    cultivo VARCHAR(100),
    animal VARCHAR(100),
    item VARCHAR(100),
    usuario VARCHAR(50),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Verificar login de usuario
$action = $input['action'] ?? '';
if ($action === 'login') {
    $telefono = trim($input['telefono'] ?? '');
    $result = $db->query("SELECT nombre, rol FROM usuarios WHERE telefono = '$telefono'");
    $row = $result->fetch_assoc();
    echo json_encode([
        'success' => !!$row,
        'nombre' => $row['nombre'] ?? null,
        'rol' => $row['rol'] ?? 'user'
    ]);
    $db->close();
    exit;
}

// Registrar nuevo usuario
if ($action === 'registrar') {
    $telefono = trim($input['telefono'] ?? '');
    $nombre = trim($input['nombre'] ?? '');
    $db->query("INSERT INTO usuarios (telefono, nombre, rol) VALUES ('$telefono', '$nombre', 'user')");
    echo json_encode(['success' => true]);
    $db->close();
    exit;
}

function getHistorial($db, $limit = 10) {
    $result = $db->query("SELECT * FROM registros ORDER BY fecha DESC LIMIT $limit");
    $registros = [];
    while ($row = $result->fetch_assoc()) {
        $registros[] = [
            'tipo' => $row['tipo'],
            'cultivo' => $row['cultivo'],
            'animal' => $row['animal'],
            'item' => $row['item'],
            'cantidad' => $row['cantidad'],
            'monto' => $row['monto'],
            'lugar' => $row['lugar'],
            'fecha' => $row['fecha']
        ];
    }
    return $registros;
}

function getStock($db) {
    $result = $db->query("SELECT item, SUM(cantidad) as total FROM registros WHERE tipo = 'compra' AND item IS NOT NULL GROUP BY item");
    $stock = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['total'] > 0) {
            $stock[] = $row['item'] . ': ' . $row['total'];
        }
    }
    return $stock;
}

function getGastos($db) {
    $result = $db->query("SELECT SUM(monto) as total FROM registros WHERE tipo = 'compra'");
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function processMessageOffline($msg, $db, $user) {
    $msgLower = strtolower($msg);
    
    if (strpos($msgLower, 'ayuda') !== false) {
        return 'Puedo ayudarte con:<br><br>• <strong>Siembra:</strong> "Sembramos maiz en lote Norte"<br>• <strong>Ganaderia:</strong> "Nacieron 2 terneros en potrero 5"<br>• <strong>Compras $:</strong> "Compre gasoil $150000"<br>• <strong>Compras lts:</strong> "Compre 200 litros de gasoil"<br>• <strong>Kms:</strong> "Hice 50 kms"<br>• <strong>Stock:</strong> "¿cual es mi stock?"<br>• <strong>Gastos:</strong> "¿cuanto gaste?"';
    }
    
    if (strpos($msgLower, 'hola') !== false) {
        return '¡Hola! ¿En qué puedo ayudarte hoy?';
    }
    
    if (strpos($msgLower, 'sembramos') !== false || strpos($msgLower, 'siembra') !== false) {
        $cant = preg_match('/(\d+)/', $msg, $m) ? $m[1] : 0;
        $cult = preg_match('/(maiz|trigo|soja|avena|sorgo|girasol)/i', $msg, $m) ? $m[1] : 'cultivo';
        $lugar = preg_match('/(lote|norte|sur|este|oeste|potrero|campo)\s*\w*/i', $msg, $m) ? $m[0] : 'lote';
        
        $db->query("INSERT INTO registros (tipo, cantidad, unidad, lugar, cultivo, usuario) VALUES ('siembra', $cant, 'ha', '$lugar', '$cult', '$user')");
        
        return "✅ <strong>SIEMBRA REGISTRADA</strong><div class='data-preview'>Cultivo: $cult<br>Cantidad: $cant ha<br>Lugar: $lugar</div>";
    }
    
    if (strpos($msgLower, 'nacieron') !== false || strpos($msgLower, 'nacimiento') !== false) {
        $cant = preg_match('/(\d+)/', $msg, $m) ? $m[1] : 0;
        $tipo = preg_match('/(terneros|vacas|caballos|ovejas|cabritos|cerdos)/i', $msg, $m) ? $m[1] : 'animales';
        $lugar = preg_match('/(potrero|lote|campo)\s*\w*/i', $msg, $m) ? $m[0] : 'potrero';
        
        $db->query("INSERT INTO registros (tipo, cantidad, lugar, animal, usuario) VALUES ('nacimiento', $cant, '$lugar', '$tipo', '$user')");
        
        return "✅ <strong>NACIMIENTO REGISTRADO</strong><div class='data-preview'>Cantidad: $cant $tipo<br>Lugar: $lugar</div>";
    }
    
    if (strpos($msgLower, 'compre') !== false || strpos($msgLower, 'compramos') !== false) {
        $item = preg_match('/(gasoil|diesel|semilla|alambre|fertilizante|bolsa|veneno|vacuna|nafta|aceite)/i', $msg, $m) ? $m[1] : 'insumo';
        
        if (preg_match('/(\d+)\s*(litros?|lts?|lt)/i', $msg, $litros)) {
            $cant = $litros[1];
            $db->query("INSERT INTO registros (tipo, cantidad, unidad, item, usuario) VALUES ('compra', $cant, 'litros', '$item', '$user')");
            return "✅ <strong>COMPRA REGISTRADA</strong><div class='data-preview'>Item: $item<br>Cantidad: $cant litros</div>";
        } else {
            $precio = preg_match('/\$?([\d,.]+)/', $msg, $m) ? str_replace(',', '', $m[1]) : 0;
            $db->query("INSERT INTO registros (tipo, monto, item, usuario) VALUES ('compra', $precio, '$item', '$user')");
            return "✅ <strong>COMPRA REGISTRADA</strong><div class='data-preview'>Item: $item<br>Monto: \$" . number_format($precio) . "</div>";
        }
    }
    
    if (strpos($msgLower, 'kms') !== false || strpos($msgLower, 'kilometros') !== false || (strpos($msgLower, 'km') !== false && strlen($msgLower) < 20)) {
        $cant = preg_match('/(\d+)/', $msg, $m) ? $m[1] : 0;
        $db->query("INSERT INTO registros (tipo, cantidad, unidad, descripcion, usuario) VALUES ('trabajo', $cant, 'kms', 'maquinaria', '$user')");
        return "✅ <strong>TRABAJO REGISTRADO</strong><div class='data-preview'>Kilometros: $cant kms</div>";
    }
    
    if (strpos($msgLower, 'stock') !== false || strpos($msgLower, 'inventario') !== false) {
        $result = $db->query("SELECT item, SUM(cantidad) as total FROM registros WHERE tipo = 'compra' AND item IS NOT NULL GROUP BY item");
        $stock = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['total'] > 0) $stock[] = $row['item'] . ': ' . $row['total'];
        }
        return empty($stock) ? '📦 <strong>STOCK</strong><div class="data-preview">No hay registros</div>' : '📦 <strong>STOCK ACTUAL</strong><div class="data-preview">' . implode('<br>', $stock) . '</div>';
    }
    
    if (strpos($msgLower, 'gaste') !== false || strpos($msgLower, 'gastos') !== false || strpos($msgLower, 'gastamos') !== false) {
        $result = $db->query("SELECT SUM(monto) as total FROM registros WHERE tipo = 'compra'");
        $row = $result->fetch_assoc();
        $total = $row['total'] ?? 0;
        return '💰 <strong>GASTOS</strong><div class="data-preview">Total: \$' . number_format($total) . '</div>';
    }
    
    if (strpos($msgLower, 'mostrar') !== false && strpos($msgLower, 'registro') !== false) {
        $result = $db->query("SELECT * FROM registros ORDER BY fecha DESC LIMIT 10");
        $registros = [];
        while ($row = $result->fetch_assoc()) {
            $registros[] = $row['tipo'] . ' - ' . ($row['cultivo'] ?? $row['animal'] ?? $row['item'] ?? '') . ' (' . $row['fecha'] . ')';
        }
        return empty($registros) ? 'No hay registros aún.' : '📋 <strong>ULTIMOS REGISTROS</strong><div class="data-preview">' . implode('<br>', $registros) . '</div>';
    }
    
    return null;
}

// Primero intentar con modo offline
$msg = processMessageOffline($message, $db, $user);

if ($msg === null && $groqKey && strlen($groqKey) > 10) {
    $historial = getHistorial($db);
    $stock = getStock($db);
    $gastos = getGastos($db);
    
    $systemPrompt = "Sos Field Data, asistente de gestion agropecuaria.
Cuando el usuario reporta una ACCION (Sembramos, Compramos, Nacieron) - SIEMPRE es un REGISTRO.
Cuando pregunta sobre stock, gastos o historial - es CONSULTA.

Ejemplos:
- \"Sembramos 50 ha de maiz\" = {\"accion\": \"registrar\", \"tipo\": \"siembra\", \"datos\": {\"cultivo\": \"maiz\", \"cantidad\": 50}}
- \"Compre gasoil 50000\" = {\"accion\": \"registrar\", \"tipo\": \"compra\", \"datos\": {\"item\": \"gasoil\", \"monto\": 50000}}
- \"¿cuanto gaste?\" = {\"accion\": \"consultar\", \"tipo\": \"gastos\"}

Respondé SOLO con JSON.";

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'llama-3.1-8b-instant',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message]
        ],
        'temperature' => 0
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groqKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        $result = json_decode($content, true);
        
        if ($result && isset($result['accion']) && $result['accion'] === 'registrar') {
            $tipo = $result['tipo'] ?? '';
            $datos = $result['datos'] ?? [];
            
            if ($tipo === 'siembra') {
                $db->query("INSERT INTO registros (tipo, cantidad, unidad, lugar, cultivo, usuario) VALUES ('siembra', " . ($datos['cantidad'] ?? 0) . ", 'ha', '" . ($datos['lugar'] ?? 'lote') . "', '" . ($datos['cultivo'] ?? 'cultivo') . "', '$user')");
                $msg = "✅ <strong>SIEMBRA REGISTRADA</strong><div class='data-preview'>Cultivo: " . ($datos['cultivo'] ?? 'cultivo') . "<br>Cantidad: " . ($datos['cantidad'] ?? 0) . "</div>";
            } elseif ($tipo === 'nacimiento') {
                $db->query("INSERT INTO registros (tipo, cantidad, lugar, animal, usuario) VALUES ('nacimiento', " . ($datos['cantidad'] ?? 0) . ", '" . ($datos['lugar'] ?? 'potrero') . "', '" . ($datos['animal'] ?? 'animales') . "', '$user')");
                $msg = "✅ <strong>NACIMIENTO REGISTRADO</strong><div class='data-preview'>Cantidad: " . ($datos['cantidad'] ?? 0) . "</div>";
            } elseif ($tipo === 'compra') {
                $db->query("INSERT INTO registros (tipo, monto, item, usuario) VALUES ('compra', " . ($datos['monto'] ?? 0) . ", '" . ($datos['item'] ?? 'insumo') . "', '$user')");
                $msg = "✅ <strong>COMPRA REGISTRADA</strong><div class='data-preview'>Item: " . ($datos['item'] ?? 'insumo') . "<br>Monto: \$" . number_format($datos['monto'] ?? 0) . "</div>";
            }
        } elseif ($result && isset($result['accion']) && $result['accion'] === 'consultar') {
            if ($result['tipo'] === 'gastos') {
                $gastos = getGastos($db);
                $msg = '💰 <strong>GASTOS</strong><div class="data-preview">Total: \$' . number_format($gastos) . '</div>';
            } elseif ($result['tipo'] === 'stock') {
                $stock = getStock($db);
                $msg = empty($stock) ? 'Sin stock' : '📦 <strong>STOCK</strong><div class="data-preview">' . implode('<br>', $stock) . '</div>';
            }
        }
    }
}

if ($msg === null) {
    $msg = 'No entendí tu mensaje. Escribí <strong>ayuda</strong> para ver qué podés registrar.';
}

$db->close();
echo json_encode(['response' => $msg]);