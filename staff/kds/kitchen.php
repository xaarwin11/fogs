<?php
require_once __DIR__ . '/../../db.php';
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager','kitchen'])) {
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Kitchen Display</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .kds-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        .kds-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 0.5rem 0;
            border-bottom: 2px solid #e0e0e0;
        }
        .kds-header-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        .kds-btn { border: none; border-radius:6px; padding:0.45rem 0.7rem; font-weight:700; cursor:pointer; transition:background 0.12s, transform 0.06s; }
        .kds-btn.primary { background:#3ecf8e; color:#fff; }
        .kds-btn.primary:hover { background:#2fae6e; }
        .kds-btn.danger { background:#e94f4f; color:#fff; }
        .kds-btn.danger:hover { background:#d63939; }
        .kds-btn.unhide { background:#1976d2; color:#fff; }
        .kds-btn.unhide:hover { background:#0d5aa8; }
        .kds-card { background:#fff; border-radius:10px; padding:0.9rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:0.8rem; }
        .kds-order-header { font-weight:700; margin-bottom:0.6rem; display:flex; justify-content:space-between; align-items:center; }
        .kds-items { margin-top:0.5rem; }

        .kds-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.45); display:none; align-items:center; justify-content:center; z-index:9999; }
        .kds-modal { background:#fff; border-radius:10px; width:90%; max-width:900px; max-height:80vh; overflow:auto; padding:1rem; box-shadow:0 8px 40px rgba(0,0,0,0.2); }
        .kds-modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem; }
        .kds-modal-close { background:transparent; border:1px solid #ddd; padding:0.3rem 0.5rem; border-radius:6px; cursor:pointer; }
    </style>
<body>
    <?php require_once __DIR__ . '/../navbar.php'; ?>
    <br>
<div class="kds-container">
    <div class="kds-header">
        <div class="kds-header-title">🍳 Kitchen Orders</div>
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <button id="btnHiddenPopup" class="kds-btn">Hidden Orders</button>
            <button id="manualRefresh" class="kds-btn">↻ Refresh</button>
        </div>
    </div>

    <div id="kdsHiddenModal" class="kds-modal-backdrop">
        <div class="kds-modal">
            <div class="kds-modal-header">
                <div style="font-weight:700">Hidden Orders (not paid)</div>
                <div>
                    <button id="closeHiddenModal" class="kds-modal-close">Close</button>
                </div>
            </div>
            <div id="hiddenOrdersList"></div>
        </div>
    </div>

    <div class="kds-grid" id="ordersGrid"></div>
</div>

<script>
    const ordersGrid = document.getElementById('ordersGrid');
    
    function parsePhpDateTimeUTC8(str) {
        if (!str) return null;
        const match = str.match(/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/);
        if (match) {
            const year = parseInt(match[1]);
            const month = parseInt(match[2]) - 1;
            const day = parseInt(match[3]);
            const hour = parseInt(match[4]);
            const min = parseInt(match[5]);
            const sec = parseInt(match[6]);
            
            const utcDate = new Date(Date.UTC(year, month, day, hour - 8, min, sec));
            return utcDate;
        }
        return new Date(str);
    }
    
    function getCurrentTimeUTC8() {
        const now = new Date();
        const utcMs = now.getTime() + (now.getTimezoneOffset() * 60000);
        const utc8Ms = utcMs + (8 * 3600000);
        const utc8Time = new Date(utc8Ms);
        return utc8Time;
    }
    
    function getElapsedTime(createdAt) {
        const now = getCurrentTimeUTC8();
        const created = parsePhpDateTimeUTC8(createdAt);
        const elapsedMs = now - created;
        const elapsedSec = Math.floor(elapsedMs / 1000);
        const elapsedMin = Math.floor(elapsedSec / 60);
        const elapsedHr = Math.floor(elapsedMin / 60);
        
        if (elapsedHr > 0) {
            return elapsedHr + 'h ' + (elapsedMin % 60) + 'm';
        } else if (elapsedMin > 0) {
            return elapsedMin + 'm ' + (elapsedSec % 60) + 's';
        } else {
            return elapsedSec + 's';
        }
    }
    
    function renderOrders(container, data, hiddenView = false) {
        container.innerHTML = '';
        (data.orders || []).forEach(order => {
            const c = document.createElement('div');
            c.className = 'kds-card';

            const allServed = order.items.every(it => (parseInt(it.quantity || 0) - parseInt(it.served || 0)) === 0);
            if (allServed) {
                c.classList.add('served');
            }

            const header = document.createElement('div');
            header.className = 'kds-order-header';
            const elapsedTime = getElapsedTime(order.updated_at || order.created_at);
            const leftSpan = document.createElement('div');
            leftSpan.textContent = '🍽 Table ' + order.table_id + ' — #' + order.order_id;
            const rightSpan = document.createElement('div');
            rightSpan.style.fontSize = '0.85rem';
            rightSpan.style.background = '#f0f0f0';
            rightSpan.style.padding = '0.2rem 0.6rem';
            rightSpan.style.borderRadius = '3px';
            rightSpan.style.color = '#666';
            rightSpan.textContent = '⏱ ' + elapsedTime;
            header.appendChild(leftSpan);
            header.appendChild(rightSpan);

            const itemsDiv = document.createElement('div');
            itemsDiv.className = 'kds-items';

            order.items.forEach(it => {
                const itemRow = document.createElement('div');
                itemRow.style.cssText = 'display:grid; grid-template-columns:1fr auto auto; align-items:center; padding:0.45rem 0; border-bottom:1px solid #f0f0f0; gap:0.4rem; font-size:0.95rem;';

                const pending = (parseInt(it.quantity || 0) - parseInt(it.served || 0));

                const name = document.createElement('div');
                name.style.fontWeight = '600';
                name.style.textAlign = 'left';
                name.textContent = it.name + ' ×' + it.quantity + (pending > 0 ? (' (' + pending + ' pending)') : '');

                const status = document.createElement('span');
                status.style.cssText = (pending === 0 ? 'background:#e94f4f; color:#fff;' : 'background:#3ecf8e; color:#fff;') + ' padding:0.2rem 0.6rem; border-radius:3px; font-size:0.82rem; font-weight:700; min-width:60px; text-align:center;';
                status.textContent = pending === 0 ? '✓ Served' : ('● Pending x' + pending);

                const serveBtn = document.createElement('button');
                serveBtn.className = 'kds-btn';
                serveBtn.style.padding = '0.25rem 0.5rem';
                serveBtn.textContent = (pending === 0) ? 'Undo' : 'Serve';
                serveBtn.onclick = () => {
                    if (pending === 0) {
                        if (confirm('Mark this item as not served? It will reappear on KDS.')) {
                            toggleItemServed(it.order_item_id, 0);
                        }
                    } else {
                        if (confirm('Serve 1 of this item?')) {
                            toggleItemServed(it.order_item_id, (parseInt(it.served || 0) + 1));
                        }
                    }
                };

                itemRow.appendChild(name);
                itemRow.appendChild(status);
                itemRow.appendChild(serveBtn);
                itemsDiv.appendChild(itemRow);
            });

            const actions = document.createElement('div');
            actions.style.cssText = 'margin-top:0.6rem; display:grid; grid-template-columns:1fr auto; gap:0.5rem; border-top:1px solid #f0f0f0; padding-top:0.6rem;';

            const markBtn = document.createElement('button');
            markBtn.className = 'kds-btn primary';
            markBtn.textContent = allServed ? '↩ Undo' : '✓ Serve All';
            markBtn.onclick = () => {
                const desired = allServed ? 0 : 1;
                const promptMsg = desired ? 'Mark all items as served? This will remove the order from KDS.' : 'Undo served for all items? This will return the order to KDS if it has pending KDS items.';
                if (!confirm(promptMsg)) return;
                const promises = order.items.map(it => {
                    const pending = (parseInt(it.quantity || 0) - parseInt(it.served || 0));
                    if ((desired === 1 && pending > 0) || (desired === 0 && (parseInt(it.served || 0) > 0))) {
                        const setVal = desired === 1 ? parseInt(it.quantity || 0) : 0;
                        return toggleItemServed(it.order_item_id, setVal, true);
                    }
                    return Promise.resolve();
                });
                Promise.all(promises).then(() => fetchKitchenOrders());
            };

            const deleteBtn = document.createElement('button');
            if (!hiddenView) {
                deleteBtn.className = 'kds-btn danger';
                deleteBtn.textContent = 'Hide';
                deleteBtn.title = 'Hide order from KDS';
                deleteBtn.onclick = () => {
                    if (confirm('Hide this order from KDS?')) {
                        fetch('hide_kds_order.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: order.order_id }) })
                            .then(r => r.json()).then(d => { if (d && d.success) fetchKitchenOrders(); }).catch(err => console.warn('hide order failed', err));
                    }
                };
            } else {
                deleteBtn.className = 'kds-btn unhide';
                deleteBtn.textContent = 'Unhide';
                deleteBtn.title = 'Unhide order';
                deleteBtn.onclick = () => {
                    if (confirm('Restore this order to the active KDS view?')) {
                        fetch('unhide_kds_order.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: order.order_id }) })
                            .then(r => r.json()).then(d => { if (d && d.success) loadHiddenOrders(); }).catch(err => console.warn('unhide order failed', err));
                    }
                };
            }

            actions.appendChild(markBtn);
            actions.appendChild(deleteBtn);

            c.appendChild(header);
            c.appendChild(itemsDiv);
            c.appendChild(actions);
            container.appendChild(c);
        });
    }

    function fetchKitchenOrders() {
        const url = 'get_kitchen_orders.php';
        fetch(url, { credentials: 'same-origin' }).then(r => r.json()).then(data => {
            if (!data || !data.success) return;
            renderOrders(ordersGrid, data, false);
        }).catch(err => console.warn('kitchen refresh failed', err));
    }

    const hiddenModal = document.getElementById('kdsHiddenModal');
    const hiddenList = document.getElementById('hiddenOrdersList');
    function loadHiddenOrders() {
        fetch('get_kitchen_orders.php?hidden=1', { credentials: 'same-origin' }).then(r => r.json()).then(data => {
            if (!data || !data.success) return;
            renderOrders(hiddenList, data, true);
        }).catch(err => console.warn('load hidden failed', err));
    }

    document.getElementById('btnHiddenPopup').addEventListener('click', () => {
        hiddenModal.style.display = 'flex';
        loadHiddenOrders();
    });
    document.getElementById('closeHiddenModal').addEventListener('click', () => hiddenModal.style.display = 'none');
    hiddenModal.addEventListener('click', (e) => { if (e.target === hiddenModal) hiddenModal.style.display = 'none'; });

    function toggleItemServed(id, setVal, skipRefresh) {
        const body = { order_item_id: id };
        if (typeof setVal !== 'undefined' && setVal !== null) body.set = setVal;
        return fetch('toggle_item_served.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
            .then(r => r.json()).then(data => {
                if (!data || !data.success) return Promise.reject('failed');
                if (!skipRefresh) fetchKitchenOrders();
                return data;
            }).catch(err => { console.warn('toggle item failed', err); return Promise.reject(err); });
    }
    
    function deleteOrder(orderId) {
        fetch('checkout_order.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: orderId }) })
            .then(r => r.json()).then(data => {
                if (data && data.success) {
                    fetchKitchenOrders();
                }
            }).catch(err => console.warn('delete order failed', err));
    }

    fetchKitchenOrders();
    let poll = setInterval(fetchKitchenOrders, 6000);
    document.getElementById('manualRefresh').addEventListener('click', fetchKitchenOrders);

    const tabActive = document.getElementById('tabActive');
    const tabHidden = document.getElementById('tabHidden');
    function setActiveTab(hidden) {
        currentHiddenView = !!hidden;
        tabActive.classList.toggle('active', !currentHiddenView);
        tabHidden.classList.toggle('active', currentHiddenView);
        fetchKitchenOrders();
    }
    tabActive.addEventListener('click', () => setActiveTab(false));
    tabHidden.addEventListener('click', () => setActiveTab(true));
</script>
</body>
</html>
