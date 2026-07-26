import { useEffect, useMemo, useState } from 'react';
import { UserCheck } from 'lucide-react';
import { Badge, Button, PageHeader, Select } from '../components/ui/Primitives.jsx';
import { useQueueStore } from '../store/queueStore.js';
import { usePermissionStore } from '../store/permissionStore.js';
import { minutesAgo } from '../utils/date.js';

const COLUMNS = [
  ['open', 'Open'],
  ['in_progress', 'In progress'],
  ['waiting_guest', 'Waiting guest'],
];

const ASSIGNABLE_STATUSES = new Set(['open', 'in_progress']);
const ESCALATION_THRESHOLD = 120;

const formatLabel = (value) => value.replaceAll('_', ' ');

const ageClass = (createdAt) => {
  const age = minutesAgo(createdAt);
  if (age >= ESCALATION_THRESHOLD * 2) return 'age-critical';
  if (age >= ESCALATION_THRESHOLD) return 'age-warn';
  return '';
};

const sortByName = (a, b) => a.name.localeCompare(b.name);

const getStaffBuckets = (staff, department) => {
  const preferred = [];
  const others = [];

  staff.forEach((person) => {
    if (person.department === department) {
      preferred.push(person);
      return;
    }

    others.push(person);
  });

  return {
    preferred: preferred.sort(sortByName),
    others: others.sort(sortByName),
  };
};

const AgeStamp = ({ createdAt }) => {
  const age = minutesAgo(createdAt);
  const hours = Math.floor(age / 60);
  const mins = age % 60;
  const label = hours > 0 ? `${hours}h ${mins}m` : `${age}m`;
  return <span className={`queue-age ${ageClass(createdAt)}`}>{label}</span>;
};

const assignmentLabelStyle = {
  display: 'grid',
  gap: '6px',
  color: 'var(--color-text-muted)',
  fontSize: '11px',
  fontWeight: 750,
  letterSpacing: '0.08em',
  textTransform: 'uppercase',
};

const assignmentRowStyle = {
  display: 'grid',
  gridTemplateColumns: 'minmax(0, 1fr) auto',
  gap: '8px',
  alignItems: 'end',
};

const assignmentButtonStyle = {
  minHeight: '34px',
  minWidth: '92px',
  paddingInline: '12px',
  borderRadius: '8px',
  background: 'var(--color-teal)',
  border: '1px solid var(--color-teal)',
  boxShadow: 'none',
  color: '#fff',
  fontSize: '12px',
  textDecoration: 'none',
  whiteSpace: 'nowrap',
};

const actionErrorStyle = {
  margin: '2px 0 0',
  color: 'var(--color-danger)',
  fontSize: '11px',
  lineHeight: 1.35,
};

const assignedLineStyle = {
  display: 'flex',
  alignItems: 'center',
  gap: '4px',
  margin: '4px 0 0',
  color: 'var(--color-text-muted)',
  fontSize: '11px',
};

const actionStripStyle = {
  display: 'flex',
  gap: '6px',
  flexWrap: 'wrap',
};

const statusActionStyle = {
  whiteSpace: 'nowrap',
};

