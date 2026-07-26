import { Fragment } from "react";
import "../../styles/components.css";
import { Badge, cx } from "../ui";

function normalizeGroups(navItems) {
  if (!navItems?.length) return [];
  return navItems[0]?.items ? navItems : [{ label: null, items: navItems }];
}

function isItemActive(item, activePath) {
  if (!activePath || !item.href) return false;
  if (item.href === activePath) return true;
  return item.href !== "/" && activePath.startsWith(`${item.href}/`);
}

export function Sidebar({
  brand = { name: "Carlton", meta: "Hotel Operations", mark: "C" },
  navItems = [],
  activePath,
  onNavigate,
  renderNavLink,
  footer,
  className,
}) {
  const groups = normalizeGroups(navItems);

  return (
    <nav className={cx("c-sidebar", className)} aria-label="Primary navigation">
      <div className="c-sidebar__brand">
        <div className="c-sidebar__mark" aria-hidden="true">
          {brand.mark || "C"}
        </div>
        <div>
          <p className="c-sidebar__name">{brand.name || "Carlton"}</p>
          {brand.meta ? <p className="c-sidebar__meta">{brand.meta}</p> : null}
        </div>
      </div>

      <div className="c-sidebar__nav">
        {groups.map((group, groupIndex) => (
          <div key={group.label || groupIndex}>
            {group.label ? (
              <div className="c-sidebar__group-label">{group.label}</div>
            ) : null}
            {group.items.map((item) => {
              const active = isItemActive(item, activePath);
              const itemKey = item.key || item.href || item.label;
              const content = (
                <>
                  <span className="cui-cluster">
                    {item.icon ? <span aria-hidden="true">{item.icon}</span> : null}
                    <span>{item.label}</span>
                  </span>
                  {item.badge ? <Badge variant={item.badgeVariant || "gold"}>{item.badge}</Badge> : null}
                </>
              );

              if (renderNavLink) {
                return (
                  <Fragment key={itemKey}>
                    {renderNavLink({
                      item,
                      active,
                      className: "c-sidebar__link",
                      children: content,
                    })}
                  </Fragment>
                );
              }

              return (
                <a
                  key={itemKey}
                  className="c-sidebar__link"
                  href={item.href || "#"}
                  aria-current={active ? "page" : undefined}
                  aria-disabled={item.disabled ? true : undefined}
                  onClick={(event) => onNavigate?.(item, event)}
                >
                  {content}
                </a>
              );
            })}
          </div>
        ))}
      </div>

      {footer ? <div className="c-sidebar__footer">{footer}</div> : null}
    </nav>
  );
}
