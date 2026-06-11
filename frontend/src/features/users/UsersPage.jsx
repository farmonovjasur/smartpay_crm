import { useMemo, useState } from 'react';
import { Plus, Search, Lock, Crown, User as UserIcon, Pencil, Trash2, KeyRound } from 'lucide-react';
import {
  PageHeader, Pagination, ErrorState, EmptyState, ConfirmDialog,
} from '@/components/common';
import { useUsers, useDeleteUser } from './hooks';
import { UserForm } from './UserForm';
import { ResetPasswordDialog } from './ResetPasswordDialog';
import { useDebounce } from '@/lib/useDebounce';
import { formatDate } from '@/lib/date';
import { showSuccess } from '@/lib/toast';
import { cn } from '@/lib/utils';

export default function UsersPage() {
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [formOpen, setFormOpen] = useState(false);
  const [editUser, setEditUser] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [resetTarget, setResetTarget] = useState(null);
  const debouncedSearch = useDebounce(search, 400);

  const filters = useMemo(
    () => ({ search: debouncedSearch || undefined, page }),
    [debouncedSearch, page]
  );

  const { data, isLoading, isError, refetch } = useUsers(filters);
  const deleteUser = useDeleteUser();

  const rows = data?.data || [];
  const total = data?.total ?? 0;
  const pageSize = data?.pageSize ?? 20;
  const startIdx = total === 0 ? 0 : (page - 1) * pageSize + 1;
  const endIdx = Math.min(page * pageSize, total);

  function handleSearchChange(v) {
    setSearch(v);
    setPage(1);
  }

  function openCreate() {
    setEditUser(null);
    setFormOpen(true);
  }

  function openEdit(user) {
    setEditUser(user);
    setFormOpen(true);
  }

  function confirmDelete() {
    if (!deleteTarget) return;
    deleteUser.mutate(deleteTarget.id, {
      onSuccess: () => {
        showSuccess("Foydalanuvchi o'chirildi");
        setDeleteTarget(null);
      },
    });
  }

  if (isError) return <ErrorState onRetry={refetch} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Foydalanuvchilar"
        count={total || undefined}
        actions={
          <button
            type="button"
            onClick={openCreate}
            className="flex items-center gap-2 rounded-btn bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-hover"
          >
            <Plus className="h-4 w-4" />
            Yangi foydalanuvchi
          </button>
        }
      />

      {/* Admin only warning */}
      <div className="flex items-center gap-3 rounded-btn border-l-[3px] border-warning bg-warning-bg px-4 py-3 text-sm font-medium text-warning-text">
        <Lock className="h-4 w-4 shrink-0" />
        Bu sahifa faqat Admin uchun
      </div>

      {/* Search bar */}
      <div className="flex items-center gap-4">
        <div className="relative flex-1">
          <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-secondary)]" />
          <input
            value={search}
            onChange={(e) => handleSearchChange(e.target.value)}
            placeholder="Ism yoki email bo'yicha qidirish..."
            className="h-11 w-full rounded-btn border border-[var(--border)] bg-[var(--card-bg)] pl-11 pr-4 text-sm outline-none placeholder:text-[var(--text-secondary)] focus:ring-2 focus:ring-primary"
          />
        </div>
      </div>

      {/* Table */}
      <div className="overflow-hidden rounded-card border border-[var(--border)] bg-[var(--card-bg)] shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--border)] bg-bg-light text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--text-secondary)]">
                <th className="w-12 px-5 py-3.5">#</th>
                <th className="px-3 py-3.5">Ism</th>
                <th className="px-3 py-3.5">Email</th>
                <th className="px-3 py-3.5">Rol</th>
                <th className="px-3 py-3.5">Oxirgi kirish</th>
                <th className="px-3 py-3.5">Holat</th>
                <th className="w-32 px-3 py-3.5 text-center">Amallar</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr>
                  <td colSpan={7} className="py-16 text-center text-[var(--text-secondary)]">
                    Yuklanmoqda…
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-16 text-center">
                    <EmptyState
                      title="Foydalanuvchilar topilmadi"
                      description={debouncedSearch ? 'Qidiruv natijasi bo‘sh' : "Hozircha foydalanuvchilar yo'q"}
                    />
                  </td>
                </tr>
              ) : (
                rows.map((user, i) => (
                  <tr
                    key={user.id}
                    className={cn(
                      'border-b border-[var(--border)] last:border-0',
                      i % 2 === 1 && 'bg-bg-light'
                    )}
                  >
                    <td className="px-5 py-3.5 text-[var(--text-secondary)]">{startIdx + i}</td>
                    <td className="px-3 py-3.5">
                      <div className="flex items-center gap-2.5">
                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">
                          {user.name?.[0]?.toUpperCase() || 'U'}
                        </span>
                        <span className="font-semibold text-[var(--text-primary)]">{user.name}</span>
                      </div>
                    </td>
                    <td className="px-3 py-3.5 text-[var(--text-primary)]">{user.email}</td>
                    <td className="px-3 py-3.5">
                      <RoleBadge role={user.role} />
                    </td>
                    <td className="px-3 py-3.5 text-[var(--text-secondary)]">
                      {user.last_login_at ? formatDate(user.last_login_at) : '—'}
                    </td>
                    <td className="px-3 py-3.5">
                      <StatusCell active={!!user.is_active} />
                    </td>
                    <td className="px-3 py-3.5">
                      <div className="flex items-center justify-center gap-2">
                        <ActionBtn
                          onClick={() => openEdit(user)}
                          icon={Pencil}
                          color="text-primary"
                          label="Tahrirlash"
                        />
                        <ActionBtn
                          onClick={() => setResetTarget(user)}
                          icon={KeyRound}
                          color="text-warning-text"
                          label="Parolni tiklash"
                        />
                        <ActionBtn
                          onClick={() => setDeleteTarget(user)}
                          icon={Trash2}
                          color="text-danger"
                          label="O'chirish"
                        />
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        <div className="flex items-center justify-between border-t border-[var(--border)] px-5 py-4">
          <span className="text-[13px] text-[var(--text-secondary)]">
            {startIdx}–{endIdx} / {total} ta
          </span>
          {data && total > 0 && (
            <Pagination page={data.page} total={total} pageSize={pageSize} onPageChange={setPage} />
          )}
        </div>
      </div>

      <UserForm open={formOpen} onOpenChange={setFormOpen} user={editUser} />
      <ResetPasswordDialog
        open={!!resetTarget}
        onOpenChange={(v) => !v && setResetTarget(null)}
        user={resetTarget}
      />
      <ConfirmDialog
        open={!!deleteTarget}
        onOpenChange={(v) => !v && setDeleteTarget(null)}
        title="Foydalanuvchini o'chirish"
        description={
          deleteTarget
            ? `"${deleteTarget.name}" (${deleteTarget.email}) foydalanuvchisini o'chirmoqchimisiz?`
            : ''
        }
        confirmLabel="Tasdiqlash"
        loading={deleteUser.isPending}
        onConfirm={confirmDelete}
      />
    </div>
  );
}

function RoleBadge({ role }) {
  if (role === 'admin') {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-purple-bg px-3 py-0.5 text-[11px] font-semibold text-purple-text">
        <Crown className="h-3 w-3" />
        Admin
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full bg-info-bg px-3 py-0.5 text-[11px] font-semibold text-info-text">
      <UserIcon className="h-3 w-3" />
      Foydalanuvchi
    </span>
  );
}

function StatusCell({ active }) {
  return (
    <span className="inline-flex items-center gap-1.5 text-xs">
      <span className={cn('h-2 w-2 rounded-full', active ? 'bg-success' : 'bg-[#94A3B8]')} />
      <span className={active ? 'text-success' : 'text-[var(--text-secondary)]'}>
        {active ? 'Faol' : 'Nofaol'}
      </span>
    </span>
  );
}

function ActionBtn({ onClick, icon: Icon, color, label }) {
  return (
    <button
      type="button"
      onClick={onClick}
      title={label}
      aria-label={label}
      className={cn('rounded-md p-1.5 transition-colors hover:bg-bg-light', color)}
    >
      <Icon className="h-[18px] w-[18px]" />
    </button>
  );
}
