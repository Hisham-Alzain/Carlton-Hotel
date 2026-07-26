import { apiClient } from './apiClient.js';

function listFolios(params = {}) {
  return apiClient.get('/folios', { params });
}

function getFolioByReservation(reservationId) {
  return apiClient.get('/reservations/' + reservationId + '/folio');
}

function postLineItem(folioId, data) {
  return apiClient.post('/folios/' + folioId + '/line-items', data);
}

function disputeLineItem(folioId, lineItemId) {
  return apiClient.patch('/folios/' + folioId + '/line-items/' + lineItemId + '/dispute');
}

function settlePayment(folioId, data) {
  return apiClient.post('/folios/' + folioId + '/payments', data);
}

export const folioService = {
  list: listFolios,
  getByReservation: getFolioByReservation,
  postLineItem,
  disputeLineItem,
  settle: settlePayment,
};
