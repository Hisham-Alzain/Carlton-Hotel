import "../../styles/components.css";

export function ErrorState({
  title = "Something went wrong",
  message,
  requestId,
  action,
  statusCode,
}) {
  return (
    <div className="cui-state" role="alert">
      <div className="cui-state__mark" aria-hidden="true">
        !
      </div>
      <div>
        <h2 className="cui-state__title">
          {statusCode ? `${statusCode}: ${title}` : title}
        </h2>
        {message ? <p className="cui-state__description">{message}</p> : null}
        {requestId ? (
          <p className="cui-state__description">Request ID: {requestId}</p>
        ) : null}
      </div>
      {action ? <div className="cui-state__actions">{action}</div> : null}
    </div>
  );
}
