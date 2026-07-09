(function () {
  const BUTTON_ID = 'saleops-pancake-push-btn';

  function text() {
    return document.body?.innerText || '';
  }

  function firstMatch(patterns, source = text()) {
    for (const pattern of patterns) {
      const match = source.match(pattern);
      if (match?.[1]) return match[1].trim();
    }
    return '';
  }

  function extractPhone(source = text()) {
    const patterns = [
      /(?:SĐT|Số điện thoại|Điện thoại|Phone)\s*[:\-]?\s*((?:0|\+84)?[0-9 .\-]{8,14})/i,
      /((?:\+?84|0)[0-9 .\-]{8,13})/
    ];

    for (const pattern of patterns) {
      const match = source.match(pattern);
      if (match?.[1]) {
        const digits = match[1].replace(/\D+/g, '').replace(/^84/, '0');
        if (digits.length >= 9) return digits;
      }
    }

    return '';
  }

  function extractCustomerName(source = text()) {
    return firstMatch([
      /(?:Khách hàng|Tên khách|Họ tên|Người nhận)\s*[:\-]?\s*([^\n]{2,80})/i,
      /(?:Customer|Name)\s*[:\-]?\s*([^\n]{2,80})/i
    ], source);
  }

  function extractAddress(source = text()) {
    return firstMatch([
      /(?:Địa chỉ|Address)\s*[:\-]?\s*([^\n]{5,220})/i,
      /(?:Giao đến|Nhận hàng tại)\s*[:\-]?\s*([^\n]{5,220})/i
    ], source);
  }

  function extractNote(source = text()) {
    return firstMatch([
      /(?:Ghi chú|Tin nhắn|Nội dung|Note)\s*[:\-]?\s*([^\n]{2,500})/i
    ], source);
  }

  function extractProducts() {
    const rows = [...document.querySelectorAll('tr, [class*=product], [class*=item], [class*=variation]')];
    const candidates = [];
    for (const row of rows) {
      const line = (row.innerText || '').replace(/\s+/g, ' ').trim();
      if (!line || line.length < 4 || line.length > 220) continue;
      if (!/(₫|vnd|[0-9][.,][0-9]{3}|x\s*\d+|SL|Số lượng)/i.test(line)) continue;
      candidates.push({ product_name: line, quantity: 1, unit_price: 0 });
      if (candidates.length >= 10) break;
    }
    return candidates;
  }

  function extractPayload() {
    const source = text();
    const url = new URL(location.href);
    const idFromUrl = firstMatch([/orders?\/([0-9A-Za-z_\-]+)/, /conversations?\/([0-9A-Za-z_\-]+)/], location.pathname);

    return {
      pancake_order_id: idFromUrl || undefined,
      conversation_id: url.searchParams.get('c_id') || url.searchParams.get('conversation_id') || undefined,
      shop_id: firstMatch([/\/shop\/(\d+)/, /shop_id[=/](\d+)/], location.href) || undefined,
      page_id: url.searchParams.get('page_id') || undefined,
      source_name: document.title || 'Pancake',
      customer_name: extractCustomerName(source),
      customer_phone: extractPhone(source),
      shipping_address: extractAddress(source),
      message: extractNote(source),
      items: extractProducts(),
      raw: {
        url: location.href,
        title: document.title,
        captured_at: new Date().toISOString(),
        text_sample: source.slice(0, 4000)
      }
    };
  }

  async function pushCurrentOrder() {
    const payload = extractPayload();
    if (!payload.customer_phone && !payload.customer_name && !payload.message) {
      throw new Error('Chưa đọc được thông tin khách hàng trên trang Pancake. Hãy mở chi tiết hội thoại/đơn rồi thử lại.');
    }

    return new Promise((resolve, reject) => {
      chrome.runtime.sendMessage({ type: 'SALEOPS_PUSH_PAYLOAD', payload }, (res) => {
        if (chrome.runtime.lastError) {
          reject(new Error(chrome.runtime.lastError.message || 'Không gọi được background worker.'));
          return;
        }

        if (!res?.ok) {
          reject(new Error(res?.message || 'Đẩy dữ liệu về SaleOps thất bại.'));
          return;
        }

        resolve(res.json);
      });
    });
  }

  function ensureButton() {
    if (document.getElementById(BUTTON_ID)) return;
    const btn = document.createElement('button');
    btn.id = BUTTON_ID;
    btn.textContent = 'Đẩy về SaleOps';
    btn.style.cssText = 'position:fixed;right:18px;bottom:88px;z-index:2147483647;background:#3782dc;color:#fff;border:0;border-radius:999px;padding:10px 16px;font-weight:700;box-shadow:0 8px 24px rgba(15,23,42,.22);cursor:pointer';
    btn.addEventListener('click', async () => {
      const original = btn.textContent;
      btn.textContent = 'Đang đẩy...';
      btn.disabled = true;
      try {
        const json = await pushCurrentOrder();
        btn.textContent = json?.data?.order?.order_code ? `Đã tạo ${json.data.order.order_code}` : 'Đã đẩy lead';
      } catch (e) {
        alert(e.message);
        btn.textContent = original;
      } finally {
        setTimeout(() => { btn.disabled = false; btn.textContent = original; }, 2500);
      }
    });
    document.documentElement.appendChild(btn);
  }

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message?.type !== 'SALEOPS_PUSH_CURRENT_ORDER') return;
    pushCurrentOrder()
      .then((json) => sendResponse({ ok: true, message: json?.message || 'Đã đẩy về SaleOps.' }))
      .catch((error) => sendResponse({ ok: false, message: error.message }));
    return true;
  });

  ensureButton();
  const observer = new MutationObserver(() => ensureButton());
  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
