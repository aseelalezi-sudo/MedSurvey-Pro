import { describe, it, expect } from 'vitest';
import { rolePermissions } from '../store/useAuthStore';

describe('Role Permissions', () => {
  it('super_admin should have all permissions', () => {
    const permissions = rolePermissions['super_admin'];
    expect(permissions.canManageUsers).toBe(true);
    expect(permissions.canDeleteResponses).toBe(true);
    expect(permissions.canExportData).toBe(true);
  });

  it('staff should have minimal permissions', () => {
    const permissions = rolePermissions['staff'];
    expect(permissions.canManageUsers).toBe(false);
    expect(permissions.canDeleteResponses).toBe(false);
    expect(permissions.canExportData).toBe(false);
    expect(permissions.canViewDepartmentReports).toBe(false);
  });

  it('head_of_department should view department reports but not manage users', () => {
    const permissions = rolePermissions['head_of_department'];
    expect(permissions.canViewDepartmentReports).toBe(true);
    expect(permissions.canManageUsers).toBe(false);
  });
});
