import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { debtorsApi, normalizeDebt } from './api';
import { normalizePage } from '@/lib/pagination';

const KEYS = {
  all: ['debtors'],
  list: (filters) => ['debtors', 'list', filters],
  detail: (id) => ['debtors', id],
};

export function useDebtors(filters) {
  return useQuery({
    queryKey: KEYS.list(filters),
    queryFn: () =>
      debtorsApi.list(filters).then((res) => {
        const page = normalizePage(res);
        return { ...page, data: page.data.map(normalizeDebt) };
      }),
    placeholderData: (prev) => prev,
  });
}

export function useDebtor(id) {
  return useQuery({
    queryKey: KEYS.detail(id),
    queryFn: () => debtorsApi.get(id).then((res) => normalizeDebt(res?.data ?? res)),
    enabled: !!id,
  });
}

export function usePayDebt(id) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body) => debtorsApi.pay(id, body),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.all });
      qc.invalidateQueries({ queryKey: KEYS.detail(id) });
      // Mijozning has_active_debt holati ham o'zgarishi mumkin.
      qc.invalidateQueries({ queryKey: ['clients'] });
    },
  });
}
