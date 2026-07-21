import { apiClient } from './apiClient.js';

function listTasks(params = {}) {
  return apiClient.get('/housekeeping/tasks', { params });
}

function getTask(id) {
  return apiClient.get('/housekeeping/tasks/' + id);
}

function assignTask(id, attendant) {
  return apiClient.patch('/housekeeping/tasks/' + id + '/assign', { attendant });
}

function updateTaskStatus(id, status) {
  return apiClient.patch('/housekeeping/tasks/' + id + '/status', { status });
}

function addNote(id, note) {
  return apiClient.patch('/housekeeping/tasks/' + id + '/note', { note });
}

export { listTasks, getTask, assignTask, updateTaskStatus, addNote };

export const housekeepingService = {
  list: listTasks,
  get: getTask,
  assign: assignTask,
  updateStatus: updateTaskStatus,
  addNote,
};
