import { toast } from 'sonner';

const recentMessages = new Map();
const DEDUP_MS = 3000;

function isDuplicate(message) {
  const now = Date.now();
  if (recentMessages.has(message) && now - recentMessages.get(message) < DEDUP_MS) {
    return true;
  }
  recentMessages.set(message, now);
  return false;
}

export function showSuccess(message) {
  toast.success(message);
}

export function showError(message) {
  if (isDuplicate(message)) return;
  toast.error(message);
}

export function showWarning(message) {
  if (isDuplicate(message)) return;
  toast.warning(message);
}
