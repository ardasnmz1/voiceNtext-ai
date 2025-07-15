<?php
require_once 'config.php';

// Kullanıcı doğrulaması
$user = requireAuth();

// Gelen isteğin metodunu kontrol et
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($method) {
    case 'POST':
        switch($action) {
            case 'send':
                handleChatMessage();
                break;
            case 'voice':
                handleVoiceInput();
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Invalid action']);
        }
        break;
    case 'GET':
        switch($action) {
            case 'history':
                getChatHistory();
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Geçersiz işlem']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Invalid method']);
}

function handleChatMessage() {
    global $user, $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['message'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Message is required']);
        return;
    }
    
    $message = sanitizeInput($data['message']);
    $chatMode = isset($data['mode']) ? sanitizeInput($data['mode']) : 'normal';
    
    try {
        // OpenAI API'ye istek gönder
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . OPENAI_API_KEY,
            'Content-Type: application/json'
        ]);
        
        $systemMessages = [
            'normal' => 'You are a helpful AI assistant.',
            'therapy' => 'You are an empathetic therapist. Listen to the user and provide support.',
            'friend' => 'You are the user\'s close friend. Be friendly and supportive.'
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $systemMessages[$chatMode]],
                ['role' => 'user', 'content' => $message]
            ],
            'temperature' => 0.7,
            'max_tokens' => 150
        ]));
        
        $response = curl_exec($ch);
        $responseData = json_decode($response, true);
        
        if (isset($responseData['error'])) {
            throw new Exception($responseData['error']['message']);
        }
        
        $aiResponse = $responseData['choices'][0]['message']['content'];
        
        // Sohbet geçmişini kaydet
        $stmt = $pdo->prepare('INSERT INTO chat_history (user_id, message, response, chat_mode) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user['user_id'], $message, $aiResponse, $chatMode]);
        
        echo json_encode([
            'response' => $aiResponse,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleVoiceInput() {
    global $user, $pdo;
    
    if (!isset($_FILES['audio'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Audio file is required']);
        return;
    }
    
    $file = $_FILES['audio'];
    
    // Dosya kontrolü
    if ($file['size'] > MAX_AUDIO_SIZE) {
        http_response_code(400);
        echo json_encode(['error' => 'File size is too large']);
        return;
    }
    
    if (!in_array($file['type'], ALLOWED_AUDIO_TYPES)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type']);
        return;
    }
    
    try {
        // Dosyayı kaydet
        $fileName = uniqid() . '_' . $file['name'];
        $filePath = UPLOAD_DIR . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('File upload failed');
        }
        
        // Ses dosyasını metne çevir (Whisper API)
        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . OPENAI_API_KEY
        ]);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new CURLFile($filePath),
            'model' => 'whisper-1',
            'language' => 'tr'
        ]);
        
        $response = curl_exec($ch);
        $transcription = json_decode($response, true);
        
        if (isset($transcription['error'])) {
            throw new Exception($transcription['error']['message']);
        }
        
        // Ses kaydını veritabanına kaydet
        $stmt = $pdo->prepare('INSERT INTO voice_records (user_id, file_path, transcription, duration) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $user['user_id'],
            $fileName,
            $transcription['text'],
            0 // Süre hesaplaması eklenebilir
        ]);
        
        echo json_encode([
            'text' => $transcription['text'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getChatHistory() {
    global $user, $pdo;
    
    try {
        $stmt = $pdo->prepare(
            'SELECT message, response, chat_mode, created_at 
            FROM chat_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 50'
        );
        $stmt->execute([$user['user_id']]);
        
        echo json_encode([
            'history' => $stmt->fetchAll()
        ]);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}