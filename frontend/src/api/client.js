import axios from 'axios';

/**
 * Client HTTP unique de l'application (baseURL, token, gestion du 401).
 * Interdit d'importer `axios` ailleurs : le token ne serait pas envoyé.
 */
const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8090/api';

export const TOKEN_KEY = 'lgit_token';
export const UNAUTHORIZED_EVENT = 'lgit:unauthorized';

const client = axios.create({
  baseURL: apiUrl,
  headers: { Accept: 'application/json' },
});

client.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY);
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// 401 (session expirée ou jeton invalide) : on oublie le jeton et on prévient l'application,
// qui déconnecte l'utilisateur ; ProtectedRoute redirige alors vers la connexion (sans boucle :
// la page de connexion elle-même ne redirige pas, et /login ne déclenche pas l'événement).
client.interceptors.response.use(
  (response) => response,
  (error) => {
    const url = error.config?.url || '';
    const estConnexion = url.endsWith('/login');
    if (error.response?.status === 401 && !estConnexion && localStorage.getItem(TOKEN_KEY)) {
      localStorage.removeItem(TOKEN_KEY);
      window.dispatchEvent(new Event(UNAUTHORIZED_EVENT));
    }
    return Promise.reject(error);
  },
);

export default client;
