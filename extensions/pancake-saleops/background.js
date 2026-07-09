chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message?.type !== 'SALEOPS_PUSH_PAYLOAD') return;

  (async () => {
    const settings = await chrome.storage.sync.get([
      'saleopsBaseUrl',
      'saleopsToken',
      'saleopsAssignmentMode',
      'saleopsSelectedSaleUserId'
    ]);

    const { saleopsBaseUrl, saleopsToken } = settings;
    if (!saleopsBaseUrl || !saleopsToken) {
      throw new Error('Chưa cấu hình SaleOps Base URL hoặc Bearer token.');
    }

    const payload = {
      ...(message.payload || {}),
      saleops: {
        ...((message.payload || {}).saleops || {}),
        assignment_mode: settings.saleopsAssignmentMode || 'self',
        selected_sale_user_id: settings.saleopsSelectedSaleUserId
          ? Number(settings.saleopsSelectedSaleUserId)
          : undefined
      }
    };

    const res = await fetch(`${saleopsBaseUrl.replace(/\/+$/, '')}/api/v1/pancake/extension/orders`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${saleopsToken}`
      },
      body: JSON.stringify(payload)
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(json.message || `SaleOps lỗi HTTP ${res.status}`);
    }

    sendResponse({ ok: true, json });
  })().catch((error) => {
    sendResponse({ ok: false, message: error.message });
  });

  return true;
});
