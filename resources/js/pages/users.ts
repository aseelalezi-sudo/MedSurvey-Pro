import Alpine from 'alpinejs';

interface User {
  id: string | number;
  name: string;
  username: string;
  email: string;
  role: string;
  department: string | null;
  isActive?: boolean;
  permissions?: string[];
  rolePermissions?: Record<string, string[]>;
}

interface CustomWindow {
  Alpine?: {
    initTree: (el: HTMLElement | null) => void;
  };
  lucide?: {
    createIcons: () => void;
  };
}

interface UserManagementComponent {
  $nextTick: (cb: () => void) => void;
  showToastMsg: (msg: string, type?: string) => void;
  refreshUsersContent: (url?: string, pushUrl?: boolean) => Promise<void>;
}

document.addEventListener('alpine:init', () => {
  Alpine.data('userManagement', (props: { isAr: boolean; rolePermissions: Record<string, string[]> }) => ({
    showModal: false,
    showPasswordModal: false,
    showDeleteModal: false,
    editingUser: null as User | null,
    passwordUser: null as User | null,
    userToDelete: null as string | number | null,
    isRefreshing: false,
    toast: { show: false, message: '', type: 'success' },
    showPassword: false,
    showPassword2: false,
    fieldErrors: {},
    formData: {
      name: '',
      username: '',
      email: '',
      role: 'staff',
      department: '',
      direct_permissions: [] as string[],
    },

    isInherited(permission: string) {
      const inherited = props.rolePermissions[this.formData.role] || [];
      return inherited.includes(permission);
    },

    togglePermission(permission: string) {
      if (this.isInherited(permission)) return;

      const idx = this.formData.direct_permissions.indexOf(permission);
      if (idx > -1) {
        this.formData.direct_permissions.splice(idx, 1);
      } else {
        this.formData.direct_permissions.push(permission);
      }
    },

    showToastMsg(message: string, type = 'success') {
      this.toast = { show: true, message, type };
      setTimeout(() => {
        this.toast.show = false;
      }, 3000);
    },

    async refreshUsersContent(url = window.location.href, pushUrl = false) {
      this.isRefreshing = true;

      try {
        const response = await fetch(url, {
          headers: {
            Accept: 'text/html',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        if (!response.ok) throw new Error('Failed to refresh users');

        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const nextContent = doc.getElementById('users-content');
        const currentContent = document.getElementById('users-content');

        if (nextContent && currentContent) {
          currentContent.innerHTML = nextContent.innerHTML;
          const win = window as unknown as CustomWindow;
          if (win.Alpine) win.Alpine.initTree(currentContent);
        }

        if (pushUrl) {
          window.history.pushState({}, '', url);
        }

        const self = this as unknown as UserManagementComponent;
        const win = window as unknown as CustomWindow;
        self.$nextTick(() => win.lucide && win.lucide.createIcons());
      } catch (error) {
        console.error(error);
        (this as unknown as UserManagementComponent).showToastMsg(
          props.isAr ? 'تعذر تحديث قائمة المستخدمين' : 'Could not refresh users list',
          'error',
        );
      } finally {
        this.isRefreshing = false;
      }
    },

    async submitUserFilters() {
      const form = document.getElementById('usersFilterForm') as HTMLFormElement;
      const params = new URLSearchParams(Array.from(new FormData(form)) as string[][]);
      [...params.keys()].forEach((key) => {
        if (!params.get(key)) params.delete(key);
      });
      const qs = params.toString();
      const url = `${form.action}${qs ? `?${qs}` : ''}`;

      await (this as unknown as UserManagementComponent).refreshUsersContent(url, true);
    },

    async submitUserAction(form: HTMLFormElement, successMessage: string, afterSuccess: (() => void) | null = null) {
      this.isRefreshing = true;
      this.fieldErrors = {};

      try {
        const response = await fetch(form.action, {
          method: form.method || 'POST',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement).content,
          },
          body: new FormData(form),
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
          if (response.status === 422 && result.errors) {
            this.fieldErrors = result.errors;
            const self = this as unknown as UserManagementComponent;
            const win = window as unknown as CustomWindow;
            self.$nextTick(() => win.lucide && win.lucide.createIcons());
            return;
          }
          throw new Error(result.error || result.message || 'Action failed');
        }

        if (typeof afterSuccess === 'function') afterSuccess();
        form.reset();
        const self = this as unknown as UserManagementComponent;
        self.showToastMsg(successMessage);
        await self.refreshUsersContent();
      } catch (error: unknown) {
        (this as unknown as UserManagementComponent).showToastMsg(
          error instanceof Error ? error.message : 'Network Error',
          'error',
        );
      } finally {
        this.isRefreshing = false;
      }
    },

    openCreateModal() {
      this.editingUser = null;
      this.fieldErrors = {};
      this.formData = {
        name: '',
        username: '',
        email: '',
        role: 'staff',
        department: '',
        direct_permissions: [],
      };
      this.showPassword = false;
      this.showModal = true;
    },

    openEditModal(user: User) {
      this.editingUser = user;
      this.fieldErrors = {};
      this.formData = {
        name: user.name,
        username: user.username,
        email: user.email,
        role: user.role,
        department: user.department || '',
        direct_permissions: (user.permissions || []).map((p: any) => (p.name ? p.name : p)),
      };

      // Filter out inherited permissions so they don't get saved again as direct
      const inherited = props.rolePermissions[user.role] || [];
      this.formData.direct_permissions = this.formData.direct_permissions.filter((p) => !inherited.includes(p));
      this.showPassword = false;
      this.showModal = true;
    },

    openPasswordModal(user: User) {
      this.passwordUser = user;
      this.showPassword2 = false;
      this.fieldErrors = {};
      this.showPasswordModal = true;
    },

    openDeleteModal(userId: string | number) {
      this.userToDelete = userId;
      this.fieldErrors = {};
      this.showDeleteModal = true;
    },
  }));
});
