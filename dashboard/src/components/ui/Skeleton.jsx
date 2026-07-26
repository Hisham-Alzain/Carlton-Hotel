import "../../styles/components.css";
import { cx } from "./utils";

export function Skeleton({
  className,
  width,
  height,
  radius,
  style,
  ...props
}) {
  return (
    <span
      className={cx("cui-skeleton", className)}
      style={{
        inlineSize: width,
        blockSize: height,
        borderRadius: radius,
        ...style,
      }}
      aria-hidden="true"
      {...props}
    />
  );
}

export function SkeletonText({ lines = 3, className }) {
  return (
    <div className={cx("cui-stack", className)} aria-hidden="true">
      {Array.from({ length: lines }).map((_, index) => (
        <Skeleton
          key={index}
          className="cui-skeleton--text"
          width={index === lines - 1 ? "72%" : "100%"}
        />
      ))}
    </div>
  );
}

export function TableSkeleton({ rows = 6, columns = 5 }) {
  return (
    <tbody aria-hidden="true">
      {Array.from({ length: rows }).map((_, rowIndex) => (
        <tr key={rowIndex}>
          {Array.from({ length: columns }).map((_, columnIndex) => (
            <td key={columnIndex}>
              <Skeleton height="0.85rem" width={columnIndex === 0 ? "8rem" : "100%"} />
            </td>
          ))}
        </tr>
      ))}
    </tbody>
  );
}
