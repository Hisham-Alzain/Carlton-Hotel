import "../../styles/components.css";
import { Badge, Button } from "../ui";

function initials(user) {
  const source = user?.name || user?.email || "C";
  return source
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("")
    .toUpperCase();
}

export function Header({
  propertyName = "Carlton Hotel",
  businessDate,
  locale = "en",
  onLocaleChange,
  user,
  actions,
  notificationsCount,
  onMenuClick,
}) {
  const nextLocale = locale === "ar" ? "en" : "ar";

  return (
    <header className="c-header">
      <div className="c-header__menu">
        <Button variant="ghost" size="sm" iconOnly onClick={onMenuClick} aria-label="Open navigation">
          =
        </Button>
      </div>

      <div>
        <p className="c-header__title">{propertyName}</p>
        {businessDate ? <p className="c-header__meta">{businessDate}</p> : null}
      </div>

      <div className="c-header__actions">
        {actions}
        {notificationsCount ? (
          <Badge variant="gold" dot>
            {notificationsCount}
          </Badge>
        ) : null}
        {onLocaleChange ? (
          <Button variant="secondary" size="sm" onClick={() => onLocaleChange(nextLocale)}>
            {locale === "ar" ? "EN" : "AR"}
          </Button>
        ) : null}
        {user ? (
          <div className="c-header__user">
            <span className="c-header__avatar" aria-hidden="true">
              {initials(user)}
            </span>
            <span>{user.name || user.email}</span>
          </div>
        ) : null}
      </div>
    </header>
  );
}
