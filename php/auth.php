<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Gelen isteğin metodunu kontrol et
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($method) {
    case 'GET':
        switch($action) {
            case 'verify':
                handleVerify();
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Invalid action']);
        }
        break;
    case 'POST':
        switch($action) {
            case 'register':
                handleRegister();
                break;
            case 'login':
                handleLogin();
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Invalid action']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Invalid method']);
}

function handleVerify() {
    try {
        $token = getBearerToken();
        if (!$token) {
            throw new Exception('No token provided');
        }

        $decoded = verifyJWT($token);
        if (!$decoded) {
            throw new Exception('Invalid token');
        }

        // Kullanıcı bilgilerini al
        global $pdo;
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
        $stmt->execute([$decoded['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('User not found');
        }

        // Kullanıcı ayarlarını al
        $stmt = $pdo->prepare('SELECT * FROM user_settings WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $settings = $stmt->fetch();

        echo json_encode([
            'valid' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'settings' => $settings
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            'valid' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getBearerToken() {
    $headers = apache_request_headers();
    if (!isset($headers['Authorization'])) {
        return null;
    }
    
    if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        return $matches[1];
    }
    
    return null;
}

function handleRegister() {
    try {
        $input = file_get_contents('php://input');
        if (!$input) {
            throw new Exception('Input data is empty');
        }
        
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON parsing error: ' . json_last_error_msg());
        }
        
        if (!isset($data['username']) || !isset($data['password'])) {
            throw new Exception('Username and password are required');
        }
        
        $username = sanitizeInput($data['username']);
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        global $pdo;
        
        // Kullanıcı adının benzersiz olduğunu kontrol et
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new Exception('This username is already in use');
        }
        
        // Yeni kullanıcıyı kaydet
        $stmt = $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
        $stmt->execute([$username, $password]);
        
        $userId = $pdo->lastInsertId();
        
        // Varsayılan kullanıcı ayarlarını oluştur
        $stmt = $pdo->prepare('INSERT INTO user_settings (user_id) VALUES (?)');
        $stmt->execute([$userId]);
        
        echo json_encode([
            'message' => 'Registration successful',
            'user_id' => $userId
        ]);
        
    } catch(Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleLogin() {
    try {
        $input = file_get_contents('php://input');
        if (!$input) {
            throw new Exception('Gelen veri boş');
        }
        
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON çözümleme hatası: ' . json_last_error_msg());
        }
        
        if (!isset($data['username']) || !isset($data['password'])) {
            throw new Exception('Kullanıcı adı ve şifre gerekli');
        }
        
        $username = sanitizeInput($data['username']);
        
        global $pdo;
        
        $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($data['password'], $user['password'])) {
            throw new Exception('Invalid username or password');
        }
        
        // Kullanıcı ayarlarını al
        $stmt = $pdo->prepare('SELECT * FROM user_settings WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $settings = $stmt->fetch();
        
        // JWT token oluştur
        $token = generateJWT($user);
        
        echo json_encode([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'settings' => $settings
            ]
        ]);
        
    } catch(Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}