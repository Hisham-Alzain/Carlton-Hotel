import { forwardRef } from "react";
import "../../styles/components.css";
import { cx } from "./utils";

export const Card = forwardRef(function Card(
  { className, raised = false, children, ...props },
  ref,
) {
  return (
    <section
      ref={ref}
      className={cx("cui-card", raised && "cui-card--raised", className)}
      {...props}
    >
      {children}
    </section>
  );
});

export function CardHeader({ className, children, ...props }) {
  return (
    <div className={cx("cui-card__header", className)} {...props}>
      {children}
    </div>
  );
}

export function CardTitle({ as: Component = "h2", className, children, ...props }) {
  return (
    <Component className={cx("cui-card__title", className)} {...props}>
      {children}
    </Component>
  );
}

export function CardDescription({ className, children, ...props }) {
  return (
    <p className={cx("cui-card__description", className)} {...props}>
      {children}
    </p>
  );
}

export function CardContent({ className, children, ...props }) {
  return (
    <div className={cx("cui-card__content", className)} {...props}>
      {children}
    </div>
  );
}

export function CardFooter({ className, children, ...props }) {
  return (
    <div className={cx("cui-card__footer", className)} {...props}>
      {children}
    </div>
  );
}
