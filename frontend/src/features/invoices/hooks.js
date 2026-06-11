import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { invoicesApi, normalizeInvoice } from './api';
import { normalizePage } from '@/lib/pagination';

const KEYS = {
  all: ['invoices'],
  list: (filters) => ['invoices', 'list', filters],
  detail: (id) => ['invoices', id],
};

export function useInvoices(filters) {
  return useQuery({
    queryKey: KEYS.list(filters),
    queryFn: () =>
      invoicesApi.list(filters).then((res) => {
        const page = normalizePage(res);
        return { ...page, data: page.data.map(normalizeInvoice) };
      }),
    placeholderData: (prev) => prev,
  });
}

export function useInvoice(id) {
  return useQuery({
    queryKey: KEYS.detail(id),
    queryFn: () => invoicesApi.get(id).then((res) => normalizeInvoice(res?.data ?? res)),
    enabled: !!id,
  });
}

export function useGenerateInvoice() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body) => invoicesApi.generate(body),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.all }),
  });
}

export function useDeleteInvoice() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id) => invoicesApi.remove(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.all }),
  });
}
