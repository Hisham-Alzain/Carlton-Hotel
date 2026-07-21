import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Badge, Button, Card, Field, Input, Select, PageHeader } from '../components/ui/Primitives.jsx';
import { reservationsService } from '../services/reservationsService.js';
import { useAuthStore } from '../store/authStore.js';
import { pickLocalized } from '../utils/i18n.js';

const ROOM_TYPE_OPTIONS = [
  { value: 'rt_deluxe_king',     label: 'Deluxe King' },
  { value: 'rt_deluxe_twin',     label: 'Deluxe Twin' },
  { value: 'rt_premium_suite',   label: 'Premium Suite' },
  { value: 'rt_executive_suite', label: 'Executive Suite' },
];

const SOURCE_OPTIONS = [
  { value: 'direct',      label: 'Direct' },
  { value: 'phone',       label: 'Phone' },
  { value: 'booking_com', label: 'Booking.com' },
  { value: 'corporate',   label: 'Corporate' },
];

const PAYMENT_OPTIONS = [
  { value: 'deposit_paid', label: 'Deposit paid' },
  { value: 'authorized',   label: 'Authorized' },
  { value: 'unpaid',       label: 'Unpaid' },
];

const INITIAL_FORM = {
  guest_name:     '',
  guest_name_ar:  '',
  nationality:    '',
  phone:          '',
  room_type_id:   'rt_deluxe_king',
  check_in:       '',
  check_out:      '',
  adults:         1,
  children:       0,
  source:         'direct',
  payment_status: 'deposit_paid',
  notes:          '',
};

function validateForm(form, t) {
  const errs = {};
  if (!form.guest_name.trim()) {
    errs.guest_name = t('Guest name is required', 'اسم الضيف مطلوب');
  }
  if (!form.check_in) {
    errs.check_in = t('Check-in date is required', 'تاريخ الوصول مطلوب');
  }
  if (!form.check_out) {
    errs.check_out = t('Check-out date is required', 'تاريخ المغادرة مطلوب');
  }
  if (form.check_in && form.check_out && form.check_out <= form.check_in) {
    errs.check_out = t('Check-out must be after check-in', 'تاريخ المغادرة يجب أن يكون بعد تاريخ الوصول');
  }
  if (!form.room_type_id) {
    errs.room_type_id = t('Room type is required', 'نوع الغرفة مطلوب');
  }
  return errs;
}

const SectionLabel = ({ label }) => (
  <div style={{
    gridColumn: '1 / -1',
    fontSize: 11,
    fontWeight: 600,
    letterSpacing: '0.06em',
    textTransform: 'uppercase',
    color: 'var(--color-text-muted)',
    borderBottom: '1px solid var(--color-border)',
    paddingBottom: 6,
    marginBottom: 4,
    marginTop: 8,
  }}>
    {label}
  </div>
);

const GRID_STYLE = {
  display: 'grid',
  gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
  gap: '0 24px',
};

