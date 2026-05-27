import { render, act, cleanup } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSurveySessionTimer } from '../hooks/useSurveySessionTimer';
import { useSurveyStore } from '../store/useSurveyStore';

function TimerHarness() {
  useSurveySessionTimer();
  return null;
}

function renderTimer() {
  return render(
    <MemoryRouter>
      <TimerHarness />
    </MemoryRouter>
  );
}

describe('useSurveySessionTimer', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    useSurveyStore.getState().resetSurveySession();
    useSurveyStore.getState().startSurveySessionTimer();
  });

  afterEach(() => {
    cleanup();
    useSurveyStore.getState().resetSurveySession();
    vi.useRealTimers();
  });

  it('pauses on user activity and resumes after inactivity', () => {
    renderTimer();

    act(() => {
      window.dispatchEvent(new PointerEvent('pointerdown'));
    });

    expect(useSurveyStore.getState().sessionTimer?.paused).toBe(true);
    expect(useSurveyStore.getState().sessionTimer?.remainingMs).toBe(180000);

    act(() => {
      vi.advanceTimersByTime(1000);
    });

    expect(useSurveyStore.getState().sessionTimer?.remainingMs).toBe(180000);
    expect(useSurveyStore.getState().sessionTimer?.paused).toBe(true);

    act(() => {
      vi.advanceTimersByTime(2000);
    });

    expect(useSurveyStore.getState().sessionTimer?.paused).toBe(false);
  });

  it('keeps the timer paused while activity continues', () => {
    renderTimer();

    act(() => {
      window.dispatchEvent(new PointerEvent('pointerdown'));
    });

    act(() => {
      vi.advanceTimersByTime(2000);
    });

    act(() => {
      window.dispatchEvent(new WheelEvent('wheel'));
    });

    act(() => {
      vi.advanceTimersByTime(2500);
    });

    expect(useSurveyStore.getState().sessionTimer?.paused).toBe(true);
    expect(useSurveyStore.getState().sessionTimer?.remainingMs).toBe(180000);

    act(() => {
      vi.advanceTimersByTime(500);
    });

    expect(useSurveyStore.getState().sessionTimer?.paused).toBe(false);
  });
});
