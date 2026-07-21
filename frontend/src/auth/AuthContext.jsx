import { createContext, useContext, useEffect, useState } from 'react';
import client from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('lgit_token');
    if (!token) {
      setLoading(false);
      return;
    }
    client
      .get('/me')
      .then((res) => setUser(res.data))
      .catch(() => localStorage.removeItem('lgit_token'))
      .finally(() => setLoading(false));
  }, []);

  async function login(email, password) {
    const res = await client.post('/login', { email, password });
    localStorage.setItem('lgit_token', res.data.token);
    setUser(res.data.user);
  }

  async function logout() {
    try {
      await client.post('/logout');
    } finally {
      localStorage.removeItem('lgit_token');
      setUser(null);
    }
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
