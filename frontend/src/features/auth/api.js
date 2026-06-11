import api from '@/lib/api';

export const authApi = {
  login: (credentials) => api.post('/auth/login', credentials).then((r) => r.data),
  refresh: () => api.post('/auth/refresh').then((r) => r.data),
  logout: () => api.post('/auth/logout'),
  // Bootstrap sessiya tekshiruvi: 401 bo'lsa refresh urinmaymiz (oddiy guest holati).
  me: () => api.get('/auth/me', { skipAuthRefresh: true }).then((r) => r.data),
};
