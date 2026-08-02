/**
 * Date helpers for `<input type="datetime-local">`.
 *
 * The inputs speak naive local time ("2026-07-30T09:00") while the API stores
 * UTC. Sending the raw input string makes the server read local wall-clock time
 * as UTC, which shifts every date by the user's offset. Always convert with
 * `fromLocalInput` before posting, and render with `toLocalInput`.
 */

function pad(value) {
  return String(value).padStart(2, '0');
}

/** UTC/ISO timestamp -> value a datetime-local input can display. */
export function toLocalInput(value) {
  if (!value) return '';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** datetime-local value -> ISO string carrying the browser's offset. */
export function fromLocalInput(value) {
  if (!value) return null;

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;

  return date.toISOString();
}

/** True when a datetime-local value points at a different minute than the stored one. */
export function localInputChanged(inputValue, storedValue) {
  if (!inputValue) return false;

  const next = new Date(inputValue);
  if (Number.isNaN(next.getTime())) return false;
  if (!storedValue) return true;

  const current = new Date(storedValue);
  if (Number.isNaN(current.getTime())) return true;

  return Math.floor(next.getTime() / 60000) !== Math.floor(current.getTime() / 60000);
}

export function formatDate(value) {
  return value
    ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
    : '—';
}

export function formatDateTime(value) {
  return value
    ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
    : '';
}
