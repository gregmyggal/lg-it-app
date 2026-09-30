import { useContext } from 'react';
import { ToastContext } from '../components/toast/ToastContext';

/** @returns {{success: (message: string) => void, error: (message: string) => void}} */
export function useToast() {
  return useContext(ToastContext);
}
