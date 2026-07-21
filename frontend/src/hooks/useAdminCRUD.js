import { useState, useCallback, useEffect } from 'react';
import axios from 'axios';

/**
 * useAdminCRUD - Hook for managing CRUD operations in admin pages
 * Handles loading, error, success states and common operations
 */
export function useAdminCRUD(endpoint, initialForm = {}) {
  const [items, setItems] = useState([]);
  const [form, setForm] = useState(initialForm);
  const [editingId, setEditingId] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [loading, setLoading] = useState(false);
  const [formLoading, setFormLoading] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState(null);

  // Fetch all items
  const fetchItems = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await axios.get(endpoint);
      setItems(response.data);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors du chargement');
    } finally {
      setLoading(false);
    }
  }, [endpoint]);

  // Initial fetch
  useEffect(() => {
    fetchItems();
  }, [fetchItems]);

  // Reset form
  const resetForm = useCallback(() => {
    setForm(initialForm);
    setEditingId(null);
    setError(null);
  }, [initialForm]);

  // Handle form input change
  const handleFormChange = useCallback((e) => {
    const { name, value, type, checked } = e.target;
    setForm((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }));
  }, []);

  // Handle form field change
  const setFormField = useCallback((name, value) => {
    setForm((prev) => ({
      ...prev,
      [name]: value,
    }));
  }, []);

  // Create new item
  const handleCreate = useCallback(async (formData = form) => {
    try {
      setFormLoading(true);
      setError(null);
      const response = await axios.post(endpoint, formData);
      setItems((prev) => [...prev, response.data]);
      resetForm();
      setSuccess('Créé avec succès');
      setTimeout(() => setSuccess(null), 3000);
      return response.data;
    } catch (err) {
      const errorMsg = err.response?.data?.message || 'Erreur lors de la création';
      setError(errorMsg);
      throw err;
    } finally {
      setFormLoading(false);
    }
  }, [endpoint, form, resetForm]);

  // Start editing
  const startEdit = useCallback((item) => {
    setEditingId(item.id);
    setForm(item);
    setError(null);
  }, []);

  // Update item
  const handleUpdate = useCallback(async (id = editingId, formData = form) => {
    if (!id) {
      setError('ID manquant');
      return;
    }

    try {
      setFormLoading(true);
      setError(null);
      const response = await axios.patch(`${endpoint}/${id}`, formData);
      setItems((prev) =>
        prev.map((item) => (item.id === id ? response.data : item))
      );
      resetForm();
      setSuccess('Mis à jour avec succès');
      setTimeout(() => setSuccess(null), 3000);
      return response.data;
    } catch (err) {
      const errorMsg = err.response?.data?.message || 'Erreur lors de la mise à jour';
      setError(errorMsg);
      throw err;
    } finally {
      setFormLoading(false);
    }
  }, [endpoint, editingId, form, resetForm]);

  // Delete item
  const handleDelete = useCallback(async (id) => {
    try {
      setLoading(true);
      setError(null);
      await axios.delete(`${endpoint}/${id}`);
      setItems((prev) => prev.filter((item) => item.id !== id));
      setDeleteConfirm(null);
      setSuccess('Supprimé avec succès');
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      const errorMsg = err.response?.data?.message || 'Erreur lors de la suppression';
      setError(errorMsg);
    } finally {
      setLoading(false);
    }
  }, [endpoint]);

  // Request delete confirmation
  const requestDelete = useCallback((id, name) => {
    setDeleteConfirm({ id, name });
  }, []);

  // Clear messages
  const clearMessages = useCallback(() => {
    setError(null);
    setSuccess(null);
  }, []);

  return {
    // State
    items,
    form,
    editingId,
    error,
    success,
    loading,
    formLoading,
    deleteConfirm,

    // Actions
    handleCreate,
    handleUpdate,
    handleDelete,
    startEdit,
    resetForm,
    fetchItems,
    handleFormChange,
    setFormField,
    setForm,
    requestDelete,
    clearMessages,
    setDeleteConfirm,
  };
}

/**
 * Hook for managing a single form in a modal
 */
export function useAdminForm(initialValues = {}) {
  const [values, setValues] = useState(initialValues);
  const [errors, setErrors] = useState({});
  const [touched, setTouched] = useState({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleChange = useCallback((e) => {
    const { name, value, type, checked } = e.target;
    setValues((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }));
    // Clear error when user starts typing
    if (errors[name]) {
      setErrors((prev) => ({
        ...prev,
        [name]: null,
      }));
    }
  }, [errors]);

  const handleBlur = useCallback((e) => {
    const { name } = e.target;
    setTouched((prev) => ({
      ...prev,
      [name]: true,
    }));
  }, []);

  const setFieldValue = useCallback((name, value) => {
    setValues((prev) => ({
      ...prev,
      [name]: value,
    }));
    if (errors[name]) {
      setErrors((prev) => ({
        ...prev,
        [name]: null,
      }));
    }
  }, [errors]);

  const setFieldError = useCallback((name, error) => {
    setErrors((prev) => ({
      ...prev,
      [name]: error,
    }));
  }, []);

  const resetForm = useCallback(() => {
    setValues(initialValues);
    setErrors({});
    setTouched({});
  }, [initialValues]);

  const resetToValues = useCallback((newValues) => {
    setValues(newValues);
    setErrors({});
    setTouched({});
  }, []);

  return {
    values,
    errors,
    touched,
    isSubmitting,
    setIsSubmitting,
    handleChange,
    handleBlur,
    setFieldValue,
    setFieldError,
    setValues,
    resetForm,
    resetToValues,
  };
}

/**
 * Hook for managing async operations with loading/error states
 */
export function useAsync(asyncFunction, immediate = true) {
  const [status, setStatus] = useState('idle');
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  const execute = useCallback(async (...args) => {
    setStatus('pending');
    setData(null);
    setError(null);
    try {
      const response = await asyncFunction(...args);
      setData(response);
      setStatus('success');
      return response;
    } catch (err) {
      setError(err);
      setStatus('error');
      throw err;
    }
  }, [asyncFunction]);

  useEffect(() => {
    if (immediate) {
      execute();
    }
  }, [execute, immediate]);

  return { execute, status, data, error };
}
