import { useEffect, useState } from 'react';
import { BedDouble, CheckSquare, ClipboardList, UserCheck } from 'lucide-react';
import { Badge, Button, Card, Field, Input, PageHeader, Select, Skeleton, ErrorState, EmptyState } from '../components/ui/Primitives.jsx';
import { useHousekeepingStore } from '../store/housekeepingStore.js';
import { useAuthStore } from '../store/authStore.js';
import { pickLocalized } from '../utils/i18n.js';
import { formatDate } from '../utils/date.js';

const STATUS_META = {
  pending:     { label: { en: 'Pending',      ar: 'في الانتظار' }, variant: 'neutral' },
  in_progress: { label: { en: 'In progress',  ar: 'جارٍ' },        variant: 'info' },
  done:        { label: { en: 'Done',          ar: 'تم' },           variant: 'success' },
  inspected:   { label: { en: 'Inspected',     ar: 'تم التفتيش' },   variant: 'neutral' },
};

const TASK_TYPE_LABELS = {
  checkout_clean: { en: 'Checkout clean', ar: 'تنظيف ما بعد المغادرة' },
  stayover:       { en: 'Stayover',       ar: 'إقامة مستمرة' },
  deep_clean:     { en: 'Deep clean',     ar: 'تنظيف عميق' },
  inspection:     { en: 'Inspection',     ar: 'تفتيش' },
};