const OperationsQueue = () => {
  const {
    items,
    assignableStaff,
    assignableStaffLoading,
    connected,
    connect,
    disconnect,
    updateStatus,
    claim,
    assign,
    fetchAssignableStaff,
  } = useQueueStore();
  const canManageQueue = usePermissionStore((s) => s.can('operations_queue.manage'));
  const canAssignQueue = usePermissionStore((s) => s.can('operations_queue.assign'));
  const [dept, setDept] = useState('all');
  const [draftAssignees, setDraftAssignees] = useState({});
  const [pendingAction, setPendingAction] = useState(null);
  const [actionErrors, setActionErrors] = useState({});

  useEffect(() => {
    connect();
    return disconnect;
  }, [connect, disconnect]);

  useEffect(() => {
    if (canAssignQueue) {
      void fetchAssignableStaff().catch(() => {});
    }
  }, [canAssignQueue, fetchAssignableStaff]);

  const departments = useMemo(() => {
    const seen = new Set();
    items.forEach((item) => { if (item.department) seen.add(item.department); });
    return ['all', ...Array.from(seen).sort()];
  }, [items]);

  const filtered = useMemo(() => (
    dept === 'all' ? items : items.filter((item) => item.department === dept)
  ), [items, dept]);

  const grouped = useMemo(() => (
    Object.fromEntries(COLUMNS.map(([status]) => [
      status,
      filtered
        .filter((item) => item.status === status)
        .sort((a, b) => minutesAgo(b.created_at) - minutesAgo(a.created_at)),
    ]))
  ), [filtered]);

  const clearActionError = (itemId) => {
    setActionErrors((current) => {
      if (!current[itemId]) return current;
      const next = { ...current };
      delete next[itemId];
      return next;
    });
  };

  const setDraftAssignee = (itemId, value) => {
    setDraftAssignees((current) => ({
      ...current,
      [itemId]: value,
    }));
    clearActionError(itemId);
  };

  const runCardAction = async (itemId, kind, action) => {
    setPendingAction({ itemId, kind });
    clearActionError(itemId);

    try {
      return await action();
    } catch (error) {
      setActionErrors((current) => ({
        ...current,
        [itemId]: error?.payload?.message || error?.message || 'Action failed.',
      }));
      return null;
    } finally {
      setPendingAction(null);
    }
  };

  const handleStatusChange = (item, nextStatus) => runCardAction(item.id, 'status', () => updateStatus(item.id, nextStatus));

  const handleClaim = (item) => runCardAction(item.id, 'claim', () => claim(item.id));

  const handleAssign = async (item) => {
    const assignee = draftAssignees[item.id] ?? item.assigned_to ?? '';

    if (!assignee || assignee === item.assigned_to) {
      return;
    }

    const result = await runCardAction(item.id, 'assign', () => assign(item.id, assignee));

    if (result) {
      setDraftAssignees((current) => {
        if (!(item.id in current)) return current;
        const next = { ...current };
        delete next[item.id];
        return next;
      });
    }
  };

  return (
    <>
      <PageHeader
        title="Live operations queue"
        subtitle="Service requests and guest tickets across all departments."
        actions={<Badge variant={connected ? 'success' : 'warning'}>{connected ? 'Live mock connected' : 'Disconnected'}</Badge>}
      />

      <div className="queue-toolbar">
        <div className="queue-filter-row">
          <Select
            value={dept}
            onChange={(e) => setDept(e.target.value)}
            aria-label="Filter by department"
          >
            {departments.map((d) => (
              <option key={d} value={d}>{d === 'all' ? 'All departments' : d}</option>
            ))}
          </Select>
          {dept !== 'all' && (
            <button type="button" className="queue-filter-clear" onClick={() => setDept('all')}>
              Clear filter
            </button>
          )}
        </div>
        <div className="queue-legend">
          <span className="queue-legend-item"><span className="queue-legend-dot age-warn" />Over {ESCALATION_THRESHOLD}m</span>
          <span className="queue-legend-item"><span className="queue-legend-dot age-critical" />Over {ESCALATION_THRESHOLD * 2}m</span>
        </div>
      </div>

      <div className="queue-grid">
        {COLUMNS.map(([status, label]) => (
          <section className="queue-column" key={status}>
            <h2>{label} · {grouped[status]?.length || 0}</h2>
            {!grouped[status]?.length && (
              <div className="queue-empty">
                {dept !== 'all' ? `No ${dept} work in this state.` : 'No guest work is waiting in this state.'}
              </div>
            )}
            {grouped[status]?.map((item) => {
              const age = minutesAgo(item.created_at);
              const cardClass = age >= ESCALATION_THRESHOLD * 2
                ? 'queue-card is-critical'
                : age >= ESCALATION_THRESHOLD
                  ? 'queue-card is-escalated'
                  : 'queue-card';
              const currentAssignee = draftAssignees[item.id] ?? item.assigned_to ?? '';
              const canEditAssignment = canAssignQueue && ASSIGNABLE_STATUSES.has(item.status);
              const isActionPending = pendingAction?.itemId === item.id;
              const isStatusPending = isActionPending && pendingAction.kind === 'status';
              const isClaimPending = isActionPending && pendingAction.kind === 'claim';
              const isAssignPending = isActionPending && pendingAction.kind === 'assign';
              const { preferred, others } = getStaffBuckets(assignableStaff, item.department);
              const rosterNames = new Set(assignableStaff.map((person) => person.name));
              const showCurrentAssigneeOption = Boolean(currentAssignee) && !rosterNames.has(currentAssignee);

              return (
                <article className={cardClass} key={item.id}>
                  <div className="queue-card-top">
                    <Badge variant={item.type === 'support_ticket' ? 'warning' : 'info'}>
                      {formatLabel(item.type)}
                    </Badge>
                    <AgeStamp createdAt={item.created_at} />
                  </div>
                  <h3>{item.title}</h3>
                  <p className="queue-card-meta">
                    {item.guest_name}
                    {item.room_number ? <> · <strong>Room {item.room_number}</strong></> : ''}
                    {item.department ? <> · <span className="queue-dept">{formatLabel(item.department)}</span></> : ''}
                  </p>
                  <p style={assignedLineStyle}>
                    <UserCheck size={11} />
                    <span>{item.assigned_to || 'Unassigned'}</span>
                  </p>
                  {canEditAssignment && (
                    <div style={{ display: 'grid', gap: '6px' }}>
                      <label style={assignmentLabelStyle}>
                        Assign to
                        <div style={assignmentRowStyle}>
                          <Select
                            value={currentAssignee}
                            onChange={(event) => setDraftAssignee(item.id, event.target.value)}
                            disabled={assignableStaffLoading || isAssignPending}
                            aria-label={`Assign ${item.reference}`}
                          >
                            <option value="">
                              {assignableStaffLoading ? 'Loading staff roster...' : 'Choose a staff member'}
                            </option>
                            {showCurrentAssigneeOption && (
                              <option value={currentAssignee}>
                                Current assignee: {currentAssignee}
                              </option>
                            )}
                            {preferred.length > 0 && (
                              <optgroup label={`Best match for ${formatLabel(item.department || 'operations')}`}>
                                {preferred.map((person) => (
                                  <option key={person.id} value={person.name}>
                                    {person.name} · {person.title}
                                  </option>
                                ))}
                              </optgroup>
                            )}
                            {others.length > 0 && (
                              <optgroup label="Other staff">
                                {others.map((person) => (
                                  <option key={person.id} value={person.name}>
                                    {person.name} · {person.title}
                                  </option>
                                ))}
                              </optgroup>
                            )}
                          </Select>
                          <Button
                            variant="secondary"
                            onClick={() => handleAssign(item)}
                            isLoading={isAssignPending}
                            disabled={isAssignPending || !currentAssignee || currentAssignee === item.assigned_to}
                            style={assignmentButtonStyle}
                          >
                            Assign
                          </Button>
                        </div>
                      </label>
                      {actionErrors[item.id] && <p style={actionErrorStyle}>{actionErrors[item.id]}</p>}
                    </div>
                  )}
                  <div style={actionStripStyle}>
                    {canManageQueue && status !== 'waiting_guest' && (
                      <Button
                        variant="secondary"
                        onClick={() => handleStatusChange(item, status === 'open' ? 'in_progress' : 'waiting_guest')}
                        isLoading={isStatusPending}
                        disabled={isStatusPending}
                        style={statusActionStyle}
                      >
                        {status === 'open' ? 'Begin service' : 'Await guest'}
                      </Button>
                    )}
                    {canManageQueue && status === 'open' && !item.assigned_to && (
                      <Button
                        variant="ghost"
                        onClick={() => handleClaim(item)}
                        isLoading={isClaimPending}
                        disabled={isClaimPending}
                        style={statusActionStyle}
                      >
                        Claim
                      </Button>
                    )}
                  </div>
                </article>
              );
            })}
          </section>
        ))}
      </div>
    </>
  );
};

export default OperationsQueue;
