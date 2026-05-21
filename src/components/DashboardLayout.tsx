import { useState, useEffect, useRef } from 'react';
import { Outlet, useNavigate, useLocation } from 'react-router-dom';
import { UserRole, rolePermissions, useAuthStore } from '../store/useAuthStore';
import { useTranslation } from 'react-i18next';
import { useSettingsStore } from '../store/useSettingsStore';

import ThemeToggle from './ThemeToggle';
import { ticketsAPI } from '../api/client';
import { usePredictiveStore } from '../store/usePredictiveStore';
import {
  Stethoscope,
  BarChart3,
  FileText,
  ClipboardList,
  UserCog,
  Settings,
  LogOut,
  Home,
  AlertCircle,
  Trophy,
  Heart,
  Brain,
  TrendingUp,
  ShieldAlert,
  ChevronDown,
  Mail,
  Clock,
  Menu,
  X,
  ChevronLeft,
  ChevronRight,
  Activity,
  KeyRound,
  Bug,
  Database,
} from 'lucide-react';
import ChangePasswordModal from './ChangePasswordModal';

type DashboardTab = 'dashboard' | 'responses' | 'surveys' | 'users' | 'settings' | 'tickets' | 'hall-of-fame' | 'predictive' | 'reports' | 'audit' | 'monitoring' | 'error-logs' | 'backups' | 'home';

interface NavItem {
  id: DashboardTab;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  show: boolean;
  badge?: number;
  action?: () => void;
}

const roleLabelsMap: Record<UserRole, string> = {
  super_admin: 'role_super_admin',
  admin: 'role_admin',
  unit_manager: 'role_unit_manager',
  head_of_department: 'role_head',
  staff: 'role_staff',
};

