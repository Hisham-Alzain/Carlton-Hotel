import { cloneMockData, matchesSearch, paginateItems, throwMockError } from '../envelope.js';
import { addMinutesUtc } from '../../utils/date.js';

const baseDay = '2026-06-28T00:00:00.000Z';
function at(min) { return addMinutesUtc(baseDay, min); }

const tasksSeed = [
  {
    id: 'hk_001',
    room_id: 'room_301',
    room_number: '301',
    floor: 3,
    room_type_name: 'Deluxe King',
    status: 'pending',
    priority: 'rush',
    task_type: 'checkout_clean',
    assigned_to: null,
    notes: 'VIP guest Dana Abboud due out 12:00',
    estimated_minutes: 45,
    created_at: at(-90),
    updated_at: at(-90),
  },
  {
    id: 'hk_002',
    room_id: 'room_303',
    room_number: '303',
    floor: 3,
    room_type_name: 'Deluxe King',
    status: 'in_progress',
    priority: 'normal',
    task_type: 'stayover',
    assigned_to: 'Layth Saleh',
    notes: null,
    estimated_minutes: 30,
    created_at: at(-120),
    updated_at: at(-40),
  },
  {
    id: 'hk_003',
    room_id: 'room_405',
    room_number: '405',
    floor: 4,
    room_type_name: 'Premium Suite',
    status: 'done',
    priority: 'normal',
    task_type: 'checkout_clean',
    assigned_to: 'Fatima Nour',
    notes: null,
    estimated_minutes: 60,
    created_at: at(-180),
    updated_at: at(-60),
  },
  {
    id: 'hk_004',
    room_id: 'room_407',
    room_number: '407',
    floor: 4,
    room_type_name: 'Premium Suite',
    status: 'pending',
    priority: 'normal',
    task_type: 'stayover',
    assigned_to: null,
    notes: null,
    estimated_minutes: 30,
    created_at: at(-60),
    updated_at: at(-60),
  },
  {
    id: 'hk_005',
    room_id: 'room_502',
    room_number: '502',
    floor: 5,
    room_type_name: 'Premium Twin',
    status: 'pending',
    priority: 'rush',
    task_type: 'checkout_clean',
    assigned_to: null,
    notes: 'New arrival expected 14:00',
    estimated_minutes: 45,
    created_at: at(-30),
    updated_at: at(-30),
  },
  {
    id: 'hk_006',
    room_id: 'room_701',
    room_number: '701',
    floor: 7,
    room_type_name: 'Executive Suite',
    status: 'inspected',
    priority: 'normal',
    task_type: 'inspection',
    assigned_to: 'Layth Saleh',
    notes: 'Pre-arrival VIP check complete',
    estimated_minutes: 20,
    created_at: at(-200),
    updated_at: at(-150),
  },
  {
    id: 'hk_007',
    room_id: 'room_703',
    room_number: '703',
    floor: 7,
    room_type_name: 'Executive Suite',
    status: 'pending',
    priority: 'normal',
    task_type: 'deep_clean',
    assigned_to: null,
    notes: 'Scheduled maintenance clean',
    estimated_minutes: 90,
    created_at: at(-10),
    updated_at: at(-10),
  },
];

let tasks = cloneMockData(tasksSeed);

const VALID_STATUSES = ['pending', 'in_progress', 'done', 'inspected'];

export function listHousekeepingTasks(params = {}) {
  const { status, floor, assigned_to, query, page, per_page } = params;

  let filtered = tasks.filter((task) => {
    if (status && task.status !== status) return false;
    if (floor !== undefined && floor !== null && String(task.floor) !== String(floor)) return false;
    if (assigned_to !== undefined && assigned_to !== null && task.assigned_to !== assigned_to) return false;
    if (query && !matchesSearch([task.room_number, task.assigned_to, task.task_type, task.notes].filter(Boolean), query)) return false;
    return true;
  });

  return paginateItems(filtered, { page, per_page });
}

export function getTaskById(id) {
  const task = tasks.find((t) => t.id === id);
  if (!task) throwMockError({ message: 'Housekeeping task not found', error_code: 'not_found' }, 404);
  return { ...task };
}

export function assignTask(id, attendant) {
  const task = tasks.find((t) => t.id === id);
  if (!task) throwMockError({ message: 'Housekeeping task not found', error_code: 'not_found' }, 404);
  task.assigned_to = attendant;
  task.updated_at = new Date().toISOString();
  return { ...task };
}

export function updateTaskStatus(id, status) {
  if (!VALID_STATUSES.includes(status)) {
    throwMockError({ message: 'Invalid status value', error_code: 'validation_error', errors: { status: ['Must be one of: ' + VALID_STATUSES.join(', ')] } }, 422);
  }
  const task = tasks.find((t) => t.id === id);
  if (!task) throwMockError({ message: 'Housekeeping task not found', error_code: 'not_found' }, 404);
  task.status = status;
  task.updated_at = new Date().toISOString();
  return { ...task };
}

export function addTaskNote(id, note) {
  const task = tasks.find((t) => t.id === id);
  if (!task) throwMockError({ message: 'Housekeeping task not found', error_code: 'not_found' }, 404);
  task.notes = note;
  task.updated_at = new Date().toISOString();
  return { ...task };
}

export function createHousekeepingTask(taskData) {
  const task = {
    id: 'hk_' + Date.now().toString().slice(-6),
    room_id: taskData.room_id,
    room_number: taskData.room_number,
    floor: taskData.floor,
    room_type_name: taskData.room_type_name || null,
    status: 'pending',
    priority: taskData.priority || 'rush',
    task_type: taskData.task_type || 'checkout_clean',
    assigned_to: null,
    notes: taskData.notes || null,
    estimated_minutes: 45,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  };
  tasks.push(task);
  return { ...task };
}

export function resetHousekeepingMockData() {
  tasks = cloneMockData(tasksSeed);
}
