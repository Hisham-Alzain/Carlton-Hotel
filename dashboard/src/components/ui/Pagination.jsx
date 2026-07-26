import "../../styles/components.css";
import { Button } from "./Button";

export function Pagination({
  page = 1,
  pageSize = 10,
  total = 0,
  totalPages,
  onPageChange,
  labels = {},
}) {
  const resolvedTotalPages = totalPages || Math.max(1, Math.ceil(total / pageSize));
  const currentPage = Math.min(Math.max(page, 1), resolvedTotalPages);
  const firstItem = total === 0 ? 0 : (currentPage - 1) * pageSize + 1;
  const lastItem = Math.min(total, currentPage * pageSize);

  return (
    <nav className="cui-pagination" aria-label={labels.ariaLabel || "Pagination"}>
      <span>
        {labels.summary
          ? labels.summary({ firstItem, lastItem, total, page: currentPage, totalPages: resolvedTotalPages })
          : `${firstItem}-${lastItem} of ${total}`}
      </span>
      <div className="cui-pagination__controls">
        <Button
          variant="secondary"
          size="sm"
          disabled={currentPage <= 1}
          onClick={() => onPageChange?.(currentPage - 1)}
        >
          {labels.previous || "Previous"}
        </Button>
        <span>
          {labels.page
            ? labels.page({ page: currentPage, totalPages: resolvedTotalPages })
            : `Page ${currentPage} of ${resolvedTotalPages}`}
        </span>
        <Button
          variant="secondary"
          size="sm"
          disabled={currentPage >= resolvedTotalPages}
          onClick={() => onPageChange?.(currentPage + 1)}
        >
          {labels.next || "Next"}
        </Button>
      </div>
    </nav>
  );
}
