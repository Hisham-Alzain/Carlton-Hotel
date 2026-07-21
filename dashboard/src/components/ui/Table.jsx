import { forwardRef } from "react";
import "../../styles/components.css";
import { cx } from "./utils";

export const Table = forwardRef(function Table(
  {
    className,
    wrapperClassName,
    sticky = false,
    dense = true,
    caption,
    children,
    ...props
  },
  ref,
) {
  return (
    <div className={cx("cui-table-wrap", wrapperClassName)}>
      <table
        ref={ref}
        className={cx(
          "cui-table",
          sticky && "cui-table--sticky",
          dense && "cui-table--dense",
          className,
        )}
        {...props}
      >
        {caption ? <caption className="sr-only">{caption}</caption> : null}
        {children}
      </table>
    </div>
  );
});

export const TableHeader = forwardRef(function TableHeader(
  { className, ...props },
  ref,
) {
  return <thead ref={ref} className={className} {...props} />;
});

export const TableBody = forwardRef(function TableBody(
  { className, ...props },
  ref,
) {
  return <tbody ref={ref} className={className} {...props} />;
});

export const TableRow = forwardRef(function TableRow(
  { className, selected = false, ...props },
  ref,
) {
  return (
    <tr
      ref={ref}
      className={className}
      aria-selected={selected ? true : undefined}
      {...props}
    />
  );
});

export const TableHead = forwardRef(function TableHead(
  { className, scope = "col", ...props },
  ref,
) {
  return <th ref={ref} className={className} scope={scope} {...props} />;
});

export const TableCell = forwardRef(function TableCell(
  { className, numeric = false, style, ...props },
  ref,
) {
  return (
    <td
      ref={ref}
      className={className}
      style={numeric ? { textAlign: "end", ...style } : style}
      {...props}
    />
  );
});

export function TableActions({ className, children, ...props }) {
  return (
    <div className={cx("cui-table__actions", className)} {...props}>
      {children}
    </div>
  );
}

export function TableEmpty({
  colSpan,
  title = "No records found",
  description,
  action,
}) {
  return (
    <tr>
      <td colSpan={colSpan}>
        <div className="cui-state">
          <div className="cui-state__mark" aria-hidden="true">
            C
          </div>
          <div>
            <h3 className="cui-state__title">{title}</h3>
            {description ? (
              <p className="cui-state__description">{description}</p>
            ) : null}
          </div>
          {action ? <div className="cui-state__actions">{action}</div> : null}
        </div>
      </td>
    </tr>
  );
}
