const baseUrl = document.getElementById('baseUrl');
const token = document.getElementById('token');
const assignmentMode = document.getElementById('assignmentMode');
const selectedSaleUserId = document.getElementById('selectedSaleUserId');
const status = document.getElementById('status');

chrome.storage.sync.get([
  'saleopsBaseUrl',
  'saleopsToken',
  'saleopsAssignmentMode',
  'saleopsSelectedSaleUserId'
], (items) => {
  baseUrl.value = items.saleopsBaseUrl || '';
  token.value = items.saleopsToken || '';
  assignmentMode.value = items.saleopsAssignmentMode || 'self';
  selectedSaleUserId.value = items.saleopsSelectedSaleUserId || '';
});

document.getElementById('save').addEventListener('click', () => {
  const normalizedBaseUrl = baseUrl.value.trim().replace(/\/+$/, '');
  chrome.storage.sync.set({
    saleopsBaseUrl: normalizedBaseUrl,
    saleopsToken: token.value.trim(),
    saleopsAssignmentMode: assignmentMode.value,
    saleopsSelectedSaleUserId: selectedSaleUserId.value.trim()
  }, () => {
    status.textContent = 'Đã lưu cấu hình.';
  });
});
