import { useAuthStore, User, UserRole } from '../store/useAuthStore';
import { useSettingsStore } from '../store/useSettingsStore';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

const roleColors: Record<UserRole, string> = {
  super_admin: 'from-purple-500 to-indigo-500',
  admin: 'from-blue-500 to-cyan-500',
  unit_manager: 'from-teal-500 to-cyan-600',
  head_of_department: 'from-green-500 to-emerald-500',
  staff: 'from-amber-500 to-orange-500',
};

const emptyUserForm = {
  name: '',
  username: '',
  email: '',
  password: '',
  role: 'staff' as UserRole,
  department: '',
};

import {
  Users,
  Plus,
  Edit3,
  Trash2,
  Shield,
  X,
  Check,
  Eye,
  EyeOff,
  UserCheck,
  UserX,
  Search,
  Mail,
  Building2,
  Calendar,
  AlertCircle,
  KeyRound,
} from 'lucide-react';

export default function UserManagement() {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language === 'ar';
  const { settings } = useSettingsStore();
  const departments = settings.departments.filter(d => d.isActive).map(d => d.name);
  const activeDepartmentNames = new Set(departments.map(d => d.trim().toLowerCase()));
  const isKnownDepartment = (department?: string | null) =>
    Boolean(department && activeDepartmentNames.has(department.trim().toLowerCase()));
  const { users, currentUser, createUser, updateUser, changeUserPassword, deleteUser, toggleUserStatus, loadUsers } = useAuthStore();

  useEffect(() => {
    loadUsers();
  }, [loadUsers]);
  const [showModal, setShowModal] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [showConfirmDelete, setShowConfirmDelete] = useState<string | null>(null);
  const [passwordUser, setPasswordUser] = useState<User | null>(null);
  const [passwordForm, setPasswordForm] = useState({ password: '', confirmPassword: '', currentPassword: '' });
  const [passwordError, setPasswordError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [filterRole, setFilterRole] = useState<UserRole | 'all'>('all');
  const [showPassword, setShowPassword] = useState(false);

  // Form state
  const [formData, setFormData] = useState({
    ...emptyUserForm,
  });
  const [formError, setFormError] = useState('');
  const canSelectDepartment = formData.role === 'head_of_department';

  const filteredUsers = users.filter(user => {
    const matchesSearch = 
      user.name.includes(searchTerm) || 
      user.username.includes(searchTerm) ||
      user.email.includes(searchTerm);
    const matchesRole = filterRole === 'all' || user.role === filterRole;
    return matchesSearch && matchesRole;
  });

  const handleOpenModal = (user?: User) => {
    if (user) {
      setEditingUser(user);
      setFormData({
        name: user.name,
        username: user.username,
        email: user.email,
        password: '',
        role: user.role,
        department: user.role === 'head_of_department' && isKnownDepartment(user.department) ? user.department || '' : '',
      });
    } else {
      setEditingUser(null);
      setFormData({ ...emptyUserForm });
    }
    setFormError('');
    setShowPassword(false);
    setShowModal(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');

    // Validation
    if (!formData.name || !formData.username || !formData.email) {
      setFormError(t('user_err_fill_all'));
      return;
    }

    if (!editingUser && !formData.password) {
      setFormError(t('user_err_password_required'));
      return;
    }

    try {
      if (editingUser) {
        // Update existing user
        const updates: Partial<User> = {
          name: formData.name,
          email: formData.email,
          role: formData.role,
          department: formData.role === 'head_of_department' ? formData.department || null : null,
        };
        if (formData.password) {
          updates.password = formData.password;
        }
        await updateUser(editingUser.id, updates);
      } else {
        // Create new user
        await createUser({
          username: formData.username,
          name: formData.name,
          email: formData.email,
          password: formData.password,
          role: formData.role,
          department: formData.role === 'head_of_department' ? formData.department || undefined : undefined,
          isActive: true,
        });
      }

      await loadUsers();
      setShowModal(false);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : t('user_err_username_exists');
      setFormError(message);
    }
  };

  const handleDelete = async (userId: string) => {
    try {
      await deleteUser(userId);
      await loadUsers();
      setShowConfirmDelete(null);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Error deleting';
      alert(message);
    }
  };

  const handleOpenPasswordModal = (user: User) => {
    setPasswordUser(user);
    setPasswordForm({ password: '', confirmPassword: '', currentPassword: '' });
    setPasswordError('');
    setShowPassword(false);
  };

  const handleChangePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    setPasswordError('');

    if (!passwordUser) return;
    if (passwordForm.password.length < 8) {
      setPasswordError(t('user_password_err_min'));
      return;
    }
    if (passwordForm.password !== passwordForm.confirmPassword) {
      setPasswordError(t('user_password_err_match'));
      return;
    }

    const needsCurrentPassword = currentUser?.id === passwordUser.id || (['super_admin', 'admin'].includes(currentUser?.role || '') && currentUser?.id !== passwordUser.id);
    if (needsCurrentPassword && !passwordForm.currentPassword) {
      setPasswordError(t('user_password_current_required'));
      return;
    }

    try {
      await changeUserPassword(passwordUser.id, passwordForm.password, passwordForm.currentPassword);
      setPasswordUser(null);
      setPasswordForm({ password: '', confirmPassword: '', currentPassword: '' });
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'user_password_err_change';
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      setPasswordError(t(message as any, message)); // Fallback to message string if translation is missing
    }
  };

  return (
    <div className="text-start">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-linear-to-r from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-200 dark:shadow-none">
              <Users className="w-5 h-5 text-white" />
            </div>
            <div className="flex flex-col gap-0.5">
              <h2 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-white leading-tight">{t('user_management_title')}</h2>
              <p className="text-xs text-gray-500 dark:text-slate-400">{users.length} {t('user_registered_count')}</p>
            </div>
          </div>
          <button
            onClick={() => handleOpenModal()}
            type="button"
            className="flex items-center gap-2 bg-linear-to-r from-purple-600 to-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold hover:shadow-lg transition-all cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            <span className="hidden sm:inline">{t('user_new_title')}</span>
            <span className="sm:hidden">{t('user_new_short')}</span>
          </button>
        </div>
        {/* Filters */}
        <div className="bg-white dark:bg-slate-900 rounded-2xl p-4 mb-6 border border-gray-100 dark:border-slate-800 shadow-sm flex flex-wrap gap-4 items-center">
          <div className="relative flex-1 min-w-[200px]">
            <Search className={`absolute ${isRtl ? 'right-3' : 'left-3'} top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400`} />
            <input
              type="text"
              value={searchTerm}
              onChange={e => setSearchTerm(e.target.value)}
              placeholder={t('user_search_placeholder')}
              className="w-full pr-10 pl-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white placeholder-gray-450"
            />
          </div>
          <div className="flex items-center gap-2">
            <Shield className="w-4 h-4 text-gray-400" />
            <select
              value={filterRole}
              onChange={e => setFilterRole(e.target.value as UserRole | 'all')}
              className="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white"
            >
              <option value="all">{t('user_roles_all')}</option>
              <option value="super_admin">{t('user_role_super_admin')}</option>
              <option value="admin">{t('user_role_admin')}</option>
              <option value="unit_manager">{t('user_role_unit_manager')}</option>
              <option value="head_of_department">{t('user_role_head_of_department')}</option>
              <option value="staff">{t('user_role_staff')}</option>
            </select>
          </div>
        </div>

        {/* Users Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredUsers.map((user, i) => (
            <div
              key={user.id}
              className={`h-full bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800/80 shadow-sm hover:shadow-md transition-all overflow-hidden animate-slide-up flex flex-col ${
                !user.isActive ? 'opacity-60' : ''
              }`}
              style={{ animationDelay: `${Math.min(i, 6) * 50}ms` }}
            >
              {/* User Header */}
              <div className={`p-5 bg-linear-to-r ${roleColors[user.role]} text-white relative`}>
                <div className={`absolute top-3 ${isRtl ? 'left-3' : 'right-3'}`}>
                  <span className={`text-xs font-bold px-2.5 py-1 rounded-full ${
                    user.isActive ? 'bg-white/20' : 'bg-red-500/50'
                  }`}>
                    {user.isActive ? t('user_status_active') : t('user_status_inactive')}
                  </span>
                </div>
                <div className="flex items-center gap-4">
                  <div className="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center text-2xl font-bold">
                    {user.name.charAt(0)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <h3 className="font-bold text-lg truncate">{user.name}</h3>
                    <p className="text-white/70 text-sm truncate" dir="ltr">@{user.username}</p>
                  </div>
                </div>
              </div>

              {/* User Details */}
              <div className="p-4 space-y-3">
                <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-slate-350">
                  <Mail className="w-4 h-4 text-gray-400" />
                  <span className="truncate">{user.email}</span>
                </div>
                <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-slate-350">
                  <Shield className="w-4 h-4 text-gray-400" />
                  <span className={`font-medium truncate ${roleColors[user.role].includes('purple') ? 'text-purple-600 dark:text-purple-400' : roleColors[user.role].includes('blue') ? 'text-blue-600 dark:text-blue-400' : roleColors[user.role].includes('green') ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'}`}>
                    {t('user_role_' + user.role)}
                  </span>
                  {user.role === 'head_of_department' && isKnownDepartment(user.department) ? (
                    <>
                      <span className="text-gray-300 dark:text-slate-650">•</span>
                      <Building2 className="w-4 h-4 text-gray-400" />
                      <span className="truncate text-gray-600 dark:text-slate-350">{user.department}</span>
                    </>
                  ) : null}
                </div>
                <div className="min-h-5 flex items-center gap-2 text-xs text-gray-400 dark:text-slate-500">
                  {user.lastLogin ? (
                    <>
                      <Calendar className="w-3.5 h-3.5" />
                      <span>{t('user_last_login')} {new Date(user.lastLogin).toLocaleDateString(i18n.language === 'ar' ? 'ar-SA' : 'en-US')}</span>
                    </>
                  ) : null}
                </div>
              </div>

              {/* Actions */}
              <div className="px-4 pb-4 flex items-center gap-2">
                <button
                  onClick={() => handleOpenModal(user)}
                  disabled={currentUser?.role !== 'super_admin' && user.role === 'super_admin'}
                  type="button"
                  className="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 dark:text-slate-300 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-750 rounded-xl transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  <Edit3 className="w-4 h-4" />
                  {t('user_action_edit')}
                </button>
                <button
                  onClick={() => handleOpenPasswordModal(user)}
                  disabled={currentUser?.role !== 'super_admin' && user.role === 'super_admin'}
                  type="button"
                  className="flex items-center justify-center p-2 rounded-xl bg-indigo-100 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-200 dark:hover:bg-indigo-950/50 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                  title={t('user_action_change_password')}
                >
                  <KeyRound className="w-4 h-4" />
                </button>
                <button
                  onClick={async () => {
                    try {
                      await toggleUserStatus(user.id);
                    } catch (err: unknown) {
                      const message = err instanceof Error ? err.message : 'Error updating';
                      alert(message);
                    }
                  }}
                  disabled={user.id === currentUser?.id || (currentUser?.role !== 'super_admin' && user.role === 'super_admin')}
                  type="button"
                  className={`flex items-center justify-center p-2 rounded-xl transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed ${
                    user.id === currentUser?.id
                      ? 'bg-gray-100 dark:bg-slate-800 text-gray-300 dark:text-slate-600 cursor-not-allowed'
                      : user.isActive
                        ? 'bg-amber-100 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 hover:bg-amber-200 dark:hover:bg-amber-950/50'
                        : 'bg-green-100 dark:bg-green-950/30 text-green-600 dark:text-green-400 hover:bg-green-200 dark:hover:bg-green-950/50'
                  }`}
                  title={user.isActive ? t('user_action_deactivate') : t('user_action_activate')}
                >
                  {user.isActive ? <UserX className="w-4 h-4" /> : <UserCheck className="w-4 h-4" />}
                </button>
                <button
                  onClick={() => setShowConfirmDelete(user.id)}
                  disabled={user.id === currentUser?.id || (currentUser?.role !== 'super_admin' && user.role === 'super_admin')}
                  type="button"
                  className={`flex items-center justify-center p-2 rounded-xl transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed ${
                    user.id === currentUser?.id
                      ? 'bg-gray-100 dark:bg-slate-800 text-gray-300 dark:text-slate-600 cursor-not-allowed'
                      : 'bg-red-100 dark:bg-red-950/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-950/50'
                  }`}
                  title={t('user_action_delete')}
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            </div>
          ))}
        </div>

        {filteredUsers.length === 0 && (
          <div className="text-center py-16">
            <Users className="w-16 h-16 text-gray-200 dark:text-slate-750 mx-auto mb-4" />
            <p className="text-gray-500 dark:text-slate-400">{t('user_no_users_found')}</p>
          </div>
        )}
      </div>

      {/* User Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/65 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto animate-scale-in border border-gray-150 dark:border-slate-800">
            <div className="p-6 border-b border-gray-100 dark:border-slate-850 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 rounded-t-2xl">
              <h2 className="text-xl font-bold text-gray-800 dark:text-white">
                {editingUser ? t('user_edit_modal_title') : t('user_add_modal_title')}
              </h2>
              <button onClick={() => setShowModal(false)} type="button" className="text-gray-400 hover:text-gray-600 cursor-pointer">
                <X className="w-6 h-6" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="p-6 space-y-4" autoComplete="off">
              {formError && (
                <div className="flex items-center gap-2 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm">
                  <AlertCircle className="w-5 h-5 shrink-0" />
                  {formError}
                </div>
              )}

              <div>
                <label className="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
                  {t('user_form_name_label')} <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  autoComplete="off"
                  name="new-user-full-name"
                  value={formData.name}
                  onChange={e => setFormData({ ...formData, name: e.target.value })}
                  placeholder={t('user_form_name_placeholder')}
                  className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-450"
                />
              </div>

              <div>
                <label className="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
                  {t('user_form_username_label')} {!editingUser && <span className="text-red-500">*</span>}
                </label>
                <input
                  type="text"
                  autoComplete="off"
                  name="new-user-username"
                  value={formData.username}
                  onChange={e => setFormData({ ...formData, username: e.target.value })}
                  placeholder={t('user_form_username_placeholder')}
                  className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-450"
                  dir="ltr"
                  disabled={!!editingUser}
                />
              </div>

              <div>
                <label className="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
                  {t('user_form_email_label')} <span className="text-red-500">*</span>
                </label>
                <input
                  type="email"
                  autoComplete="off"
                  name="new-user-email"
                  value={formData.email}
                  onChange={e => setFormData({ ...formData, email: e.target.value })}
                  placeholder={t('user_form_email_placeholder')}
                  className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-450"
                  dir="ltr"
                />
              </div>

              <div>
                <label className="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
                  {editingUser ? t('user_form_password_edit_label') : t('user_form_password_new_label')}
                  {!editingUser && <span className="text-red-500"> *</span>}
                </label>
                <div className="relative">
                  <input
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="new-password"
                    name="new-user-password"
                    value={formData.password}
                    onChange={e => setFormData({ ...formData, password: e.target.value })}
                    placeholder={editingUser ? t('user_form_password_edit_placeholder') : t('user_form_password_new_placeholder')}
                    className="w-full px-4 py-3 pl-12 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-450"
                    dir="ltr"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className={`absolute ${isRtl ? 'left-3' : 'right-3'} top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer`}
                  >
                    {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                  </button>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{t('user_form_role_label')}</label>
                  <select
                    value={formData.role}
                    onChange={e => {
                      const role = e.target.value as UserRole;
                      setFormData({
                        ...formData,
                        role,
                        department: role === 'head_of_department' ? formData.department : '',
                      });
                    }}
                    disabled={editingUser?.id === currentUser?.id}
                    className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed"
                  >
                    <option value="staff">{t('user_role_staff')}</option>
                    <option value="head_of_department">{t('user_role_head_of_department')}</option>
                    <option value="unit_manager">{t('user_role_unit_manager')}</option>
                    <option value="admin">{t('user_role_admin')}</option>
                    {currentUser?.role === 'super_admin' && (
                      <option value="super_admin">{t('user_role_super_admin')}</option>
                    )}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{t('user_form_department_label')}</label>
                  <select
                    value={formData.department}
                    onChange={e => setFormData({ ...formData, department: e.target.value })}
                    disabled={!canSelectDepartment}
                    className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed"
                  >
                    <option value="">{t('user_form_department_none')}</option>
                    {departments.map(d => (
                      <option key={d} value={d}>{d}</option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Role Permissions Preview */}
              <div className="bg-gray-50 dark:bg-slate-950 rounded-xl p-4 border border-gray-100 dark:border-slate-850">
                <h4 className="text-sm font-bold text-gray-600 dark:text-slate-400 mb-3">{t('user_permissions_title')}</h4>
                <div className="space-y-2 text-xs">
                  {formData.role === 'super_admin' && (
                    <>
                      <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
                        <Check className="w-4 h-4" />
                        <span>{t('user_perm_full_system')}</span>
                      </div>
                      <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
                        <Check className="w-4 h-4" />
                        <span>{t('user_perm_manage_users')}</span>
                      </div>
                    </>
                  )}
                  {formData.role === 'admin' && (
                    <>
                      <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
                        <Check className="w-4 h-4" />
                        <span>{t('user_perm_manage_surveys')}</span>
                      </div>
                      <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
                        <Check className="w-4 h-4" />
                        <span>{t('user_perm_view_all_reports')}</span>
                      </div>
                      <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
                        <Check className="w-4 h-4" />
                        <span>{t('user_perm_manage_users_no')}</span>
                      </div>
                    </>
                  )}
                  {formData.role === 'head_of_department' && (
                    <>
                      <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
                        <Check className="w-4 h-4" />
                        <span>{t('user_perm_view_dept_reports')}</span>
                      </div>
                      <div className="flex items-center gap-2 text-red-500">
                        <X className="w-4 h-4" />
                        <span>{t('user_perm_manage_surveys')}</span>
                      </div>
                    </>
                  )}
                  {formData.role === 'unit_manager' && (
                    <>
                      <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
                        <Check className="w-4 h-4" />
                        <span>{t('user_perm_view_all_reports_export')}</span>
                      </div>
                      <div className="flex items-center gap-2 text-red-500">
                        <X className="w-4 h-4" />
                        <span>{t('user_perm_manage_surveys')}</span>
                      </div>
                    </>
                  )}
                  {formData.role === 'staff' && (
                    <>
                      <div className="flex items-center gap-2 text-red-500">
                        <X className="w-4 h-4" />
                        <span>{t('user_perm_view_reports_limited')}</span>
                      </div>
                      <div className="flex items-center gap-2 text-red-500">
                        <X className="w-4 h-4" />
                        <span>{t('user_perm_manage_surveys')}</span>
                      </div>
                    </>
                  )}
                </div>
              </div>

              <div className="flex items-center gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                >
                  {t('user_cancel')}
                </button>
                <button
                  type="submit"
                  className="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-linear-to-r from-purple-600 to-indigo-600 text-white font-bold shadow-lg shadow-purple-200 dark:shadow-none hover:shadow-xl transition-all cursor-pointer"
                >
                  <Check className="w-5 h-5" />
                  {editingUser ? t('user_save_changes') : t('user_add_user_btn')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Change Password Modal */}
      {passwordUser && (
        <div className="fixed inset-0 bg-black/65 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full animate-scale-in border border-gray-150 dark:border-slate-800">
            <div className="p-6 border-b border-gray-100 dark:border-slate-850 flex items-center justify-between">
              <div>
                <h2 className="text-xl font-bold text-gray-800 dark:text-white">{t('user_password_modal_title')}</h2>
                <p className="text-xs text-gray-500 dark:text-slate-400 mt-1">@{passwordUser.username}</p>
              </div>
              <button onClick={() => setPasswordUser(null)} type="button" className="text-gray-400 hover:text-gray-600 cursor-pointer">
                <X className="w-6 h-6" />
              </button>
            </div>

            <form onSubmit={handleChangePassword} className="p-6 space-y-4">
              {passwordError && (
                <div className="flex items-center gap-2 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm">
                  <AlertCircle className="w-5 h-5 shrink-0" />
                  {passwordError}
                </div>
              )}

              {(currentUser?.id === passwordUser.id || (['super_admin', 'admin'].includes(currentUser?.role || '') && currentUser?.id !== passwordUser.id)) && (
                <div>
                  <label className="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
                    {t(currentUser?.id === passwordUser.id 
                      ? 'user_password_current_label' 
                      : currentUser?.role === 'super_admin' ? 'user_password_super_admin_label' : 'user_password_admin_label'
                    )} <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <input
                      type={showPassword ? 'text' : 'password'}
                      value={passwordForm.currentPassword}
                      onChange={e => setPasswordForm({ ...passwordForm, currentPassword: e.target.value })}
                      placeholder={t(currentUser?.id === passwordUser.id 
                        ? 'user_password_current_placeholder' 
                        : currentUser?.role === 'super_admin' ? 'user_password_super_admin_placeholder' : 'user_password_admin_placeholder'
                      )}
                      className="w-full px-4 py-3 pl-12 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-450"
                      dir="ltr"
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className={`absolute ${isRtl ? 'left-3' : 'right-3'} top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer`}
                    >
                      {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                    </button>
                  </div>
                </div>
              )}

              <div>
                <label className="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{t('user_password_new_label')}</label>
                <div className="relative">
                  <input
                    type={showPassword ? 'text' : 'password'}
                    value={passwordForm.password}
                    onChange={e => setPasswordForm({ ...passwordForm, password: e.target.value })}
                    placeholder={t('user_password_new_placeholder')}
                    className="w-full px-4 py-3 pl-12 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-450"
                    dir="ltr"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className={`absolute ${isRtl ? 'left-3' : 'right-3'} top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer`}
                  >
                    {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                  </button>
                </div>
              </div>

              <div>
                <label className="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{t('user_password_confirm_label')}</label>
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={passwordForm.confirmPassword}
                  onChange={e => setPasswordForm({ ...passwordForm, confirmPassword: e.target.value })}
                  placeholder={t('user_password_confirm_placeholder')}
                  className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-450"
                  dir="ltr"
                />
              </div>

              <div className="rounded-xl bg-amber-50 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/35 px-4 py-3 text-xs text-amber-700 dark:text-amber-400 leading-relaxed">
                {t('user_password_session_note')}
              </div>

              <div className="flex items-center gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setPasswordUser(null)}
                  className="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                >
                  {t('user_cancel')}
                </button>
                <button
                  type="submit"
                  className="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-200 dark:shadow-none hover:bg-indigo-700 transition-all cursor-pointer"
                >
                  <KeyRound className="w-5 h-5" />
                  {t('user_password_save_btn')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {showConfirmDelete && (
        <div className="fixed inset-0 bg-black/65 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-sm w-full p-6 animate-scale-in border border-gray-150 dark:border-slate-800">
            <div className="text-center">
              <div className="w-16 h-16 bg-red-100 dark:bg-red-950/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <Trash2 className="w-8 h-8 text-red-500" />
              </div>
              <h3 className="text-lg font-bold text-gray-800 dark:text-white mb-2">{t('user_delete_confirm_title')}</h3>
              <p className="text-gray-500 dark:text-slate-400 text-sm mb-6">{t('user_delete_confirm_desc')}</p>
              <div className="flex items-center gap-3">
                <button
                  onClick={() => setShowConfirmDelete(null)}
                  type="button"
                  className="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                >
                  {t('user_cancel')}
                </button>
                <button
                  onClick={() => handleDelete(showConfirmDelete)}
                  type="button"
                  className="flex-1 px-4 py-3 rounded-xl bg-red-500 text-white font-bold hover:bg-red-600 transition-colors cursor-pointer"
                >
                  {t('user_action_delete')}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
