import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Banknote, Building2, CheckCircle, CreditCard, ExternalLink, ReceiptText, UserCircle, ConciergeBell, PlaneTakeoff } from 'lucide-react';
import { Badge, Button, Card, Field, PageHeader, Select, Skeleton, Textarea } from '../components/ui/Primitives.jsx';
import { GuestJourneyBar } from '../components/common/GuestJourneyBar.jsx';
import { useReservationStore } from '../store/reservationStore.js';
import { useAuthStore } from '../store/authStore.js';
import { pickLocalized } from '../utils/i18n.js';
import { formatDate } from '../utils/date.js';
import { formatMoney } from '../utils/money.js';

const STATUS_BADGE = {
  arriving_today: 'info',
  checked_in: 'success',
  due_out: 'warning',
  upcoming: 'neutral',
  departed: 'neutral',
};

const VIP_BADGE = { platinum: 'warning', gold: 'info' };

const PAYMENT_STATUS_BADGE = {
  authorized: 'info',
  partial: 'warning',
  unpaid: 'danger',
  paid: 'success',
  deposit_paid: 'neutral',
};

const SOURCE_LABEL = {
  direct: 'Direct',
  booking_com: 'Booking.com',
  phone: 'Phone',
  corporate: 'Corporate',
};

const PAYMENT_METHODS = [
  { id: 'cash', label: 'Cash', Icon: Banknote },
  { id: 'credit_card', label: 'Credit card', Icon: CreditCard },
  { id: 'bank_transfer', label: 'Bank transfer', Icon: Building2 },
];

const fmt = (s) => s.replaceAll('_', ' ');

const NEXT_STEPS = {
  upcoming: [
    { label: 'Open Folio', labelAr: 'فتح الفاتورة', icon: ReceiptText, to: (id) => `/reservations/${id}/folio` },
  ],
  arriving_today: [
    { label: 'Open Folio', labelAr: 'فتح الفاتورة', icon: ReceiptText, to: (id) => `/reservations/${id}/folio` },
  ],
  checked_in: [
    { label: 'Open Folio', labelAr: 'فتح الفاتورة', icon: ReceiptText, to: (id) => `/reservations/${id}/folio` },
    { label: 'Live Queue', labelAr: 'قائمة الانتظار', icon: ConciergeBell, to: () => '/operations/queue' },
  ],
  due_out: [
    { label: 'Open Folio', labelAr: 'فتح الفاتورة', icon: ReceiptText, to: (id) => `/reservations/${id}/folio` },
    { label: 'Departure Services', labelAr: 'خدمات المغادرة', icon: PlaneTakeoff, to: () => '/operations/departures' },
  ],
  departed: [
    { label: 'View Settled Folio', labelAr: 'عرض الفاتورة', icon: ReceiptText, to: (id) => `/reservations/${id}/folio` },
  ],
};

