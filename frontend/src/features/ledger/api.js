import api from '@/lib/api';
import { buildListParams } from '@/lib/queryParams';

/**
 * Qarzdaftari API — barcha endpoint'lar.
 */
export const ledgerApi = {
  list: (filters) =>
    api.get('/ledger', { params: buildListParams(filters) }).then((r) => r.data),

  get: (id) =>
    api.get(`/ledger/${id}`).then((r) => r.data),

  create: (data) =>
    api.post('/ledger', data).then((r) => r.data),

  update: (id, data) =>
    api.put(`/ledger/${id}`, data).then((r) => r.data),

  pay: (id, body) =>
    api.post(`/ledger/${id}/pay`, body).then((r) => r.data),

  remove: (id) =>
    api.delete(`/ledger/${id}`).then((r) => r.data),

  summary: () =>
    api.get('/ledger/summary').then((r) => r.data),
};
