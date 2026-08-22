import { useEffect, useState, useMemo } from 'react';
import { useForm, useFieldArray } from 'react-hook-form';
import { X, Search, Plus, Trash2, Building2, Save } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { useCreateLedger } from './hooks';
import { clientsApi } from '@/features/clients/api';
import { handleMutationError } from '@/lib/mutationErrors';
import { showSuccess } from '@/lib/toast';
import { formatMoney } from '@/lib/money';
import { useDebounce } from '@/lib/useDebounce';
import { cn } from '@/lib/utils';

/**
 * Yangi qarz qo'shish dialogi — 2 bosqichli:
 * 1. Mijoz qidirish va tanlash
 * 2. Xizmat qatorlari + summalar kiritish
 */
export function AddLedgerDialog({ open, onOpenChange }) {
  const [step, setStep] = useState(1);
  const [selectedClient, setSelectedClient] = useState(null);
  const mutation = useCreateLedger();

  const {
    register,
    handleSubmit,
    control,
    reset,
    watch,
    setError,
    formState: { errors },
  } = useForm({
    defaultValues: {
      notes: '',
      items: [{ description: '', amount: '' }],
    },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: 'items',
  });

  const watchedItems = watch('items');

  // Jami summa real-time hisoblash
  const totalAmount = useMemo(() => {
    return (watchedItems || []).reduce((sum, item) => {
      const val = parseFloat(item?.amount) || 0;
      return sum + val;
    }, 0);
  }, [watchedItems]);

  useEffect(() => {
    if (open) {
      setStep(1);
      setSelectedClient(null);
      reset({ notes: '', items: [{ description: '', amount: '' }] });
    }
  }, [open, reset]);

  function handleClientSelect(client) {
    setSelectedClient(client);
    setStep(2);
  }

  function handleBack() {
    setStep(1);
    setSelectedClient(null);
  }

  function onSubmit(data) {
    // Validate items
    const validItems = data.items.filter(
      (item) => item.description.trim() !== '' && parseFloat(item.amount) > 0
    );

    if (validItems.length === 0) {
      setError('items', { message: 'Kamida bitta xizmat qatori kiritilishi shart.' });
      return;
    }

    mutation.mutate(
      {
        client_id: selectedClient.id,
        notes: data.notes || null,
        items: validItems.map((item) => ({
          description: item.description.trim(),
          amount: String(item.amount),
        })),
      },
      {
        onSuccess: () => {
          showSuccess('Qarz muvaffaqiyatli yaratildi.');
          onOpenChange(false);
        },
        onError: (err) => handleMutationError(err, setError),
      }
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className={cn('max-w-2xl', step === 1 && 'max-w-lg')}>
        {/* Header */}
        <div className="flex items-center justify-between border-b border-[var(--border)] pb-4">
          <div>
            <h2 className="text-lg font-semibold text-[var(--text-primary)]">
              Yangi qarz qo'shish
            </h2>
            <p className="text-xs text-[var(--text-secondary)]">
              {step === 1 ? 'Mijozni tanlang' : 'Xizmat va summa kiriting'}
            </p>
          </div>
          <button
            type="button"
            onClick={() => onOpenChange(false)}
            className="rounded-lg p-1.5 text-[var(--text-secondary)] hover:bg-[var(--hover-bg)]"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {step === 1 ? (
          <ClientSearchStep onSelect={handleClientSelect} />
        ) : (
          <form onSubmit={handleSubmit(onSubmit)} className="mt-4 space-y-5">
            {/* Tanlangan mijoz info */}
            <div className="flex items-center gap-3 rounded-lg border border-[var(--border)] bg-[var(--hover-bg)] p-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary-bg">
                <Building2 className="h-5 w-5 text-primary" />
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-[var(--text-primary)] truncate">
                  {selectedClient?.name}
                </p>
                <p className="text-xs text-[var(--text-secondary)]">
                  INN: {selectedClient?.inn} • {selectedClient?.phone}
                </p>
              </div>
              <button
                type="button"
                onClick={handleBack}
                className="text-xs font-medium text-primary hover:underline"
              >
                O'zgartirish
              </button>
            </div>

            {/* Xizmat qatorlari */}
            <div>
              <label className="mb-2 block text-sm font-medium text-[var(--text-primary)]">
                Xizmatlar
              </label>
              <div className="space-y-2">
                {fields.map((field, index) => (
                  <div key={field.id} className="flex items-start gap-2">
                    <div className="flex-1">
                      <input
                        {...register(`items.${index}.description`, {
                          required: 'Xizmat izoh kiriting',
                        })}
                        placeholder="Xizmat turi (masalan: Router o'rnatish)"
                        className={cn(
                          'h-10 w-full rounded-btn border bg-[var(--card-bg)] px-3 text-sm outline-none placeholder:text-[var(--text-secondary)] focus:ring-2 focus:ring-primary',
                          errors.items?.[index]?.description
                            ? 'border-danger'
                            : 'border-[var(--border)]'
                        )}
                      />
                    </div>
                    <div className="w-40">
                      <input
                        {...register(`items.${index}.amount`, {
                          required: 'Summa kiriting',
                          validate: (v) =>
                            (parseFloat(v) > 0) || 'Musbat son kiriting',
                        })}
                        type="number"
                        min="0"
                        step="any"
                        placeholder="Summa"
                        className={cn(
                          'h-10 w-full rounded-btn border bg-[var(--card-bg)] px-3 text-sm outline-none tabular-nums placeholder:text-[var(--text-secondary)] focus:ring-2 focus:ring-primary',
                          errors.items?.[index]?.amount
                            ? 'border-danger'
                            : 'border-[var(--border)]'
                        )}
                      />
                    </div>
                    {fields.length > 1 && (
                      <button
                        type="button"
                        onClick={() => remove(index)}
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-btn border border-[var(--border)] text-[var(--text-secondary)] transition-colors hover:border-danger hover:bg-danger-bg hover:text-danger-text"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    )}
                  </div>
                ))}
              </div>

              <button
                type="button"
                onClick={() => append({ description: '', amount: '' })}
                className="mt-2 flex items-center gap-1.5 rounded-btn border border-dashed border-[var(--border)] px-3 py-2 text-xs font-medium text-[var(--text-secondary)] transition-colors hover:border-primary hover:text-primary"
              >
                <Plus className="h-3.5 w-3.5" />
                Qo'shish
              </button>

              {errors.items?.message && (
                <p className="mt-1 text-xs text-danger">{errors.items.message}</p>
              )}
            </div>

            {/* Umumiy izoh */}
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--text-primary)]">
                Umumiy izoh (ixtiyoriy)
              </label>
              <textarea
                {...register('notes')}
                rows={2}
                placeholder="Qo'shimcha izoh..."
                className="w-full rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-3 py-2 text-sm outline-none placeholder:text-[var(--text-secondary)] focus:ring-2 focus:ring-primary"
              />
            </div>

            {/* Jami va tugmalar */}
            <div className="flex items-center justify-between border-t border-[var(--border)] pt-4">
              <div>
                <span className="text-sm text-[var(--text-secondary)]">Jami: </span>
                <span className="text-lg font-bold text-[var(--text-primary)]">
                  {formatMoney(totalAmount)}
                </span>
                <span className="ml-1 text-sm text-[var(--text-secondary)]">so'm</span>
              </div>
              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={() => onOpenChange(false)}
                  className="rounded-btn border border-[var(--border)] px-4 py-2.5 text-sm font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--hover-bg)]"
                >
                  Bekor qilish
                </button>
                <button
                  type="submit"
                  disabled={mutation.isPending}
                  className="flex items-center gap-2 rounded-btn bg-primary px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:opacity-90 disabled:opacity-60"
                >
                  <Save className="h-4 w-4" />
                  {mutation.isPending ? 'Saqlanmoqda...' : 'Saqlash'}
                </button>
              </div>
            </div>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}

/**
 * 1-bosqich: Mijoz qidirish va tanlash paneli.
 * Mavjud /api/clients endpointidan foydalanadi.
 */
function ClientSearchStep({ onSelect }) {
  const [search, setSearch] = useState('');
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  const debouncedSearch = useDebounce(search, 300);

  useEffect(() => {
    if (!debouncedSearch || debouncedSearch.length < 2) {
      setResults([]);
      return;
    }

    let cancelled = false;
    setLoading(true);

    clientsApi
      .list({ search: debouncedSearch, pageSize: 10 })
      .then((res) => {
        if (!cancelled) {
          // normalizeClient may be needed, but data comes in raw form
          const items = (res?.data || []).map((c) => ({
            id: c.id,
            name: c.name,
            inn: c.inn,
            phone: c.phone,
            payment_type: c.payment_type ?? c.paymentType ?? '',
            product_count: c.product_count ?? c.productCount ?? 0,
          }));
          setResults(items);
        }
      })
      .catch(() => {
        if (!cancelled) setResults([]);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [debouncedSearch]);

  return (
    <div className="mt-4 space-y-3">
      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-secondary)]" />
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Mijoz qidirish (ism, INN, telefon)..."
          autoFocus
          className="h-11 w-full rounded-btn border border-[var(--border)] bg-[var(--card-bg)] pl-10 pr-4 text-sm outline-none placeholder:text-[var(--text-secondary)] focus:ring-2 focus:ring-primary"
        />
      </div>

      <div className="max-h-[320px] overflow-y-auto rounded-lg border border-[var(--border)]">
        {loading ? (
          <p className="py-8 text-center text-sm text-[var(--text-secondary)]">Qidirilmoqda…</p>
        ) : results.length === 0 ? (
          <p className="py-8 text-center text-sm text-[var(--text-secondary)]">
            {search.length < 2
              ? 'Mijoz nomini, INN yoki telefon raqamini kiriting'
              : 'Natija topilmadi'}
          </p>
        ) : (
          results.map((client) => (
            <button
              key={client.id}
              type="button"
              onClick={() => onSelect(client)}
              className="flex w-full items-center gap-3 border-b border-[var(--border)] px-4 py-3 text-left transition-colors hover:bg-[var(--hover-bg)] last:border-0"
            >
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-bg">
                <Building2 className="h-4 w-4 text-primary" />
              </div>
              <div className="min-w-0 flex-1">
                <p className="font-semibold text-[var(--text-primary)] truncate">
                  {client.name}
                </p>
                <p className="text-xs text-[var(--text-secondary)]">
                  INN: {client.inn} • {client.phone}
                </p>
              </div>
            </button>
          ))
        )}
      </div>
    </div>
  );
}
