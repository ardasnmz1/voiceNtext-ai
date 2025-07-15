import React, { useState, useEffect, useRef } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import {
  Container,
  Box,
  Paper,
  TextField,
  IconButton,
  Typography,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  AppBar,
  Toolbar,
  Button
} from '@mui/material';
import {
  Mic,
  MicOff,
  Send,
  VolumeUp,
  ExitToApp
} from '@mui/icons-material';
import axios from 'axios';
import io from 'socket.io-client';

const Chat = () => {
  const { user, logout } = useAuth();
  const [message, setMessage] = useState('');
  const [chatHistory, setChatHistory] = useState([]);
  const [isRecording, setIsRecording] = useState(false);
  const [chatMode, setChatMode] = useState('normal');
  const [isPlaying, setIsPlaying] = useState(false);
  const chatContainerRef = useRef(null);
  const mediaRecorderRef = useRef(null);
  const socketRef = useRef(null);

  useEffect(() => {
    // Socket.io bağlantısı
    socketRef.current = io('http://localhost:5000');

    socketRef.current.on('transcription', (data) => {
      setMessage(data.text);
    });

    return () => {
      if (socketRef.current) {
        socketRef.current.disconnect();
      }
    };
  }, []);

  useEffect(() => {
    // Sohbet geçmişini otomatik kaydır
    if (chatContainerRef.current) {
      chatContainerRef.current.scrollTop = chatContainerRef.current.scrollHeight;
    }
  }, [chatHistory]);

  const handleSendMessage = async () => {
    if (!message.trim()) return;

    try {
      const response = await axios.post('/chat', {
        message: message,
        mode: chatMode
      }, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });

      const newMessage = {
        text: message,
        isUser: true,
        timestamp: new Date().toISOString()
      };

      const aiResponse = {
        text: response.data.response,
        isUser: false,
        timestamp: response.data.timestamp
      };

      setChatHistory(prev => [...prev, newMessage, aiResponse]);
      setMessage('');

      // Sesli yanıt
      if (response.data.audio_data) {
        const audio = new Audio(`data:audio/mp3;base64,${response.data.audio_data}`);
        audio.play();
      }

    } catch (error) {
      console.error('Mesaj gönderme hatası:', error);
    }
  };

  const startRecording = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      mediaRecorderRef.current = new MediaRecorder(stream);
      const audioChunks = [];

      mediaRecorderRef.current.ondataavailable = (event) => {
        audioChunks.push(event.data);
      };

      mediaRecorderRef.current.onstop = async () => {
        const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
        const formData = new FormData();
        formData.append('audio', audioBlob);

        try {
          const response = await axios.post('/speech-to-text', formData, {
            headers: {
              'Authorization': `Bearer ${localStorage.getItem('token')}`,
              'Content-Type': 'multipart/form-data'
            }
          });
          setMessage(response.data.text);
        } catch (error) {
          console.error('Ses tanıma hatası:', error);
        }
      };

      mediaRecorderRef.current.start();
      setIsRecording(true);
    } catch (error) {
      console.error('Mikrofon erişim hatası:', error);
    }
  };

  const stopRecording = () => {
    if (mediaRecorderRef.current && isRecording) {
      mediaRecorderRef.current.stop();
      setIsRecording(false);
      mediaRecorderRef.current.stream.getTracks().forEach(track => track.stop());
    }
  };

  const handleLogout = () => {
    logout();
  };

  return (
    <Box sx={{ flexGrow: 1 }}>
      <AppBar position="static">
        <Toolbar>
          <Typography variant="h6" component="div" sx={{ flexGrow: 1 }}>
            Sesli AI Asistan
          </Typography>
          <FormControl sx={{ m: 1, minWidth: 120 }}>
            <InputLabel id="chat-mode-label">Mod</InputLabel>
            <Select
              labelId="chat-mode-label"
              value={chatMode}
              label="Mod"
              onChange={(e) => setChatMode(e.target.value)}
            >
              <MenuItem value="normal">Normal</MenuItem>
              <MenuItem value="therapy">Terapi</MenuItem>
              <MenuItem value="friend">Arkadaş</MenuItem>
            </Select>
          </FormControl>
          <Button color="inherit" onClick={handleLogout} startIcon={<ExitToApp />}>
            Çıkış
          </Button>
        </Toolbar>
      </AppBar>

      <Container maxWidth="md" sx={{ mt: 4, mb: 4 }}>
        <Paper
          elevation={3}
          sx={{
            height: '70vh',
            display: 'flex',
            flexDirection: 'column',
            p: 2
          }}
        >
          <Box
            ref={chatContainerRef}
            sx={{
              flexGrow: 1,
              overflowY: 'auto',
              mb: 2,
              display: 'flex',
              flexDirection: 'column',
              gap: 1
            }}
          >
            {chatHistory.map((msg, index) => (
              <Box
                key={index}
                sx={{
                  alignSelf: msg.isUser ? 'flex-end' : 'flex-start',
                  maxWidth: '70%',
                  backgroundColor: msg.isUser ? 'primary.main' : 'grey.800',
                  borderRadius: 2,
                  p: 1
                }}
              >
                <Typography variant="body1" color="white">
                  {msg.text}
                </Typography>
                <Typography variant="caption" color="grey.400">
                  {new Date(msg.timestamp).toLocaleTimeString()}
                </Typography>
              </Box>
            ))}
          </Box>

          <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
            <IconButton
              color={isRecording ? 'secondary' : 'primary'}
              onClick={isRecording ? stopRecording : startRecording}
            >
              {isRecording ? <MicOff /> : <Mic />}
            </IconButton>
            <TextField
              fullWidth
              variant="outlined"
              placeholder="Mesajınızı yazın..."
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              onKeyPress={(e) => e.key === 'Enter' && handleSendMessage()}
            />
            <IconButton color="primary" onClick={handleSendMessage}>
              <Send />
            </IconButton>
          </Box>
        </Paper>
      </Container>
    </Box>
  );
};

export default Chat;