<?php
// Veritabanı bağlantı bilgileri
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'voice_ai');

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// OpenAI API ayarları
define('OPENAI_API_KEY', $_ENV['OPENAI_API_KEY']);

// JWT ayarları
define('JWT_SECRET', $_ENV['JWT_SECRET']);
define('JWT_EXPIRE', 86400); // 24 saat

// Ses ayarları
define('UPLOAD_DIR', '../uploads/');
define('MAX_AUDIO_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_AUDIO_TYPES', ['audio/wav', 'audio/mp3', 'audio/mpeg']);

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Veritabanı bağlantısı
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die(json_encode([
        'error' => 'Veritabanı bağlantı hatası: ' . $e->getMessage()
    ]));
}

// Yardımcı fonksiyonlar
function generateJWT($user) {
    $payload = [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'exp' => time() + JWT_EXPIRE
    ];
    
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode($payload);
    
    $base64UrlHeader = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64UrlPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    
    $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    
    return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
}

function verifyJWT($token) {
    try {
        $tokenParts = explode('.', $token);
        if (count($tokenParts) != 3) {
            return false;
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $tokenParts;
        
        // Decode the header and payload
        $header = json_decode(base64_decode(strtr($base64UrlHeader, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($base64UrlHeader)) % 4)), true);
        $payload = json_decode(base64_decode(strtr($base64UrlPayload, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($base64UrlPayload)) % 4)), true);
        
        if (!$header || !$payload) {
            return false;
        }
        
        // Verify signature
        $signature = base64_decode(strtr($base64UrlSignature, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($base64UrlSignature)) % 4));
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, JWT_SECRET, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    } catch(Exception $e) {
        return false;
    }
}

function requireAuth() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        die(json_encode(['error' => 'Authorization header required']));
    }
    
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    $payload = verifyJWT($token);
    
    if (!$payload) {
        http_response_code(401);
        die(json_encode(['error' => 'Invalid or expired token']));
    }
    
    return $payload;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}