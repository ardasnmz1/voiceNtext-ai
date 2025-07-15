from flask import Flask, request, jsonify
from flask_cors import CORS
from flask_socketio import SocketIO, emit
from gtts import gTTS
from io import BytesIO
import speech_recognition as sr
import openai
import os
from dotenv import load_dotenv
from datetime import datetime, timedelta
import json
from passlib.hash import pbkdf2_sha256
from functools import wraps
import jwt

load_dotenv()

app = Flask(__name__)
CORS(app)
socketio = SocketIO(app, cors_allowed_origins="*")

# OpenAI API anahtarını yükle
openai.api_key = os.getenv('OPENAI_API_KEY')

# JWT için gizli anahtar
JWT_SECRET = os.getenv('JWT_SECRET', 'your-secret-key')

# Kullanıcı veritabanı (gerçek uygulamada bir veritabanı kullanılmalı)
users_db = {}

# Sohbet geçmişi
chat_history = {}

def token_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        token = request.headers.get('Authorization')
        if not token:
            return jsonify({'message': 'Token bulunamadı!'}), 401
        try:
            token = token.split(' ')[1]
            data = jwt.decode(token, JWT_SECRET, algorithms=['HS256'])
            current_user = users_db.get(data['username'])
            if not current_user:
                return jsonify({'message': 'Geçersiz kullanıcı!'}), 401
        except:
            return jsonify({'message': 'Geçersiz token!'}), 401
        return f(current_user, *args, **kwargs)
    return decorated

@app.route('/register', methods=['POST'])
def register():
    data = request.get_json()
    username = data.get('username')
    password = data.get('password')
    
    if username in users_db:
        return jsonify({'message': 'Kullanıcı adı zaten kullanımda!'}), 400
    
    users_db[username] = {
        'username': username,
        'password': pbkdf2_sha256.hash(password),
        'created_at': datetime.now().isoformat()
    }
    
    return jsonify({'message': 'Kayıt başarılı!'}), 201

@app.route('/login', methods=['POST'])
def login():
    data = request.get_json()
    username = data.get('username')
    password = data.get('password')
    
    user = users_db.get(username)
    if not user or not pbkdf2_sha256.verify(password, user['password']):
        return jsonify({'message': 'Geçersiz kullanıcı adı veya şifre!'}), 401
    
    token = jwt.encode({
        'username': username,
        'exp': datetime.utcnow() + timedelta(days=1)
    }, JWT_SECRET)
    
    return jsonify({'token': token})

@app.route('/chat', methods=['POST'])
@token_required
def chat(current_user):
    data = request.get_json()
    message = data.get('message')
    chat_mode = data.get('mode', 'normal')  # normal, therapy, friend
    
    if not message:
        return jsonify({'error': 'Mesaj bulunamadı'}), 400
    
    # Kullanıcının sohbet geçmişini al
    user_history = chat_history.get(current_user['username'], [])
    
    # Sohbet moduna göre sistem mesajını ayarla
    system_messages = {
        'normal': 'Sen yardımcı bir AI asistanısın.',
        'therapy': 'Sen empatik bir terapistsin. Kullanıcıyı dinle ve destek ol.',
        'friend': 'Sen kullanıcının yakın arkadaşısın. Samimi ve destekleyici ol.'
    }
    
    # OpenAI API'ye gönderilecek mesajları hazırla
    messages = [
        {"role": "system", "content": system_messages[chat_mode]}
    ]
    
    # Sohbet geçmişini ekle (son 5 mesaj)
    messages.extend(user_history[-5:])
    messages.append({"role": "user", "content": message})
    
    try:
        # OpenAI API'den yanıt al
        response = openai.ChatCompletion.create(
            model="gpt-3.5-turbo",
            messages=messages,
            temperature=0.7,
            max_tokens=150
        )
        
        ai_response = response.choices[0].message['content']
        
        # Sohbet geçmişini güncelle
        user_history.append({"role": "user", "content": message})
        user_history.append({"role": "assistant", "content": ai_response})
        chat_history[current_user['username']] = user_history
        
        return jsonify({
            'response': ai_response,
            'audio_url': '/text-to-speech',  # Frontend'de ses için kullanılacak
            'timestamp': datetime.now().isoformat()
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/text-to-speech', methods=['POST'])
@token_required
def text_to_speech(current_user):
    data = request.get_json()
    text = data.get('text')
    
    if not text:
        return jsonify({'error': 'Metin bulunamadı'}), 400
    
    try:
        # Metni sese çevir
        tts = gTTS(text=text, lang='tr')
        fp = BytesIO()
        tts.write_to_fp(fp)
        fp.seek(0)
        
        # Base64 formatında ses verisini döndür
        audio_data = base64.b64encode(fp.read()).decode()
        return jsonify({'audio_data': audio_data})
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/speech-to-text', methods=['POST'])
@token_required
def speech_to_text(current_user):
    if 'audio' not in request.files:
        return jsonify({'error': 'Ses dosyası bulunamadı'}), 400
    
    audio_file = request.files['audio']
    
    try:
        # Ses dosyasını metne çevir
        recognizer = sr.Recognizer()
        with sr.AudioFile(audio_file) as source:
            audio_data = recognizer.record(source)
            text = recognizer.recognize_google(audio_data, language='tr-TR')
        
        return jsonify({'text': text})
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@socketio.on('stream-audio')
def handle_stream_audio(audio_data):
    try:
        # Gerçek zamanlı ses akışını işle
        recognizer = sr.Recognizer()
        audio = sr.AudioData(audio_data, sample_rate=16000, sample_width=2)
        text = recognizer.recognize_google(audio, language='tr-TR')
        
        # Metni tüm bağlı istemcilere gönder
        emit('transcription', {'text': text}, broadcast=True)
        
    except Exception as e:
        emit('error', {'error': str(e)})

if __name__ == '__main__':
    socketio.run(app, debug=True, port=5000)