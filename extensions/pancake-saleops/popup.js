const status = document.getElementById('status');

document.getElementById('push').addEventListener('click', async () => {
  status.textContent = 'Đang đọc dữ liệu Pancake...';
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (!tab?.id) {
    status.textContent = 'Không tìm thấy tab hiện tại.';
    return;
  }
  chrome.tabs.sendMessage(tab.id, { type: 'SALEOPS_PUSH_CURRENT_ORDER' }, (res) => {
    if (chrome.runtime.lastError) {
      status.textContent = 'Không gọi được content script. Hãy F5 trang Pancake rồi thử lại.';
      return;
    }
    status.textContent = res?.message || 'Đã gửi yêu cầu.';
  });
});
