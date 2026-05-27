import { useEffect, useRef, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSurveyStore } from '../store/useSurveyStore';

const PAUSE_AFTER_INTERACTION_MS = 3000;
const INTERACTION_THROTTLE_MS = 500;
const ACTIVITY_EVENTS = [
  'pointerdown',
  'pointermove',
  'keydown',
  'input',
  'scroll',
  'touchstart',
  'wheel',
] as const;

export function useSurveySessionTimer() {
  const navigate = useNavigate();
  const sessionTimer = useSurveyStore(s => s.sessionTimer);
  const decrementSessionTimer = useSurveyStore(s => s.decrementSessionTimer);
  const reportInteraction = useSurveyStore(s => s.reportInteraction);
  const resumeSessionTimer = useSurveyStore(s => s.resumeSessionTimer);
  const resetSurveySession = useSurveyStore(s => s.resetSurveySession);

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const lastInteractionAtRef = useRef(Number.NEGATIVE_INFINITY);
  const interactionTick = sessionTimer?.interactionTick ?? 0;
  const remainingMsValue = sessionTimer?.remainingMs ?? 0;
  const pausedValue = sessionTimer?.paused ?? true;

  // Any real user activity pauses the countdown. It resumes after inactivity.
  useEffect(() => {
    if (!sessionTimer || remainingMsValue <= 0) return;

    const handleActivity = () => {
      const now = Date.now();
      if (now - lastInteractionAtRef.current < INTERACTION_THROTTLE_MS) return;

      lastInteractionAtRef.current = now;
      reportInteraction();
    };

    ACTIVITY_EVENTS.forEach(eventName => {
      window.addEventListener(eventName, handleActivity, { passive: true });
    });

    return () => {
      ACTIVITY_EVENTS.forEach(eventName => {
        window.removeEventListener(eventName, handleActivity);
      });
    };
  }, [sessionTimer, remainingMsValue, reportInteraction]);

  // Countdown: every 1s, decrease remaining when not paused
  useEffect(() => {
    if (!remainingMsValue || pausedValue) return;

    const intervalId = window.setInterval(() => {
      decrementSessionTimer();
    }, 1000);

    return () => window.clearInterval(intervalId);
  }, [pausedValue, remainingMsValue, decrementSessionTimer]);

  // When interaction tick changes: pause, then resume after inactivity
  useEffect(() => {
    if (!remainingMsValue || interactionTick === 0) return;

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
  }, [interactionTick, resumeSessionTimer, pausedValue, remainingMsValue]);

  // When timer reaches 0, redirect to home
  useEffect(() => {
    if (!sessionTimer) return;
    if (remainingMsValue > 0) return;

    resetSurveySession();
    navigate('/', { replace: true });
  }, [remainingMsValue, sessionTimer, resetSurveySession, navigate]);

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
