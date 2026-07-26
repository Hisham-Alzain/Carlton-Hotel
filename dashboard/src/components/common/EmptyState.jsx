import "../../styles/components.css";

export function EmptyState({
  title = "Nothing to show yet",
  description,
  action,
  secondaryAction,
  mark = "C",
}) {
  return (
    <div className="cui-state">
      <div className="cui-state__mark" aria-hidden="true">
        {mark}
      </div>
      <div>
        <h2 className="cui-state__title">{title}</h2>
        {description ? (
          <p className="cui-state__description">{description}</p>
        ) : null}
      </div>
      {action || secondaryAction ? (
        <div className="cui-state__actions">
          {action}
          {secondaryAction}
        </div>
      ) : null}
    </div>
  );
}
