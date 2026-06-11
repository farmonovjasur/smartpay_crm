import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { FileUpload } from '@/components/common';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table';
import { Download } from 'lucide-react';
import api from '@/lib/api';
import { downloadFile } from '@/lib/download';
import { showSuccess } from '@/lib/toast';
import { handleMutationError } from '@/lib/mutationErrors';

export function ClientImportDialog({ open, onOpenChange }) {
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [downloading, setDownloading] = useState(false);
  const qc = useQueryClient();

  async function handleDownloadTemplate() {
    setDownloading(true);
    try {
      await downloadFile('/clients/template', undefined, 'mijozlar_shablon.xlsx');
    } finally {
      setDownloading(false);
    }
  }

  const dryRun = useMutation({
    mutationFn: (f) => {
      const fd = new FormData();
      fd.append('file', f);
      return api.post('/clients/import?dryRun=true', fd, { headers: { 'Content-Type': 'multipart/form-data' } }).then((r) => r.data);
    },
    onSuccess: (data) => setPreview(data),
    onError: (err) =>
      handleMutationError(err, {
        statusMessages: {
          400: "Fayl formati noto'g'ri",
          413: 'Fayl hajmi 5 MB dan oshmasligi kerak',
        },
      }),
  });

  const confirm = useMutation({
    mutationFn: () => {
      const fd = new FormData();
      fd.append('file', file);
      return api.post('/clients/import?dryRun=false', fd, { headers: { 'Content-Type': 'multipart/form-data' } }).then((r) => r.data);
    },
    onSuccess: (data) => {
      showSuccess(`${data.importedCount} ta mijoz import qilindi`);
      qc.invalidateQueries({ queryKey: ['clients'] });
      handleClose();
    },
    onError: (err) =>
      handleMutationError(err, {
        statusMessages: {
          400: "Fayl formati noto'g'ri",
          413: 'Fayl hajmi 5 MB dan oshmasligi kerak',
        },
      }),
  });

  function handleFile(f) {
    setFile(f);
    setPreview(null);
    dryRun.mutate(f);
  }

  function handleClose() {
    setFile(null);
    setPreview(null);
    onOpenChange(false);
  }

  const hasErrors = preview?.errorRows?.length > 0;
  const canConfirm = preview && !hasErrors;

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="max-w-2xl">
        <DialogHeader><DialogTitle>Excel import</DialogTitle></DialogHeader>

        {/* Template download hint */}
        <div className="flex items-center justify-between rounded-lg border border-dashed border-[var(--border)] bg-bg-light px-4 py-3">
          <div className="text-sm text-[var(--text-secondary)]">
            Avval <span className="font-medium text-[var(--text-primary)]">namuna shablon</span>ni yuklab oling va to'ldiring
          </div>
          <button
            type="button"
            onClick={handleDownloadTemplate}
            disabled={downloading}
            className="flex items-center gap-1.5 rounded-btn border border-info px-3 py-1.5 text-xs font-medium text-info-text transition-colors hover:bg-info-bg disabled:opacity-60"
          >
            <Download className="h-3.5 w-3.5" />
            {downloading ? 'Yuklanmoqda...' : 'Namuna yuklab olish'}
          </button>
        </div>

        <FileUpload onFile={handleFile} disabled={dryRun.isPending || confirm.isPending} />

        {dryRun.isPending && <p className="text-sm text-[var(--text-secondary)]">Tekshirilmoqda…</p>}

        {preview && (
          <div className="space-y-3 mt-4">
            <div className="flex gap-4 text-sm">
              <span>Jami: <strong>{preview.totalRows}</strong></span>
              <span className="text-success">Import: <strong>{preview.importedCount}</strong></span>
              <span className="text-warning">Dublikat: <strong>{preview.duplicateRows?.length || 0}</strong></span>
              <span className="text-danger">Xato: <strong>{preview.errorRows?.length || 0}</strong></span>
            </div>

            {preview.errorRows?.length > 0 && (
              <div className="max-h-40 overflow-auto border rounded-btn">
                <Table>
                  <TableHeader><TableRow><TableHead>Qator</TableHead><TableHead>Xato</TableHead></TableRow></TableHeader>
                  <TableBody>
                    {preview.errorRows.map((r, i) => (
                      <TableRow key={i}><TableCell>{r.row}</TableCell><TableCell className="text-danger text-xs">{r.errors?.join(', ')}</TableCell></TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}

            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={handleClose}>Bekor qilish</Button>
              <Button onClick={() => confirm.mutate()} disabled={!canConfirm || confirm.isPending}>
                {confirm.isPending ? 'Import...' : 'Tasdiqlash'}
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
