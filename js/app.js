// Global variables
let currentUser = null;
let currentMode = "normal";
let mediaRecorder = null;
let audioChunks = [];
let currentLanguage = localStorage.getItem("language") || "tr";

// DOM elements
const loginBtn = document.getElementById("loginBtn");
const registerBtn = document.getElementById("registerBtn");
const logoutBtn = document.getElementById("logoutBtn");
const authButtons = document.getElementById("authButtons");
const userInfo = document.getElementById("userInfo");
const username = document.getElementById("username");
const loginModal = new bootstrap.Modal(document.getElementById("loginModal"));
const registerModal = new bootstrap.Modal(
  document.getElementById("registerModal"),
);
const chatMessages = document.getElementById("chatMessages");
const messageInput = document.getElementById("messageInput");
const sendBtn = document.getElementById("sendBtn");
const voiceBtn = document.getElementById("voiceBtn");
const recordingStatus = document.getElementById("recordingStatus");
const modeButtons = document.querySelectorAll(".mode-selector .btn");

// Language translations
const translations = {
  en: {
    loginRequired: "Please login to send messages",
    loginFailed: "Login failed",
    loginError: "An error occurred during login",
    loginSuccess: "Login successful",
    registerSuccess: "Registration successful! You can now login.",
    registerFailed: "Registration failed",
    registerError: "An error occurred during registration",
    passwordMismatch: "Passwords do not match",
    authCheckFailed: "Authentication check failed",
    voiceMessageFailed: "Failed to send voice message",
    voiceMessageError: "An error occurred while sending voice message",
    messageSendFailed: "Failed to send message",
    messageSendError: "An error occurred while sending message",
    microphoneError: "Failed to access microphone",
    aiResponseError: "Failed to get AI response",
    historyLoadError: "Failed to load chat history",
    settingsLoadError: "Failed to load settings",
    settingsUpdateError: "Failed to update settings",
    profileLoadError: "Failed to load profile",
    profileUpdateError: "Failed to update profile",
    navProfile: "Profile",
    navSettings: "Settings",
    navLogout: "Logout",
  },
  tr: {
    loginRequired: "Mesaj göndermek için giriş yapmalısınız",
    loginFailed: "Giriş başarısız",
    loginError: "Giriş işlemi sırasında bir hata oluştu",
    loginSuccess: "Giriş başarılı",
    registerSuccess: "Kayıt başarılı! Şimdi giriş yapabilirsiniz.",
    registerFailed: "Kayıt başarısız",
    registerError: "Kayıt işlemi sırasında bir hata oluştu",
    passwordMismatch: "Şifreler eşleşmiyor",
    authCheckFailed: "Kimlik doğrulama başarısız",
    voiceMessageFailed: "Ses mesajı gönderilemedi",
    voiceMessageError: "Ses mesajı gönderme sırasında bir hata oluştu",
    messageSendFailed: "Mesaj gönderilemedi",
    messageSendError: "Mesaj gönderme sırasında bir hata oluştu",
    microphoneError: "Mikrofona erişilemedi",
    aiResponseError: "AI yanıtı alınamadı",
    historyLoadError: "Sohbet geçmişi yüklenemedi",
    settingsLoadError: "Ayarlar yüklenemedi",
    settingsUpdateError: "Ayarlar güncellenemedi",
    profileLoadError: "Profil yüklenemedi",
    profileUpdateError: "Profil güncellenemedi",
    navProfile: "Profil",
    navSettings: "Ayarlar",
    navLogout: "Çıkış",
  },
};

// Get translated text
function getText(key) {
  return translations[currentLanguage]?.[key] || translations.en[key];
}

// Event Listeners
document.addEventListener("DOMContentLoaded", () => {
  checkAuth();
  setupEventListeners();
  updateNavigation();
});

// Modal Event Listeners
document
  .getElementById("profileModal")
  .addEventListener("show.bs.modal", loadProfile);
document
  .getElementById("settingsModal")
  .addEventListener("show.bs.modal", loadSettings);

// Profile and Settings Functions
async function loadProfile() {
  try {
    const response = await fetch("/voice-ai/php/settings.php?action=profile", {
      method: "GET",
      headers: {
        Authorization: `Bearer ${localStorage.getItem("token")}`,
      },
    });
    const result = await response.json();
    if (!response.ok || !result.success)
      throw new Error(result.error || getText("profileLoadError"));
    const data = result.data;

    // Update profile information
    document.getElementById("profileUsername").value = data.username;
    document.getElementById("totalChats").textContent = data.total_chats;
    document.getElementById("activeDays").textContent = data.active_days;
    document.getElementById("lastChat").textContent = data.last_chat || "-";
    document.getElementById("totalVoiceRecords").textContent =
      data.total_voice_records;
    document.getElementById("totalDuration").textContent = data.total_duration;

    // Update profile picture if exists
    if (data.profile_picture) {
      document.getElementById("profileImage").src = data.profile_picture;
    }
  } catch (error) {
    showAlert("error", error.message || getText("profileLoadError"));
  }
}