export default function DashboardLayout() {
  const navigate = useNavigate();
  const location = useLocation();
  const { t, i18n } = useTranslation();
  const { settings } = useSettingsStore();
  const { currentUser, logout } = useAuthStore();

  const onLogout = () => {
    logout();
    navigate('/');
  };
  const onHome = () => navigate('/');

  const hospitalMobileName = settings.hospital.shortName || settings.hospital.name;
  const permissions = currentUser ? rolePermissions[currentUser.role] : null;
  const [openTicketsCount, setOpenTicketsCount] = useState(0);
  const [predictiveCount, setPredictiveCount] = useState(0);
  const [showChangePasswordModal, setShowChangePasswordModal] = useState(false);

  // Profile Dropdown States & Helpers
  const [showProfileDropdown, setShowProfileDropdown] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Sidebar Collapse and Mobile Drawer States
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(() => {
    const saved = localStorage.getItem('sidebar_collapsed');
    return saved ? JSON.parse(saved) : false;
  });
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  const toggleSidebar = () => {
    setIsSidebarCollapsed((prev: boolean) => {
      const next = !prev;
      localStorage.setItem('sidebar_collapsed', JSON.stringify(next));
      return next;
    });
  };

  useEffect(() => {
    setIsMobileMenuOpen(false);
  }, [location.pathname]);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setShowProfileDropdown(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  const getUserInitials = (name: string) => {
    if (!name) return '?';
    return name.trim().split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
  };

  const getRoleGradient = (role: UserRole) => {
    switch (role) {
      case 'super_admin': return 'from-violet-500 to-indigo-600';
      case 'admin': return 'from-teal-500 to-emerald-600';
      case 'unit_manager': return 'from-teal-500 to-cyan-600';
      case 'head_of_department': return 'from-orange-500 to-amber-600';
      case 'staff': return 'from-blue-500 to-sky-600';
      default: return 'from-gray-500 to-slate-600';
    }
  };

  const getRoleBadgeStyle = (role: UserRole) => {
    switch (role) {
      case 'super_admin': return 'bg-violet-50 text-violet-700 dark:bg-violet-950/20 dark:text-violet-400 border-violet-200 dark:border-violet-900/40';
      case 'admin': return 'bg-teal-50 text-teal-700 dark:bg-teal-950/20 dark:text-teal-400 border-teal-200 dark:border-teal-900/40';
      case 'unit_manager': return 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/20 dark:text-cyan-400 border-cyan-200 dark:border-cyan-900/40';
      case 'head_of_department': return 'bg-orange-50 text-orange-700 dark:bg-orange-950/20 dark:text-orange-400 border-orange-200 dark:border-orange-900/40';
      case 'staff': return 'bg-blue-50 text-blue-700 dark:bg-blue-950/20 dark:text-blue-400 border-blue-200 dark:border-blue-900/40';
      default: return 'bg-gray-50 text-gray-700 dark:bg-gray-950/20 dark:text-gray-400 border-gray-200 dark:border-gray-900/40';
    }
  };

  // Load predictive data from centralized store (single API call shared across all components)
  const { activeWarningCount, loadPredictiveData } = usePredictiveStore();

  useEffect(() => {
    ticketsAPI.getAll({ status: 'open' }).then(tickets => {
      setOpenTicketsCount(tickets.length);
    }).catch(() => {});

    if (currentUser?.role !== 'staff') {
      const activated = settings.activatedPredictivePlans || [];
      loadPredictiveData(activated);
    }
  }, [settings, currentUser, loadPredictiveData]);

  useEffect(() => {
    setPredictiveCount(activeWarningCount);
  }, [activeWarningCount]);

  // Determine active tab from current path
  const path = location.pathname;
  let activeTab: DashboardTab = 'dashboard';
  if (path.includes('/dashboard/responses')) activeTab = 'responses';
  else if (path.includes('/dashboard/reports')) activeTab = 'reports';
  else if (path.includes('/dashboard/surveys')) activeTab = 'surveys';
  else if (path.includes('/dashboard/tickets')) activeTab = 'tickets';
  else if (path.includes('/dashboard/users')) activeTab = 'users';
  else if (path.includes('/dashboard/settings')) activeTab = 'settings';
  else if (path.includes('/dashboard/hall-of-fame')) activeTab = 'hall-of-fame';
  else if (path.includes('/dashboard/predictive')) activeTab = 'predictive';
  else if (path.includes('/dashboard/audit')) activeTab = 'audit';
  else if (path.includes('/dashboard/monitoring')) activeTab = 'monitoring';
  else if (path.includes('/dashboard/error-logs')) activeTab = 'error-logs';
  else if (path.includes('/dashboard/backups')) activeTab = 'backups';

  const handleNavigate = (tab: DashboardTab) => {
    if (tab === 'dashboard') navigate('/dashboard');
    else navigate(`/dashboard/${tab}`);
  };

  const categories: { id: string; title: string; items: NavItem[]; mobileOnly?: boolean }[] = [
    {
      id: 'analytics',
      title: t('nav_group_analytics', 'التحليلات والتقارير'),
      items: [
        { id: 'dashboard' as DashboardTab, label: t('nav_dashboard'), icon: BarChart3, show: currentUser?.role !== 'staff', badge: undefined },
        { id: 'predictive' as DashboardTab, label: t('nav_predictive', 'التنبؤ والإنذار المبكر (AI)'), icon: Brain, show: !!(permissions?.canViewAllReports || permissions?.canViewDepartmentReports), badge: predictiveCount > 0 ? predictiveCount : undefined },
        { id: 'reports' as DashboardTab, label: t('nav_reports', 'التقارير المتقدمة'), icon: TrendingUp, show: !!(permissions?.canViewAllReports || permissions?.canViewDepartmentReports), badge: undefined },
      ]
    },
    {
      id: 'feedback',
      title: t('nav_group_feedback', 'المتابعة والآراء'),
      items: [
        { id: 'tickets' as DashboardTab, label: t('nav_tickets'), icon: AlertCircle, show: !!(permissions?.canViewAllReports || permissions?.canViewDepartmentReports), badge: openTicketsCount > 0 ? openTicketsCount : undefined },
        { id: 'responses' as DashboardTab, label: t('nav_responses'), icon: FileText, show: true, badge: undefined },
        { id: 'hall-of-fame' as DashboardTab, label: t('nav_honor'), icon: Trophy, show: currentUser?.role !== 'staff', badge: undefined },
      ]
    },
    {
      id: 'management',
      title: t('nav_group_management', 'النظام والإدارة'),
      items: [
        { id: 'surveys' as DashboardTab, label: t('nav_surveys'), icon: ClipboardList, show: !!permissions?.canManageSurveys, badge: undefined },
        { id: 'users' as DashboardTab, label: t('nav_users'), icon: UserCog, show: !!permissions?.canManageUsers, badge: undefined },
        { id: 'audit' as DashboardTab, label: t('nav_audit', 'سجل العمليات والأمان'), icon: ShieldAlert, show: !!permissions?.canManageUsers, badge: undefined },
        { id: 'monitoring' as DashboardTab, label: t('nav_monitoring', 'مراقبة أداء النظام'), icon: Activity, show: !!permissions?.canManageUsers, badge: undefined },
        { id: 'error-logs' as DashboardTab, label: t('nav_error_logs', 'سجل أخطاء النظام'), icon: Bug, show: !!permissions?.canManageUsers, badge: undefined },
        { id: 'backups' as DashboardTab, label: t('nav_backups', 'النسخ الاحتياطي'), icon: Database, show: !!permissions?.canManageUsers, badge: undefined },
        { id: 'settings' as DashboardTab, label: t('nav_settings'), icon: Settings, show: !!permissions?.canManageUsers, badge: undefined },
      ]
    },
    {
      id: 'quick-actions',
      title: t('nav_group_quick_actions', 'إجراءات سريعة'),
      items: [
        { id: 'home' as DashboardTab, label: t('homepage'), icon: Home, show: true, badge: undefined, action: onHome },
      ],
      mobileOnly: true
    }
  ];

  const isRtl = i18n.language === 'ar';
  const sidebarWidthClass = isSidebarCollapsed ? 'md:w-20' : 'md:w-64';
  const mainPaddingClass = isRtl
    ? isSidebarCollapsed ? 'md:pr-20' : 'md:pr-64'
    : isSidebarCollapsed ? 'md:pl-20' : 'md:pl-64';

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-[#080b11] text-gray-900 dark:text-slate-100 transition-colors duration-300 flex overflow-x-hidden">
      
      {/* 1. BACKDROP OVERLAY FOR MOBILE */}
      {isMobileMenuOpen && (
        <div 
          onClick={() => setIsMobileMenuOpen(false)}
          className="fixed inset-0 bg-black/40 backdrop-blur-sm z-45 md:hidden animate-fade-in"
        />
      )}

      {/* 2. SIDEBAR CONTAINER */}
      <aside 
        className={`fixed top-0 bottom-0 z-50 flex flex-col bg-white dark:bg-slate-900 border-gray-150 dark:border-slate-800/85 shadow-lg md:shadow-none transition-all duration-300 ease-in-out
          ${isRtl ? 'right-0 border-l' : 'left-0 border-r'}
          w-72 ${sidebarWidthClass}
          ${isMobileMenuOpen 
            ? 'translate-x-0' 
            : isRtl 
              ? 'translate-x-full md:translate-x-0' 
              : '-translate-x-full md:translate-x-0'
          }`}
      >
        {/* Sidebar Brand Logo Header */}
        <div className="h-16 flex items-center justify-between px-4 border-b border-gray-100 dark:border-slate-800/80">
          <div className="flex items-center gap-2.5 overflow-hidden">
            <button 
              onClick={onHome}
              className="w-10 h-10 min-w-10 bg-linear-to- from-teal-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-md shadow-teal-200 dark:shadow-teal-900/40 hover:scale-105 transition-transform"
              title={t('homepage')}
            >
              <Stethoscope className="w-5 h-5 text-white" />
            </button>
            {!isSidebarCollapsed && (
              <div className="text-start animate-fade-in">
                <span className="text-sm font-black text-gray-950 dark:text-white block leading-none">MedSurvey Pro</span>
                <span className="text-[9px] text-gray-400 dark:text-slate-500 font-bold block mt-1 leading-none">{t('control_panel', 'لوحة التحكم')}</span>
              </div>
            )}
          </div>

          {/* Close button inside mobile menu */}
          <button 
            onClick={() => setIsMobileMenuOpen(false)}
            className="md:hidden w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-200 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800 cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Categories and Navigation Items */}
        <div className="flex-1 overflow-y-auto px-3 py-4 space-y-6 scrollbar-hide select-none">
          {categories.map((category) => {
            const visibleItems = category.items.filter(item => item.show);
            if (visibleItems.length === 0) return null;

            return (
              <div key={category.id} className={`space-y-1.5 ${category.mobileOnly ? 'md:hidden' : ''}`}>
                {/* Category Header (Only shown if sidebar is expanded) */}
                {!isSidebarCollapsed ? (
                  <h5 className="px-3 text-[10px] font-black text-gray-400 dark:text-slate-505 tracking-wider uppercase text-start animate-fade-in">
                    {category.title}
                  </h5>
                ) : (
                  <div className="h-px bg-gray-100 dark:bg-slate-800/60 mx-2" />
                )}

                {/* Category Items */}
                <div className="space-y-1">
                  {visibleItems.map((item) => {
                    const Icon = item.icon;
                    const isActive = activeTab === item.id;
                    
                    // Dynamic active classes
                    const activeBg = isActive
                      ? 'bg-teal-50/70 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-l-2 md:border-l-0 md:border-r-2 md:border-teal-600 dark:md:border-teal-500 font-black'
                      : 'text-gray-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 hover:bg-gray-50 dark:hover:bg-slate-850';

                    return (
                      <button
                        key={item.id}
                        onClick={() => {
                          if (item.action) {
                            item.action();
                          } else {
                            handleNavigate(item.id);
                          }
                          setIsMobileMenuOpen(false);
                        }}
                        className={`w-full flex items-center gap-3.5 px-3 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition-all relative group cursor-pointer text-start ${activeBg}`}
                        title={isSidebarCollapsed ? item.label : undefined}
                      >
                        <Icon className={`w-4 h-4 min-w-4 transition-transform group-hover:scale-105 ${isActive ? 'text-teal-600 dark:text-teal-400' : ''}`} />
                        {!isSidebarCollapsed && (
                          <span className="truncate animate-fade-in">{item.label}</span>
                        )}

                        {/* Badges */}
                        {item.badge !== undefined && (
                          <span className={`absolute ${isSidebarCollapsed ? 'top-1.5 right-1.5' : 'left-3 top-1/2 -translate-y-1/2'} flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] text-white font-black ring-2 ring-white dark:ring-slate-900 animate-pulse`}>
                            {item.badge}
                          </span>
                        )}

                        {/* Tooltip for Collapsed Sidebar */}
                        {isSidebarCollapsed && (
                          <div className={`absolute z-50 hidden group-hover:block bg-slate-950 text-white text-[11px] font-bold py-1 px-2.5 rounded-lg whitespace-nowrap shadow-md pointer-events-none transition-all
                            ${isRtl ? 'right-full mr-2' : 'left-full ml-2'}`}>
                            {item.label}
                          </div>
                        )}
                      </button>
                    );
                  })}
                </div>
              </div>
            );
          })}
        </div>

        {/* Sidebar Footer (Collapse button at bottom of desktop sidebar) */}
        <div className="p-3 border-t border-gray-100 dark:border-slate-800/80 hidden md:block">
          <button
            onClick={toggleSidebar}
            className="w-full flex items-center justify-center p-2.5 rounded-xl border border-gray-100 dark:border-slate-800/60 hover:bg-gray-50 dark:hover:bg-slate-850 hover:border-gray-200 dark:hover:border-slate-700 text-gray-400 hover:text-gray-600 dark:hover:text-slate-200 cursor-pointer transition-all shadow-sm group"
            title={isSidebarCollapsed ? t('expand_sidebar', 'توسيع القائمة') : t('collapse_sidebar', 'طي القائمة')}
          >
            {isSidebarCollapsed ? (
              isRtl ? <ChevronLeft className="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" /> : <ChevronRight className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
            ) : (
              <div className="flex items-center gap-2.5 font-bold text-xs select-none">
                {isRtl ? <ChevronRight className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" /> : <ChevronLeft className="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />}
                <span className="text-gray-400 dark:text-slate-500 font-extrabold group-hover:text-gray-600 dark:group-hover:text-slate-300">{t('collapse_sidebar', 'طي القائمة')}</span>
              </div>
            )}
          </button>
        </div>
      </aside>

      {/* 3. MAIN WORKSPACE WRAPPER */}
      <div 
        className={`flex-1 min-w-0 flex flex-col min-h-screen transition-all duration-300 ease-in-out ${mainPaddingClass}`}
      >
        {/* Fixed Header */}
        <header className="bg-white/95 dark:bg-slate-900/95 border-b border-gray-150 dark:border-slate-800/80 sticky top-0 z-40 shadow-sm transition-colors duration-300 backdrop-blur-md">
          {/* Top Bar */}
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex items-center justify-between h-14 sm:h-16 gap-2 min-w-0">
              
              {/* Logo (shown on mobile) & Hospital Identity */}
              <div className="flex items-center gap-2 sm:gap-4 min-w-0">
                {/* Hamburger menu for mobile drawer trigger */}
                <button
                  onClick={() => setIsMobileMenuOpen(true)}
                  className="md:hidden p-2 rounded-xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-gray-500 hover:text-gray-700 dark:hover:text-slate-300 cursor-pointer shadow-sm"
                  title="القائمة"
                >
                  <Menu className="w-5 h-5" />
                </button>

                {/* Hospital Identity */}
                <div className="flex items-center gap-2 sm:gap-2.5 min-w-0 max-w-[52vw] sm:max-w-[280px] lg:max-w-[360px]">
                  {settings.hospital.logo ? (
                    <div className="relative group bg-white p-0.5 rounded-lg border border-gray-200 dark:border-slate-600 shadow-md flex items-center justify-center shrink-0">
                      <img src={settings.hospital.logo} alt={settings.hospital.name} className="h-6 sm:h-7 w-auto max-w-[60px] sm:max-w-[80px] object-contain rounded-md transform group-hover:scale-105 transition-transform duration-300" />
                    </div>
                  ) : (
                    <div className="w-8 h-8 bg-teal-50 dark:bg-teal-950/40 border border-teal-200 dark:border-teal-800/40 rounded-xl flex items-center justify-center text-teal-600 dark:text-teal-400 shadow-sm">
                      <Heart className="w-4 h-4" />
                    </div>
                  )}
                  <div className="text-start flex min-w-0 flex-col gap-0.5 overflow-hidden">
                    <span className="text-xs sm:hidden font-black text-gray-900 dark:text-white block leading-snug whitespace-nowrap">{hospitalMobileName}</span>
                    <span className="hidden sm:block text-sm font-black text-gray-900 dark:text-white leading-snug whitespace-nowrap">{settings.hospital.name}</span>
                    <span className="text-[9px] sm:text-[10px] text-gray-400 dark:text-slate-400 block leading-snug truncate">{settings.hospital.operatingTitle || t('operating_hospital', 'المستشفى المشغل')}</span>
                  </div>
                </div>
              </div>

              {/* Quick Actions */}
              <div className="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
                <button 
                  onClick={onHome}
                  className="hidden sm:flex items-center gap-1.5 text-xs sm:text-sm text-gray-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-xl hover:bg-teal-50 dark:hover:bg-slate-800 transition-all cursor-pointer font-bold"
                  title={t('homepage')}
                >
                  <Home className="w-4 h-4" />
                  <span className="hidden lg:inline">{t('homepage')}</span>
                </button>

                {/* Theme Toggle Switcher */}
                <ThemeToggle />

                {/* 4. USER PROFILE CHIP AND DROPDOWN */}
                {currentUser && (
                  <div className="relative" ref={dropdownRef}>
                    {/* Profile Trigger Chip */}
                    <button
                      onClick={() => setShowProfileDropdown(!showProfileDropdown)}
                      type="button"
                      className="flex max-w-[44vw] lg:max-w-[240px] items-center gap-2.5 p-1 sm:pr-3.5 sm:pl-2.5 rounded-full sm:rounded-xl border border-gray-150 dark:border-slate-800 bg-white dark:bg-slate-900 hover:bg-gray-50 dark:hover:bg-slate-850 hover:border-gray-200 dark:hover:border-slate-700 transition-all cursor-pointer shadow-sm group select-none"
                    >
                      <div className={`w-8 h-8 rounded-full bg-linear-to- ${getRoleGradient(currentUser.role)} flex items-center justify-center text-white text-xs font-black shadow-md shadow-indigo-100 dark:shadow-none transition-transform group-hover:scale-105`}>
                        {getUserInitials(currentUser.name)}
                      </div>
                      <div className="hidden sm:flex min-w-0 flex-col text-start">
                        <span className="text-xs font-black text-gray-800 dark:text-slate-200 leading-snug group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors font-bold truncate">
                          {currentUser.name}
                        </span>
                        <span className="text-[9px] text-gray-400 dark:text-slate-500 font-bold truncate">
                          @{currentUser.username}
                        </span>
                      </div>
                      <ChevronDown className={`w-3.5 h-3.5 text-gray-400 dark:text-slate-500 hidden sm:block transition-transform duration-250 ${showProfileDropdown ? 'rotate-180 text-teal-600 dark:text-teal-400' : ''}`} />
                    </button>

                    {/* Profile Dropdown Menu */}
                    {showProfileDropdown && (
                      <div className="absolute end-0 top-full mt-2 w-[min(18rem,calc(100vw-2rem))] bg-white/95 dark:bg-slate-900/95 backdrop-blur-md rounded-2xl shadow-xl border border-gray-150 dark:border-slate-800/80 py-3 z-[80] animate-scale-in origin-top">
                        {/* User Detailed Card */}
                        <div className="px-4 py-3 border-b border-gray-50 dark:border-slate-850/60 flex flex-col items-center text-center">
                          <div className={`w-14 h-14 rounded-2xl bg-linear-to- ${getRoleGradient(currentUser.role)} flex items-center justify-center text-white text-lg font-black shadow-lg shadow-indigo-100 dark:shadow-none mb-3`}>
                            {getUserInitials(currentUser.name)}
                          </div>
                          <h4 className="max-w-full break-words font-black text-sm text-gray-900 dark:text-white leading-tight">{currentUser.name}</h4>
                          <span className="max-w-full truncate text-xs text-gray-400 dark:text-slate-505 font-semibold mt-0.5">@{currentUser.username}</span>
                          
                          <div className="mt-2.5 flex flex-wrap gap-1.5 justify-center">
                            <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border shadow-sm ${getRoleBadgeStyle(currentUser.role)}`}>
                              {t(roleLabelsMap[currentUser.role])}
                            </span>
                            {currentUser.department && (
                              <span className="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border border-teal-100 dark:border-teal-900/35 bg-teal-50/50 dark:bg-teal-950/15 text-teal-700 dark:text-teal-400 shadow-sm">
                                {currentUser.department}
                              </span>
                            )}
                          </div>
                        </div>

                        {/* Details / Meta Information */}
                        <div className="px-4 py-2 text-[10px] font-bold text-gray-400 dark:text-slate-505 uppercase tracking-wider mt-2 text-start">
                          {t('user_profile_details', 'تفاصيل الحساب')}
                        </div>

                        <div className="px-4 py-1.5 space-y-2 text-start">
                          <div className="flex items-center gap-2.5 text-xs text-gray-600 dark:text-slate-400">
                            <Mail className="w-3.5 h-3.5 text-gray-400" />
                            <span className="truncate font-semibold">{currentUser.email || t('no_email', 'لا يوجد بريد إلكتروني')}</span>
                          </div>
                          {currentUser.lastLogin && (
                            <div className="flex items-center gap-2.5 text-[11px] text-gray-500 dark:text-slate-400">
                              <Clock className="w-3.5 h-3.5 text-gray-400" />
                              <span className="font-semibold">
                                {t('last_login', 'آخر دخول')}: {new Date(currentUser.lastLogin).toLocaleDateString(i18n.language === 'ar' ? 'ar-SA' : 'en-US')}
                              </span>
                            </div>
                          )}
                        </div>

                        <div className="h-px bg-gray-50 dark:bg-slate-850/60 my-2.5" />

                        <div className="px-2 space-y-1">
                          {/* Change Password Action */}
                          <button
                            onClick={() => {
                              setShowProfileDropdown(false);
                              setShowChangePasswordModal(true);
                            }}
                            type="button"
                            className="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs sm:text-sm text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-850 font-bold transition-all cursor-pointer text-start"
                          >
                            <KeyRound className="w-4 h-4" />
                            <span>{t('user_action_change_password', 'تغيير كلمة المرور')}</span>
                          </button>

                          {/* Logout Action */}
                          <button
                            onClick={() => {
                              setShowProfileDropdown(false);
                              onLogout();
                            }}
                            type="button"
                            className="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs sm:text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20 font-black transition-all cursor-pointer text-start"
                          >
                            <LogOut className="w-4 h-4" />
                            <span>{t('logout')}</span>
                          </button>
                        </div>
                      </div>
                    )}
                  </div>
                )}
              </div>
            </div>
          </div>
        </header>

        {/* Page Content */}
        <main className="animate-fade-in p-3 sm:p-6 lg:p-8 flex-1 max-w-7xl w-full min-w-0 mx-auto">
          <Outlet />
        </main>
      </div>

      {currentUser && (
        <ChangePasswordModal
          isOpen={showChangePasswordModal}
          onClose={() => setShowChangePasswordModal(false)}
          userId={currentUser.id}
          username={currentUser.username}
        />
      )}
    </div>
  );
}
