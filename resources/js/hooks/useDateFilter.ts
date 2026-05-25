import { useState, useMemo } from 'react';

export type DateFilterType = 'all' | 'today' | 'week' | 'last7' | 'month' | 'last30' | 'quarter' | 'custom';

export function useDateFilter(initialFilter: DateFilterType = 'all') {
  const [dateFilter, setDateFilter] = useState<DateFilterType>(initialFilter);
  const [customStartDate, setCustomStartDate] = useState<string>('');
  const [customEndDate, setCustomEndDate] = useState<string>('');

  // Computes the start and end Date objects (useful for client-side filtering)
  const dateRange = useMemo(() => {
    if (dateFilter === 'all') return null;

    const now = new Date();
    let start: Date;
    let end = new Date(now);
    end.setHours(23, 59, 59, 999);

    if (dateFilter === 'custom') {
      if (!customStartDate && !customEndDate) return null;

      start = customStartDate ? new Date(`${customStartDate}T00:00:00`) : new Date(0);
      end = customEndDate ? new Date(`${customEndDate}T23:59:59.999`) : end;
      return { start, end };
    }

    start = new Date(now);
    start.setHours(0, 0, 0, 0);

    if (dateFilter === 'week' || dateFilter === 'last7') {
      start.setDate(start.getDate() - 6);
    } else if (dateFilter === 'month' || dateFilter === 'last30') {
      start.setDate(start.getDate() - 29);
    } else if (dateFilter === 'quarter') {
      start.setMonth(start.getMonth() - 3);
    }

    return { start, end };
  }, [dateFilter, customStartDate, customEndDate]);

  // Computes ISO strings for API requests
  const apiDateStrings = useMemo(() => {
    if (dateFilter === 'custom') {
      return {
        startDate: customStartDate || undefined,
        endDate: customEndDate || undefined,
      };
    }
    
    if (dateFilter !== 'all') {
      let daysToSubtract = 0;
      if (dateFilter === 'week' || dateFilter === 'last7') daysToSubtract = 7;
      if (dateFilter === 'month' || dateFilter === 'last30') daysToSubtract = 30;
      if (dateFilter === 'quarter') daysToSubtract = 90;
      
      const start = new Date(Date.now() - daysToSubtract * 86400000);
      return {
        startDate: start.toISOString(),
        endDate: undefined,
      };
    }

    return { startDate: undefined, endDate: undefined };
  }, [dateFilter, customStartDate, customEndDate]);

  return {
    dateFilter,
    setDateFilter,
    customStartDate,
    setCustomStartDate,
    customEndDate,
    setCustomEndDate,
    dateRange,
    apiDateStrings,
  };
}
