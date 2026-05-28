export type UserRole = 'super_admin' | 'admin' | 'unit_manager' | 'head_of_department' | 'staff';

export interface UserPermission {
  canManageUsers: boolean;
  canManageSurveys: boolean;
  canViewAllReports: boolean;
  canViewDepartmentReports: boolean;
  canViewResponses: boolean;
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
  department?: string | null;
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
  ipAddress?: string | null;
  userAgent?: string | null;
  deviceName?: string | null;
  user?: {
    id: string;
    name: string;
    username: string;
    role: string;
  };
}
