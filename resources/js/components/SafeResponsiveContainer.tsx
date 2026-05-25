import { ReactNode, useEffect, useRef, useState } from 'react';
import { ResponsiveContainer } from 'recharts';

interface SafeResponsiveContainerProps {
  children: ReactNode;
  width?: number | string;
  height: number | string;
  minHeight?: number;
}

export default function SafeResponsiveContainer({
  children,
  width = '100%',
  height,
  minHeight = 1,
}: SafeResponsiveContainerProps) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const [size, setSize] = useState({ width: 0, height: 0 });

  useEffect(() => {
    const element = containerRef.current;
    if (!element) return;

    const updateSize = () => {
      const rect = element.getBoundingClientRect();
      setSize({
        width: Math.floor(rect.width),
        height: Math.floor(rect.height),
      });
    };

    updateSize();

    if (typeof ResizeObserver === 'undefined') {
      return;
    }

    const scheduleUpdate = () => {
      if (typeof requestAnimationFrame === 'function') {
        requestAnimationFrame(updateSize);
      } else {
        updateSize();
      }
    };

    const observer = new ResizeObserver(scheduleUpdate);

    observer.observe(element);
    return () => observer.disconnect();
  }, []);

  const validWidth = size.width > 0;
  const validHeight = size.height > 0;

  return (
    <div
      ref={containerRef}
      className="min-w-0 w-full"
      style={{ width, height, minHeight }}
    >
      {validWidth && validHeight ? (
        <ResponsiveContainer width={size.width} height={size.height}>
          {children}
        </ResponsiveContainer>
      ) : null}
    </div>
  );
}
