import { Check } from 'lucide-react';
import { pickLocalized } from '../../utils/i18n.js';

const STAGES = [
  { key: 'upcoming',        en: 'Booked',    ar: 'محجوز' },
  { key: 'arriving_today',  en: 'Arriving',  ar: 'الوصول' },
  { key: 'checked_in',      en: 'In House',  ar: 'في الفندق' },
  { key: 'due_out',         en: 'Departing', ar: 'المغادرة' },
  { key: 'departed',        en: 'Complete',  ar: 'اكتمل' },
];

const ORDER = STAGES.map((s) => s.key);

export function GuestJourneyBar({ status, locale }) {
  const idx = Math.max(0, ORDER.indexOf(status ?? 'upcoming'));
  const items = [];

  STAGES.forEach((stage, i) => {
    const done = i < idx;
    const active = i === idx;
    const mod = done ? ' is-done' : active ? ' is-active' : '';

    if (i > 0) {
      items.push(<div key={`ln-${i}`} className={`journey-connector${done ? ' is-done' : ''}`} />);
    }
    items.push(
      <div key={stage.key} className="journey-step">
        <div className={`journey-dot${mod}`}>
          {done && <Check size={9} strokeWidth={3} />}
        </div>
        <span className={`journey-label${mod}`}>
          {pickLocalized({ en: stage.en, ar: stage.ar }, locale)}
        </span>
      </div>
    );
  });

  return (
    <div className="journey-bar" role="status" aria-label="Guest journey stage">
      {items}
    </div>
  );
}
