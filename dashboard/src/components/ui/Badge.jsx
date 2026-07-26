import "../../styles/components.css";
import { cx } from "./utils";

const STATUS_VARIANTS = {
  active: "success",
  approved: "success",
  assigned: "info",
  cancelled: "danger",
  checked_in: "success",
  checked_out: "neutral",
  closed: "neutral",
  confirmed: "info",
  critical: "critical",
  delayed: "warning",
  done: "success",
  failed: "danger",
  high: "warning",
  in_progress: "info",
  new: "gold",
  open: "info",
  overdue: "critical",
  paid: "success",
  pending: "warning",
  rejected: "danger",
  stale: "danger",
  unpaid: "warning",
  vip: "gold",
};

function normalizeStatus(status) {
  return String(status ?? "")
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, "_");
}

export function Badge({
  children,
  status,
  variant,
  dot = false,
  className,
  ...props
}) {
  const normalizedStatus = normalizeStatus(status || children);
  const resolvedVariant = variant || STATUS_VARIANTS[normalizedStatus] || "neutral";

  return (
    <span
      className={cx("cui-badge", `cui-badge--${resolvedVariant}`, className)}
      {...props}
    >
      {dot ? <span className="cui-badge__dot" aria-hidden="true" /> : null}
      {children || status}
    </span>
  );
}
