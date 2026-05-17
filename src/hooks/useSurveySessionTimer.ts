import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSurveyStore } from '../store/useSurveyStore';

export function useSurveySessionTimer() {
  const navigate = useNavigate();
  const { sessionExpiresAt, resetSurveySession } = useSurveyStore();
  const [now, setNow] = useState(Date.now());

  useEffect(() => {
    if (!sessionExpiresAt) return;

    const intervalId = window.setInterval(() => {
      setNow(Date.now());
    }, 1000);

    return () => window.clearInterval(intervalId);
  }, [sessionExpiresAt]);

  useEffect(() => {
    if (!sessionExpiresAt || now < sessionExpiresAt) return;

    resetSurveySession();
    navigate('/', { replace: true });
  }, [navigate, now, resetSurveySession, sessionExpiresAt]);

  const remainingSeconds = Math.max(0, Math.ceil(((sessionExpiresAt || now) - now) / 1000));

  return useMemo(() => {
    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;

    return {
      remainingSeconds,
      formattedTime: `${minutes}:${String(seconds).padStart(2, '0')}`,
    };
  }, [remainingSeconds]);
}
