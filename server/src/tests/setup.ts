import crypto from 'crypto';

process.env.JWT_SECRET = process.env.JWT_SECRET || crypto.randomUUID();
process.env.REFRESH_SECRET = process.env.REFRESH_SECRET || crypto.randomUUID();
process.env.NODE_ENV = 'test';
