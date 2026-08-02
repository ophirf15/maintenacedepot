/**
 * Plain-language status vocabulary.
 *
 * Crews using this app on a phone are not all native English speakers, so every
 * state gets short everyday wording plus an icon and colour, and the same state
 * always looks the same everywhere in the app.
 */
const TONES = {
  neutral: 'bg-neutral-100 text-neutral-700 border-neutral-200',
  info: 'bg-info-100 text-info-600 border-info-600/20',
  brand: 'bg-brand-100 text-brand-700 border-brand-700/20',
  warn: 'bg-warn-100 text-warn-600 border-warn-600/25',
  danger: 'bg-danger-100 text-danger-600 border-danger-600/20',
};

const DOTS = {
  neutral: 'bg-neutral-400',
  info: 'bg-info-600',
  brand: 'bg-brand-600',
  warn: 'bg-warn-600',
  danger: 'bg-danger-600',
};

const STATUS = {
  // Borrow requests
  draft: { label: 'Not sent yet', icon: 'edit', tone: 'neutral' },
  submitted: { label: 'Waiting for approval', icon: 'hourglass', tone: 'info' },
  pending_modification_accept: { label: 'Waiting for borrower', icon: 'help', tone: 'warn' },
  approved: { label: 'Approved', icon: 'check-circle', tone: 'brand' },
  rejected: { label: 'Rejected', icon: 'x-circle', tone: 'danger' },
  cancelled: { label: 'Cancelled', icon: 'x', tone: 'neutral' },
  completed: { label: 'Finished', icon: 'check', tone: 'neutral' },

  // Loans
  reserved: { label: 'Ready for pick-up', icon: 'package', tone: 'brand' },
  checked_out: { label: 'Out with borrower', icon: 'truck', tone: 'info' },
  return_pending: { label: 'Return submitted', icon: 'hourglass', tone: 'warn' },
  overdue: { label: 'Overdue — return now', icon: 'alert', tone: 'danger' },
  returned: { label: 'Returned', icon: 'check-circle', tone: 'brand' },
  closed: { label: 'Closed', icon: 'check', tone: 'neutral' },

  // Request lines
  pending: { label: 'Waiting', icon: 'hourglass', tone: 'info' },
  allocated: { label: 'Unit assigned', icon: 'check-circle', tone: 'brand' },
  fulfilled: { label: 'Picked up', icon: 'handshake', tone: 'brand' },
  waitlisted: { label: 'On waitlist', icon: 'clock', tone: 'warn' },

  // Item / equipment statuses
  available: { label: 'Available', icon: 'check-circle', tone: 'brand' },
  unavailable: { label: 'Not available', icon: 'x-circle', tone: 'neutral' },
  in_use: { label: 'In use', icon: 'truck', tone: 'info' },
  'checked-out': { label: 'Out on loan', icon: 'truck', tone: 'info' },
  'out-of-service': { label: 'Out of service', icon: 'alert', tone: 'danger' },
  'in-maintenance': { label: 'In repair', icon: 'wrench', tone: 'warn' },

  // Tickets / work
  open: { label: 'Open', icon: 'alert', tone: 'warn' },
  in_progress: { label: 'Being fixed', icon: 'wrench', tone: 'info' },
  resolved: { label: 'Fixed', icon: 'check-circle', tone: 'brand' },

  // Inspections
  pass: { label: 'Looks good', icon: 'check-circle', tone: 'brand' },
  fail: { label: 'Problem found', icon: 'alert', tone: 'danger' },

  // Priority / severity
  low: { label: 'Low', icon: 'info', tone: 'neutral' },
  normal: { label: 'Normal', icon: 'info', tone: 'neutral' },
  medium: { label: 'Medium', icon: 'info', tone: 'warn' },
  high: { label: 'High', icon: 'alert', tone: 'danger' },
  urgent: { label: 'Urgent', icon: 'alert', tone: 'danger' },
  critical: { label: 'Unsafe', icon: 'alert', tone: 'danger' },
};

function humanize(value) {
  return String(value || '')
    .replace(/[_-]/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

export function statusMeta(value) {
  const key = String(value || '').trim();
  const found = STATUS[key];

  if (found) return found;

  return { label: humanize(key) || 'Unknown', icon: 'info', tone: 'neutral' };
}

export function toneClasses(tone) {
  return TONES[tone] || TONES.neutral;
}

export function dotClass(tone) {
  return DOTS[tone] || DOTS.neutral;
}

/** Short "what should I do next" hint shown on request/loan cards. */
export function nextStepHint(status, isApprover = false) {
  const hints = {
    draft: 'Open it and send it to the depot.',
    submitted: isApprover ? 'Open it to approve or change it.' : 'Waiting for the depot to approve.',
    pending_modification_accept: 'The depot changed this request. Accept or reject the changes.',
    reserved: 'Go to the depot and pick up the tools (scan the tags).',
    checked_out: 'Return the tools before the due date.',
    return_pending: 'Return submitted — waiting for the depot to inspect the tools.',
    overdue: 'This is overdue. Return it now or ask for more time.',
  };

  return hints[status] || '';
}
