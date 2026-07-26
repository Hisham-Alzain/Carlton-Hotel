import "../../styles/components.css";
import { cx } from "../ui";

export function PageHeader({
  eyebrow,
  title,
  description,
  meta,
  actions,
  breadcrumbs,
  className,
}) {
  return (
    <header className={cx("cui-page-header", className)}>
      <div>
        {breadcrumbs ? <div className="cui-cluster">{breadcrumbs}</div> : null}
        {eyebrow ? <p className="cui-page-header__eyebrow">{eyebrow}</p> : null}
        <h1 className="cui-page-header__title">{title}</h1>
        {description ? (
          <p className="cui-page-header__description">{description}</p>
        ) : null}
        {meta ? <div className="cui-cluster">{meta}</div> : null}
      </div>
      {actions ? <div className="cui-page-header__actions">{actions}</div> : null}
    </header>
  );
}
