const baseUrl = document.getElementById('baseUrl');
const token = document.getElementById('token');
const status = document.getElementById('status');

chrome.storage.sync.get(['saleopsBaseUrl', 'saleopsToken'], (items) => {
  baseUrl.value = items.saleopsBaseUrl || '';
  token.value = items.saleopsToken || '';
});

document.getElementById('save').addEventListener('click', () => {
  const normalizedBaseUrl = baseUrl.value.trim().replace(/\/+$/, '');
  chrome.storage.sync.set({
    saleopsBaseUrl: normalizedBaseUrl,
    saleopsToken: token.value.trim()
  }, () => {
    status.textContent = 'Đã lưu cấu hình.';
  });
});
