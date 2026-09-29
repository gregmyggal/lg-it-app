import axios from 'axios';

// Hardcoded for local development - backend runs on port 8000
const apiUrl = 'http://localhost:8000/api';
console.log('[DEBUG] API URL:', apiUrl);

const client = axios.create({
  baseURL: apiUrl,
});

client.interceptors.request.use((config) => {
  const token = localStorage.getItem('lgit_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default client;
