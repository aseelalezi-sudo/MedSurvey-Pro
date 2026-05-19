import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import globals from 'globals';
import prettier from 'eslint-config-prettier';

export default tseslint.config(
  { ignores: ['dist/', 'server/', '.kilo/', 'node_modules/', 'playwright-report/', 'test-results/'] },
  {
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2020,
      globals: globals.browser,
    },
    plugins: {
      'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
      '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-empty-object-type': 'warn',

      // Allow calling setState in effects for Zustand stores (React 19 new rule)
      'react-hooks/set-state-in-effect': 'off',

      // Allow catch-and-rethrow without cause (pre-existing pattern)
      'preserve-caught-error': 'off',

      // Allow Date.now() and similar in hooks (pre-existing pattern)
      'react-hooks/purity': 'off',

      // Allow variables accessed before declaration
      'react-hooks/immutability': 'off',
    },
  },
  prettier,
);
