import { describe, it, expect } from 'vitest';
import { cn } from '../utils/cn';

describe('Utility: cn (Class merger)', () => {
  it('should combine plain string classes', () => {
    expect(cn('class1', 'class2')).toBe('class1 class2');
  });

  it('should filter out falsy inputs', () => {
    expect(cn('class1', null, undefined, false, 'class2')).toBe('class1 class2');
  });

  it('should resolve dynamic conditional class objects', () => {
    expect(cn({ 'class-active': true, 'class-disabled': false, 'class-hover': true })).toBe('class-active class-hover');
  });

  it('should resolve arrays of classes', () => {
    expect(cn(['class1', 'class2'], ['class3'])).toBe('class1 class2 class3');
  });

  it('should correctly merge conflicting Tailwind classes using twMerge', () => {
    // twMerge should override padding and text-colors with the latter arguments
    expect(cn('px-2 py-1', 'px-4')).toBe('py-1 px-4');
    expect(cn('text-red-500 bg-white', 'text-blue-500')).toBe('bg-white text-blue-500');
    expect(cn('m-2', 'm-5')).toBe('m-5');
  });
});