const CreateReservation = () => {
  const navigate = useNavigate();
  const locale = useAuthStore((s) => s.locale);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);

  const [form, setForm] = useState(INITIAL_FORM);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState({});
  const [success, setSuccess] = useState(null);

  const handleChange = (field) => (e) =>
    setForm((prev) => ({ ...prev, [field]: e.target.value }));

  const handleSubmit = async (e) => {
    e.preventDefault();
    const validationErrors = validateForm(form, t);
    if (Object.keys(validationErrors).length) {
      setErrors(validationErrors);
      return;
    }
    setErrors({});
    setSubmitting(true);
    try {
      await reservationsService.create(form);
      setSuccess(true);
      navigate('/reservations');
    } catch (err) {
      const payload = err.payload || {};
      setErrors(
        payload.errors || { _form: payload.message || t('Something went wrong. Please try again.', 'حدث خطأ ما. يرجى المحاولة مجددًا.') }
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <PageHeader
        title={t('New Reservation', 'حجز جديد')}
        subtitle={t('Create a walk-in or advance reservation.', 'إنشاء حجز طارئ أو مسبق.')}
      />

      <form onSubmit={handleSubmit} noValidate>
        <Card padded>
          {errors._form && (
            <div style={{
              marginBottom: 16,
              padding: '10px 14px',
              borderRadius: 6,
              background: '#fde8d8',
              color: '#9a3412',
              fontSize: 14,
            }}>
              {errors._form}
            </div>
          )}

          <div style={GRID_STYLE}>
            <SectionLabel label={t('Guest', 'بيانات الضيف')} />

            <Field label={t('Guest name (EN) *', 'الاسم بالإنجليزية *')} error={errors.guest_name}>
              <Input
                value={form.guest_name}
                onChange={handleChange('guest_name')}
                placeholder="Jane Smith"
                autoComplete="off"
              />
            </Field>
            <Field label={t('Guest name (AR)', 'الاسم بالعربية')} error={errors.guest_name_ar}>
              <Input
                value={form.guest_name_ar}
                onChange={handleChange('guest_name_ar')}
                placeholder="جين سميث"
                dir="rtl"
              />
            </Field>
            <Field label={t('Nationality', 'الجنسية')} error={errors.nationality}>
              <Input
                value={form.nationality}
                onChange={handleChange('nationality')}
                placeholder="LB"
                maxLength={3}
              />
            </Field>
            <Field label={t('Phone', 'رقم الهاتف')} error={errors.phone}>
              <Input
                value={form.phone}
                onChange={handleChange('phone')}
                type="tel"
                placeholder="+961 1 234 567"
              />
            </Field>
          </div>

          <div style={{ ...GRID_STYLE, marginTop: 20 }}>
            <SectionLabel label={t('Stay', 'تفاصيل الإقامة')} />

            <Field label={t('Room type *', 'نوع الغرفة *')} error={errors.room_type_id}>
              <Select value={form.room_type_id} onChange={handleChange('room_type_id')}>
                {ROOM_TYPE_OPTIONS.map((rt) => (
                  <option key={rt.value} value={rt.value}>{rt.label}</option>
                ))}
              </Select>
            </Field>
            <Field label={t('Check-in *', 'تاريخ الوصول *')} error={errors.check_in}>
              <Input
                type="date"
                value={form.check_in}
                onChange={handleChange('check_in')}
              />
            </Field>
            <Field label={t('Check-out *', 'تاريخ المغادرة *')} error={errors.check_out}>
              <Input
                type="date"
                value={form.check_out}
                onChange={handleChange('check_out')}
              />
            </Field>
            <Field label={t('Adults', 'البالغون')} error={errors.adults}>
              <Input
                type="number"
                min={1}
                value={form.adults}
                onChange={handleChange('adults')}
              />
            </Field>
            <Field label={t('Children', 'الأطفال')} error={errors.children}>
              <Input
                type="number"
                min={0}
                value={form.children}
                onChange={handleChange('children')}
              />
            </Field>
          </div>

          <div style={{ ...GRID_STYLE, marginTop: 20 }}>
            <SectionLabel label={t('Booking', 'تفاصيل الحجز')} />

            <Field label={t('Source', 'المصدر')} error={errors.source}>
              <Select value={form.source} onChange={handleChange('source')}>
                {SOURCE_OPTIONS.map((s) => (
                  <option key={s.value} value={s.value}>{s.label}</option>
                ))}
              </Select>
            </Field>
            <Field label={t('Payment status', 'حالة الدفع')} error={errors.payment_status}>
              <Select value={form.payment_status} onChange={handleChange('payment_status')}>
                {PAYMENT_OPTIONS.map((p) => (
                  <option key={p.value} value={p.value}>{p.label}</option>
                ))}
              </Select>
            </Field>
          </div>

          <div style={{ marginTop: 20 }}>
            <Field label={t('Notes', 'ملاحظات')} error={errors.notes}>
              <textarea
                className="textarea"
                value={form.notes}
                onChange={handleChange('notes')}
                rows={3}
                placeholder={t(
                  'Optional — allergies, preferences, special requests…',
                  'اختياري — الحساسية، التفضيلات، الطلبات الخاصة…',
                )}
              />
            </Field>
          </div>

          <div style={{ marginTop: 24, display: 'flex', justifyContent: 'flex-end', gap: 12 }}>
            <Button
              type="button"
              variant="ghost"
              onClick={() => navigate('/reservations')}
              disabled={submitting}
            >
              {t('Cancel', 'إلغاء')}
            </Button>
            <Button type="submit" variant="primary" isLoading={submitting}>
              {t('Create reservation', 'إنشاء الحجز')}
            </Button>
          </div>
        </Card>
      </form>
    </>
  );
};

export default CreateReservation;