export default function HousekeepingBoard() {
  const {
    tasks,
    meta,
    isLoading,
    error,
    floorFilter,
    statusFilter,
    fetchTasks,
    assignTask,
    updateStatus,
    setFloorFilter,
    setStatusFilter,
  } = useHousekeepingStore();

  const locale = useAuthStore((s) => s.locale);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);

  const [assigningId, setAssigningId] = useState(null);
  const [attendantInput, setAttendantInput] = useState('');

  useEffect(() => {
    fetchTasks();
  }, [fetchTasks, floorFilter, statusFilter]);

  if (isLoading) return <Skeleton lines={6} />;

  if (error) {
    return (
      <ErrorState
        title={t('Failed to load housekeeping tasks', 'تعذّر تحميل مهام التدبير المنزلي')}
        message={error.message}
        requestId={error.request_id}
      />
    );
  }

  const pendingCount     = tasks.filter((tk) => tk.status === 'pending').length;
  const inProgressCount  = tasks.filter((tk) => tk.status === 'in_progress').length;
  const doneCount        = tasks.filter((tk) => tk.status === 'done' || tk.status === 'inspected').length;

  return (
    <div className="hk-board">
      <PageHeader
        title={t('Housekeeping Board', 'لوحة التدبير المنزلي')}
        icon={<BedDouble size={20} />}
      />

      <div className="hk-stats-strip">
        <div className="hk-stat">
          <ClipboardList size={16} />
          <span className="hk-stat-value">{tasks.length}</span>
          <span className="hk-stat-label">{t('Total', 'الإجمالي')}</span>
        </div>
        <div className="hk-stat">
          <BedDouble size={16} />
          <span className="hk-stat-value">{pendingCount}</span>
          <span className="hk-stat-label">{t('Pending', 'في الانتظار')}</span>
        </div>
        <div className="hk-stat">
          <CheckSquare size={16} />
          <span className="hk-stat-value">{inProgressCount}</span>
          <span className="hk-stat-label">{t('In Progress', 'جارٍ')}</span>
        </div>
        <div className="hk-stat">
          <UserCheck size={16} />
          <span className="hk-stat-value">{doneCount}</span>
          <span className="hk-stat-label">{t('Done / Inspected', 'تم / تم التفتيش')}</span>
        </div>
      </div>

      <div className="hk-toolbar">
        <Field label={t('Floor', 'الطابق')}>
          <Select
            value={floorFilter}
            onChange={(e) => setFloorFilter(e.target.value)}
          >
            <option value="all">{t('All floors', 'جميع الطوابق')}</option>
            <option value="3">3</option>
            <option value="4">4</option>
            <option value="5">5</option>
            <option value="7">7</option>
          </Select>
        </Field>

        <Field label={t('Status', 'الحالة')}>
          <Select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
          >
            <option value="all">{t('All statuses', 'جميع الحالات')}</option>
            <option value="pending">{t('Pending', 'في الانتظار')}</option>
            <option value="in_progress">{t('In progress', 'جارٍ')}</option>
            <option value="done">{t('Done', 'تم')}</option>
            <option value="inspected">{t('Inspected', 'تم التفتيش')}</option>
          </Select>
        </Field>
      </div>

      {tasks.length === 0 ? (
        <EmptyState
          title={t('No tasks', 'لا مهام')}
          message={t('All rooms are clean.', 'جميع الغرف نظيفة.')}
        />
      ) : (
        <div className="hk-task-list">
          {tasks.map((task) => (
            <div
              key={task.id}
              className={'hk-task-card' + (task.priority === 'rush' ? ' is-rush' : '')}
            >
              <div className="hk-task-header">
                <div className="hk-task-identity">
                  <Badge variant="neutral">
                    {t('Floor', 'طابق')} {task.floor}
                  </Badge>
                  <span className="hk-room-number">{task.room_number}</span>
                  <span className="hk-sep" aria-hidden="true">·</span>
                  <span className="hk-task-type">
                    {pickLocalized(TASK_TYPE_LABELS[task.task_type] ?? { en: task.task_type, ar: task.task_type }, locale)}
                  </span>
                </div>
                <Badge variant={STATUS_META[task.status]?.variant ?? 'neutral'}>
                  {pickLocalized(STATUS_META[task.status]?.label ?? { en: task.status, ar: task.status }, locale)}
                </Badge>
              </div>

              {task.assigned_to && (
                <div className="hk-assignee">
                  <UserCheck size={13} />
                  <span>{task.assigned_to}</span>
                </div>
              )}

              {!task.assigned_to && assigningId !== task.id && (
                <Button
                  variant="ghost"
                  size="sm"
                  icon={UserCheck}
                  onClick={() => { setAssigningId(task.id); setAttendantInput(''); }}
                >
                  {t('Assign', 'تعيين')}
                </Button>
              )}

              {assigningId === task.id && (
                <div className="hk-assign-form">
                  <Input
                    value={attendantInput}
                    onChange={(e) => setAttendantInput(e.target.value)}
                    placeholder={t('Attendant name', 'اسم العامل')}
                  />
                  <Button
                    size="sm"
                    onClick={async () => {
                      await assignTask(task.id, attendantInput);
                      setAssigningId(null);
                    }}
                  >
                    {t('Confirm', 'تأكيد')}
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setAssigningId(null)}
                  >
                    {t('Cancel', 'إلغاء')}
                  </Button>
                </div>
              )}

              <div className="hk-task-actions">
                {task.status === 'pending' && (
                  <Button variant="ghost" size="sm" onClick={() => updateStatus(task.id, 'in_progress')}>
                    {t('Begin', 'ابدأ')}
                  </Button>
                )}
                {task.status === 'in_progress' && (
                  <Button variant="ghost" size="sm" onClick={() => updateStatus(task.id, 'done')}>
                    {t('Done', 'تم')}
                  </Button>
                )}
                {task.status === 'done' && (
                  <Button variant="ghost" size="sm" onClick={() => updateStatus(task.id, 'inspected')}>
                    {t('Inspect', 'فتّش')}
                  </Button>
                )}
              </div>

              <div className="hk-task-meta">
                {task.estimated_minutes && (
                  <span className="hk-est">{task.estimated_minutes} {t('min', 'دقيقة')}</span>
                )}
                {task.updated_at && (
                  <span className="hk-updated">{formatDate(task.updated_at, locale)}</span>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
