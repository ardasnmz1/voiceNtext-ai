<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voice AI - Voice Assistant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .chat-container {
            height: 70vh;
            overflow-y: auto;
        }
        .chat-message {
            margin: 10px;
            padding: 10px;
            border-radius: 10px;
        }
        .user-message {
            background-color: #007bff;
            color: white;
            margin-left: 20%;
        }
        .ai-message {
            background-color: #e9ecef;
            margin-right: 20%;
        }
        .recording-indicator {
            width: 20px;
            height: 20px;
            background-color: red;
            border-radius: 50%;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.5; }
            100% { transform: scale(1); opacity: 1; }
        }
        .mode-selector {
            margin-bottom: 20px;
        }
        .mode-selector .btn {
            margin: 0 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Voice AI</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="profileLink">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="settingsLink">Settings</a>
                    </li>
                </ul>
                <div class="d-flex" id="authButtons">
                    <button class="btn btn-outline-light me-2" id="loginBtn">Login</button>
                    <button class="btn btn-light" id="registerBtn">Register</button>
                </div>
                <div class="d-none" id="userInfo">
                    <span class="text-light me-2" id="username"></span>
                    <button class="btn btn-outline-light" id="logoutBtn">Logout</button>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Login Form Modal -->
        <div class="modal fade" id="loginModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Login</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="loginForm">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Register Form Modal -->
        <div class="modal fade" id="registerModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Register</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="registerForm">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" name="password_confirm" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Register</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="row">
            <div class="col-md-12">
                <div class="mode-selector text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary active" data-mode="normal">Normal Mode</button>
                        <button type="button" class="btn btn-outline-primary" data-mode="therapy">Therapy Mode</button>
                        <button type="button" class="btn btn-outline-primary" data-mode="friend">Friend Mode</button>
                    </div>
                </div>
                <div class="chat-container border rounded p-3">
                    <div id="chatMessages"></div>
                </div>
                <div class="input-group mt-3">
                    <input type="text" id="messageInput" class="form-control" placeholder="Type your message...">
                    <button class="btn btn-primary" id="sendBtn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                    <button class="btn btn-secondary" id="voiceBtn">
                        <i class="fas fa-microphone"></i>
                    </button>
                </div>
                <div class="text-center mt-2 d-none" id="recordingStatus">
                    <div class="d-inline-block recording-indicator me-2"></div>
                    <span>Recording...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6>Profile Picture</h6>
                        <div class="text-center mb-3">
                            <img id="profileImage" src="images/default-avatar.svg" class="profile-picture">
                        </div>
                        <form id="profilePictureForm" class="mb-3">
                            <div class="input-group">
                                <input type="file" class="form-control" id="profilePicture" name="profile_picture" accept="image/*">
                                <button class="btn btn-primary" type="submit">Upload</button>
                            </div>
                        </form>
                    </div>
                    <div class="mb-3">
                        <h6>Profile Information</h6>
                        <form id="profileForm">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" id="profileUsername">
                            </div>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </form>
                    </div>
                    <div class="mb-3 profile-stats">
                        <h6>Statistics</h6>
                        <p>Total Chats: <span id="totalChats">0</span></p>
                        <p>Active Days: <span id="activeDays">0</span></p>
                        <p>Last Chat: <span id="lastChat">-</span></p>
                        <p>Total Voice Records: <span id="totalVoiceRecords">0</span></p>
                        <p>Total Duration: <span id="totalDuration">0</span> seconds</p>
                    </div>
                    <div class="mb-3">
                        <h6>Change Password</h6>
                        <form id="passwordForm">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="settingsForm">
                        <div class="mb-3">
                            <label class="form-label">Default Chat Mode</label>
                            <select class="form-select" name="default_chat_mode">
                                <option value="normal">Normal Mode</option>
                                <option value="therapy">Therapy Mode</option>
                                <option value="friend">Friend Mode</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="voiceEnabled" name="voice_enabled">
                            <label class="form-check-label">Voice Feature Enabled</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="notificationsEnabled" name="notifications_enabled">
                            <label class="form-check-label">Notifications Enabled</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Theme</label>
                            <select class="form-select" name="theme">
                                <option value="light">Light</option>
                                <option value="dark">Dark</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Language</label>
                            <select class="form-select" name="language">
                                <option value="tr">Turkish</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js"></script>
</body>
</html>