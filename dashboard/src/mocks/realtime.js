import { queueItems } from './seed.js';

const listeners = new Set();

export const subscribeToQueue = (callback) => {
  listeners.add(callback);
  callback([...queueItems]);

  const timer = setInterval(() => {
    const item = {
      uuid: `queue-${Date.now()}`,
      type: 'service_request',
      source: 'app',
      category: 'maintenance',
      department: 'engineering',
      status: 'open',
      priority: 2,
      guest: { uuid: 'guest-live', name: 'Live Guest' },
      room: String(200 + Math.floor(Math.random() * 70)),
      subject: 'New simulated guest request',
      created_at: new Date().toISOString(),
    };
    queueItems.unshift(item);
    listeners.forEach((listener) => listener([...queueItems]));
  }, 18000);

  return () => {
    clearInterval(timer);
    listeners.delete(callback);
  };
};

export const updateQueueItem = (uuid, patch) => {
  const index = queueItems.findIndex((item) => item.uuid === uuid);
  if (index >= 0) {
    queueItems[index] = { ...queueItems[index], ...patch };
    listeners.forEach((listener) => listener([...queueItems]));
  }
  return queueItems[index];
};
