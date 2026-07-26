import { Loader2 } from 'lucide-react';
import clsx from 'clsx';

export const Button = ({ children, variant = 'primary', isLoading = false, className, icon: Icon, ...props }) => (
  <button className={clsx('button', variant, className)} disabled={isLoading || props.disabled} {...props}>
    {isLoading ? <Loader2 size={16} /> : Icon ? <Icon size={16} /> : null}
    {children}
  </button>
);

export const Badge = ({ children, variant = 'neutral' }) => (
  <span className={clsx('badge', variant)}>{children}</span>
);

export const Card = ({ children, className, padded = false }) => (
  <section className={clsx('card', padded && 'pad', className)}>{children}</section>
);

export const Field = ({ label, children, error }) => (
  <label className="field">
    {label && <span>{label}</span>}
    {children}
    {error && <small className="alert">{error}</small>}
  </label>
);

export const Input = (props) => <input className="input" {...props} />;
export const Select = (props) => <select className="select" {...props} />;
export const Textarea = (props) => <textarea className="textarea" {...props} />;

export const PageHeader = ({ title, subtitle, actions }) => (
  <header className="page-header">
    <div>
      <h1>{title}</h1>
      {subtitle && <p style={{ unicodeBidi: 'isolate' }}>{subtitle}</p>}
    </div>
    {actions}
  </header>
);

export const EmptyState = ({ title = 'Nothing here yet', message, action }) => (
  <div className="empty-state card">
    <h2>{title}</h2>
    {message && <p>{message}</p>}
    {action}
  </div>
);

export const ErrorState = ({ title = 'Something went wrong', message, requestId, action }) => (
  <div className="error-state card">
    <h2>{title}</h2>
    {message && <p>{message}</p>}
    {requestId && <p className="ltr-value">Request ID: {requestId}</p>}
    {action}
  </div>
);

export const Skeleton = ({ lines = 3 }) => (
  <div className="card pad">
    {Array.from({ length: lines }).map((_, index) => (
      <div
        key={index}
        style={{
          height: 14,
          width: `${82 - index * 12}%`,
          margin: '10px 0',
          borderRadius: 8,
          background: 'linear-gradient(90deg, #eee7db, #f8f4ea, #eee7db)',
        }}
      />
    ))}
  </div>
);

const activateRow = (event, row, onRowClick) => {
  if (!onRowClick || !['Enter', ' '].includes(event.key)) return;
  event.preventDefault();
  onRowClick(row);
};

export const Table = ({ columns, rows, onRowClick, empty }) => (
  <div className="table-wrap">
    <table className="table">
      <thead>
        <tr>
          {columns.map((column) => <th key={column.key}>{column.label}</th>)}
        </tr>
      </thead>
      <tbody>
        {rows.length ? rows.map((row) => (
          <tr
            key={row.uuid || row.id}
            className={onRowClick ? 'clickable' : undefined}
            role={onRowClick ? 'button' : undefined}
            tabIndex={onRowClick ? 0 : undefined}
            onClick={() => onRowClick?.(row)}
            onKeyDown={(event) => activateRow(event, row, onRowClick)}
          >
            {columns.map((column) => <td key={column.key}>{column.render ? column.render(row) : row[column.key]}</td>)}
          </tr>
        )) : (
          <tr>
            <td colSpan={columns.length}>{empty || 'No records found.'}</td>
          </tr>
        )}
      </tbody>
    </table>
  </div>
);
