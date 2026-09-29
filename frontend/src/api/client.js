import axios from 'axios';

// Use environment variable, fallback to Docker port 8090
const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8090/api';
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
