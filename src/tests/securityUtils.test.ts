import { describe, it, expect } from 'vitest';
import { maskPhoneNumber } from '../utils/securityUtils';

describe('Utility: securityUtils', () => {
  describe('maskPhoneNumber', () => {
    it('should mask standard 9 digit phone numbers', () => {
      expect(maskPhoneNumber('777123456')).toBe('777****56');
    });

    it('should mask numbers of length greater than 5', () => {
      expect(maskPhoneNumber('1234567')).toBe('123****67');
    });

    it('should return the original phone number if it is too short', () => {
      expect(maskPhoneNumber('123')).toBe('123');
      expect(maskPhoneNumber('')).toBe('');
    });
  });
});
