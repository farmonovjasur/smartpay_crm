import { ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-react';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table';
import { cn } from '@/lib/utils';

/**
 * @typedef {{ key: string, label: string, sortable?: boolean, render?: (row: any) => any, className?: string }} Column
 * @param {{ columns: Column[], data: any[], loading?: boolean, sortKey?: string, sortDir?: 'asc'|'desc', onSort?: (key: string) => void, emptyText?: string, rowKey?: string|((row:any)=>string) }} props
 */
export function DataTable({ columns, data, loading, sortKey, sortDir, onSort, emptyText = "Ma'lumot topilmadi", rowKey = 'id' }) {
  const getKey = typeof rowKey === 'function' ? rowKey : (row) => row[rowKey];

  return (
    <Table>
      <TableHeader>
        <TableRow>
          {columns.map((col) => (
            <TableHead key={col.key} className={cn(col.sortable && 'cursor-pointer select-none', col.className)} onClick={() => col.sortable && onSort?.(col.key)}>
              <span className="inline-flex items-center gap-1">
                {col.label}
                {col.sortable && <SortIcon active={sortKey === col.key} dir={sortKey === col.key ? sortDir : null} />}
              </span>
            </TableHead>
          ))}
        </TableRow>
      </TableHeader>
      <TableBody>
        {loading ? (
          <TableRow><TableCell colSpan={columns.length} className="h-24 text-center text-[var(--text-secondary)]">Yuklanmoqda…</TableCell></TableRow>
        ) : data.length === 0 ? (
          <TableRow><TableCell colSpan={columns.length} className="h-24 text-center text-[var(--text-secondary)]">{emptyText}</TableCell></TableRow>
        ) : (
          data.map((row) => (
            <TableRow key={getKey(row)}>
              {columns.map((col) => (
                <TableCell key={col.key} className={col.className}>{col.render ? col.render(row) : row[col.key]}</TableCell>
              ))}
            </TableRow>
          ))
        )}
      </TableBody>
    </Table>
  );
}

function SortIcon({ active, dir }) {
  if (!active) return <ArrowUpDown className="h-3 w-3 opacity-40" />;
  return dir === 'asc' ? <ArrowUp className="h-3 w-3" /> : <ArrowDown className="h-3 w-3" />;
}
