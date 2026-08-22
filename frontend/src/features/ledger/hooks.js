import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ledgerApi } from './api';
import { normalizePage } from '@/lib/pagination';

const KEYS = {
  all: ['ledger'],
  list: (filters) => ['ledger', 'list', filters],
  detail: (id) => ['ledger', id],
  summary: ['ledger', 'summary'],
};

export function useLedgerEntries(filters) {
  return useQuery({
    queryKey: KEYS.list(filters),
    queryFn: () =>
      ledgerApi.list(filters).then((res) => normalizePage(res)),
    placeholderData: (prev) => prev,
  });
}

export function useLedgerEntry(id) {
  return useQuery({
    queryKey: KEYS.detail(id),
    queryFn: () => ledgerApi.get(id).then((res) => res?.data ?? res),
    enabled: !!id,
  });
}

export function useLedgerSummary() {
  return useQuery({
    queryKey: KEYS.summary,
    queryFn: ledgerApi.summary,
  });
}

export function useCreateLedger() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data) => ledgerApi.create(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.all });
      qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });
}

export function useUpdateLedger(id) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data) => ledgerApi.update(id, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.all });
      qc.invalidateQueries({ queryKey: KEYS.detail(id) });
    },
  });
}

export function usePayLedger(id) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body) => ledgerApi.pay(id, body),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.all });
      qc.invalidateQueries({ queryKey: KEYS.detail(id) });
      qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });
}

export function useDeleteLedger() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id) => ledgerApi.remove(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.all });
      qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });
}
