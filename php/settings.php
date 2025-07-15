<?php
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get user ID from token
function getUserIdFromToken() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        throw new Exception('Authorization header not found');
    }
    
    $auth = $headers['Authorization'];
    $token = str_replace('Bearer ', '', $auth);

    if (!$token) {
        throw new Exception('Authorization token not found');
    }
    
    try {
        $decoded = verifyJWT($token);
        if (!$decoded || !isset($decoded['user_id'])) {
            throw new Exception('Invalid or expired token');
        }
        return $decoded['user_id'];
    } catch (Exception $e) {
        throw new Exception('Invalid token: ' . $e->getMessage());
    }
}

// Get profile information
function getProfile($userId) {
    global $pdo;
    
    try {
        // Get user information
        $stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Get chat statistics
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total_chats,
            COUNT(DISTINCT DATE(created_at)) as active_days,
            MAX(created_at) as last_chat
            FROM chat_history 
            WHERE user_id = ?");
        $stmt->execute([$userId]);
        $chatStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get voice record statistics
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total_voice_records,
            COALESCE(SUM(duration), 0) as total_duration
            FROM voice_records 
            WHERE user_id = ?");
        $stmt->execute([$userId]);
        $voiceStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return array_merge($user, $chatStats, $voiceStats);
    } catch (Exception $e) {
        throw new Exception('Error fetching profile: ' . $e->getMessage());
    }
}

// Get user settings
function getSettings($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT default_chat_mode, voice_enabled, notification_enabled as notifications_enabled, theme, preferred_language as language FROM user_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            // Return default settings if none exist
            return [
                'default_chat_mode' => 'normal',
                'voice_enabled' => true,
                'notifications_enabled' => true,
                'theme' => 'light',
                'language' => 'tr'
            ];
        }
        
        return $settings;
    } catch (Exception $e) {
        throw new Exception('Error fetching settings: ' . $e->getMessage());
    }
}

// Update profile
function updateProfile($userId, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$data['username'], $userId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('No changes made to profile');
        }
        
        return true;
    } catch (Exception $e) {
        throw new Exception('Error updating profile: ' . $e->getMessage());
    }
}

// Update profile picture
function updateProfilePicture($userId) {
    global $pdo;
    
    try {
        if (!isset($_FILES['profile_picture'])) {
            throw new Exception('No file uploaded');
        }
        
        $file = $_FILES['profile_picture'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileError = $file['error'];
        
        if ($fileError !== UPLOAD_ERR_OK) {
            throw new Exception('Error uploading file');
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($fileTmpName);
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Invalid file type');
        }
        
        // Generate unique filename
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = uniqid('profile_') . '.' . $extension;
        $uploadPath = __DIR__ . '/../uploads/profiles/' . $newFileName;
        
        // Create directory if it doesn't exist
        if (!file_exists(__DIR__ . '/../uploads/profiles')) {
            mkdir(__DIR__ . '/../uploads/profiles', 0777, true);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($fileTmpName, $uploadPath)) {
            throw new Exception('Error saving file');
        }
        
        // Update database
        $profilePicturePath = '/voice-ai/uploads/profiles/' . $newFileName;
        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->execute([$profilePicturePath, $userId]);
        
        return ['profile_picture' => $profilePicturePath];
    } catch (Exception $e) {
        throw new Exception('Error updating profile picture: ' . $e->getMessage());
    }
}

// Change password
function changePassword($userId, $data) {
    global $pdo;
    
    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($data['current_password'], $user['password'])) {
            throw new Exception('Current password is incorrect');
        }
        
        // Update password
        $newPasswordHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newPasswordHash, $userId]);
        
        return true;
    } catch (Exception $e) {
        throw new Exception('Error changing password: ' . $e->getMessage());
    }
}

// Update settings
function updateSettings($userId, $data) {
    global $pdo;
    
    try {
        $validSettings = [
            'default_chat_mode' => ['normal', 'therapy', 'friend'],
            'voice_enabled' => [true, false],
            'notification_enabled' => [true, false],
            'theme' => ['light', 'dark'],
            'preferred_language' => ['en', 'tr']
        ];

        // Rename 'language' key to 'preferred_language'
        if (isset($data['language'])) {
            $data['preferred_language'] = $data['language'];
            unset($data['language']);
        }

        // Rename 'notifications_enabled' to 'notification_enabled'
        if (isset($data['notifications_enabled'])) {
            $data['notification_enabled'] = $data['notifications_enabled'];
            unset($data['notifications_enabled']);
        }

        // Validate settings
        foreach ($data as $key => $value) {
            if (!isset($validSettings[$key])) {
                throw new Exception("Invalid setting: $key");
            }
            if (is_bool($validSettings[$key][0])) {
                $data[$key] = (bool)$value;
            } else if (!in_array($value, $validSettings[$key])) {
                throw new Exception("Invalid value for $key");
            }
        }

        // Check if settings exist
        $stmt = $pdo->prepare("SELECT 1 FROM user_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->fetch()) {
            // Update existing settings
            $sql = "UPDATE user_settings SET ";
            $updates = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
            
            $sql .= implode(", ", $updates);
            $sql .= " WHERE user_id = ?";
            $params[] = $userId;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            // Insert new settings
            $columns = array_keys($data);
            $values = array_fill(0, count($columns), '?');
            
            $sql = "INSERT INTO user_settings (user_id, " . implode(", ", $columns) . ") ";
            $sql .= "VALUES (?, " . implode(", ", $values) . ")";
            
            $params = array_merge([$userId], array_values($data));
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        return true;
    } catch (Exception $e) {
        throw new Exception('Error updating settings: ' . $e->getMessage());
    }
}

// Main request handler
header('Content-Type: application/json');
try {
    $userId = getUserIdFromToken();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $response = null;

    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'profile':
                    $response = getProfile($userId);
                    break;
                case 'settings':
                    $response = getSettings($userId);
                    break;
                default:
                    throw new Exception('Invalid action');
            }
            break;

        case 'POST':
            $input = file_get_contents('php://input');
            $data = $input ? json_decode($input, true) : [];
            
            if (json_last_error() !== JSON_ERROR_NONE && $action !== 'update_profile_picture') {
                throw new Exception('Invalid JSON data');
            }

            switch ($action) {
                case 'update_profile':
                    $response = updateProfile($userId, $data);
                    break;
                case 'update_profile_picture':
                    $response = updateProfilePicture($userId);
                    break;
                case 'change_password':
                    $response = changePassword($userId, $data);
                    break;
                case 'update_settings':
                    $response = updateSettings($userId, $data);
                    break;
                default:
                    throw new Exception('Invalid action');
            }
            break;

        default:
            throw new Exception('Invalid method');
    }

    echo json_encode(['success' => true, 'data' => $response]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}