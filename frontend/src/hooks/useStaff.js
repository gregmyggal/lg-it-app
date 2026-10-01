import { useCallback, useEffect, useState } from 'react';
import api from '../api/client';

export function useStaff(filtres = {}) {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const reload = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const params = new URLSearchParams(filtres);
      const response = await api.get(`/staff?${params.toString()}`);
      setData(response.data);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [filtres]);

  useEffect(() => {
    reload();
  }, [reload]);

  return { data, loading, error, reload };
}

export function useCreateStaff() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const create = useCallback(async (payload) => {
    try {
      setLoading(true);
      setError(null);
      const response = await api.post('/staff', payload);
      return response;
    } catch (err) {
      setError(err.response?.data?.errors || err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  return { create, loading, error };
}

export function useUpdateStaff() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const update = useCallback(async (id, payload) => {
    try {
      setLoading(true);
      setError(null);
      const response = await api.put(`/staff/${id}`, payload);
      return response;
    } catch (err) {
      setError(err.response?.data?.errors || err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  return { update, loading, error };
}

export function useDeactivateStaff() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const deactivate = useCallback(async (id, payload = {}) => {
    try {
      setLoading(true);
      setError(null);
      const response = await api.post(`/staff/${id}/desactiver`, payload);
      return response;
    } catch (err) {
      setError(err.response?.data?.message || err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  return { deactivate, loading, error };
}

export function useReactivateStaff() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const reactivate = useCallback(async (id) => {
    try {
      setLoading(true);
      setError(null);
      const response = await api.post(`/staff/${id}/reactiver`);
      return response;
    } catch (err) {
      setError(err.response?.data?.message || err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  return { reactivate, loading, error };
}

/** POST d'une action de la fiche (renvoie la réponse axios) ; les erreurs 4xx portent un message métier. */
function useActionStaff(chemin) {
  const [loading, setLoading] = useState(false);

  const run = useCallback(async (id) => {
    try {
      setLoading(true);
      return await api.post(`/staff/${id}/${chemin}`);
    } finally {
      setLoading(false);
    }
  }, [chemin]);

  return { run, loading };
}

/** Renvoie l'invitation ou envoie un lien de réinitialisation (selon l'état d'accès du compte). */
export function useSendStaffLink() {
  const { run, loading } = useActionStaff('envoyer-lien');
  return { send: run, loading };
}

/** Génère un lien à transmettre soi-même (repli quand l'email n'arrive pas). */
export function useGenerateStaffLink() {
  const { run, loading } = useActionStaff('generer-lien');
  return { generate: run, loading };
}

export function useGetImpactInfo() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [impact, setImpact] = useState(null);

  const fetch = useCallback(async (id) => {
    try {
      setLoading(true);
      setError(null);
      const response = await api.get(`/staff/${id}/impact-info`);
      setImpact(response.data.data);
      return response.data.data;
    } catch (err) {
      setError(err.response?.data?.message || err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  return { fetch, impact, loading, error };
}
