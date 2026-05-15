import { useState, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Ticket, TicketStatus, SurveyResponse } from '../types';
import { useAuthStore } from '../store/useAuthStore';
import { useSurveyStore } from '../store/useSurveyStore';
import { ticketsAPI, responsesAPI } from '../api/client';
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
  Trash2,
  Star,
  Calendar
} from 'lucide-react';

export default function TicketsPage() {
  const { currentUser } = useAuthStore();
  const { surveys } = useSurveyStore();
  const { t, i18n } = useTranslation();
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [selectedResponse, setSelectedResponse] = useState<SurveyResponse | null>(null);
  const [loadingResponse, setLoadingResponse] = useState(false);

  useEffect(() => {
    ticketsAPI.getAll().then(data => {
      setTickets(data as Ticket[]);
    }).catch(() => {});
  }, []);
  
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<TicketStatus | 'all'>('all');
  const [selectedTicket, setSelectedTicket] = useState<Ticket | null>(null);
  const [resolutionNotes, setResolutionNotes] = useState('');
  const [activeMenu, setActiveMenu] = useState<string | null>(null);

  const filteredTickets = useMemo(() => {
    let baseTickets = tickets;
    
    // Filter by department for head_of_department
    if (currentUser?.role === 'head_of_department' && currentUser?.department) {
      baseTickets = baseTickets.filter(t => t.department === currentUser.department);
    }

    return baseTickets.filter(t => {
      const matchesSearch = 
        t.patientName.includes(searchTerm) || 
        t.department.includes(searchTerm) ||
        t.description.includes(searchTerm);
      const matchesStatus = statusFilter === 'all' || t.status === statusFilter;
      return matchesSearch && matchesStatus;
    }).sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime());
  }, [tickets, searchTerm, statusFilter, currentUser]);

  const handleUpdateStatus = async (id: string, status: TicketStatus, notes?: string) => {
    try {
      const updateData: { status: TicketStatus; resolutionNotes?: string } = { status };
      if (notes) updateData.resolutionNotes = notes;
      
      await ticketsAPI.update(id, updateData);
      const refreshed = await ticketsAPI.getAll();
      setTickets(refreshed as Ticket[]);
      setSelectedTicket(null);
      setResolutionNotes('');
    } catch (error) {
      logger.error('Failed to update ticket:', error);
    }
  };

  const handleDeleteTicket = async (id: string) => {
    if (confirm(t('ticket_confirm_delete'))) {
      try {
        // Note: Delete endpoint not implemented, resolve instead
        await ticketsAPI.update(id, { status: 'resolved', resolutionNotes: t('ticket_deleted_status_note') });
        const refreshed = await ticketsAPI.getAll();
        setTickets(refreshed as Ticket[]);
        setActiveMenu(null);
      } catch (error) {
        logger.error('Failed to delete ticket:', error);
      }
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

  const getQuestionTitle = (surveyId: string, questionId: string, answersObj?: Record<string, string | number | boolean | string[] | null>): string => {
    if (questionId.endsWith('_reason')) {
      return t('reason_for_low_rating_label', 'سبب تدني التقييم');
    }

    let survey = surveys.find(s => s.id === surveyId);
    if (!survey && surveys.length > 0) {
      survey = surveys[0]; // fallback
    }
    if (!survey) return questionId;

    // Flatten all questions to support index-based lookups for seed data (q1, q2...)
    const allQuestions: { id: string; title: string }[] = [];
    survey.sections.forEach(sec => {
      allQuestions.push(...sec.questions);
    });

    // 1. Direct match by ID (for actual real responses)
    const directQuestion = allQuestions.find(q => q.id === questionId);
    if (directQuestion) return directQuestion.title;

    // 2. If it's a seed question (e.g. "q1", "q2", "q13"...)
    if (/^q\d+$/.test(questionId)) {
      const index = parseInt(questionId.substring(1)) - 1;
      if (index >= 0 && index < allQuestions.length) {
        return allQuestions[index].title;
      }
    }

    // 3. Fallback: Match by sequential index of the answer key within answersObj (for regenerated IDs from updated surveys)
    if (answersObj) {
      const keys = Object.keys(answersObj).filter(k => !k.endsWith('_reason')); // ignore follow-up reason keys
      const keyIndex = keys.indexOf(questionId);
      if (keyIndex >= 0 && keyIndex < allQuestions.length) {
        return allQuestions[keyIndex].title;
      }
    }

    return questionId;
  };

  const getStatusInfo = (status: TicketStatus) => {
    switch (status) {
      case 'open': return { label: t('ticket_status_open'), color: 'bg-red-100 text-red-700 dark:bg-red-950/30 dark:text-red-400', icon: AlertCircle };
      case 'in_progress': return { label: t('ticket_status_in_progress'), color: 'bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400', icon: Clock };
      case 'resolved': return { label: t('ticket_status_resolved'), color: 'bg-green-100 text-green-700 dark:bg-green-950/30 dark:text-green-400', icon: CheckCircle2 };
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

      {/* Search */}
      <div className="bg-white dark:bg-slate-900 rounded-2xl p-4 mb-6 shadow-sm border border-gray-100 dark:border-slate-800">
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
      </div>

      {/* Tickets List */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredTickets.map(ticket => {
          const status = getStatusInfo(ticket.status);
          const StatusIcon = status.icon;
          
          return (
            <div 
              key={ticket.id}
              className={`bg-white dark:bg-slate-900 rounded-2xl border transition-all hover:shadow-md overflow-hidden ${ticket.status === 'open' ? 'border-red-100 dark:border-red-900/30' : 'border-gray-100 dark:border-slate-800'}`}
            >
              <div className={`p-1 ${ticket.priority === 'high' ? 'bg-red-500' : 'bg-orange-400'}`} />
              <div className="p-5">
                <div className="flex items-center justify-between mb-4">
                  <div className={`flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold ${status.color}`}>
                    <StatusIcon className="w-3.5 h-3.5" />
                    {status.label}
                  </div>
                  <span className="text-[10px] text-gray-400 dark:text-slate-500 font-medium">
                    {new Date(ticket.createdAt).toLocaleString(i18n.language === 'ar' ? 'ar-SA' : 'en-US')}
                  </span>
                </div>

                <div className="mb-4">
                   <h3 className="font-bold text-gray-900 dark:text-white mb-1 flex items-center gap-2">
                     <Building2 className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                     {t('tickets_dept_label')} {ticket.department}
                   </h3>
                   <p className="text-sm text-gray-600 dark:text-slate-350 line-clamp-2 leading-relaxed">
                     {ticket.description}
                   </p>
                </div>

                <div className="space-y-2 mb-5">
                   <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-slate-400">
                      <UserIcon className="w-3.5 h-3.5" />
                      <span>{t('tickets_patient_label')} {ticket.patientName}</span>
                   </div>
                   <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-slate-400" dir="ltr">
                      <Phone className="w-3.5 h-3.5" />
                      <span>{ticket.patientPhone}</span>
                   </div>
                </div>

                <div className="flex items-center gap-2 pt-4 border-t border-gray-50 dark:border-slate-800/60">
                  {ticket.status !== 'resolved' ? (
                    <button 
                      onClick={() => setSelectedTicket(ticket)}
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
                              if (ticket.status === 'resolved') {
                                handleUpdateStatus(ticket.id, 'resolved');
                                setActiveMenu(null);
                              } else {
                                setSelectedTicket(ticket); 
                                setActiveMenu(null);
                              }
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
                          
                          <button 
                            onClick={() => handleDeleteTicket(ticket.id)}
                            type="button"
                            className="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20 cursor-pointer"
                          >
                            <Trash2 className="w-4 h-4" />
                            {t('tickets_delete_option')}
                          </button>
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
                   <button onClick={() => setSelectedTicket(null)} type="button" className="cursor-pointer"><X className="w-6 h-6" /></button>
                </div>
                <p className="text-red-100 text-sm">{t('tickets_patient_label')} {selectedTicket.patientName}</p>
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
                      onClick={() => handleUpdateStatus(selectedTicket.id, 'in_progress')}
                      type="button"
                      className="py-3 rounded-2xl bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-450 font-bold border border-amber-200 dark:border-amber-900/40 cursor-pointer"
                    >
                      {t('tickets_start_process_btn')}
                    </button>
                    <button 
                      disabled={!resolutionNotes}
                      onClick={() => handleUpdateStatus(selectedTicket.id, 'resolved', resolutionNotes)}
                      type="button"
                      className={`py-3 rounded-2xl font-bold flex items-center justify-center gap-2 cursor-pointer ${resolutionNotes ? 'bg-green-600 text-white shadow-lg shadow-green-100 dark:shadow-none' : 'bg-gray-100 dark:bg-slate-800 text-gray-400 dark:text-slate-500'}`}
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
            <div className={`p-6 text-white shrink-0 bg-gradient-to-r ${
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
                  <div className="w-10 h-10 rounded-full bg-teal-50 dark:bg-teal-950/40 border border-teal-100 dark:border-teal-900 flex items-center justify-center text-teal-600 dark:text-teal-450 font-bold text-sm shadow-sm flex-shrink-0">
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
                    <Building2 className="w-3.5 h-3.5 text-gray-400 flex-shrink-0" />
                    <span className="truncate">{selectedResponse.department}</span>
                  </div>
                  <div className="flex items-center gap-1.5 bg-white dark:bg-slate-800 px-2.5 py-1.5 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm">
                    <UserIcon className="w-3.5 h-3.5 text-gray-400 flex-shrink-0" />
                    <span>{selectedResponse.patientInfo.gender || t('gender')}</span>
                  </div>
                  <div className="flex items-center gap-1.5 bg-white dark:bg-slate-800 px-2.5 py-1.5 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm col-span-2 sm:col-span-1">
                    <Calendar className="w-3.5 h-3.5 text-gray-400 flex-shrink-0" />
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
