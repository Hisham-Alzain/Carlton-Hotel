import {
  useEffect,
  useId,
  useRef,
} from "react";
import { createPortal } from "react-dom";
import "../../styles/components.css";
import { Button } from "./Button";
import { cx } from "./utils";

export function Modal({
  open,
  title,
  description,
  children,
  footer,
  onClose,
  size = "md",
  closeLabel = "Close",
  closeOnOverlayClick = true,
  className,
}) {
  const titleId = useId();
  const descriptionId = useId();
  const panelRef = useRef(null);

  useEffect(() => {
    if (!open || typeof document === "undefined") return undefined;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    function handleKeyDown(event) {
      if (event.key === "Escape") onClose?.();
    }

    document.addEventListener("keydown", handleKeyDown);
    window.requestAnimationFrame(() => panelRef.current?.focus());

    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [open, onClose]);

  if (!open || typeof document === "undefined") return null;

  function handleOverlayMouseDown(event) {
    if (closeOnOverlayClick && event.target === event.currentTarget) {
      onClose?.();
    }
  }

  return createPortal(
    <div className="cui-modal__overlay" onMouseDown={handleOverlayMouseDown}>
      <section
        ref={panelRef}
        className={cx("cui-modal", `cui-modal--${size}`, className)}
        role="dialog"
        aria-modal="true"
        aria-labelledby={title ? titleId : undefined}
        aria-describedby={description ? descriptionId : undefined}
        tabIndex={-1}
      >
        <header className="cui-modal__header">
          <div>
            {title ? (
              <h2 className="cui-modal__title" id={titleId}>
                {title}
              </h2>
            ) : null}
            {description ? (
              <p className="cui-modal__description" id={descriptionId}>
                {description}
              </p>
            ) : null}
          </div>
          {onClose ? (
            <Button variant="ghost" size="sm" iconOnly onClick={onClose} aria-label={closeLabel}>
              x
            </Button>
          ) : null}
        </header>
        <div className="cui-modal__body">{children}</div>
        {footer ? <footer className="cui-modal__footer">{footer}</footer> : null}
      </section>
    </div>,
    document.body,
  );
}
