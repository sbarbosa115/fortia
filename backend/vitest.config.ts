import path from 'node:path';
import react from '@vitejs/plugin-react';
import {defineConfig} from 'vitest/config';

const r = (dir: string): string => path.resolve(__dirname, dir);

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@shared': r('assets/shared'),
      '@console': r('assets/console'),
      '@respondent': r('assets/respondent'),
      '@api-types': r('assets/types'),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./vitest.setup.ts'],
    include: ['assets/**/*.test.{ts,tsx}'],
    css: false,
    restoreMocks: true,
  },
});