async function loadSettings() {
  try {
    const response = await fetch("/voice-ai/php/settings.php?action=settings", {
      method: "GET",
      headers: {
        Authorization: `Bearer ${localStorage.getItem("token")}`,
      },
    });
    const result = await response.json();
    if (!response.ok || !result.success)
      throw new Error(result.error || getText("settingsLoadError"));
    const data = result.data;

    // Update settings form
    document.querySelector('select[name="default_chat_mode"]').value =
      data.default_chat_mode;
    document.querySelector('input[name="voice_enabled"]').checked =
      data.voice_enabled;
    document.querySelector('input[name="notifications_enabled"]').checked =
      data.notifications_enabled;
    document.querySelector('select[name="theme"]').value = data.theme;
    document.querySelector('select[name="language"]').value = data.language;
  } catch (error) {
    showAlert("error", error.message || getText("settingsLoadError"));
  }
}

function setupEventListeners() {
  // Auth butonları
  loginBtn.addEventListener("click", () => loginModal.show());
  registerBtn.addEventListener("click", () => registerModal.show());
  logoutBtn.addEventListener("click", handleLogout);

  // Form submit
  document.getElementById("loginForm").addEventListener("submit", handleLogin);
  document
    .getElementById("registerForm")
    .addEventListener("submit", handleRegister);

  // Mesaj gönderme
  messageInput.addEventListener("keypress", (e) => {
    if (e.key === "Enter") handleSendMessage();
  });
  sendBtn.addEventListener("click", handleSendMessage);

  // Ses kaydı
  voiceBtn.addEventListener("click", toggleVoiceRecording);

  // Mod seçimi
  modeButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      modeButtons.forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      currentMode = btn.dataset.mode;
    });
  });
}

// Update navigation menu items
function updateNavigation() {
  const navItems = document.querySelector(".navbar-nav");
  if (navItems) {
    navItems.innerHTML = `
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">${getText("navProfile")}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#settingsModal">${getText("navSettings")}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="handleLogout()">${getText("navLogout")}</a>
            </li>
        `;
  }
}

// Kimlik doğrulama işlemleri
async function checkAuth() {
  const token = localStorage.getItem("token");
  const savedUser = localStorage.getItem("user");

  if (token && savedUser) {
    try {
      const response = await fetch("/voice-ai/php/auth.php?action=verify", {
        headers: { Authorization: `Bearer ${token}` },
      });

      const data = await response.json();
      console.log("Verify response:", data);
      if (response.ok && data.valid) {
        // Kullanıcı bilgilerini güncelle
        currentUser = data.user;
        localStorage.setItem("user", JSON.stringify(currentUser));

        handleAuthSuccess({ token: token, user: currentUser });
        loadChatHistory();
      } else {
        throw new Error(data.error || getText("authCheckFailed"));
      }
    } catch (error) {
      console.error("Auth check failed:", error);
      showAlert("error", error.message || getText("authCheckFailed"));
      handleLogout();
    }
  }
}

function handleAuthSuccess(data) {
  currentUser = data.user;
  localStorage.setItem("token", data.token);
  localStorage.setItem("user", JSON.stringify(currentUser));

  authButtons.style.display = "none";
  userInfo.style.display = "flex";
  username.textContent = currentUser.username;
  updateNavigation();

  // Apply user settings
  if (currentUser.settings) {
    currentMode = currentUser.settings.default_chat_mode;
    modeButtons.forEach((btn) => {
      btn.classList.toggle("active", btn.dataset.mode === currentMode);
    });

    if (currentUser.settings.theme) {
      document.body.setAttribute("data-theme", currentUser.settings.theme);
    }

    if (currentUser.settings.language) {
      currentLanguage = currentUser.settings.language;
      updateNavigation();
    }
  }
}

function handleLogout() {
  currentUser = null;
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  authButtons.style.display = "flex";
  userInfo.style.display = "none";
  username.textContent = "";
  chatMessages.innerHTML = "";
  updateNavigation();
}

// Show alert function
function showAlert(type, message) {
  const alertDiv = document.createElement("div");
  alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
  alertDiv.style.zIndex = "1050";
  alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
  document.body.appendChild(alertDiv);

  setTimeout(() => {
    alertDiv.remove();
  }, 5000);
}

