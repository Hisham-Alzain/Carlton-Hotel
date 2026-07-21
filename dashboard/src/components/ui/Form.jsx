import {
  cloneElement,
  forwardRef,
  isValidElement,
  useId,
} from "react";
import "../../styles/components.css";
import { cx } from "./utils";

export function Field({
  id,
  label,
  hint,
  error,
  required = false,
  className,
  children,
}) {
  const generatedId = useId();
  const controlId = id || generatedId;
  const hintId = hint ? `${controlId}-hint` : undefined;
  const errorId = error ? `${controlId}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

  const control = isValidElement(children)
    ? cloneElement(children, {
        id: children.props.id || controlId,
        "aria-describedby": [children.props["aria-describedby"], describedBy]
          .filter(Boolean)
          .join(" ") || undefined,
        "aria-invalid": error ? true : children.props["aria-invalid"],
      })
    : children;

  return (
    <div className={cx("cui-field", className)}>
      {label ? (
        <label className="cui-field__label" htmlFor={controlId}>
          {label}
          {required ? " *" : null}
        </label>
      ) : null}
      {control}
      {hint ? (
        <p className="cui-field__hint" id={hintId}>
          {hint}
        </p>
      ) : null}
      {error ? (
        <p className="cui-field__error" id={errorId} role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
}

export const Input = forwardRef(function Input(
  { className, error, type = "text", ...props },
  ref,
) {
  return (
    <input
      ref={ref}
      type={type}
      className={cx("cui-control", className)}
      {...props}
      aria-invalid={error ? true : props["aria-invalid"]}
    />
  );
});

export const Select = forwardRef(function Select(
  { className, error, options, placeholder, children, ...props },
  ref,
) {
  return (
    <select
      ref={ref}
      className={cx("cui-control", "cui-select", className)}
      {...props}
      aria-invalid={error ? true : props["aria-invalid"]}
    >
      {placeholder ? (
        <option value="" disabled>
          {placeholder}
        </option>
      ) : null}
      {options
        ? options.map((option) => {
            const value = typeof option === "string" ? option : option.value;
            const label = typeof option === "string" ? option : option.label;
            return (
              <option key={value} value={value} disabled={option.disabled}>
                {label}
              </option>
            );
          })
        : children}
    </select>
  );
});

export const Textarea = forwardRef(function Textarea(
  { className, error, ...props },
  ref,
) {
  return (
    <textarea
      ref={ref}
      className={cx("cui-control", "cui-textarea", className)}
      {...props}
      aria-invalid={error ? true : props["aria-invalid"]}
    />
  );
});
