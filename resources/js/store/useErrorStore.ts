import { create } from 'zustand';

export interface ApiError {
  id: string;
  message: string;
  status?: number;
}

interface ErrorState {
  apiErrors: ApiError[];
  fatalError: Error | null;
  hasFatalError: boolean;
  
  addApiError: (message: string, status?: number) => void;
  dismissApiError: (id: string) => void;
  setFatalError: (error: Error | null) => void;
  clearAllErrors: () => void;
}

let errorCount = 0;

export const useErrorStore = create<ErrorState>((set) => ({
  apiErrors: [],
  fatalError: null,
  hasFatalError: false,

  addApiError: (message, status) => {
    set((state) => {
      // Prevent spamming the same error
      if (state.apiErrors.some((e) => e.message === message)) {
        return state;
      }
      const id = `error-${++errorCount}`;
      const newError = { id, message, status };
      
      // Auto-dismiss after 6 seconds
      setTimeout(() => {
        set((s) => ({
          apiErrors: s.apiErrors.filter((e) => e.id !== id),
        }));
      }, 6000);

      return {
        apiErrors: [...state.apiErrors, newError],
      };
    });
  },

  dismissApiError: (id) => {
    set((state) => ({
      apiErrors: state.apiErrors.filter((e) => e.id !== id),
    }));
  },

  setFatalError: (error) => {
    set({
      fatalError: error,
      hasFatalError: !!error,
    });
  },

  clearAllErrors: () => {
    set({
      apiErrors: [],
      fatalError: null,
      hasFatalError: false,
    });
  },
}));