async function handleLogin(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  const data = {
    username: formData.get("username"),
    password: formData.get("password"),
  };

  try {
    const response = await fetch("/voice-ai/php/auth.php?action=login", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(data),
    });

    const responseData = await response.json();
    if (!response.ok) {
      throw new Error(responseData.error || getText("loginFailed"));
    }

    handleAuthSuccess(responseData);
    loginModal.hide();
    e.target.reset();
    loadChatHistory();
    showAlert("success", getText("loginSuccess"));
    updateNavigation();
  } catch (error) {
    console.error("Login failed:", error);
    showAlert("error", error.message || getText("loginError"));
  }
}

async function handleRegister(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  const data = Object.fromEntries(formData);

  if (data.password !== data.password_confirm) {
    showAlert("error", getText("passwordMismatch"));
    return;
  }

  try {
    const response = await fetch("/voice-ai/php/auth.php?action=register", {
      method: "POST",
      body: JSON.stringify({
        username: data.username,
        password: data.password,
      }),
      headers: { "Content-Type": "application/json" },
    });

    const result = await response.json();
    if (response.ok) {
      showAlert("success", getText("registerSuccess"));
      registerModal.hide();
      loginModal.show();
      e.target.reset();
    } else {
      throw new Error(result.error || getText("registerFailed"));
    }
  } catch (error) {
    console.error("Registration failed:", error);
    showAlert("error", error.message || getText("registerError"));
  }
}

async function handleSendMessage() {
  if (!currentUser) {
    showAlert("error", getText("loginRequired"));
    return;
  }

  const message = messageInput.value.trim();
  if (!message) return;

  try {
    appendMessage(message, true);
    messageInput.value = "";

    const response = await fetch("/voice-ai/php/chat.php?action=send", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${localStorage.getItem("token")}`,
      },
      body: JSON.stringify({
        message: message,
        mode: currentMode,
      }),
    });

    const data = await response.json();
    if (!response.ok) {
      throw new Error(data.error || getText("messageSendFailed"));
    }

    appendMessage(data.response, false);
  } catch (error) {
    console.error("Message send failed:", error);
    showAlert("error", error.message || getText("messageSendError"));
  }
}

async function sendVoiceMessage(audioBlob) {
  if (!currentUser) {
    showAlert("error", getText("loginRequired"));
    return;
  }

  const formData = new FormData();
  formData.append("audio", audioBlob, "recording.wav");
  formData.append("mode", currentMode);

  try {
    const response = await fetch("/voice-ai/php/chat.php?action=voice", {
      method: "POST",
      body: formData,
      headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
    });

    const data = await response.json();
    if (!response.ok) {
      throw new Error(data.error || getText("voiceMessageFailed"));
    }

    appendMessage(data.text, true);

    // AI yanıtını al
    const aiResponse = await fetch("/voice-ai/php/chat.php?action=send", {
      method: "POST",
      body: JSON.stringify({
        message: data.text,
        mode: currentMode,
      }),
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${localStorage.getItem("token")}`,
      },
    });

    const aiData = await aiResponse.json();
    if (!aiResponse.ok) {
      throw new Error(aiData.error || getText("aiResponseError"));
    }

    appendMessage(aiData.response, false);
  } catch (error) {
    console.error("Voice message send failed:", error);
    showAlert("error", error.message || getText("voiceMessageError"));
  }
}

async function loadChatHistory() {
  if (!currentUser) return;

  try {
    const response = await fetch("/voice-ai/php/chat.php?action=history", {
      headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
    });

    const data = await response.json();
    if (!response.ok) {
      throw new Error(data.error || getText("historyLoadError"));
    }

    chatMessages.innerHTML = "";
    data.history.reverse().forEach((chat) => {
      appendMessage(chat.message, true);
      appendMessage(chat.response, false);
    });
  } catch (error) {
    console.error("Failed to load chat history:", error);
    showAlert("error", error.message || getText("historyLoadError"));
  }
}

async function toggleVoiceRecording() {
  if (!currentUser) {
    showAlert("error", getText("loginRequired"));
    return;
  }

  if (!mediaRecorder) {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      mediaRecorder = new MediaRecorder(stream);

      mediaRecorder.ondataavailable = (e) => {
        audioChunks.push(e.data);
      };

      mediaRecorder.onstop = async () => {
        const audioBlob = new Blob(audioChunks, { type: "audio/wav" });
        await sendVoiceMessage(audioBlob);
        audioChunks = [];
      };

      startRecording();
    } catch (error) {
      console.error("Microphone access failed:", error);
      showAlert("error", getText("microphoneError"));
    }
  } else {
    stopRecording();
  }
}

