import "../../styles/components.css";
import { cx } from "../ui";

const LABELS = {
  connected: "Live",
  reconnecting: "Reconnecting",
  stale: "Stale",
  offline: "Offline",
};

export function RealtimeIndicator({ status = "connected", label, className }) {
  return (
    <span className={cx("cui-realtime", `cui-realtime--${status}`, className)}>
      <span className="cui-realtime__dot" aria-hidden="true" />
      {label || LABELS[status] || status}
    </span>
  );
}
