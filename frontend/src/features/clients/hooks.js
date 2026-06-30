import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { clientsApi, normalizeClient } from './api';
import { normalizePage } from '@/lib/pagination';

const KEYS = { all: ['clients'], list: (f) => ['clients', 'list', f], detail: (id) => ['clients', id] };

export function useClients(filters) {
  return useQuery({
    queryKey: KEYS.list(filters),
    queryFn: () => clientsApi.list(filters).then((res) => {
      const page = normalizePage(res);
      return { ...page, data: page.data.map(normalizeClient) };
    }),
    placeholderData: (prev) => prev, // keepPreviousData
  });
}

export function useClient(id) {
  return useQuery({
    queryKey: KEYS.detail(id),
    queryFn: () => clientsApi.get(id).then((res) => normalizeClient(res?.data ?? res)),
    enabled: !!id,
  });
}

export function useCreateClient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: clientsApi.create,
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.all }),
  });
}

export function useUpdateClient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, ...data }) => clientsApi.update(id, data),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.all }),
  });
}

export function useDeleteClient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: clientsApi.remove,
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.all }),
  });
}

export function useMarkMonthlyPaid(id) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body) => clientsApi.markMonthlyPaid(id, body),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.all });
      qc.invalidateQueries({ queryKey: KEYS.detail(id) });
      qc.invalidateQueries({ queryKey: ['payments', id] });
    },
  });
}

export function usePrepay(id) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body) => clientsApi.prepay(id, body),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.all });
      qc.invalidateQueries({ queryKey: KEYS.detail(id) });
      qc.invalidateQueries({ queryKey: ['prepayments', id] });
    },
  });
}

export function usePrepayments(id) {
  return useQuery({
    queryKey: ['prepayments', id],
    queryFn: () => clientsApi.getPrepayments(id).then((res) => res?.data ?? res),
    enabled: !!id,
  });
}

export function usePayments(id) {
  return useQuery({
    queryKey: ['payments', id],
    queryFn: () => clientsApi.getPayments(id).then((res) => res?.data ?? res),
    enabled: !!id,
  });
}