function startRecording() {
  if (mediaRecorder && mediaRecorder.state === "inactive") {
    mediaRecorder.start();
    voiceBtn.classList.add("recording");
    recordingStatus.style.display = "inline";
  }
}

function stopRecording() {
  if (mediaRecorder && mediaRecorder.state === "recording") {
    mediaRecorder.stop();
    mediaRecorder.stream.getTracks().forEach((track) => track.stop());
    mediaRecorder = null;
    voiceBtn.classList.remove("recording");
    recordingStatus.style.display = "none";
  }
}

function handleThemeChange(theme) {
  document.body.setAttribute("data-theme", theme);
  if (currentUser && currentUser.settings) {
    currentUser.settings.theme = theme;
    localStorage.setItem("user", JSON.stringify(currentUser));

    fetch("/voice-ai/php/settings.php?action=settings", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${localStorage.getItem("token")}`,
      },
      body: JSON.stringify({
        theme: theme,
      }),
    }).catch((error) => {
      console.error("Failed to update theme:", error);
      showAlert("error", getText("settingsUpdateError"));
    });
  }
}

function handleLanguageChange(language) {
  currentLanguage = language;
  localStorage.setItem("language", language);
  if (currentUser && currentUser.settings) {
    currentUser.settings.language = language;
    localStorage.setItem("user", JSON.stringify(currentUser));

    fetch("/voice-ai/php/settings.php?action=settings", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${localStorage.getItem("token")}`,
      },
      body: JSON.stringify({
        language: language,
      }),
    }).catch((error) => {
      console.error("Failed to update language:", error);
      showAlert("error", getText("settingsUpdateError"));
    });
  }
  updateNavigation();
}

// Profile Form Submit Handler
document.getElementById("profileForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  try {
    const formData = new FormData(e.target);
    const response = await fetch(
      "/voice-ai/php/settings.php?action=update_profile",
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${localStorage.getItem("token")}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify(Object.fromEntries(formData)),
      },
    );
    const result = await response.json();
    if (!response.ok || !result.success)
      throw new Error(result.error || "Profil güncellenemedi");
    showAlert("success", "Profil başarıyla güncellendi");
  } catch (error) {
    showAlert("error", error.message);
  }
});

// Profile Picture Form Submit Handler
document
  .getElementById("profilePictureForm")
  .addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      const formData = new FormData(e.target);
      const response = await fetch(
        "/voice-ai/php/settings.php?action=update_profile_picture",
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
          body: formData,
        },
      );
      const result = await response.json();
      if (!response.ok || !result.success)
        throw new Error(result.error || "Profil fotoğrafı güncellenemedi");
      const data = result.data;
      document.getElementById("profileImage").src = data.profile_picture;
      showAlert("success", "Profil fotoğrafı başarıyla güncellendi");
    } catch (error) {
      showAlert("error", error.message);
    }
  });

// Password Form Submit Handler
document
  .getElementById("passwordForm")
  .addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      const formData = new FormData(e.target);
      const response = await fetch(
        "/voice-ai/php/settings.php?action=change_password",
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${localStorage.getItem("token")}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify(Object.fromEntries(formData)),
        },
      );
      const result = await response.json();
      if (!response.ok || !result.success)
        throw new Error(result.error || "Şifre değiştirilemedi");
      showAlert("success", "Şifre başarıyla değiştirildi");
      e.target.reset();
    } catch (error) {
      showAlert("error", error.message);
    }
  });

// Settings Form Submit Handler
document
  .getElementById("settingsForm")
  .addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      const formData = new FormData(e.target);
      const settings = Object.fromEntries(formData);
      settings.voice_enabled = formData.get("voice_enabled") === "on";
      settings.notifications_enabled =
        formData.get("notifications_enabled") === "on";

      const response = await fetch(
        "/voice-ai/php/settings.php?action=update_settings",
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${localStorage.getItem("token")}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify(settings),
        },
      );
      const result = await response.json();
      if (!response.ok || !result.success)
        throw new Error(result.error || "Ayarlar güncellenemedi");
      showAlert("success", "Ayarlar başarıyla güncellendi");

      // Apply theme change immediately
      handleThemeChange(settings.theme);
    } catch (error) {
      showAlert("error", error.message);
    }
  });

// Append message to chat
function appendMessage(message, isUser) {
  const messageElement = document.createElement("div");
  messageElement.classList.add("message", isUser ? "user-message" : "ai-message");

  const iconElement = document.createElement("i");
  iconElement.classList.add("fas", isUser ? "fa-user" : "fa-robot");

  const textElement = document.createElement("div");
  textElement.classList.add("message-text");
  textElement.textContent = message;

  messageElement.appendChild(iconElement);
  messageElement.appendChild(textElement);

  chatMessages.appendChild(messageElement);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}
