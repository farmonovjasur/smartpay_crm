/**
 * @param {{ title: string, description?: string, count?: number|string, actions?: React.ReactNode }} props
 */
export function PageHeader({ title, description, count, actions }) {
  return (
    <div className="flex items-center justify-between">
      <div className="flex items-center gap-3">
        <div>
          <h1 className="flex items-center gap-3 text-2xl font-bold text-[var(--text-primary)]">
            {title}
            {count !== undefined && count !== null && (
              <span className="rounded-xl bg-primary-bg px-2.5 py-1 text-sm font-semibold text-primary-text">
                {count}
              </span>
            )}
          </h1>
          {description && <p className="mt-1 text-sm text-[var(--text-secondary)]">{description}</p>}
        </div>
      </div>
      {actions && <div className="flex items-center gap-3">{actions}</div>}
    </div>
  );
}
