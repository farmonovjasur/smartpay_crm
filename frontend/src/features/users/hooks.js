import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { usersApi, normalizeUser } from './api';
import { normalizePage } from '@/lib/pagination';

const KEYS = {
  all: ['users'],
  list: (filters) => ['users', 'list', filters],
  detail: (id) => ['users', id],
};

export function useUsers(filters) {
  return useQuery({
    queryKey: KEYS.list(filters),
    queryFn: () =>
      usersApi.list(filters).then((res) => {
        const page = normalizePage(res);
        return { ...page, data: page.data.map(normalizeUser) };
      }),
    placeholderData: (prev) => prev,
  });
}

export function useCreateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: usersApi.create,
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.all }),
  });
}

export function useUpdateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, ...body }) => usersApi.update(id, body),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.all }),
  });
}

export function useDeleteUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id) => usersApi.remove(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.all }),
  });
}

export function useResetPassword() {
  return useMutation({
    mutationFn: (id) => usersApi.resetPassword(id),
  });
}
