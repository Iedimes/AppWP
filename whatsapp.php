<?php
// WhatsApp Webhook para Field Data
// Usar con Twilio o Meta Business API

header('Content-Type: application/json');

$config = include 'config.php';
$groqKey = $config['groq_api_key'] ?? '';

$input = json_decode(file_get_contents('php://input'), true);

// Detectar si viene de WhatsApp (Twilio) o mensaje directo
$message = $input['Body'] ?? $input['message'] ?? $_POST['message'] ?? '';
$from = $input['From'] ?? $input['from'] ?? 'unknown';

// Si es Twilio, el mensaje viene como 'Body'
if (isset($input['MessageSid'])) {
    $message = $input['Body'] ?? '';
    $from = $input['From'] ?? '';
}

// Responder a Twilio webhook
if (isset($_GET['hub_verify_token'])) {
    $token = $_GET['hub_verify_token'];
    $challenge = $_GET['hub_challenge'];
    
    if ($token === 'MI_TOKEN_DE_VERIFICACION') {
        echo $challenge;
        exit;
    }
    http_response_code(403);
    exit;
}

// Procesar mensaje
if ($message) {
    $response = processMessage($message, $groqKey);
    
    // Si es Twilio, responder en formato TwiML
    if (isset($input['MessageSid'])) {
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Message>' . strip_tags($response) . '</Message>
</Response>';
    } else {
        echo json_encode(['response' => $response]);
    }
} else {
    echo json_encode(['status' => 'ok']);
}

function processMessage($msg, $groqKey) {
    $msgLower = strtolower($msg);
    
    // Abrir base de datos
    $db = new SQLite3('field_data.db');
    $db->exec("CREATE TABLE IF NOT EXISTS registros (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL,
        descripcion TEXT,
        cantidad REAL,
        unidad TEXT,
        lugar TEXT,
        monto REAL,
        cultivo TEXT,
        animal TEXT,
        item TEXT,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Modo offline (regex)
    if (strpos($msgLower, 'ayuda') !== false) {
        return "Field Data - Comandos:\n\n" .
            "• Siembra: 'Sembramos 50 ha de maiz'\n" .
            "• Nacimiento: 'Nacieron 2 terneros'\n" .
            "• Compra $: 'Compre gasoil 150000'\n" .
            "• Compra lt: 'Compre 200 litros de gasoil'\n" .
            "• Kms: 'Hice 50 kms'\n" .
            "• Stock: 'cual es mi stock'\n" .
            "• Gastos: 'cuanto gaste'";
    }
    
    if (strpos($msgLower, 'sembramos') !== false || strpos($msgLower, 'siembra') !== false) {
        $cant = preg_match('/(\d+)/', $msg, $m) ? $m[1] : 0;
        $cult = preg_match('/(maiz|trigo|soja|avena|sorgo|girasol)/i', $msg, $m) ? $m[1] : 'cultivo';
        $lugar = preg_match('/(lote|norte|sur|este|oeste|potrero|campo)\s*\w*/i', $msg, $m) ? $m[0] : 'lote';
        
        $db->exec("INSERT INTO registros (tipo, cantidad, unidad, lugar, cultivo) VALUES ('siembra', $cant, 'ha', '$lugar', '$cult')");
        return "✅ SIEMBRA REGISTRADA\nCultivo: $cult\nCantidad: $cant ha\nLugar: $lugar";
    }
    
    if (strpos($msgLower, 'nacieron') !== false) {
        $cant = preg_match('/(\d+)/', $msg, $m) ? $m[1] : 0;
        $tipo = preg_match('/(terneros|vacas|caballos|ovejas|cabritos|cerdos)/i', $msg, $m) ? $m[1] : 'animales';
        
        $db->exec("INSERT INTO registros (tipo, cantidad, animal) VALUES ('nacimiento', $cant, '$tipo')");
        return "✅ NACIMIENTO REGISTRADO\nCantidad: $cant $tipo";
    }
    
    if (strpos($msgLower, 'compre') !== false || strpos($msgLower, 'compramos') !== false) {
        $item = preg_match('/(gasoil|diesel|semilla|alambre|fertilizante|bolsa|veneno|vacuna|nafta|aceite)/i', $msg, $m) ? $m[1] : 'insumo';
        
        if (preg_match('/(\d+)\s*(litros?|lts?|lt)/i', $msg, $litros)) {
            $cant = $litros[1];
            $db->exec("INSERT INTO registros (tipo, cantidad, unidad, item) VALUES ('compra', $cant, 'litros', '$item')");
            return "✅ COMPRA REGISTRADA\nItem: $item\nCantidad: $cant litros";
        } else {
            $precio = preg_match('/\$?([\d,.]+)/', $msg, $m) ? str_replace(',', '', $m[1]) : 0;
            $db->exec("INSERT INTO registros (tipo, monto, item) VALUES ('compra', $precio, '$item')");
            return "✅ COMPRA REGISTRADA\nItem: $item\nMonto: \$" . number_format($precio);
        }
    }
    
    if (strpos($msgLower, 'kms') !== false || strpos($msgLower, 'km') !== false) {
        $cant = preg_match('/(\d+)/', $msg, $m) ? $m[1] : 0;
        $db->exec("INSERT INTO registros (tipo, cantidad, unidad, descripcion) VALUES ('trabajo', $cant, 'kms', 'maquinaria')");
        return "✅ TRABAJO REGISTRADO\nKilometros: $cant kms";
    }
    
    if (strpos($msgLower, 'stock') !== false || strpos($msgLower, 'inventario') !== false) {
        $result = $db->query("SELECT item, SUM(cantidad) as total FROM registros WHERE tipo = 'compra' AND item IS NOT NULL GROUP BY item");
        $stock = [];
        while ($row = $result->fetchArray()) {
            if ($row['total'] > 0) $stock[] = $row['item'] . ': ' . $row['total'];
        }
        if (empty($stock)) return "📦 STOCK: No hay registros";
        return "📦 STOCK ACTUAL\n" . implode("\n", $stock);
    }
    
    if (strpos($msgLower, 'gaste') !== false || strpos($msgLower, 'gastos') !== false) {
        $result = $db->query("SELECT SUM(monto) as total FROM registros WHERE tipo = 'compra'");
        $row = $result->fetchArray();
        $total = $row['total'] ?? 0;
        return "💰 GASTOS TOTALES\n\$" . number_format($total);
    }
    
    if (strpos($msgLower, 'hola') !== false) {
        return "¡Hola! Soy Field Data. Escribí 'ayuda' para ver los comandos disponibles.";
    }
    
    return "No entendí. Escribí 'ayuda' para ver los comandos.";
}
