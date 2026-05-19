import { create } from 'zustand';
import { Ticket, TicketStatus } from '../types';
import { ticketsAPI } from '../api/client';
import { createLogger } from '../utils/logger';

const logger = createLogger('TicketsStore');

interface TicketsState {
  tickets: Ticket[];
  loadingTickets: boolean;
  
  loadTickets: (params?: Record<string, any>) => Promise<void>;
  updateTicketStatus: (id: string, status: TicketStatus, notes?: string) => Promise<void>;
}

export const useTicketsStore = create<TicketsState>((set) => ({
  tickets: [],
  loadingTickets: false,

  loadTickets: async (params) => {
    set({ loadingTickets: true });
    try {
      const data = await ticketsAPI.getAll(params);
      set({ tickets: data as Ticket[], loadingTickets: false });
    } catch (error) {
      logger.error('Failed to load tickets:', error);
      set({ loadingTickets: false });
    }
  },

  updateTicketStatus: async (id, status, notes) => {
    try {
      const updateData: { status: TicketStatus; resolutionNotes?: string } = { status };
      const trimmedNotes = notes?.trim();
      if (trimmedNotes) updateData.resolutionNotes = trimmedNotes;
      
      await ticketsAPI.update(id, updateData);
      
      // Update local state in the store immediately for seamless UI transition
      set((state) => ({
        tickets: state.tickets.map((t) => 
          t.id === id ? { ...t, status, resolutionNotes: trimmedNotes || t.resolutionNotes } : t
        )
      }));
    } catch (error) {
      logger.error('Failed to update ticket status in store:', error);
      throw error;
    }
  }
}));
