import { useEffect, useMemo, useRef, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { ArrowLeft, Send, ShieldAlert, Sparkles, UserCheck } from "lucide-react";
import {
  Badge,
  Button,
  Card,
  ErrorState,
  Field,
  Input,
  PageHeader,
  Select,
  Skeleton,
  Textarea,
} from "../components/ui/Primitives.jsx";
import { useAuthStore } from "../store/authStore.js";
import { usePermissionStore } from "../store/permissionStore.js";
import { useTicketStore } from "../store/ticketStore.js";
import { PERMISSIONS } from "../utils/permissions.js";
import { formatDateTime, formatRelativeAge } from "../utils/date.js";

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

const RECOVERY_STAGE_VARIANT = {
  triage: "warning",
  active: "info",
  escalated: "danger",
  awaiting_guest: "warning",
  recovering: "success",
  closed: "neutral",
};

const SOURCE_LABEL = {
  whatsapp: "WhatsApp",
  email: "Email",
  front_desk: "Front Desk",
  phone: "Phone",
};

const ACTION_OPTIONS = [
  {
    value: "folio_credit",
    label: "Post folio credit",
    note: "Reverse the incorrect charge and confirm the corrected folio.",
    amount: "45",
  },
  {
    value: "courtesy_amenity",
    label: "Send courtesy amenity",
    note: "Dispatch fruit, water, and a handwritten apology card.",
  },
  {
    value: "apology_call",
    label: "Apology call",
    note: "Call the guest back and confirm the recovery plan personally.",
  },
  {
    value: "room_move",
    label: "Room move",
    note: "Offer a different room if the issue is tied to room condition or location.",
  },
  {
    value: "late_checkout",
    label: "Late checkout",
    note: "Extend departure time as a goodwill recovery gesture.",
  },
  {
    value: "engineering_dispatch",
    label: "Dispatch engineering",
    note: "Send engineering to verify the room and close any physical issue.",
  },
  {
    value: "transport_hold",
    label: "Confirm transport",
    note: "Lock the transfer and share the driver details with the guest.",
    amount: "35",
  },
  {
    value: "manager_visit",
    label: "Manager visit",
    note: "Ask the duty manager to visit the room or call directly.",
  },
];

const OWNER_OPTIONS = [
  { id: "front_desk_supervisor", name: "Omar Mansour", title: "Front Desk Supervisor", team: "Front Office" },
  { id: "duty_manager", name: "Nadia Hariri", title: "Duty Manager", team: "Executive Office" },
  { id: "concierge", name: "Omar Nasser", title: "Operations Concierge", team: "Guest Experience" },
  { id: "guest_relations", name: "Laila Khoury", title: "Guest Relations Manager", team: "Guest Relations" },
  { id: "engineering_supervisor", name: "Hassan Idris", title: "Engineering Supervisor", team: "Engineering" },
  { id: "housekeeping_lead", name: "Layth Saleh", title: "Housekeeping Lead", team: "Housekeeping" },
];

const REPLY_TEMPLATES = [
  {
    label: "Apology + action",
    body: "I have ownership of this now. We are correcting it and I will confirm once the folio is clean.",
  },
  {
    label: "Manager follow-up",
    body: "A duty manager is reviewing this with me now. I will call you back with a confirmed resolution.",
  },
  {
    label: "Close the loop",
    body: "The recovery action is complete and we are waiting on your confirmation before we close the case.",
  },
];

const fmt = (value = "") => String(value).replaceAll("_", " ");

const getOwnerOption = (value) =>
  OWNER_OPTIONS.find((option) => option.id === value || option.name === value || option.title === value) || null;

const getActionOption = (value) => ACTION_OPTIONS.find((option) => option.value === value) || ACTION_OPTIONS[0];

const getTimestamp = (entry) => entry?.at || entry?.sent_at || entry?.created_at || null;

const sortNewest = (items = []) =>
  [...items].sort((a, b) => new Date(getTimestamp(b) || 0).getTime() - new Date(getTimestamp(a) || 0).getTime());

const formatValue = (amount, currency = "") => {
  if (amount === null || amount === undefined || amount === "") return "—";
  const numeric = Number(amount);
  if (!Number.isFinite(numeric)) return "—";
  return `${numeric.toLocaleString("en-US")} ${currency || ""}`.trim();
};

function Metric({ label, value }) {
  return (
    <div
      style={{
        display: "flex",
        flexDirection: "column",
        gap: "0.25rem",
        padding: "0.625rem 0.75rem",
        borderRadius: "8px",
        background: "var(--color-bg)",
        border: "1px solid var(--color-border)",
      }}
    >
      <span style={{ fontSize: "0.6875rem", color: "var(--color-text-muted)", textTransform: "uppercase", letterSpacing: 0 }}>
        {label}
      </span>
      <strong style={{ fontSize: "0.9rem", color: "var(--color-text-strong)", fontWeight: 600 }}>
        {value || "—"}
      </strong>
    </div>
  );
}

function TimelineItem({ entry, locale }) {
  const variant = entry.kind === "escalation" ? "danger" : "info";
  return (
    <article
      style={{
        padding: "0.75rem 0.875rem",
        borderRadius: "8px",
        border: "1px solid var(--color-border)",
        background: "var(--color-bg)",
        display: "flex",
        flexDirection: "column",
        gap: "0.45rem",
      }}
    >
      <div style={{ display: "flex", justifyContent: "space-between", gap: "0.75rem", alignItems: "flex-start" }}>
        <Badge variant={variant}>{fmt(entry.kind)}</Badge>
        <span className="tkt-msg-time">{formatDateTime(getTimestamp(entry), { locale })}</span>
      </div>
      <strong style={{ color: "var(--color-text-strong)", fontSize: "0.875rem" }}>{entry.label}</strong>
      {entry.detail && (
        <p style={{ margin: 0, color: "var(--color-text-body)", fontSize: "0.875rem", lineHeight: 1.5 }}>
          {entry.detail}
        </p>
      )}
      {entry.actor && (
        <span style={{ color: "var(--color-text-muted)", fontSize: "0.75rem" }}>
          {entry.actor}
        </span>
      )}
    </article>
  );
}

function ActionItem({ action, locale }) {
  const hasValue = Number(action.amount) > 0;
  return (
    <article
      style={{
        padding: "0.75rem 0.875rem",
        borderRadius: "8px",
        border: "1px solid var(--color-border)",
        background: "var(--color-surface)",
        display: "flex",
        flexDirection: "column",
        gap: "0.45rem",
      }}
    >
      <div style={{ display: "flex", justifyContent: "space-between", gap: "0.75rem", alignItems: "flex-start" }}>
        <div style={{ display: "flex", flexDirection: "column", gap: "0.25rem" }}>
          <strong style={{ color: "var(--color-text-strong)", fontSize: "0.875rem" }}>{action.label}</strong>
          <span style={{ color: "var(--color-text-muted)", fontSize: "0.75rem" }}>{fmt(action.action_type)}</span>
        </div>
        <span className="tkt-msg-time">{formatDateTime(getTimestamp(action), { locale })}</span>
      </div>
      {action.detail && (
        <p style={{ margin: 0, color: "var(--color-text-body)", fontSize: "0.875rem", lineHeight: 1.5 }}>
          {action.detail}
        </p>
      )}
      <div style={{ display: "flex", flexWrap: "wrap", gap: "0.4rem" }}>
        <Badge variant="neutral">{action.actor || "Staff"}</Badge>
        {hasValue && <Badge variant="success">{formatValue(action.amount, action.currency)}</Badge>}
      </div>
    </article>
  );
}

function PlaybookStep({ step }) {
  const variant = step.status === "done" ? "success" : step.status === "active" ? "warning" : "neutral";
  return (
    <div
      style={{
        display: "flex",
        alignItems: "flex-start",
        justifyContent: "space-between",
        gap: "0.75rem",
        padding: "0.625rem 0.75rem",
        borderRadius: "8px",
        border: "1px solid var(--color-border)",
        background: "var(--color-bg)",
      }}
    >
      <div style={{ display: "flex", flexDirection: "column", gap: "0.25rem" }}>
        <strong style={{ color: "var(--color-text-strong)", fontSize: "0.875rem" }}>{step.label}</strong>
        {step.note && <span style={{ color: "var(--color-text-muted)", fontSize: "0.75rem", lineHeight: 1.4 }}>{step.note}</span>}
      </div>
      <Badge variant={variant}>{fmt(step.status)}</Badge>
    </div>
  );
}

const SupportTicketDetail = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const {
    selected,
    isLoadingDetail,
    error,
    fetchOne,
    updateStatus,
    sendReply,
    assignOwner,
    escalate,
    logRecoveryAction,
  } = useTicketStore();
  const locale = useAuthStore((s) => s.locale);
  const canManageTicket = usePermissionStore(
    (s) => s.can(PERMISSIONS.TICKETS_MANAGE) || s.can(PERMISSIONS.SUPPORT_TICKETS_MANAGE),
  );

  const [replyBody, setReplyBody] = useState("");
  const [ownerId, setOwnerId] = useState("");
  const [actionType, setActionType] = useState("folio_credit");
  const [actionAmount, setActionAmount] = useState("");
  const [actionNote, setActionNote] = useState("");
  const [escalationLevel, setEscalationLevel] = useState("2");
  const [escalationOwnerId, setEscalationOwnerId] = useState("");
  const [escalationNote, setEscalationNote] = useState("");
  const [isStatusSaving, setIsStatusSaving] = useState(false);
  const [isAssigning, setIsAssigning] = useState(false);
  const [isLoggingAction, setIsLoggingAction] = useState(false);
  const [isEscalating, setIsEscalating] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const threadRef = useRef(null);

  useEffect(() => {
    fetchOne(id).catch(() => {});
  }, [fetchOne, id]);

  useEffect(() => {
    if (threadRef.current) {
      threadRef.current.scrollTop = threadRef.current.scrollHeight;
    }
  }, [selected?.conversation?.length]);

  useEffect(() => {
    if (!selected) return;

    const recovery = selected.recovery || {};
    const ownerOption = getOwnerOption(recovery.owner?.id || recovery.owner?.name || selected.assigned_to);
    const escalationTargetOption = getOwnerOption(
      recovery.escalation_target?.id || recovery.escalation_target?.name || recovery.owner?.id || selected.assigned_to,
    );
    const actionOption = getActionOption(recovery.form_defaults?.action_type || recovery.actions?.[0]?.action_type || "folio_credit");

    setOwnerId(recovery.form_defaults?.owner_id || ownerOption?.id || "");
    setActionType(actionOption.value);
    setActionAmount(recovery.form_defaults?.amount ?? actionOption.amount ?? "");
    setActionNote(recovery.form_defaults?.note || recovery.next_step || actionOption.note || "");
    setEscalationLevel(String(recovery.form_defaults?.escalation_level || recovery.escalation_level || 1));
    setEscalationOwnerId(recovery.form_defaults?.escalation_owner_id || escalationTargetOption?.id || ownerOption?.id || "");
    setEscalationNote(recovery.objective || "");
    setReplyBody("");
  }, [selected?.id]);

  const recovery = selected?.recovery || {};
  const conversation = selected?.conversation || [];
  const recoveryActions = useMemo(() => sortNewest(recovery.actions || []), [recovery.actions]);
  const ownerTrail = useMemo(
    () => sortNewest((recovery.trail || []).filter((entry) => ["owner", "escalation"].includes(entry.kind))),
    [recovery.trail],
  );
  const playbook = useMemo(() => recovery.playbook || [], [recovery.playbook]);
  const guestPreferences = Array.isArray(selected?.guest_profile?.preferences) ? selected.guest_profile.preferences : [];
  const currentOwner = getOwnerOption(recovery.owner?.id || recovery.owner?.name || selected?.assigned_to);
  const currentEscalationTarget = getOwnerOption(
    recovery.escalation_target?.id || recovery.escalation_target?.name || escalationOwnerId,
  );
  const recoveryStage = recovery.stage || "triage";
  const inlineError = error && selected ? error : null;

  const handleStatusChange = async (event) => {
    const nextStatus = event.target.value;
    setIsStatusSaving(true);
    try {
      await updateStatus(id, nextStatus);
    } catch {
      // Store keeps the action error visible inline.
    } finally {
      setIsStatusSaving(false);
    }
  };

  const handleAssignOwner = async () => {
    if (!canManageTicket) return;
    const owner = getOwnerOption(ownerId);
    if (!owner) return;

    setIsAssigning(true);
    try {
      await assignOwner(id, owner);
    } catch {
      // Store keeps the action error visible inline.
    } finally {
      setIsAssigning(false);
    }
  };

  const handleRecoveryActionType = (event) => {
    const nextValue = event.target.value;
    const template = getActionOption(nextValue);
    setActionType(nextValue);
    setActionNote(template.note || "");
    setActionAmount(template.amount || "");
  };

  const handleLogRecoveryAction = async () => {
    if (!canManageTicket) return;

    const template = getActionOption(actionType);
    const amount = actionAmount === "" ? undefined : Number(actionAmount);

    if (actionAmount !== "" && !Number.isFinite(amount)) return;

    setIsLoggingAction(true);
    try {
      await logRecoveryAction(id, {
        action_type: template.value,
        label: template.label,
        note: actionNote.trim() || template.note || template.label,
        detail: actionNote.trim() || template.note || template.label,
        amount,
        currency: recovery.compensation_currency || "USD",
      });
      setActionAmount("");
      setActionNote("");
    } catch {
      // Store keeps the action error visible inline.
    } finally {
      setIsLoggingAction(false);
    }
  };

  const handleEscalate = async () => {
    if (!canManageTicket) return;
    const targetOwner = getOwnerOption(escalationOwnerId);
    if (!targetOwner) return;

    setIsEscalating(true);
    try {
      await escalate(id, {
        level: Number(escalationLevel || 0),
        target_owner: targetOwner,
        reason: escalationNote.trim() || recovery.objective || "Escalated from the recovery desk.",
        note: escalationNote.trim() || null,
      });
      setEscalationNote("");
    } catch {
      // Store keeps the action error visible inline.
    } finally {
      setIsEscalating(false);
    }
  };

  const handleSend = async () => {
    const trimmed = replyBody.trim();
    if (!trimmed) return;

    setIsSending(true);
    try {
      await sendReply(id, trimmed);
      setReplyBody("");
    } catch {
      // Store keeps the action error visible inline.
    } finally {
      setIsSending(false);
    }
  };

  if (isLoadingDetail || (!selected && !error)) return <Skeleton lines={10} />;

  if (error && !selected) {
    return (
      <ErrorState
        title="Could not load ticket"
        message={error.message}
        requestId={error.request_id}
      />
    );
  }

  if (!selected) return null;

  return (
    <div className="tkt-layout">
      <div className="tkt-page-top">
        <button type="button" className="tkt-back-link" onClick={() => navigate("/operations/tickets")}>
          <ArrowLeft size={15} />
          <span>Support Tickets</span>
        </button>
        <PageHeader
          title={selected.title}
          subtitle={`D3 recovery workspace · ${selected.reference} · ${SOURCE_LABEL[selected.source] || selected.source} · opened ${formatRelativeAge(selected.created_at, { locale })}`}
          actions={
            <div style={{ display: "flex", gap: "0.5rem", alignItems: "center", flexWrap: "wrap" }}>
              <Badge variant={PRIORITY_VARIANT[selected.priority] || "neutral"}>{selected.priority}</Badge>
              <Badge variant={STATUS_VARIANT[selected.status] || "neutral"}>{fmt(selected.status)}</Badge>
              <Badge variant={RECOVERY_STAGE_VARIANT[recoveryStage] || "neutral"}>{`Recovery ${fmt(recoveryStage)}`}</Badge>
            </div>
          }
        />
      </div>

      {inlineError && (
        <div
          style={{
            marginBlockEnd: "1rem",
            padding: "0.75rem 0.875rem",
            borderRadius: "8px",
            border: "1px solid rgba(180, 69, 69, 0.24)",
            background: "rgba(180, 69, 69, 0.08)",
            display: "flex",
            alignItems: "center",
            gap: "0.5rem",
          }}
        >
          <ShieldAlert size={16} />
          <div style={{ display: "flex", flexDirection: "column", gap: "0.15rem" }}>
            <strong style={{ color: "var(--color-text-strong)", fontSize: "0.875rem" }}>
              Recovery action failed
            </strong>
            <span style={{ color: "var(--color-text-body)", fontSize: "0.8125rem" }}>
              {inlineError.message}
              {inlineError.request_id ? ` · Request ID: ${inlineError.request_id}` : ""}
            </span>
          </div>
        </div>
      )}

      <div className="tkt-body">
        <aside className="tkt-meta-panel">
          <Card padded>
            <div className="tkt-status-row" style={{ display: "flex", flexDirection: "column", gap: "0.75rem" }}>
              <Field label="Status">
                <Select value={selected.status} onChange={handleStatusChange} disabled={isStatusSaving}>
                  <option value="open">Open</option>
                  <option value="in_progress">In progress</option>
                  <option value="waiting_guest">Waiting on guest</option>
                  <option value="resolved">Resolved</option>
                  <option value="closed">Closed</option>
                </Select>
              </Field>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: "0.625rem" }}>
                <Metric label="Owner" value={currentOwner ? `${currentOwner.name} · ${currentOwner.title}` : selected.assigned_to || "Unassigned"} />
                <Metric label="Escalation" value={recovery.escalation_level ? `Level ${recovery.escalation_level}` : "None"} />
                <Metric label="Actions" value={String(recoveryActions.length)} />
                <Metric label="Value recovered" value={formatValue(recovery.compensation_total, recovery.compensation_currency)} />
              </div>
            </div>

            <dl className="tkt-attrs">
              <div className="tkt-attr">
                <dt>Reference</dt>
                <dd className="ltr-value">{selected.reference}</dd>
              </div>
              <div className="tkt-attr">
                <dt>Guest</dt>
                <dd>{selected.guest_name}</dd>
              </div>
              {selected.room_number && (
                <div className="tkt-attr">
                  <dt>Room</dt>
                  <dd>{selected.room_number}</dd>
                </div>
              )}
              <div className="tkt-attr">
                <dt>Department</dt>
                <dd>{fmt(selected.department)}</dd>
              </div>
              <div className="tkt-attr">
                <dt>Source</dt>
                <dd>{SOURCE_LABEL[selected.source] || selected.source}</dd>
              </div>
              <div className="tkt-attr">
                <dt>Opened</dt>
                <dd>{formatDateTime(selected.created_at, { locale })}</dd>
              </div>
              {selected.due_at && (
                <div className="tkt-attr">
                  <dt>Due</dt>
                  <dd>{formatDateTime(selected.due_at, { locale })}</dd>
                </div>
              )}
            </dl>

            <div style={{ marginTop: "1rem", paddingTop: "1rem", borderTop: "1px solid var(--color-border)" }}>
              <p className="tkt-desc-label">Guest context</p>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: "0.625rem" }}>
                <Metric label="Tier" value={selected.guest_profile?.tier || "—"} />
                <Metric label="Nationality" value={selected.guest_profile?.nationality || "—"} />
                <Metric label="Language" value={selected.guest_profile?.language || "—"} />
                <Metric label="Preference" value={guestPreferences[0] || "—"} />
              </div>
            </div>

            <div className="tkt-description">
              <p className="tkt-desc-label">Recovery objective</p>
              <p>{recovery.objective || selected.description || "No recovery objective recorded."}</p>
            </div>
          </Card>

          <Card padded>
            <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", gap: "0.75rem", marginBottom: "0.875rem" }}>
              <div>
                <p className="tkt-desc-label">Recovery playbook</p>
                <h2 style={{ margin: 0, fontSize: "1rem", color: "var(--color-text-strong)" }}>5-star recovery path</h2>
              </div>
              <Badge variant={RECOVERY_STAGE_VARIANT[recoveryStage] || "neutral"}>{fmt(recoveryStage)}</Badge>
            </div>

            <div style={{ display: "flex", flexDirection: "column", gap: "0.625rem" }}>
              {playbook.length ? playbook.map((step) => <PlaybookStep key={step.id || step.label} step={step} />) : (
                <p className="tkt-thread-empty">No recovery playbook has been seeded for this case.</p>
              )}
            </div>

            {recovery.next_step && (
              <div style={{ marginTop: "1rem", paddingTop: "1rem", borderTop: "1px solid var(--color-border)" }}>
                <p className="tkt-desc-label">Next step</p>
                <p style={{ margin: 0, color: "var(--color-text-body)", lineHeight: 1.5 }}>{recovery.next_step}</p>
              </div>
            )}
          </Card>
        </aside>

        <div className="tkt-thread-panel" style={{ gap: "1rem" }}>
          <Card padded>
            <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", gap: "0.75rem", marginBottom: "0.875rem" }}>
              <div>
                <p className="tkt-desc-label">Recovery control desk</p>
                <h2 style={{ margin: 0, fontSize: "1rem", color: "var(--color-text-strong)" }}>Owner, recovery action, and escalation</h2>
              </div>
              <div style={{ display: "flex", flexWrap: "wrap", gap: "0.5rem", alignItems: "center" }}>
                <Badge variant={canManageTicket ? "success" : "neutral"}>{canManageTicket ? "Manageable" : "Read only"}</Badge>
                {currentEscalationTarget && <Badge variant="info">{currentEscalationTarget.name}</Badge>}
              </div>
            </div>

            {!canManageTicket && (
              <div style={{ marginBottom: "1rem", padding: "0.75rem 0.875rem", borderRadius: "8px", background: "var(--color-bg)", border: "1px solid var(--color-border)", display: "flex", alignItems: "center", gap: "0.5rem" }}>
                <ShieldAlert size={16} />
                <span style={{ color: "var(--color-text-body)", fontSize: "0.875rem" }}>
                  Recovery actions are read-only without the manage permission.
                </span>
              </div>
            )}

            <div style={{ display: "grid", gridTemplateColumns: "minmax(0, 1fr) auto", gap: "0.75rem", alignItems: "end" }}>
              <Field label="Current owner">
                <Select value={ownerId} onChange={(event) => setOwnerId(event.target.value)} disabled={!canManageTicket}>
                  <option value="">Select owner</option>
                  {OWNER_OPTIONS.map((owner) => (
                    <option key={owner.id} value={owner.id}>
                      {owner.name} · {owner.title}
                    </option>
                  ))}
                </Select>
              </Field>
              <Button
                type="button"
                variant="secondary"
                icon={UserCheck}
                isLoading={isAssigning}
                disabled={!canManageTicket || !ownerId || isAssigning}
                onClick={handleAssignOwner}
              >
                Reassign owner
              </Button>
            </div>

            <div style={{ display: "grid", gridTemplateColumns: "minmax(0, 1fr) minmax(0, 180px)", gap: "0.75rem", marginTop: "1rem" }}>
              <Field label="Recovery action">
                <Select value={actionType} onChange={handleRecoveryActionType} disabled={!canManageTicket}>
                  {ACTION_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field label="Value / credit">
                <Input
                  type="number"
                  min="0"
                  step="1"
                  value={actionAmount}
                  onChange={(event) => setActionAmount(event.target.value)}
                  placeholder="0"
                  disabled={!canManageTicket}
                />
              </Field>
            </div>

            <div style={{ marginTop: "0.75rem" }}>
              <Field label="Action note">
                <Textarea
                  rows={3}
                  value={actionNote}
                  onChange={(event) => setActionNote(event.target.value)}
                  disabled={!canManageTicket}
                  placeholder="Describe the recovery step, compensation, or follow-up."
                />
              </Field>
            </div>

            <div style={{ display: "flex", justifyContent: "flex-end", marginTop: "0.75rem" }}>
              <Button
                type="button"
                variant="primary"
                icon={Sparkles}
                isLoading={isLoggingAction}
                disabled={!canManageTicket || isLoggingAction}
                onClick={handleLogRecoveryAction}
              >
                Log recovery action
              </Button>
            </div>

            <div style={{ marginTop: "1rem", paddingTop: "1rem", borderTop: "1px solid var(--color-border)" }}>
              <div style={{ display: "grid", gridTemplateColumns: "minmax(0, 120px) minmax(0, 1fr)", gap: "0.75rem", alignItems: "end" }}>
                <Field label="Escalation level">
                  <Select value={escalationLevel} onChange={(event) => setEscalationLevel(event.target.value)} disabled={!canManageTicket}>
                    <option value="1">Level 1</option>
                    <option value="2">Level 2</option>
                    <option value="3">Level 3</option>
                  </Select>
                </Field>
                <Field label="Escalate to">
                  <Select value={escalationOwnerId} onChange={(event) => setEscalationOwnerId(event.target.value)} disabled={!canManageTicket}>
                    <option value="">Select target owner</option>
                    {OWNER_OPTIONS.map((owner) => (
                      <option key={owner.id} value={owner.id}>
                        {owner.name} · {owner.title}
                      </option>
                    ))}
                  </Select>
                </Field>
              </div>

              <div style={{ marginTop: "0.75rem" }}>
                <Field label="Escalation reason">
                  <Textarea
                    rows={3}
                    value={escalationNote}
                    onChange={(event) => setEscalationNote(event.target.value)}
                    disabled={!canManageTicket}
                    placeholder="Explain why the case needs a manager handoff."
                  />
                </Field>
              </div>

              <div style={{ display: "flex", justifyContent: "flex-end", marginTop: "0.75rem" }}>
                <Button
                  type="button"
                  variant="danger"
                  icon={ShieldAlert}
                  isLoading={isEscalating}
                  disabled={!canManageTicket || isEscalating || !escalationOwnerId}
                  onClick={handleEscalate}
                >
                  Escalate case
                </Button>
              </div>
            </div>
          </Card>

          <Card padded>
            <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", gap: "0.75rem", marginBottom: "0.875rem" }}>
              <div>
                <p className="tkt-desc-label">Recovery log</p>
                <h2 style={{ margin: 0, fontSize: "1rem", color: "var(--color-text-strong)" }}>Actions and handoff trail</h2>
              </div>
              <Badge variant="neutral">{recoveryActions.length + ownerTrail.length} entries</Badge>
            </div>

            <div style={{ display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: "1rem" }}>
              <div style={{ display: "flex", flexDirection: "column", gap: "0.625rem" }}>
                <p className="tkt-desc-label">Recovery actions</p>
                {recoveryActions.length ? (
                  recoveryActions.map((action) => <ActionItem key={action.id} action={action} locale={locale} />)
                ) : (
                  <p className="tkt-thread-empty">No recovery actions yet.</p>
                )}
              </div>

              <div style={{ display: "flex", flexDirection: "column", gap: "0.625rem" }}>
                <p className="tkt-desc-label">Owner / escalation trail</p>
                {ownerTrail.length ? (
                  ownerTrail.map((entry) => <TimelineItem key={entry.id} entry={entry} locale={locale} />)
                ) : (
                  <p className="tkt-thread-empty">No owner or escalation trail yet.</p>
                )}
              </div>
            </div>
          </Card>

          <Card padded>
            <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", gap: "0.75rem", marginBottom: "0.875rem" }}>
              <div>
                <p className="tkt-desc-label">Guest conversation</p>
                <h2 style={{ margin: 0, fontSize: "1rem", color: "var(--color-text-strong)" }}>Thread with recovery follow-up</h2>
              </div>
              <Badge variant="neutral">
                {conversation.length} message{conversation.length !== 1 ? "s" : ""}
              </Badge>
            </div>

            <div className="tkt-thread" ref={threadRef}>
              {conversation.length === 0 ? (
                <p className="tkt-thread-empty">No messages yet.</p>
              ) : (
                conversation.map((msg) => (
                  <div
                    key={msg.id}
                    className={`tkt-msg ${msg.author_type === "staff" ? "staff" : "guest"}`}
                  >
                    <div className="tkt-msg-header">
                      <span className="tkt-msg-author">{msg.author_name}</span>
                      <span className="tkt-msg-time">{formatDateTime(msg.sent_at, { locale })}</span>
                    </div>
                    <p className="tkt-msg-body">{msg.body}</p>
                  </div>
                ))
              )}
            </div>

            <div style={{ display: "flex", flexWrap: "wrap", gap: "0.5rem", marginBottom: "0.75rem" }}>
              {REPLY_TEMPLATES.map((template) => (
                <Button
                  key={template.label}
                  type="button"
                  variant="ghost"
                  onClick={() => setReplyBody(template.body)}
                  style={{ padding: "0.4rem 0.65rem" }}
                >
                  {template.label}
                </Button>
              ))}
            </div>

            <div className="tkt-reply-form">
              <Textarea
                rows={3}
                value={replyBody}
                onChange={(event) => setReplyBody(event.target.value)}
                placeholder="Write a reply…"
                onKeyDown={(event) => {
                  if (event.key === "Enter" && (event.ctrlKey || event.metaKey)) {
                    handleSend();
                  }
                }}
              />
              <div className="tkt-reply-actions">
                <span className="tkt-reply-hint">Ctrl+Enter to send</span>
                <Button
                  type="button"
                  icon={Send}
                  onClick={handleSend}
                  isLoading={isSending}
                  disabled={!replyBody.trim() || isSending}
                >
                  Send reply
                </Button>
              </div>
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
};

export default SupportTicketDetail;
