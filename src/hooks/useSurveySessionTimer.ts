import { useEffect, useRef, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSurveyStore } from '../store/useSurveyStore';

const PAUSE_AFTER_INTERACTION_MS = 3000;

export function useSurveySessionTimer() {
  const navigate = useNavigate();
  const sessionTimer = useSurveyStore(s => s.sessionTimer);
  const decrementSessionTimer = useSurveyStore(s => s.decrementSessionTimer);
  const resumeSessionTimer = useSurveyStore(s => s.resumeSessionTimer);
  const resetSurveySession = useSurveyStore(s => s.resetSurveySession);

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const interactionTick = sessionTimer?.interactionTick ?? 0;

  // Countdown: every 1s, decrease remaining when not paused
  useEffect(() => {
    if (!sessionTimer || sessionTimer.paused || sessionTimer.remainingMs <= 0) return;

    const intervalId = window.setInterval(() => {
      decrementSessionTimer();
    }, 1000);

    return () => window.clearInterval(intervalId);
  }, [sessionTimer?.paused, sessionTimer?.remainingMs, decrementSessionTimer, sessionTimer]);

  // When interaction tick changes: pause, then resume after inactivity
  useEffect(() => {
    if (!sessionTimer || interactionTick === 0) return;

    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    debounceRef.current = setTimeout(() => {
      resumeSessionTimer();
      debounceRef.current = null;
    }, PAUSE_AFTER_INTERACTION_MS);

    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
        debounceRef.current = null;
      }
    };
  }, [interactionTick, resumeSessionTimer, sessionTimer]);

  // When timer reaches 0, redirect to home
  useEffect(() => {
    if (!sessionTimer || sessionTimer.remainingMs > 0) return;

    resetSurveySession();
    navigate('/', { replace: true });
  }, [sessionTimer?.remainingMs, resetSurveySession, navigate]);

  const remainingSeconds = sessionTimer
    ? Math.max(0, Math.ceil(sessionTimer.remainingMs / 1000))
    : 0;

  return useMemo(() => {
    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;

    return {
      remainingSeconds,
      formattedTime: `${minutes}:${String(seconds).padStart(2, '0')}`,
    };
  }, [remainingSeconds]);
}
