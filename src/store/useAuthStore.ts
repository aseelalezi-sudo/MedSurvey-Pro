import { useCallback, useEffect } from 'react';
import { create } from 'zustand';
import { authAPI, usersAPI, setToken } from '../api/client';
import { createLogger } from '../utils/logger';

const logger = createLogger('AuthStore');

// Re-export types that components expect
export type UserRole = 'super_admin' | 'admin' | 'head_of_department' | 'staff';

export interface UserPermission {
  canManageUsers: boolean;
  canManageSurveys: boolean;
  canViewAllReports: boolean;
  canViewDepartmentReports: boolean;
  canExportData: boolean;
  canDeleteResponses: boolean;
}

export interface User {
  id: string;
  username: string;
  password?: string;
  name: string;
  email: string;
  role: UserRole;
  department?: string;
  createdAt: string;
  lastLogin?: string;
  isActive: boolean;
  avatar?: string;
}

export interface AuditLog {
  id: string;
  userId: string;
  action: string;
  details: string;
  timestamp: string;
  user?: {
    id: string;
    name: string;
    username: string;
    role: string;
  };
}

// Default permissions for each role
export const rolePermissions: Record<UserRole, UserPermission> = {
  super_admin: {
    canManageUsers: true,
    canManageSurveys: true,
    canViewAllReports: true,
    canViewDepartmentReports: true,
    canExportData: true,
    canDeleteResponses: true,
  },
  admin: {
    canManageUsers: false,
    canManageSurveys: true,
    canViewAllReports: true,
    canViewDepartmentReports: true,
    canExportData: true,
    canDeleteResponses: false,
  },
  head_of_department: {
    canManageUsers: false,
    canManageSurveys: false,
    canViewAllReports: false,
    canViewDepartmentReports: true,
    canExportData: false,
    canDeleteResponses: false,
  },
  staff: {
    canManageUsers: false,
    canManageSurveys: false,
    canViewAllReports: false,
    canViewDepartmentReports: false,
    canExportData: false,
    canDeleteResponses: false,
  },
};

interface AuthState {
  currentUser: User | null;
  users: User[];
  loginError: string;
  initialLoadDone: boolean;

  setCurrentUser: (user: User | null) => void;
  setUsers: (users: User[]) => void;
  setLoginError: (error: string) => void;
  setInitialLoadDone: (done: boolean) => void;

  loadUsers: () => Promise<User[]>;
  login: (username: string, password: string) => Promise<boolean>;
  logout: () => Promise<void>;
  createUser: (userData: Omit<User, 'id' | 'createdAt'>) => Promise<User>;
  updateUser: (id: string, updates: Partial<User>) => Promise<boolean>;
  changeUserPassword: (id: string, password: string) => Promise<boolean>;
  deleteUser: (id: string) => Promise<boolean>;
  toggleUserStatus: (id: string) => Promise<boolean>;
}

// Global Zustand Store for Auth State
export const useAuthZustandStore = create<AuthState>((set, get) => ({
  currentUser: null,
  users: [],
  loginError: '',
  initialLoadDone: false,

  setCurrentUser: (currentUser) => set({ currentUser }),
  setUsers: (users) => set({ users }),
  setLoginError: (loginError) => set({ loginError }),
  setInitialLoadDone: (initialLoadDone) => set({ initialLoadDone }),

  loadUsers: async () => {
    try {
      const data = await usersAPI.getAll();
      set({ users: data as User[] });
      return data as User[];
    } catch (error) {
      logger.error('Failed to load users:', error);
      throw error;
    }
  },

  login: async (username, password) => {
    set({ loginError: '' });
    try {
      const { token, user } = await authAPI.login(username, password);
      setToken(token);
      set({ currentUser: user as User });
      return true;
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'اسم المستخدم أو كلمة المرور غير صحيحة';
      set({ loginError: message });
      return false;
    }
  },

  logout: async () => {
    try {
      await authAPI.logout();
    } catch {
      // Ignore logout errors
    }
    setToken(null);
    set({ currentUser: null });
  },

  createUser: async (userData) => {
    try {
      const user = await usersAPI.create(userData);
      // Trigger a reload of users
      await get().loadUsers();
      return user as User;
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'حدث خطأ أثناء إنشاء المستخدم';
      throw new Error(message);
    }
  },

  updateUser: async (id, updates) => {
    try {
      const updated = await usersAPI.update(id, updates);
      if (get().currentUser?.id === id) {
        set({ currentUser: updated as User });
      }
      await get().loadUsers();
      return true;
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'حدث خطأ أثناء تحديث المستخدم';
      throw new Error(message);
    }
  },

  changeUserPassword: async (id, password) => {
    try {
      const updated = await usersAPI.changePassword(id, password);
      if (get().currentUser?.id === id) {
        set({ currentUser: updated as User });
      }
      await get().loadUsers();
      return true;
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'حدث خطأ أثناء تغيير كلمة المرور';
      throw new Error(message);
    }
  },

  deleteUser: async (id) => {
    if (get().currentUser?.id === id) throw new Error('لا يمكن حذف الحساب الحالي');
    try {
      await usersAPI.delete(id);
      await get().loadUsers();
      return true;
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'حدث خطأ أثناء حذف المستخدم';
      throw new Error(message);
    }
  },

  toggleUserStatus: async (id) => {
    try {
      await usersAPI.toggle(id);
      await get().loadUsers();
      return true;
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'حدث خطأ أثناء تغيير حالة المستخدم';
      throw new Error(message);
    }
  },
}));

// Component-Facing Hook Wrapper (Maintains 100% Backward Compatibility)
export function useAuthStore() {
  const store = useAuthZustandStore();

  // Initial load - automatically restore session using secure cookies
  useEffect(() => {
    if (!store.initialLoadDone) {
      store.setInitialLoadDone(true);
      authAPI.me()
        .then((user) => {
          store.setCurrentUser(user as User);
        })
        .catch(() => {
          setToken(null);
          store.setCurrentUser(null);
        });
    }
  }, [store.initialLoadDone, store]);

  const hasPermission = useCallback((permission: keyof UserPermission): boolean => {
    if (!store.currentUser) return false;
    const permissions = rolePermissions[store.currentUser.role];
    return permissions[permission];
  }, [store.currentUser]);

  const canAccess = useCallback((requiredRole: UserRole[]): boolean => {
    if (!store.currentUser) return false;
    const roleHierarchy: Record<UserRole, number> = {
      staff: 1,
      head_of_department: 2,
      admin: 3,
      super_admin: 4,
    };
    const userLevel = roleHierarchy[store.currentUser.role];
    const requiredLevel = Math.min(...requiredRole.map(r => roleHierarchy[r]));
    return userLevel >= requiredLevel;
  }, [store.currentUser]);

  return {
    currentUser: store.currentUser,
    users: store.users,
    loginError: store.loginError,
    setLoginError: store.setLoginError,
    login: store.login,
    logout: store.logout,
    createUser: store.createUser,
    updateUser: store.updateUser,
    changeUserPassword: store.changeUserPassword,
    deleteUser: store.deleteUser,
    toggleUserStatus: store.toggleUserStatus,
    hasPermission,
    canAccess,
    rolePermissions,
    loadUsers: store.loadUsers,
  };
}
