import { useRef, useState } from 'react';
import { Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { validateImportFile } from '@/lib/fileValidation';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * @param {{ onFile: (file: File) => void, disabled?: boolean, className?: string }} props
 */
export function FileUpload({ onFile, disabled, className }) {
  const t = useT();
  const [error, setError] = useState(null);
  const [fileName, setFileName] = useState(null);
  const inputRef = useRef(null);

  function handleChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    const result = validateImportFile({ filename: file.name, size: file.size });
    if (!result.valid) {
      setError(result.error);
      setFileName(null);
      return;
    }
    setError(null);
    setFileName(file.name);
    onFile(file);
  }

  return (
    <div className={cn('space-y-2', className)}>
      <input ref={inputRef} type="file" accept=".xlsx" onChange={handleChange} className="hidden" disabled={disabled} />
      <Button type="button" variant="outline" disabled={disabled} onClick={() => inputRef.current?.click()}>
        <Upload className="mr-2 h-4 w-4" />
        {t('clients.import.selectFile')}
      </Button>
      {fileName && <p className="text-sm text-[var(--text-secondary)]">{fileName}</p>}
      {error && <p className="text-xs text-danger">{error}</p>}
    </div>
  );
}