const ReservationDetail = () => {
  const { uuid: id } = useParams();
  const { current, rooms, fetchReservation, fetchAvailableRooms, checkIn, checkOut, updateNotes, isLoading } =
    useReservationStore();
  const locale = useAuthStore((s) => s.locale);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);
  const [room, setRoom] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('');
  const [savingCheckIn, setSavingCheckIn] = useState(false);
  const [savingCheckOut, setSavingCheckOut] = useState(false);
  const [notes, setNotes] = useState('');
  const [savingNotes, setSavingNotes] = useState(false);

  useEffect(() => {
    fetchReservation(id).then((reservation) => {
      if (reservation) {
        setNotes(reservation.notes || '');
        if (!reservation.assigned_room_id) {
          fetchAvailableRooms(reservation.id);
        }
      }
    });
  }, [fetchAvailableRooms, fetchReservation, id]);

  if (isLoading || !current) return <Skeleton lines={8} />;

  const canCheckIn =
    ['arriving_today', 'upcoming'].includes(current.status) && !current.assigned_room_id;
  const canCheckOut = ['checked_in', 'due_out'].includes(current.status);
  const needsPayment = current.folio_balance > 0;
  const nextSteps = NEXT_STEPS[current.status] ?? [];

  const handleCheckIn = async () => {
    if (!room) return;
    setSavingCheckIn(true);
    await checkIn(id, room);
    setSavingCheckIn(false);
  };

  const handleNotes = async () => {
    setSavingNotes(true);
    await updateNotes(id, notes);
    setSavingNotes(false);
  };

  const handleCheckOut = async () => {
    if (needsPayment && !paymentMethod) return;
    setSavingCheckOut(true);
    await checkOut(id, paymentMethod ? { payment_method: paymentMethod } : {});
    setSavingCheckOut(false);
  };

  return (
    <div className="res-detail">
      <PageHeader
        title={current.reservation_code}
        subtitle={`${current.guest.full_name} · ${formatDate(current.arrival_at)} → ${formatDate(current.departure_at)} · ${current.nights} night${current.nights !== 1 ? 's' : ''}`}
        actions={
          <div className="res-detail-badges">
            <Badge variant={PAYMENT_STATUS_BADGE[current.payment_status] || 'neutral'}>
              {fmt(current.payment_status)}
            </Badge>
            <Badge variant={STATUS_BADGE[current.status] || 'neutral'}>{fmt(current.status)}</Badge>
          </div>
        }
      />

      <GuestJourneyBar status={current.status} locale={locale} />

      <div className="res-detail-grid">
        <div className="res-detail-main">

          <Card padded className="rd-card">
            <p className="rd-section-label">{t('Stay details', 'تفاصيل الإقامة')}</p>
            <div className="rd-meta-grid">
              <div className="rd-meta-item">
                <span>{t('Room type', 'نوع الغرفة')}</span>
                <strong>{current.room_type?.name || '—'}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Assigned room', 'الغرفة المخصصة')}</span>
                <strong>
                  {current.assigned_room ? `Room ${current.assigned_room.number}` : t('Unassigned', 'غير مخصصة')}
                </strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Nights', 'الليالي')}</span>
                <strong>{current.nights}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Guests', 'الضيوف')}</span>
                <strong>
                  {current.adults} adult{current.adults !== 1 ? 's' : ''}
                  {current.children
                    ? `, ${current.children} child${current.children !== 1 ? 'ren' : ''}`
                    : ''}
                </strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Source', 'المصدر')}</span>
                <strong>{SOURCE_LABEL[current.source] || current.source}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Total', 'الإجمالي')}</span>
                <strong>{formatMoney(current.total_amount)}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Nightly rate', 'السعر الليلي')}</span>
                <strong>{current.nightly_rate ? formatMoney(current.nightly_rate) : '—'}</strong>
              </div>
            </div>

            {current.folio_balance > 0 && (
              <div className="rd-folio-alert">
                <span className="rd-folio-alert-label">{t('Outstanding folio', 'رصيد مستحق')}</span>
                <span className="rd-folio-alert-amount">{formatMoney(current.folio_balance)}</span>
              </div>
            )}
          </Card>

          {canCheckIn && (
            <Card padded className="rd-card rd-action-card">
              <p className="rd-section-label">{t('Check-in', 'تسجيل الوصول')}</p>
              <Field label={t('Available room (matching type)', 'غرفة متاحة (من نفس النوع)')}>
                <Select value={room} onChange={(e) => setRoom(e.target.value)}>
                  <option value="">{t('Select room', 'اختر الغرفة')}</option>
                  {rooms.map((r) => (
                    <option key={r.id} value={r.id}>
                      {r.number} · {r.room_type?.name}
                    </option>
                  ))}
                </Select>
              </Field>
              <div className="rd-action-footer">
                <Button
                  isLoading={savingCheckIn}
                  disabled={!room || savingCheckIn}
                  onClick={handleCheckIn}
                >
                  {t('Confirm check-in', 'تأكيد الوصول')}
                </Button>
              </div>
            </Card>
          )}

          {canCheckOut && (
            <Card padded className="rd-card rd-action-card rd-checkout-card">
              <p className="rd-section-label">{t('Check-out', 'تسجيل المغادرة')}</p>

              {needsPayment ? (
                <>
                  <div className="rd-folio-settle">
                    <div className="rd-folio-settle-row">
                      <span className="rd-folio-settle-label">{t('Outstanding balance', 'الرصيد المستحق')}</span>
                      <span className="rd-folio-settle-amount">
                        {formatMoney(current.folio_balance)}
                      </span>
                    </div>
                    <span className="rd-folio-settle-note">{t('Settle before completing check-out', 'سدد الرصيد قبل إتمام المغادرة')}</span>
                  </div>

                  <div className="rd-payment-group">
                    <p className="rd-sublabel">{t('Payment method', 'طريقة الدفع')}</p>
                    <div className="rd-payment-row">
                      {PAYMENT_METHODS.map(({ id: pmId, label, Icon }) => (
                        <label
                          key={pmId}
                          className={`rd-payment-card${paymentMethod === pmId ? ' is-selected' : ''}`}
                        >
                          <input
                            type="radio"
                            name="payment_method"
                            value={pmId}
                            checked={paymentMethod === pmId}
                            onChange={() => setPaymentMethod(pmId)}
                          />
                          <Icon size={18} />
                          <span>{label}</span>
                        </label>
                      ))}
                    </div>
                  </div>

                  <div className="rd-action-footer">
                    <Button
                      variant="danger"
                      isLoading={savingCheckOut}
                      disabled={!paymentMethod || savingCheckOut}
                      onClick={handleCheckOut}
                    >
                      {t('Settle & check out', 'سدد وأتمم المغادرة')}
                    </Button>
                  </div>
                </>
              ) : (
                <>
                  <div className="rd-folio-clear">
                    <CheckCircle size={16} />
                    <span>{t('Folio clear — no outstanding balance', 'الفاتورة صافية — لا رصيد مستحق')}</span>
                  </div>
                  <div className="rd-action-footer">
                    <Button
                      variant="danger"
                      isLoading={savingCheckOut}
                      disabled={savingCheckOut}
                      onClick={handleCheckOut}
                    >
                      {t('Complete check-out', 'إتمام المغادرة')}
                    </Button>
                  </div>
                </>
              )}
            </Card>
          )}

          {current.status === 'departed' && (
            <div className="rd-departed">
              <CheckCircle size={18} />
              <div>
                <strong>{t('Stay complete', 'اكتملت الإقامة')}</strong>
                <p>{t('This guest has checked out. The folio is settled and the room has been released.', 'غادر هذا الضيف. تمت تسوية الفاتورة وتحرير الغرفة.')}</p>
              </div>
            </div>
          )}

          {nextSteps.length > 0 && (
            <Card padded className="rd-card">
              <p className="rd-section-label">{t('Next steps', 'الخطوات التالية')}</p>
              <div className="rd-next-links">
                {nextSteps.map((step) => (
                  <Link key={step.label} to={step.to(id)} className="rd-next-link">
                    <step.icon size={15} />
                    <span>{t(step.label, step.labelAr)}</span>
                    <ExternalLink size={12} className="rd-next-link-icon" />
                  </Link>
                ))}
                {current.guest.profile_id && (
                  <Link to={`/guests/${current.guest.profile_id}`} className="rd-next-link">
                    <UserCircle size={15} />
                    <span>{t('Guest Profile', 'ملف الضيف')}</span>
                    <ExternalLink size={12} className="rd-next-link-icon" />
                  </Link>
                )}
              </div>
            </Card>
          )}

        </div>

        <div className="res-detail-side">

          <Card padded className="rd-card rd-guest-card">
            <p className="rd-section-label">{t('Guest', 'الضيف')}</p>
            <div className="rd-guest-header">
              <div className="rd-guest-avatar">{current.guest.full_name.charAt(0)}</div>
              <div className="rd-guest-names">
                <span className="rd-guest-name">{current.guest.full_name}</span>
                {current.guest.full_name_ar && (
                  <span className="rd-guest-name-ar" dir="rtl">
                    {current.guest.full_name_ar}
                  </span>
                )}
              </div>
            </div>
            <div className="rd-guest-attrs">
              {current.guest.vip_level && current.guest.vip_level !== 'standard' && (
                <div className="rd-guest-attr">
                  <span>VIP</span>
                  <Badge variant={VIP_BADGE[current.guest.vip_level] || 'neutral'}>
                    {current.guest.vip_level}
                  </Badge>
                </div>
              )}
              <div className="rd-guest-attr">
                <span>{t('Nationality', 'الجنسية')}</span>
                <strong>{current.guest.nationality}</strong>
              </div>
              <div className="rd-guest-attr">
                <span>{t('Phone', 'الهاتف')}</span>
                <strong className="rd-masked">{current.guest.phone_masked}</strong>
              </div>
              <div className="rd-guest-attr">
                <span>{t('Email', 'البريد')}</span>
                <strong className="rd-masked">{current.guest.email_masked}</strong>
              </div>
            </div>
            {current.guest.profile_id && (
              <Link to={`/guests/${current.guest.profile_id}`} className="rd-guest-profile-link">
                <UserCircle size={14} />
                {t('View full profile', 'عرض الملف الكامل')}
              </Link>
            )}
          </Card>

          <Card padded className="rd-card">
            <p className="rd-section-label">{t('Staff notes', 'ملاحظات الموظفين')}</p>
            <Field label="">
              <Textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder={t('Add internal notes about this guest or stay…', 'أضف ملاحظات داخلية حول هذا الضيف أو الإقامة...')}
                rows={4}
              />
            </Field>
            <div className="rd-action-footer">
              <Button
                variant="secondary"
                isLoading={savingNotes}
                disabled={savingNotes}
                onClick={handleNotes}
              >
                {t('Save note', 'حفظ الملاحظة')}
              </Button>
            </div>
          </Card>

          {current.timeline?.length > 0 && (
            <Card padded className="rd-card">
              <p className="rd-section-label">{t('Activity', 'النشاط')}</p>
              <div className="rd-timeline">
                {current.timeline.map((event) => (
                  <div key={event.id} className="rd-timeline-event">
                    <div className="rd-timeline-dot" />
                    <div className="rd-timeline-body">
                      <span className="rd-timeline-label">{event.label}</span>
                      <span className="rd-timeline-meta">
                        {event.actor} · {formatDate(event.at)}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </Card>
          )}

        </div>
      </div>
    </div>
  );
};

export default ReservationDetail;
