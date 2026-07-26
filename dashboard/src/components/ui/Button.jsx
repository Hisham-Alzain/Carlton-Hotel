import { forwardRef } from "react";
import "../../styles/components.css";
import { cx } from "./utils";

export const Button = forwardRef(function Button(
  {
    as: Component = "button",
    variant = "primary",
    size = "md",
    fullWidth = false,
    iconOnly = false,
    loading = false,
    disabled = false,
    leftIcon,
    rightIcon,
    className,
    children,
    type,
    tabIndex,
    onClick,
    ...props
  },
  ref,
) {
  const isButton = Component === "button";
  const isDisabled = disabled || loading;

  function handleClick(event) {
    if (isDisabled) {
      event.preventDefault();
      return;
    }

    onClick?.(event);
  }

  return (
    <Component
      {...props}
      ref={ref}
      className={cx(
        "cui-button",
        `cui-button--${variant}`,
        `cui-button--${size}`,
        fullWidth && "cui-button--full",
        iconOnly && "cui-button--icon",
        className,
      )}
      disabled={isButton ? isDisabled : undefined}
      aria-disabled={!isButton && isDisabled ? true : undefined}
      aria-busy={loading ? true : undefined}
      tabIndex={!isButton && isDisabled ? -1 : tabIndex}
      type={isButton ? type ?? "button" : undefined}
      onClick={handleClick}
    >
      {loading ? <span className="cui-button__spinner" aria-hidden="true" /> : leftIcon}
      {children ? <span className="cui-button__label">{children}</span> : null}
      {!loading ? rightIcon : null}
    </Component>
  );
});
