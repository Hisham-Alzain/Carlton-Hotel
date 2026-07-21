import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { AlertCircle, ArrowLeft, CheckCircle, CircleDollarSign, Plus, UserCircle } from 'lucide-react';
import { Badge, Button, Card, Field, Input, PageHeader, Select, Skeleton, ErrorState } from '../components/ui/Primitives.jsx';
import { useFolioStore } from '../store/folioStore.js';
import { useAuthStore } from '../store/authStore.js';
import { pickLocalized } from '../utils/i18n.js';
import { formatDate } from '../utils/date.js';
import { formatMoney } from '../utils/money.js';

const CATEGORY_LABELS = {
  room: 'Room', food: 'Food', beverage: 'Beverage', spa: 'Spa',
  transport: 'Transport', misc: 'Misc', tax: 'Tax',
};
const CATEGORY_BADGE = {
  room: 'info', food: 'neutral', beverage: 'neutral', spa: 'success',
  transport: 'neutral', misc: 'neutral', tax: 'neutral',
};
const STATUS_BADGE = { open: 'warning', settled: 'success', disputed: 'danger' };

export default function FolioDetail() {
  const { uuid } = useParams();
  const { folio, isLoading, error, posting, settling, fetchFolioByReservation, addLineItem, disputeItem, settle } = useFolioStore();
  const locale = useAuthStore((s) => s.locale);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);

  const [showPostForm, setShowPostForm] = useState(false);
  const [postData, setPostData] = useState({ category: 'room', description: '', amount: '', quantity: '1' });
  const [showPayForm, setShowPayForm] = useState(false);
  const [payData, setPayData] = useState({ method: 'credit_card', amount: '' });

  useEffect(() => {
    fetchFolioByReservation(uuid);
  }, [uuid]);

  if (isLoading) return <Skeleton lines={8} />;
  if (error) return <ErrorState title={t('Failed to load folio', 'فشل تحميل الفاتورة')} message={error.message} requestId={error.request_id} />;
  if (!folio) return (
    <div style={{ padding: '2rem' }}>
      <div style={{ textAlign: 'center', color: 'var(--color-text-muted)', padding: '3rem 0' }}>
        <CircleDollarSign size={40} style={{ marginBottom: '1rem', opacity: 0.4 }} />
        <p style={{ fontWeight: 600, color: 'var(--color-text-strong)' }}>{t('No folio found', 'لا فاتورة موجودة')}</p>
        <p>{t('This reservation does not have a folio yet.', 'هذا الحجز لا يحتوي على فاتورة بعد.')}</p>
      </div>
    </div>
  );

  const totalCharges = folio.line_items?.reduce((sum, item) => sum + item.amount * item.quantity, 0) ?? 0;
  const totalPayments = folio.payments?.reduce((sum, p) => sum + p.amount, 0) ?? 0;
  const balance = folio.balance ?? totalCharges - totalPayments;

  return (
    <div style={{ padding: '1.5rem', display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
      <div className="folio-nav">
        <Link to={`/reservations/${uuid}`} className="folio-back-link">
          <ArrowLeft size={14} />
          {t('Back to reservation', 'العودة إلى الحجز')}
        </Link>
        {folio.guest_id && (
          <Link to={`/guests/${folio.guest_id}`} className="folio-guest-link">
            <UserCircle size={14} />
            {t('Guest profile', 'ملف الضيف')}
          </Link>
        )}
      </div>

      <PageHeader
        title={`${t('Folio', 'الفاتورة')} ${folio.id}`}
        subtitle={`${folio.guest_name}${folio.room_number ? ` · ${t('Room', 'غرفة')} ${folio.room_number}` : ''}`}
        actions={
          <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
            <Badge variant={STATUS_BADGE[folio.status] ?? 'neutral'}>{folio.status}</Badge>
            <Badge variant={balance > 0 ? 'danger' : 'success'}>{formatMoney(balance)}</Badge>
          </div>
        }
      />

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '0.75rem' }}>
        <Card style={{ padding: '1rem' }}>
          <div style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)', marginBottom: '0.25rem' }}>{t('Total Charges', 'إجمالي الرسوم')}</div>
          <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--color-text-strong)' }}>{formatMoney(totalCharges)}</div>
        </Card>
        <Card style={{ padding: '1rem' }}>
          <div style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)', marginBottom: '0.25rem' }}>{t('Total Payments', 'إجمالي المدفوعات')}</div>
          <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--color-text-strong)' }}>{formatMoney(totalPayments)}</div>
        </Card>
        <Card style={{ padding: '1rem' }}>
          <div style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)', marginBottom: '0.25rem' }}>{t('Balance', 'الرصيد')}</div>
          <div style={{ fontSize: '1.25rem', fontWeight: 700, color: balance > 0 ? 'var(--color-danger)' : 'var(--color-success)' }}>{formatMoney(balance)}</div>
        </Card>
      </div>

      <Card>
        <div style={{ padding: '1rem 1.25rem', borderBottom: '1px solid var(--color-border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3 style={{ margin: 0, fontSize: '0.9375rem', fontWeight: 600, color: 'var(--color-text-strong)' }}>{t('Line Items', 'بنود الفاتورة')}</h3>
          <Button variant="ghost" size="sm" icon={Plus} onClick={() => setShowPostForm((v) => !v)}>
            {t('Post Charge', 'إضافة رسوم')}
          </Button>
        </div>

        {showPostForm && (
          <div style={{ padding: '1rem 1.25rem', borderBottom: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)', display: 'flex', flexWrap: 'wrap', gap: '0.75rem', alignItems: 'flex-end' }}>
            <Field label={t('Category', 'الفئة')} style={{ minWidth: '140px' }}>
              <Select
                value={postData.category}
                onChange={(e) => setPostData((d) => ({ ...d, category: e.target.value }))}
              >
                {Object.entries(CATEGORY_LABELS).map(([val, label]) => (
                  <option key={val} value={val}>{label}</option>
                ))}
              </Select>
            </Field>
            <Field label={t('Description', 'الوصف')} style={{ flex: '1 1 180px' }}>
              <Input
                value={postData.description}
                onChange={(e) => setPostData((d) => ({ ...d, description: e.target.value }))}
                placeholder={t('Enter description', 'أدخل الوصف')}
              />
            </Field>
            <Field label={t('Amount', 'المبلغ')} style={{ minWidth: '110px' }}>
              <Input
                type="number"
                value={postData.amount}
                onChange={(e) => setPostData((d) => ({ ...d, amount: e.target.value }))}
                placeholder="0.00"
              />
            </Field>
            <Field label={t('Qty', 'الكمية')} style={{ minWidth: '80px' }}>
              <Input
                type="number"
                value={postData.quantity}
                onChange={(e) => setPostData((d) => ({ ...d, quantity: e.target.value }))}
                min="1"
              />
            </Field>
            <Button
              variant="primary"
              isLoading={posting}
              onClick={async () => {
                await addLineItem(folio.id, {
                  ...postData,
                  amount: parseFloat(postData.amount),
                  quantity: parseInt(postData.quantity),
                });
                setShowPostForm(false);
                setPostData({ category: 'room', description: '', amount: '', quantity: '1' });
              }}
            >
              {t('Post', 'نشر')}
            </Button>
            <Button variant="ghost" onClick={() => setShowPostForm(false)}>{t('Cancel', 'إلغاء')}</Button>
          </div>
        )}

        <div style={{ overflowX: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.875rem' }}>
            <thead>
              <tr style={{ borderBottom: '1px solid var(--color-border)' }}>
                {[t('Category', 'الفئة'), t('Description', 'الوصف'), t('Amount', 'المبلغ'), t('Posted By', 'بواسطة'), t('Posted At', 'التاريخ'), t('Actions', 'إجراءات')].map((col) => (
                  <th key={col} style={{ padding: '0.625rem 1rem', textAlign: 'start', fontWeight: 600, color: 'var(--color-text-muted)', whiteSpace: 'nowrap' }}>{col}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {folio.line_items?.length === 0 && (
                <tr>
                  <td colSpan={6} style={{ padding: '2rem', textAlign: 'center', color: 'var(--color-text-muted)' }}>
                    {t('No charges posted yet.', 'لا رسوم مسجلة بعد.')}
                  </td>
                </tr>
              )}
              {folio.line_items?.map((item) => (
                <tr
                  key={item.id}
                  style={{
                    borderBottom: '1px solid var(--color-border)',
                    backgroundColor: item.disputed ? 'rgba(180,69,69,0.05)' : undefined,
                  }}
                >
                  <td style={{ padding: '0.625rem 1rem' }}>
                    <Badge variant={CATEGORY_BADGE[item.category] ?? 'neutral'}>{CATEGORY_LABELS[item.category] ?? item.category}</Badge>
                  </td>
                  <td style={{ padding: '0.625rem 1rem', color: 'var(--color-text-body)' }}>{item.description}</td>
                  <td style={{ padding: '0.625rem 1rem', fontVariantNumeric: 'tabular-nums', color: 'var(--color-text-strong)', fontWeight: 500 }}>
                    {formatMoney(item.amount * item.quantity)}
                  </td>
                  <td style={{ padding: '0.625rem 1rem', color: 'var(--color-text-muted)' }}>{item.posted_by}</td>
                  <td style={{ padding: '0.625rem 1rem', color: 'var(--color-text-muted)', whiteSpace: 'nowrap' }}>{formatDate(item.posted_at)}</td>
                  <td style={{ padding: '0.625rem 1rem' }}>
                    {item.disputed
                      ? <Badge variant="danger">{t('Disputed', 'متنازع عليه')}</Badge>
                      : (
                        <Button
                          variant="ghost"
                          size="sm"
                          icon={AlertCircle}
                          onClick={() => disputeItem(folio.id, item.id)}
                        >
                          {t('Dispute', 'اعتراض')}
                        </Button>
                      )
                    }
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <Card>
        <div style={{ padding: '1rem 1.25rem', borderBottom: '1px solid var(--color-border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3 style={{ margin: 0, fontSize: '0.9375rem', fontWeight: 600, color: 'var(--color-text-strong)' }}>{t('Payments', 'المدفوعات')}</h3>
          {folio.status !== 'settled' && (
            <Button variant="ghost" size="sm" icon={Plus} onClick={() => setShowPayForm((v) => !v)}>
              {t('Record Payment', 'تسجيل دفعة')}
            </Button>
          )}
        </div>

        {showPayForm && folio.status !== 'settled' && (
          <div style={{ padding: '1rem 1.25rem', borderBottom: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)', display: 'flex', flexWrap: 'wrap', gap: '0.75rem', alignItems: 'flex-end' }}>
            <Field label={t('Method', 'طريقة الدفع')} style={{ minWidth: '160px' }}>
              <Select
                value={payData.method}
                onChange={(e) => setPayData((d) => ({ ...d, method: e.target.value }))}
              >
                <option value="cash">{t('Cash', 'نقد')}</option>
                <option value="credit_card">{t('Credit Card', 'بطاقة ائتمان')}</option>
                <option value="bank_transfer">{t('Bank Transfer', 'تحويل بنكي')}</option>
              </Select>
            </Field>
            <Field label={t('Amount', 'المبلغ')} style={{ minWidth: '130px' }}>
              <Input
                type="number"
                value={payData.amount}
                onChange={(e) => setPayData((d) => ({ ...d, amount: e.target.value }))}
                placeholder="0.00"
              />
            </Field>
            <Button
              variant="primary"
              isLoading={settling}
              onClick={async () => {
                await settle(folio.id, { ...payData, amount: parseFloat(payData.amount) });
                setShowPayForm(false);
              }}
            >
              {t('Record', 'تسجيل')}
            </Button>
            <Button variant="ghost" onClick={() => setShowPayForm(false)}>{t('Cancel', 'إلغاء')}</Button>
          </div>
        )}

        <div style={{ padding: '0.5rem 0' }}>
          {!folio.payments?.length && (
            <p style={{ padding: '1rem 1.25rem', color: 'var(--color-text-muted)', fontStyle: 'italic', margin: 0 }}>
              {t('No payments recorded', 'لا مدفوعات مسجلة')}
            </p>
          )}
          {folio.payments?.map((payment) => (
            <div
              key={payment.id}
              style={{
                display: 'grid',
                gridTemplateColumns: '1fr 1fr 1fr 1fr',
                gap: '0.5rem',
                padding: '0.75rem 1.25rem',
                borderBottom: '1px solid var(--color-border)',
                fontSize: '0.875rem',
                alignItems: 'center',
              }}
            >
              <span style={{ color: 'var(--color-text-body)', textTransform: 'capitalize' }}>{payment.method?.replace('_', ' ')}</span>
              <span style={{ fontWeight: 600, color: 'var(--color-success)', fontVariantNumeric: 'tabular-nums' }}>{formatMoney(payment.amount)}</span>
              <span style={{ color: 'var(--color-text-muted)', whiteSpace: 'nowrap' }}>{formatDate(payment.paid_at)}</span>
              <span style={{ color: 'var(--color-text-muted)', fontSize: '0.8125rem' }}>{payment.reference ?? '—'}</span>
            </div>
          ))}
        </div>
      </Card>

      {balance > 0 && folio.status !== 'settled' && (
        <div style={{
          display: 'flex', alignItems: 'center', gap: '0.625rem',
          padding: '0.875rem 1.25rem', borderRadius: '8px',
          backgroundColor: 'rgba(180,69,69,0.08)', border: '1px solid rgba(180,69,69,0.25)',
          color: 'var(--color-danger)', fontWeight: 600,
        }}>
          <AlertCircle size={16} />
          {t('Outstanding', 'المبلغ المستحق')}: {formatMoney(balance)}
        </div>
      )}

      {folio.status === 'settled' && (
        <div style={{
          display: 'flex', alignItems: 'center', gap: '0.625rem',
          padding: '0.875rem 1.25rem', borderRadius: '8px',
          backgroundColor: 'rgba(38,135,101,0.08)', border: '1px solid rgba(38,135,101,0.25)',
          color: 'var(--color-success)', fontWeight: 600,
        }}>
          <CheckCircle size={16} />
          {t('Folio settled', 'تمت تسوية الفاتورة')}
        </div>
      )}
    </div>
  );
}
