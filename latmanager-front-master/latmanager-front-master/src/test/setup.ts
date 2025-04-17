import '@testing-library/jest-dom';
import { expect, afterEach, beforeAll, afterAll } from 'vitest';
import { cleanup } from '@testing-library/react';
import { setupServer } from 'msw/node';
import * as matchers from '@testing-library/jest-dom/matchers';
import { handlers } from './mocks/handlers';

// Extend Vitest's expect method with methods from react-testing-library
expect.extend(matchers as any);

// Runs a cleanup after each test case
afterEach(() => {
  cleanup();
});

// Create MSW server
export const server = setupServer(...handlers);

// Establish API mocking before all tests
beforeAll(() => {
  // Force Node to use the mocked implementation
  server.listen({ onUnhandledRequest: 'warn' });
});

// Reset any request handlers that we may add during the tests
afterEach(() => {
  server.resetHandlers();
});

// Clean up after the tests are finished
afterAll(() => {
  server.close();
});
