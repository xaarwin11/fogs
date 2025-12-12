<?php
require_once __DIR__ . '/../../db.php';
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: /fogs/login.php');
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager'])) {
    header('Location: /fogs/customer/dashboard.php');
    exit;
}

$tables = [];
try {
    $mysqli = get_db_conn();
    $stmt = $mysqli->prepare('SELECT id, table_number FROM `tables` ORDER BY table_number ASC');
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $tables[] = ['id' => (int)$row['id'], 'table_number' => (int)$row['table_number']];
        }
        $res->free();
        $stmt->close();
    }
    $mysqli->close();
} catch (Exception $ex) {
    error_log('POS DB error: ' . $ex->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>POS - Point of Sale</title>
    <link rel="stylesheet" href="/fogs/assets/style.css">
    <style>
        .pos-container { display:grid; grid-template-columns:1fr 300px; gap:1.5rem; max-width:1200px; margin:1.5rem auto; padding:1rem; }
        .products-section { }
        .product-categories { display:flex; gap:0.5rem; margin-bottom:1rem; flex-wrap:wrap; }
        .category-tab { padding:0.5rem 1rem; background:#e8dfd2; color:#333; border:none; border-radius:6px; cursor:pointer; font-weight:600; transition:background 0.2s; }
        .category-tab.active { background:#6B4226; color:#fff; }
        .category-tab:hover { background:#7a5a4e; color:#fff; }
        .products-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:1rem; }
        .product-card { background:#fff; border-radius:12px; padding:1rem; box-shadow:0 2px 8px rgba(0,0,0,0.07); cursor:pointer; transition:transform 0.2s, box-shadow 0.2s; text-align:center; display:flex; flex-direction:column; justify-content:center; }
        .product-card:hover { transform:translateY(-4px); box-shadow:0 4px 16px rgba(0,0,0,0.12); }
        .product-name { font-weight:600; font-size:0.95rem; margin-bottom:0.25rem; }
        .product-price { font-size:1.2rem; color:#c62828; font-weight:700; margin-bottom:0.5rem; } 
        .currency { font-size:0.9rem; }
        .cart-section { background:#fff; border-radius:12px; padding:1.5rem; box-shadow:0 2px 12px rgba(0,0,0,0.07); }
        .cart-header { font-size:1.3rem; font-weight:700; margin-bottom:1rem; text-align:center; border-bottom:2px solid #6B4226; padding-bottom:0.75rem; }
        .cart-items { max-height:400px; overflow-y:auto; margin-bottom:1rem; }
        .cart-item { display:grid; grid-template-columns:1fr auto auto auto; gap:0.5rem; align-items:center; padding:0.75rem 0; border-bottom:1px solid #e0e0e0; font-size:0.9rem; }
        .item-name { font-weight:600; text-align:left; }
        .item-qty-input { width:50px; padding:0.4rem; border:1px solid #ccc; border-radius:4px; text-align:center; font-weight:600; font-size:0.9rem; cursor:pointer; }
        .item-qty-input:hover, .item-qty-input:focus { border-color:#6B4226; background:#f9f9f9; outline:none; }
        .item-price { font-weight:700; text-align:right; min-width:70px; }
        .item-remove { background:#e94f4f; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.6rem; cursor:pointer; font-size:0.85rem; font-weight:600; }
        .item-remove:hover { background:#d63939; }
        .cart-total { font-size:1.4rem; font-weight:700; text-align:right; padding:1rem 0; border-top:2px solid #6B4226; margin:1rem 0; }
        .cart-actions { display:flex; gap:0.75rem; flex-direction:column; }
        .checkout-btn, .save-btn, .clear-btn { padding:0.75rem; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:1rem; transition:background 0.2s; }
        .checkout-btn { background:#2e7d32; color:#fff; }
        .checkout-btn:hover { background:#24601e; }
        .save-btn { background:#1976d2; color:#fff; }
        .save-btn:hover { background:#0d5aa8; }
        .clear-btn { background:#e0e0e0; color:#333; }
        .clear-btn:hover { background:#ccc; }
        .table-select { margin-bottom:1.5rem; }
        .table-select select { width:100%; padding:0.75rem; border:1px solid #ccc; border-radius:6px; font-size:1rem; }
        .empty-cart { text-align:center; color:#999; padding:1rem; }
        @media (max-width:768px) { .pos-container { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../navbar.php'; ?>
    
    <div class="pos-container">
        <div class="products-section">
            <h2>Menu</h2>
            <div id="categoriesContainer" class="product-categories"></div>
            <div id="productsGrid" class="products-grid"></div>
        </div>
        
        <div class="cart-section">
            <div class="table-select">
                <label for="tableSelect"><strong>Select Table</strong></label>
                <select id="tableSelect">
                    <option value="">-- Select a table --</option>
                    <?php foreach ($tables as $t): ?>
                        <option value="<?php echo $t['id']; ?>">Table <?php echo htmlspecialchars($t['table_number']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="cart-header">BILL</div>
            <div id="cartItems" class="cart-items">
                <div class="empty-cart">Select a table and add items</div>
            </div>
            <div class="cart-total">Total: <span id="cartTotal">₱0.00</span></div>
            <div class="cart-actions">
                <button class="save-btn" id="saveBtn">Save Bill</button>
                <button class="checkout-btn" id="checkoutBtn">Checkout</button>
                <button class="clear-btn" id="clearBtn">Clear Cart</button>
            </div>
        </div>
    </div>

    <div id="paymentPopup" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); align-items:center; justify-content:center; z-index:9999;">
        <div style="background:#fff; border-radius:8px; padding:1rem; width:420px; max-width:95%; box-shadow:0 8px 40px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                <div style="font-weight:700">Checkout Payment</div>
                <button id="pmClose" style="background:transparent;border:1px solid #ddd;padding:0.25rem 0.5rem;border-radius:6px;cursor:pointer;">Close</button>
            </div>
            <div style="margin-bottom:0.5rem;"><strong>Total:</strong> <span id="pmTotal">₱0.00</span></div>
            <div style="margin-bottom:0.5rem;">
                <div style="font-weight:600; margin-bottom:0.25rem;">Payment Method</div>
                <label style="margin-right:0.75rem; display:inline-flex; align-items:center; gap:0.5rem;"><input type="radio" name="pmMethod" id="pmCash" value="cash"> Cash</label>
                <label style="display:inline-flex; align-items:center; gap:0.5rem;"><input type="radio" name="pmMethod" id="pmGcash" value="gcash"> Gcash</label>
            </div>
            <div style="margin-bottom:0.5rem;"><label>Amount Given</label><br><input id="pmGiven" type="number" min="0" step="0.01" style="width:100%; padding:0.45rem; margin-top:0.25rem;" /></div>
            <div style="margin-bottom:0.75rem;"><strong>Change:</strong> <span id="pmChange">₱0.00</span></div>
            <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                <button id="pmCancel" class="clear-btn">Cancel</button>
                <button id="pmConfirm" class="checkout-btn">Confirm & Pay</button>
            </div>
        </div>
    </div>

    <script>
    let selectedTableId = null;
    let cart = {};
    let currentOrderId = null;
    let saveCartTimeout = null;

    const tableSelect = document.getElementById('tableSelect');
    const productsGrid = document.getElementById('productsGrid');
    const categoriesContainer = document.getElementById('categoriesContainer');
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const saveBtn = document.getElementById('saveBtn');
    const clearBtn = document.getElementById('clearBtn');

    let allProducts = {};
    let currentCategory = null;

    
    function updateOrderTime(orderId) {
        if (!orderId) return;
        fetch('update_order_time.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        }).then(r => r.json()).then(data => {
            if (!data.success) console.warn('Failed to update order time:', data.error);
        }).catch(err => console.warn('Failed to update order time:', err));
    }

    function saveCart(allowEmpty = false) {
        if (!selectedTableId) {
            alert('Please select a table first');
            return;
        }
        const items = Object.values(cart).map(item => ({
            product_id: item.id,
            quantity: item.qty
        }));
        if (items.length === 0 && !allowEmpty) {
            alert('Cart is empty. Add items before saving.');
            return;
        }
        console.log('saveCart: sending to server', { table_id: selectedTableId, items: items, allowEmpty: allowEmpty });
        fetch('save_pos_cart.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ table_id: selectedTableId, items: items })
        }).then(r => {
            console.log('saveCart: response status', r.status);
            return r.json();
        })
        .then(data => {
            console.log('saveCart: response data', data);
            if (data.success) {
                currentOrderId = data.order_id;
                updateOrderTime(data.order_id);
                console.log('Cart saved successfully! Order ID:', data.order_id);
                if (!allowEmpty) {
                    alert('Bill saved for Table ' + selectedTableId);
                }
            } else {
                console.error('Save cart error:', data.error);
                if (!allowEmpty) {
                    alert('Error saving bill: ' + data.error);
                }
            }
        })
        .catch(err => {
            console.error('Failed to save cart:', err);
            if (!allowEmpty) {
                alert('Error saving bill');
            }
        });
    }


    function loadCart() {
        console.log('loadCart: selectedTableId=', selectedTableId);
        
        if (!selectedTableId) {
            cart = {};
            currentOrderId = null;
            updateCart();
            return;
        }

        console.log('loadCart: fetching cart from server for table', selectedTableId);
        
        fetch('get_pos_cart.php?table_id=' + encodeURIComponent(selectedTableId), { credentials: 'same-origin' })
            .then(r => {
                console.log('loadCart: response status', r.status);
                return r.json();
            })
            .then(data => {
                console.log('loadCart: response data', data);
                if (data.success) {
                    currentOrderId = data.order_id;
                    if (data.items && data.items.length > 0) {
                        
                        cart = {};
                        data.items.forEach(item => {
                            const itemId = 'product_' + item.product_id;
                            cart[itemId] = {
                                id: item.product_id,
                                name: item.name,
                                price: item.price,
                                qty: item.quantity
                            };
                        });
                        console.log('loadCart: loaded', Object.keys(cart).length, 'items');
                    } else {
                        cart = {};
                        console.log('loadCart: no items found');
                    }
                    updateCart();
                } else {
                    console.error('Load cart error:', data.error);
                    cart = {};
                    currentOrderId = null;
                    updateCart();
                }
            })
            .catch(err => {
                console.error('Failed to load cart:', err);
                cart = {};
                updateCart();
            });
    }

    function loadProducts() {
        fetch('get_products.php', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    allProducts = data.categories;
                    renderCategories();
                    if (Object.keys(allProducts).length > 0) {
                        currentCategory = Object.keys(allProducts)[0];
                        renderProducts(currentCategory);
                    }
                }
            })
            .catch(err => console.error('Failed to load products:', err));
    }

    function renderCategories() {
        categoriesContainer.innerHTML = '';
        Object.keys(allProducts).forEach(cat => {
            const btn = document.createElement('button');
            btn.className = 'category-tab' + (cat === currentCategory ? ' active' : '');
            btn.textContent = cat;
            btn.addEventListener('click', () => {
                currentCategory = cat;
                document.querySelectorAll('.category-tab').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                renderProducts(cat);
            });
            categoriesContainer.appendChild(btn);
        });
    }

    function renderProducts(category) {
        productsGrid.innerHTML = '';
        (allProducts[category] || []).forEach(product => {
            const card = document.createElement('div');
            card.className = 'product-card';
            card.innerHTML = `
                <div class="product-name">${product.name}</div>
                <div class="product-price">₱${product.price.toFixed(2)}</div>
            `;
            card.addEventListener('click', () => addToCart(product.id, product.name, product.price));
            productsGrid.appendChild(card);
        });
    }

    function addToCart(productId, productName, productPrice) {
        console.log('addToCart: productId=', productId, ', selectedTableId=', selectedTableId);
        
        if (!selectedTableId) {
            alert('Please select a table first');
            return;
        }
        const itemId = 'product_' + productId;
        if (cart[itemId]) {
            cart[itemId].qty++;
        } else {
            cart[itemId] = { id: productId, name: productName, price: productPrice, qty: 1 };
        }
        console.log('addToCart: cart is now', cart);
        updateCart();
        updateTableStatus();
    }

    function removeFromCart(itemId) {
        delete cart[itemId];
        updateCart();
        if (Object.keys(cart).length === 0) {
            updateTableStatus();
        }
    }

    function updateCart() {
        cartItems.innerHTML = '';
        let total = 0;
        Object.entries(cart).forEach(([itemId, item]) => {
            const lineTotal = item.price * item.qty;
            total += lineTotal;
            const row = document.createElement('div');
            row.className = 'cart-item';
            row.innerHTML = `
                <div class="item-name">${item.name}</div>
                <input type="number" class="item-qty-input" value="${item.qty}" min="1" data-item-id="${itemId}" onchange="updateQuantity('${itemId}', this.value)">
                <div class="item-price">₱${lineTotal.toFixed(2)}</div>
                <button class="item-remove" onclick="removeFromCart('${itemId}')">×</button>
            `;
            cartItems.appendChild(row);
        });
        if (Object.keys(cart).length === 0) {
            cartItems.innerHTML = '<div class="empty-cart">No items in cart</div>';
        }
        cartTotal.textContent = '₱' + total.toFixed(2);
    }

    function updateQuantity(itemId, newQty) {
        const qty = parseInt(newQty) || 1;
        if (qty < 1) {
            removeFromCart(itemId);
        } else {
            cart[itemId].qty = qty;
            updateCart();
        }
    }

    function clearCart() {
        if (!selectedTableId) return;
        cart = {};
        updateCart();
        
        console.log('clearCart: auto-saving empty cart for table', selectedTableId);
        saveCart(true); 
        if (currentOrderId) updateOrderTime(currentOrderId);
        
        updateTableStatus();
    }

    function updateTableStatus() {
        if (!selectedTableId) return;
        const hasItems = Object.keys(cart).length > 0;
        const shouldBeOccupied = hasItems ? 1 : 0;
        
            fetch('/fogs/staff/toggle_table_status.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: selectedTableId, occupied: shouldBeOccupied })
        }).then(r => r.json())
        .catch(err => console.error('Failed to update table status:', err));
    }

    function checkout() {
        if (!selectedTableId || Object.keys(cart).length === 0) {
            alert('Please select a table and add items');
            return;
        }
        
        saveCart();

    
        setTimeout(() => {
            if (!currentOrderId) {
                alert('Error: No order found. Please try again.');
                return;
            }

            const popup = document.getElementById('paymentPopup');
            const pmTotal = document.getElementById('pmTotal');
            const pmGiven = document.getElementById('pmGiven');
            const pmChange = document.getElementById('pmChange');
            if (!popup || !pmTotal || !pmGiven || !pmChange) {
                alert('Payment popup not available');
                return;
            }

            const total = Object.values(cart).reduce((s, it) => s + (it.price * it.qty), 0);
            pmTotal.textContent = '₱' + total.toFixed(2);
            pmGiven.value = total.toFixed(2);
            pmChange.textContent = '₱0.00';
            const cash = document.getElementById('pmCash'); if (cash) cash.checked = true;
            popup.style.display = 'flex';

            function computeChange() {
                const given = parseFloat(pmGiven.value) || 0;
                const change = Math.max(0, given - total);
                pmChange.textContent = '₱' + change.toFixed(2);
            }

            pmGiven.removeEventListener('input', computeChange);
            pmGiven.addEventListener('input', computeChange);

            document.getElementById('pmCancel').onclick = () => { popup.style.display = 'none'; };
            document.getElementById('pmClose').onclick = () => { popup.style.display = 'none'; };

            document.getElementById('pmConfirm').onclick = () => {
                const sel = document.querySelector('input[name="pmMethod"]:checked');
                const method = sel ? sel.value : 'cash';
                const given = parseFloat(pmGiven.value) || 0;
                if (given + 0.0001 < total) { alert('Amount given is less than total'); return; }

                fetch('checkout_order.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: currentOrderId, payment_method: method, amount_paid: given })
                }).then(r => r.json()).then(data => {
                    if (data && data.success) {
                        alert('Payment saved — reference: ' + (data.reference || ''));
                        
                        cart = {};
                        currentOrderId = null;
                        updateCart();
                        tableSelect.value = '';
                        selectedTableId = null;
                        popup.style.display = 'none';
                    } else {
                        alert('Error during payment: ' + (data?.error || 'Unknown'));
                    }
                }).catch(err => { console.error('Checkout error', err); alert('Checkout failed'); });
            };
        }, 500);
    }

    tableSelect.addEventListener('change', (e) => {
        const oldTableId = selectedTableId;
        selectedTableId = parseInt(e.target.value) || null;
        console.log('Table selected: oldTableId=', oldTableId, ', newTableId=', selectedTableId, ', dropdown value=', e.target.value);
        loadCart();
    });

    clearBtn.addEventListener('click', clearCart);
    saveBtn.addEventListener('click', saveCart);
    checkoutBtn.addEventListener('click', checkout);

    loadProducts();
    </script>
</body>
</html>
