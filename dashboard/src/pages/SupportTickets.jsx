import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Badge, Card, EmptyState, ErrorState, Field, Input, PageHeader, Select, Skeleton, Table } from "../components/ui/Primitives.jsx";
import { useTicketStore } from "../store/ticketStore.js";
import { useAuthStore } from "../store/authStore.js";
import { pickLocalized } from "../utils/i18n.js";
import { formatRelativeAge } from "../utils/date.js";

const PRIORITY_VARIANT = {
  critical: "danger",
  urgent: "warning",
  high: "info",
  normal: "neutral",
  low: "neutral",
};

const STATUS_VARIANT = {
  open: "danger",
  in_progress: "warning",
  waiting_guest: "info",
  resolved: "success",
  closed: "neutral",
};

const fmt = (s) => s.replaceAll("_", " ");

const SupportTickets = () => {
  const navigate = useNavigate();
  const { tickets, meta, isLoading, error, fetchAll } = useTicketStore();
  const locale = useAuthStore((s) => s.locale);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");

  useEffect(() => {
    const timer = setTimeout(
      () => fetchAll({ query: search, status: status === "all" ? undefined : status }),
      220,
    );
    return () => clearTimeout(timer);
  }, [fetchAll, search, status]);

  const columns = [
    {
      key: "priority",
      label: "Priority",
      render: (row) => (
        <Badge variant={PRIORITY_VARIANT[row.priority] || "neutral"}>{row.priority}</Badge>
      ),
    },
    {
      key: "age",
      label: "Age",
      render: (row) => formatRelativeAge(row.created_at, locale),
    },
    { key: "reference", label: "Reference" },
    { key: "title", label: "Title" },
    { key: "guest_name", label: "Guest" },
    {
      key: "room_number",
      label: "Room",
      render: (row) => row.room_number || "—",
    },
    {
      key: "status",
      label: "Status",
      render: (row) => (
        <Badge variant={STATUS_VARIANT[row.status] || "neutral"}>{fmt(row.status)}</Badge>
      ),
    },
    { key: "department", label: "Department" },
  ];

  if (isLoading) return <Skeleton lines={6} />;

  if (error) {
    return (
      <ErrorState
        title="Could not load tickets"
        message={error.message}
        requestId={error.request_id}
      />
    );
  }

  return (
    <>
      <PageHeader
        title={t("Support Tickets", "تذاكر الدعم")}
        subtitle={t(
          "Guest inquiries and support requests from all channels.",
          "استفسارات الضيوف وطلبات الدعم من جميع القنوات."
        )}
        actions={meta && <Badge variant="neutral">{meta.total} {meta.total !== 1 ? t("tickets", "تذاكر") : t("ticket", "تذكرة")}</Badge>}
      />
      <Card className="data-card">
        <div className="toolbar">
          <div className="field-row">
            <Field label="Search">
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Reference, guest, or title"
              />
            </Field>
            <Field label="Status">
              <Select value={status} onChange={(e) => setStatus(e.target.value)}>
                <option value="all">{t("All statuses", "جميع الحالات")}</option>
                <option value="open">{t("Open", "مفتوح")}</option>
                <option value="in_progress">{t("In progress", "قيد التنفيذ")}</option>
                <option value="waiting_guest">{t("Waiting on guest", "انتظار الضيف")}</option>
                <option value="resolved">{t("Resolved", "تم الحل")}</option>
                <option value="closed">{t("Closed", "مغلق")}</option>
              </Select>
            </Field>
          </div>
          <span className="table-note">Support tickets · all channels</span>
        </div>
        <Table
          columns={columns}
          rows={tickets}
          onRowClick={(row) => navigate(`/operations/tickets/${row.id}`)}
          empty={
            <EmptyState
              title="No tickets found"
              message="Try adjusting your search or status filter."
            />
          }
        />
      </Card>
    </>
  );
};

export default SupportTickets;
