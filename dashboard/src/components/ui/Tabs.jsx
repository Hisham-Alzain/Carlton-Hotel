import { useId, useState } from "react";
import "../../styles/components.css";
import { cx } from "./utils";

export function Tabs({
  items,
  value,
  defaultValue,
  onValueChange,
  className,
  children,
}) {
  const generatedId = useId();
  const [internalValue, setInternalValue] = useState(
    defaultValue || items?.[0]?.value,
  );
  const selectedValue = value ?? internalValue;

  function select(nextValue) {
    if (value === undefined) setInternalValue(nextValue);
    onValueChange?.(nextValue);
  }

  if (!items) {
    return <div className={cx("cui-tabs", className)}>{children}</div>;
  }

  return (
    <div className={cx("cui-tabs", className)}>
      <div className="cui-tabs__list" role="tablist">
        {items.map((item) => {
          const selected = item.value === selectedValue;
          return (
            <button
              key={item.value}
              id={`${generatedId}-${item.value}-tab`}
              className="cui-tabs__trigger"
              type="button"
              role="tab"
              aria-selected={selected}
              aria-controls={`${generatedId}-${item.value}-panel`}
              disabled={item.disabled}
              onClick={() => select(item.value)}
            >
              {item.label}
            </button>
          );
        })}
      </div>
      {items.map((item) => {
        const selected = item.value === selectedValue;
        return (
          <div
            key={item.value}
            id={`${generatedId}-${item.value}-panel`}
            role="tabpanel"
            aria-labelledby={`${generatedId}-${item.value}-tab`}
            hidden={!selected}
          >
            {selected ? item.children : null}
          </div>
        );
      })}
    </div>
  );
}
