import { describe, it, expect } from 'vitest';
import bcrypt from 'bcryptjs';

describe('Encryption (bcryptjs)', () => {
  it('should hash a password and verify it correctly', async () => {
    const password = 'mySecretPassword123';
    
    // Hash password
    const hashedPassword = await bcrypt.hash(password, 12);
    
    expect(hashedPassword).not.toBe(password);
    expect(hashedPassword).toBeDefined();

    // Verify correct password
    const isMatch = await bcrypt.compare(password, hashedPassword);
    expect(isMatch).toBe(true);

    // Verify wrong password
    const isWrongMatch = await bcrypt.compare('wrongPassword', hashedPassword);
    expect(isWrongMatch).toBe(false);
  });
});
