import { useState, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Ticket, TicketStatus, SurveyResponse } from '../types';
import { useAuthStore } from '../store/useAuthStore';
import { useTicketsStore } from '../store/useTicketsStore';
import { useDateFilter, DateFilterType } from '../hooks/useDateFilter';
import { useQuestionTitle } from '../hooks/useQuestionTitle';
import { responsesAPI } from '../api/client';
import { createLogger } from '../utils/logger';

const logger = createLogger('TicketsPage');

import { 
  AlertCircle, 
  CheckCircle2, 
  Clock, 
  Search, 
  User as UserIcon, 
  Phone, 
  Building2,
  MoreVertical,
  Check,
  X,
  FileText,
  Star,
  Calendar,
  Trash2
} from 'lucide-react';

const getTicketCode = (id: string) => `#${id.slice(-8).toUpperCase()}`;

function getLocalDateInputValue(date: Date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export default function TicketsPage() {
  const { currentUser } = useAuthStore();
  const { t, i18n } = useTranslation();
  const { tickets, loadTickets, updateTicketStatus, deleteTicket } = useTicketsStore();
  const [selectedResponse, setSelectedResponse] = useState<SurveyResponse | null>(null);
  const [loadingResponse, setLoadingResponse] = useState(false);
  const { getQuestionTitle } = useQuestionTitle();
  const { dateFilter, setDateFilter, customStartDate, setCustomStartDate, customEndDate, setCustomEndDate, dateRange } = useDateFilter('all');

  useEffect(() => {
    loadTickets();
  }, [loadTickets]);
  
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<TicketStatus | 'all'>('all');
  const [selectedTicket, setSelectedTicket] = useState<Ticket | null>(null);
  const [resolutionNotes, setResolutionNotes] = useState('');
  const [activeMenu, setActiveMenu] = useState<string | null>(null);
  const canDeleteTickets = currentUser?.role === 'super_admin' || currentUser?.role === 'admin';

  const filteredTickets = useMemo(() => {
    let baseTickets = tickets;
    
    // Filter by department for head_of_department
    if (currentUser?.role === 'head_of_department' && currentUser?.department) {
      baseTickets = baseTickets.filter(t => t.department === currentUser.department);
    }

    return baseTickets.filter(t => {
      const ticketCode = getTicketCode(t.id);
      const matchesSearch = 
        t.patientName.includes(searchTerm) || 
        t.department.includes(searchTerm) ||
        t.description.includes(searchTerm) ||
        ticketCode.includes(searchTerm.toUpperCase());
      const matchesStatus = statusFilter === 'all' || t.status === statusFilter;
      const ticketDate = new Date(t.createdAt);
      const matchesDate = !dateRange || (ticketDate >= dateRange.start && ticketDate <= dateRange.end);
      return matchesSearch && matchesStatus && matchesDate;
    }).sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime());
  }, [tickets, searchTerm, statusFilter, dateRange, currentUser]);

  const openResolutionModal = (ticket: Ticket) => {
    setSelectedTicket(ticket);
    setResolutionNotes(ticket.resolutionNotes || '');
  };

  const closeResolutionModal = () => {
    setSelectedTicket(null);
    setResolutionNotes('');
  };

  const handleUpdateStatus = async (id: string, status: TicketStatus, notes?: string) => {
    try {
      await updateTicketStatus(id, status, notes);
      setSelectedTicket(null);
      setResolutionNotes('');
    } catch (error) {
      logger.error('Failed to update ticket status in page:', error);
    }
  };

  const handleDeleteTicket = async (ticket: Ticket) => {
    const confirmed = window.confirm(t('ticket_confirm_delete'));
    if (!confirmed) return;

    try {
      await deleteTicket(ticket.id);
      setActiveMenu(null);
    } catch (error) {
      logger.error('Failed to delete ticket in page:', error);
    }
  };

  const handleViewSurvey = async (responseId: string) => {
    try {
      setLoadingResponse(true);
      const data = await responsesAPI.getById(responseId);
      setSelectedResponse(data);
    } catch (error) {
      logger.error('Failed to fetch survey response:', error);
      alert(t('failed_to_load_survey_response') || 'فشل في تحميل تفاصيل الاستبيان');
    } finally {
      setLoadingResponse(false);
    }
  };

  const getStatusInfo = (status: TicketStatus) => {
    switch (status) {
      case 'open': return { label: t('ticket_status_open'), color: 'bg-red-100 text-red-700 dark:bg-red-950/30 dark:text-red-400', icon: AlertCircle };
      case 'in_progress': return { label: t('ticket_status_in_progress'), color: 'bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400', icon: Clock };
      case 'resolved': return { label: t('ticket_status_resolved'), color: 'bg-green-100 text-green-700 dark:bg-green-950/30 dark:text-green-400', icon: CheckCircle2 };
    }
  };

  const getStatusAccent = (status: TicketStatus) => {
    switch (status) {
      case 'open': return 'bg-red-500';
      case 'in_progress': return 'bg-amber-500';
      case 'resolved': return 'bg-green-500';
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-start">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 bg-red-100 dark:bg-red-950/20 rounded-2xl flex items-center justify-center shadow-sm">
            <AlertCircle className="w-6 h-6 text-red-600 dark:text-red-400" />
          </div>
          <div>
            <h1 className="text-xl font-bold text-gray-900 dark:text-white">{t('tickets_title')}</h1>
            <p className="text-sm text-gray-500 dark:text-slate-400">{t('tickets_subtitle')}</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
           <div className="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 p-1 flex items-center">
              <button 
                onClick={() => setStatusFilter('all')}
                type="button"
                className={`px-4 py-2 rounded-lg text-sm transition-all cursor-pointer ${statusFilter === 'all' ? 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white font-bold' : 'text-gray-500 dark:text-slate-400'}`}
              >{t('tickets_filter_all')}</button>
              <button 
                onClick={() => setStatusFilter('open')}
                type="button"
                className={`px-4 py-2 rounded-lg text-sm transition-all cursor-pointer ${statusFilter === 'open' ? 'bg-red-50 dark:bg-red-950/20 text-red-700 dark:text-red-400 font-bold' : 'text-gray-500 dark:text-slate-400'}`}
              >{t('ticket_status_open')}</button>
              <button 
                onClick={() => setStatusFilter('resolved')}
                type="button"
                className={`px-4 py-2 rounded-lg text-sm transition-all cursor-pointer ${statusFilter === 'resolved' ? 'bg-green-50 dark:bg-green-950/20 text-green-700 dark:text-green-400 font-bold' : 'text-gray-500 dark:text-slate-400'}`}
              >{t('tickets_filter_resolved')}</button>
           </div>
        </div>
      </div>

      {/* Search and date filters */}
      <div className="bg-white dark:bg-slate-900 rounded-2xl p-4 mb-6 shadow-sm border border-gray-100 dark:border-slate-800 space-y-4">
        <div className="relative">
          <Search className="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input 
            type="text"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder={t('tickets_search_placeholder')}
            className="w-full pr-10 pl-4 py-3 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-red-500 focus:ring-4 focus:ring-red-50 dark:focus:ring-red-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-550"
          />
        </div>

        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-300">
            <Calendar className="w-4 h-4 text-red-500 dark:text-red-400" />
            <span>{t('responses_date_filter')}</span>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {([
              ['all', t('responses_all')],
              ['today', t('responses_today')],
              ['last7', t('responses_last_7_days')],
              ['last30', t('responses_last_30_days')],
              ['custom', t('responses_custom_date')],
            ] as const).map(([value, label]) => (
              <button
                key={value}
                type="button"
                onClick={() => setDateFilter(value as DateFilterType)}
                className={`min-h-9 rounded-xl px-3 py-2 text-xs font-bold transition-all cursor-pointer ${
                  dateFilter === value
                    ? 'bg-red-600 text-white shadow-md shadow-red-100 dark:shadow-none'
                    : 'bg-gray-50 dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700'
                }`}
              >
                {label}
              </button>
            ))}
          </div>
        </div>

        {dateFilter === 'custom' && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            <label className="space-y-1.5">
              <span className="block text-xs font-bold text-gray-500 dark:text-slate-400">{t('responses_from')}</span>
              <input
                type="date"
                value={customStartDate}
                max={customEndDate || getLocalDateInputValue(new Date())}
                onChange={(e) => setCustomStartDate(e.target.value)}
                className="w-full rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-sm text-gray-900 dark:text-white outline-none focus:border-red-500 focus:ring-4 focus:ring-red-50 dark:focus:ring-red-950/15"
              />
            </label>
            <label className="space-y-1.5">
              <span className="block text-xs font-bold text-gray-500 dark:text-slate-400">{t('responses_to')}</span>
              <input
                type="date"
                value={customEndDate}
                min={customStartDate || undefined}
                max={getLocalDateInputValue(new Date())}
                onChange={(e) => setCustomEndDate(e.target.value)}
                className="w-full rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-sm text-gray-900 dark:text-white outline-none focus:border-red-500 focus:ring-4 focus:ring-red-50 dark:focus:ring-red-950/15"
              />
            </label>
          </div>
        )}
      </div>

      {/* Tickets List */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredTickets.map(ticket => {
          const status = getStatusInfo(ticket.status);
          const StatusIcon = status.icon;
          const ticketCode = getTicketCode(ticket.id);
          
          return (
            <div 
              key={ticket.id}
              className={`h-full bg-white dark:bg-slate-900 rounded-2xl border transition-all hover:shadow-md overflow-hidden flex flex-col ${ticket.status === 'open' ? 'border-red-100 dark:border-red-900/30' : 'border-gray-100 dark:border-slate-800'}`}
            >
              <div className={`h-1.5 ${getStatusAccent(ticket.status)}`} />
              <div className="p-4 flex flex-1 flex-col">
                <div className="flex items-start justify-between gap-3 mb-3">
                  <div className={`flex shrink-0 items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold ${status.color}`}>
                    <StatusIcon className="w-3.5 h-3.5" />
                    {status.label}
                  </div>
                  <span className="text-[10px] text-gray-400 dark:text-slate-500 font-medium leading-5 text-left">
                    {new Date(ticket.createdAt).toLocaleString(i18n.language === 'ar' ? 'ar-SA' : 'en-US')}
                  </span>
                </div>

                <div className="mb-3">
                   <div className="mb-1.5 flex items-center justify-between gap-3">
                    <h3 className="min-w-0 font-bold text-gray-900 dark:text-white flex items-center gap-2">
                      <Building2 className="w-4 h-4 shrink-0 text-teal-600 dark:text-teal-400" />
                      <span className="truncate">{t('tickets_dept_label')} {ticket.department}</span>
                   </h3>
                    <span className="shrink-0 rounded-lg bg-gray-50 dark:bg-slate-800 px-2 py-1 font-mono text-[10px] font-black tracking-wide text-gray-700 dark:text-slate-200" dir="ltr">
                      {ticketCode}
                    </span>
                  </div>
                   <p className="text-sm text-gray-600 dark:text-slate-350 line-clamp-2 leading-relaxed min-h-[2.5rem]">
                     {ticket.description}
                   </p>
                   {ticket.resolutionNotes && (
                    <div className="mt-2 flex items-start gap-2 rounded-xl border border-emerald-100 dark:border-emerald-900/30 bg-emerald-50/70 dark:bg-emerald-950/15 px-2.5 py-1.5">
                      <FileText className="mt-0.5 w-3.5 h-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                      <div className="min-w-0 flex-1">
                        <div className="text-[10px] font-black text-emerald-700 dark:text-emerald-400">
                          {t('tickets_form_notes_label')}
                        </div>
                        <p className="truncate text-xs font-semibold text-gray-700 dark:text-slate-300">
                          {ticket.resolutionNotes}
                        </p>
                      </div>
                    </div>
                  )}
                </div>

                <div className="mb-3 grid grid-cols-1 gap-1.5">
                   <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-slate-400">
                      <UserIcon className="w-3.5 h-3.5" />
                      <span className="truncate">{t('tickets_patient_label')} {ticket.patientName}</span>
                   </div>
                   <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-slate-400" dir="ltr">
                      <Phone className="w-3.5 h-3.5" />
                      <span>{ticket.patientPhone}</span>
                   </div>
                </div>

                <div className="mt-auto flex items-center gap-2 pt-3 border-t border-gray-50 dark:border-slate-800/60">
                  {ticket.status !== 'resolved' ? (
                    <button 
                      onClick={() => openResolutionModal(ticket)}
                      type="button"
                      className="flex-1 bg-red-600 text-white py-2 rounded-xl text-sm font-bold hover:bg-red-700 transition-colors shadow-lg shadow-red-100 dark:shadow-red-950/20 cursor-pointer"
                    >
                      {t('tickets_action_btn')}
                    </button>
                  ) : (
                    <div className="flex-1 text-center py-2 text-green-600 dark:text-green-400 text-xs font-bold flex items-center justify-center gap-1">
                      <CheckCircle2 className="w-4 h-4" />
                      {t('tickets_status_resolved_msg')}
                    </div>
                  )}
                  
                  <div className="relative">
                    <button 
                      onClick={(e) => {
                        e.stopPropagation();
                        setActiveMenu(activeMenu === ticket.id ? null : ticket.id);
                      }}
                      type="button"
                      className={`p-2 rounded-xl transition-colors cursor-pointer ${activeMenu === ticket.id ? 'bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-705'}`}
                    >
                      <MoreVertical className="w-4 h-4" />
                    </button>

                    {activeMenu === ticket.id && (
                      <>
                        <div className="fixed inset-0 z-10" onClick={() => setActiveMenu(null)} />
                        <div className="absolute left-0 bottom-full mb-2 w-56 bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-gray-100 dark:border-slate-700 py-2 z-20 animate-scale-in">
                          <div className="px-4 py-1 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider">{t('tickets_change_status_title')}</div>
                          
                          <button 
                            onClick={() => { handleUpdateStatus(ticket.id, 'open'); setActiveMenu(null); }}
                            type="button"
                            className={`w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-slate-700/60 cursor-pointer ${ticket.status === 'open' ? 'text-red-600 font-bold bg-red-50/30 dark:bg-red-950/20' : 'text-gray-600 dark:text-slate-300'}`}
                          >
                            <div className="flex items-center gap-2">
                              <AlertCircle className="w-4 h-4" />
                              <span>{t('ticket_status_open')}</span>
                            </div>
                            {ticket.status === 'open' && <Check className="w-3.5 h-3.5" />}
                          </button>

                          <button 
                            onClick={() => { handleUpdateStatus(ticket.id, 'in_progress'); setActiveMenu(null); }}
                            type="button"
                            className={`w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-slate-700/60 cursor-pointer ${ticket.status === 'in_progress' ? 'text-amber-600 font-bold bg-amber-50/30 dark:bg-amber-950/20' : 'text-gray-600 dark:text-slate-300'}`}
                          >
                            <div className="flex items-center gap-2">
                              <Clock className="w-4 h-4" />
                              <span>{t('ticket_status_in_progress')}</span>
                            </div>
                            {ticket.status === 'in_progress' && <Check className="w-3.5 h-3.5" />}
                          </button>

                          <button 
                            onClick={() => { 
                              openResolutionModal(ticket); 
                              setActiveMenu(null);
                            }}
                            type="button"
                            className={`w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-slate-700/60 cursor-pointer ${ticket.status === 'resolved' ? 'text-green-600 font-bold bg-green-50/30 dark:bg-green-950/20' : 'text-gray-600 dark:text-slate-300'}`}
                          >
                            <div className="flex items-center gap-2">
                              <CheckCircle2 className="w-4 h-4" />
                              <span>{t('ticket_status_resolved')}</span>
                            </div>
                            {ticket.status === 'resolved' && <Check className="w-3.5 h-3.5" />}
                          </button>

                          <div className="h-px bg-gray-100 dark:bg-slate-700 my-2" />
                          <div className="px-4 py-1 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider">{t('tickets_other_options_title')}</div>
                          
                          <button 
                            onClick={() => {
                              handleViewSurvey(ticket.responseId);
                              setActiveMenu(null);
                            }}
                            type="button"
                            className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700/60 cursor-pointer"
                          >
                            <FileText className="w-4 h-4" />
                            {t('tickets_view_survey_option')}
                          </button>

                          {canDeleteTickets && (
                            <button
                              onClick={() => handleDeleteTicket(ticket)}
                              type="button"
                              className="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20 cursor-pointer"
                            >
                              <Trash2 className="w-4 h-4" />
                              {t('tickets_delete_option')}
                            </button>
                          )}
                        </div>
                      </>
                    )}
                  </div>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Resolution Modal */}
      {selectedTicket && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
           <div className="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-3xl max-w-md w-full shadow-2xl animate-scale-in overflow-hidden">
              <div className="bg-red-600 p-6 text-white text-start">
                <div className="flex items-center justify-between mb-2">
                   <h3 className="text-xl font-bold">{t('tickets_modal_title')}</h3>
                   <button onClick={closeResolutionModal} type="button" className="cursor-pointer"><X className="w-6 h-6" /></button>
                </div>
                <p className="text-red-100 text-sm">{t('tickets_patient_label')} {selectedTicket.patientName}</p>
                <p className="text-red-100 text-xs font-mono mt-1" dir="ltr">{getTicketCode(selectedTicket.id)}</p>
              </div>
              
              <div className="p-6 space-y-4 text-start">
                 <div className="bg-gray-50 dark:bg-slate-800 p-4 rounded-2xl text-sm text-gray-600 dark:text-slate-300 leading-relaxed italic border border-gray-100 dark:border-slate-750">
                    "{selectedTicket.description}"
                 </div>

                 <div>
                    <label className="block text-sm font-bold text-gray-700 dark:text-slate-300 mb-2">{t('tickets_form_notes_label')}</label>
                    <textarea 
                      value={resolutionNotes}
                      onChange={(e) => setResolutionNotes(e.target.value)}
                      placeholder={t('tickets_form_notes_placeholder')}
                      className="w-full rounded-2xl border-2 border-gray-100 dark:border-slate-800 focus:border-red-500 p-4 outline-none min-h-[120px] text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
                    />
                 </div>

                 <div className="grid grid-cols-2 gap-3 pt-4">
                    <button 
                      onClick={() => handleUpdateStatus(selectedTicket.id, 'in_progress', resolutionNotes)}
                      type="button"
                      className="py-3 rounded-2xl bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-450 font-bold border border-amber-200 dark:border-amber-900/40 cursor-pointer"
                    >
                      {t('tickets_start_process_btn')}
                    </button>
                    <button 
                      disabled={!resolutionNotes.trim()}
                      onClick={() => handleUpdateStatus(selectedTicket.id, 'resolved', resolutionNotes)}
                      type="button"
                      className={`py-3 rounded-2xl font-bold flex items-center justify-center gap-2 cursor-pointer ${resolutionNotes.trim() ? 'bg-green-600 text-white shadow-lg shadow-green-100 dark:shadow-none' : 'bg-gray-100 dark:bg-slate-800 text-gray-400 dark:text-slate-500'}`}
                    >
                      <Check className="w-5 h-5" />
                      {t('tickets_close_ticket_btn')}
                    </button>
                 </div>
              </div>
           </div>
        </div>
      )}

      {/* Loading Survey Modal */}
      {loadingResponse && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[110]">
          <div className="bg-white dark:bg-slate-900 p-8 rounded-3xl border border-gray-100 dark:border-slate-800 shadow-2xl flex flex-col items-center gap-4 animate-scale-in">
            <div className="w-12 h-12 border-4 border-teal-600 border-t-transparent rounded-full animate-spin"></div>
            <p className="text-sm font-bold text-gray-700 dark:text-white">{t('loading_survey_details') || 'جاري تحميل تفاصيل الاستبيان...'}</p>
          </div>
        </div>
      )}

      {/* Survey Response Detail Modal */}
      {selectedResponse && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4 animate-fade-in" onClick={() => setSelectedResponse(null)}>
          <div className="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-3xl max-w-lg w-full max-h-[85vh] overflow-hidden shadow-2xl animate-scale-in flex flex-col" onClick={e => e.stopPropagation()}>
            {/* Header with dynamic gradient */}
            <div className={`p-6 text-white shrink-0 bg-linear-to-r ${
              selectedResponse.overallScore >= 85 ? 'from-green-500 to-teal-600' :
              selectedResponse.overallScore >= 70 ? 'from-blue-500 to-indigo-600' :
              selectedResponse.overallScore >= 50 ? 'from-amber-500 to-orange-600' :
              'from-red-500 to-rose-600'
            }`}>
              <div className="flex items-center justify-between mb-3 text-start">
                <div className="flex items-center gap-2">
                  <div className="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/20">
                    <FileText className="w-5 h-5 text-white" />
                  </div>
                  <div>
                    <h3 className="font-black text-lg leading-tight">{t('responses_details_title') || 'تفاصيل الاستبيان'}</h3>
                    <p className="text-white/80 text-xs">{t('responses_submission_date') || 'تاريخ التقديم'}: {new Date(selectedResponse.submittedAt).toLocaleDateString(i18n.language === 'ar' ? 'ar-SA' : 'en-US')}</p>
                  </div>
                </div>
                <button onClick={() => setSelectedResponse(null)} type="button" className="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors cursor-pointer">
                  <X className="w-5 h-5" />
                </button>
              </div>

              {/* Overall score banner */}
              <div className="flex items-center justify-between bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10 mt-3">
                <span className="text-sm font-bold text-white/90">{t('satisfaction_rate') || 'نسبة الرضا'}</span>
                <div className="flex items-center gap-2">
                  <span className="text-2xl font-black">{selectedResponse.overallScore}%</span>
                  <div className="w-16 h-2 bg-white/20 rounded-full overflow-hidden">
                    <div className="h-full bg-white rounded-full" style={{ width: `${selectedResponse.overallScore}%` }} />
                  </div>
                </div>
              </div>
            </div>

            {/* Scrollable content */}
            <div className="p-6 overflow-y-auto space-y-6 text-start">
              {/* Patient Profile Card */}
              <div className="bg-gray-50 dark:bg-slate-800/50 rounded-2xl p-4 border border-gray-100 dark:border-slate-800/85 space-y-3">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-full bg-teal-50 dark:bg-teal-950/40 border border-teal-100 dark:border-teal-900 flex items-center justify-center text-teal-600 dark:text-teal-450 font-bold text-sm shadow-sm shrink-0">
                    {selectedResponse.patientInfo.name ? selectedResponse.patientInfo.name.charAt(0) : '?'}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="font-bold text-sm text-gray-900 dark:text-white truncate">
                      {selectedResponse.patientInfo.name || t('anonymous')}
                    </div>
                    {selectedResponse.patientInfo.phone && (
                      <div className="text-xs text-teal-600 dark:text-teal-450 font-bold flex items-center gap-1 mt-0.5" dir="ltr">
                        <Phone className="w-3 h-3" />
                        {selectedResponse.patientInfo.phone}
                      </div>
                    )}
                  </div>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-3 border-t border-gray-200/50 dark:border-slate-700 text-xs text-gray-500 dark:text-slate-400">
                  <div className="flex items-center gap-1.5 bg-white dark:bg-slate-800 px-2.5 py-1.5 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm">
                    <Building2 className="w-3.5 h-3.5 text-gray-400 shrink-0" />
                    <span className="truncate">{selectedResponse.department}</span>
                  </div>
                  <div className="flex items-center gap-1.5 bg-white dark:bg-slate-800 px-2.5 py-1.5 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm">
                    <UserIcon className="w-3.5 h-3.5 text-gray-400 shrink-0" />
                    <span>{selectedResponse.patientInfo.gender || t('gender')}</span>
                  </div>
                  <div className="flex items-center gap-1.5 bg-white dark:bg-slate-800 px-2.5 py-1.5 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm col-span-2 sm:col-span-1">
                    <Calendar className="w-3.5 h-3.5 text-gray-400 shrink-0" />
                    <span>{selectedResponse.patientInfo.ageGroup || t('age_group')}</span>
                  </div>
                </div>
              </div>

              {/* Answers list */}
              <div className="space-y-3">
                <h4 className="font-bold text-xs text-gray-400 uppercase tracking-wider">{t('responses_detailed_answers') || 'تفاصيل الإجابات'}</h4>
                
                <div className="divide-y divide-gray-100 dark:divide-slate-800 border border-gray-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
                  {Object.entries(selectedResponse.answers).map(([key, val]) => {
                    if (!val && val !== 0) return null;
                    return (
                      <div key={key} className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-4 hover:bg-gray-50/50 dark:hover:bg-slate-800/40 transition-colors">
                        <span className="text-sm font-medium text-gray-700 dark:text-slate-300 max-w-xs">{getQuestionTitle(selectedResponse.surveyId, key, selectedResponse.answers)}</span>
                        <span className="text-sm font-bold text-gray-900 dark:text-white shrink-0 self-end sm:self-auto">
                          {typeof val === 'number' ? (
                            <span className="flex items-center gap-1 bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-450 px-3 py-1 rounded-full border border-amber-100 dark:border-amber-900/35 text-xs">
                              {val} / 5
                              <Star className="w-3.5 h-3.5 text-amber-500 fill-amber-500" />
                            </span>
                          ) : val === 'yes' ? (
                            <span className="bg-green-50 dark:bg-green-950/20 text-green-700 dark:text-green-450 px-3 py-1 rounded-full border border-green-100 dark:border-green-900/35 text-xs">
                              {t('responses_yes') || 'نعم'}
                            </span>
                          ) : val === 'no' ? (
                            <span className="bg-red-50 dark:bg-red-950/20 text-red-700 dark:text-red-450 px-3 py-1 rounded-full border border-red-100 dark:border-red-900/35 text-xs">
                              {t('responses_no') || 'لا'}
                            </span>
                          ) : (
                            <span className="bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-slate-350 px-3 py-1 rounded-full border border-gray-100 dark:border-slate-700 text-xs">
                              {String(val)}
                            </span>
                          )}
                        </span>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>

            {/* Footer */}
            <div className="p-4 bg-gray-50 dark:bg-slate-850 border-t border-gray-100 dark:border-slate-800 flex justify-end shrink-0">
              <button 
                onClick={() => setSelectedResponse(null)}
                type="button"
                className="px-6 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-300 font-bold rounded-2xl text-sm shadow-sm transition-colors cursor-pointer"
              >
                {t('close') || 'إغلاق'}
              </button>
            </div>
          </div>
        </div>
      )}

      {filteredTickets.length === 0 && (
        <div className="text-center py-24">
          <div className="w-20 h-20 bg-gray-50 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
            <CheckCircle2 className="w-10 h-10 text-gray-300 dark:text-slate-650" />
          </div>
          <p className="text-gray-500 dark:text-slate-400 font-medium">{t('tickets_no_tickets_msg')}</p>
        </div>
      )}
    </div>
  );
}
