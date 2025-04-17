import { dashboardDataSchema } from '../types';

const API_URL = 'http://localhost:8000/api';

export const api = {
  dashboard: {
    getData: async () => {
      const response = await fetch(`${API_URL}/dashboard`);
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      const data = await response.json();
      return dashboardDataSchema.parse(data);
    },
  },
};
