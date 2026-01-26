<?php
$base_url = "/fogs-1";
require_once __DIR__ . '/../../db.php';
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: $base_url/index.php");
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager'])) {
    header("Location: $base_url/index.php");
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
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/style.css">
    
    <style>
        /* FORCE FIX: Ensure the product section handles the overlay correctly */
        .products-section {
            position: relative !important; /* This is crucial for the overlay to sit inside */
            overflow: hidden; /* Keeps the rounded corners clean */
        }

        /* The Fog Overlay */
        .products-overlay {
            position: absolute;
            inset: 0; /* Top, Right, Bottom, Left = 0 */
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(3px); /* Makes it look blurry */
            z-index: 50; /* Higher than everything else in the box */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #555;
            font-weight: bold;
            font-size: 1.1rem;
            text-align: center;
        }

        .products-overlay span {
            background: #fff;
            padding: 10px 20px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid #eee;
        }

        /* Input Fix: Remove spinners */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../navbar.php'; ?>
    
    <div class="pos-container">
        <div class="products-section">
            <div id="productOverlay" class="products-overlay">
                <span>Select a table to start</span>
            </div>

            <h2>Menu</h2>
            <div style="margin-bottom: 1rem;">
                <input type="text" id="productSearch" placeholder="🔍 Search products..." 
                       style="width: 90%; padding: 0.75rem; border-radius: 8px; border: 1px solid #ddd;" disabled>
            </div>
            
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

    <div id="paymentPopup" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#fff; border-radius:12px; width:450px; max-width:95%; box-shadow:0 10px 50px rgba(0,0,0,0.3); overflow:hidden;">
        
        <div style="background:#f8f9fa; padding:1.25rem; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; width:95%;">
            <h3 style="padding:5px; margin:0; font-size:1.2rem;">Finalize Payment</h3>
            <button id="pmClose" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#999;">&times;</button>
        </div>

        <div style="padding:1.5rem; text-align:center;">
            <div style="text-align:center; margin-bottom:1.5rem;">
                <div style="color:#666; font-size:0.9rem; text-transform:uppercase; letter-spacing:1px;">Amount Due</div>
                <div id="pmTotal" style="font-size:2.5rem; font-weight:800; color:#222;">₱0.00</div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:1.5rem;">
                <label style="cursor:pointer; border:2px solid #ddd; border-radius:8px; padding:10px; text-align:center; display:block;" id="labelCash">
                    <input type="radio" name="pmMethod" id="pmCash" value="cash" checked style="display:none;">
                    <strong>💵 Cash</strong>
                </label>
                <label style="cursor:pointer; border:2px solid #ddd; border-radius:8px; padding:10px; text-align:center; display:block;" id="labelGcash">
                    <input type="radio" name="pmMethod" id="pmGcash" value="gcash" style="display:none;">
                    <strong>📱 GCash</strong>
                </label>
            </div>

            <div style="margin-bottom:1rem;">
                <label style="font-weight:600; color:#444;">Amount Received</label>
                <input id="pmGiven" type="number" step="0.01" style="width:90%; padding:1rem; font-size:1.5rem; border:2px solid #eee; border-radius:8px; margin-top:5px; text-align:right;" placeholder="0.00">
            </div>

            <div style="display:flex; gap:8px; margin-bottom:1.5rem; flex-wrap:wrap;">
                <button type="button" class="quick-cash" onclick="setCash(100)">₱100</button>
                <button type="button" class="quick-cash" onclick="setCash(200)">₱200</button>
                <button type="button" class="quick-cash" onclick="setCash(300)">₱300</button>
                <button type="button" class="quick-cash" onclick="setCash(500)">₱500</button>
                <button type="button" class="quick-cash" onclick="setCash(1000)">₱1000</button>
            </div>

            <div style="background:#f1f8e9; padding:1rem; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-weight:600; color:#2e7d32;">CHANGE</span>
                <span id="pmChange" style="font-size:1.5rem; font-weight:700; color:#2e7d32;">₱0.00</span>
            </div>
        </div>

        <div style="padding:1rem; border-top:1px solid #eee; display:flex; gap:10px;">
            <button id="pmCancel" style="flex:1; padding:1rem; border-radius:8px; border:1px solid #ddd; background:#fff; cursor:pointer; font-weight:600;">Back</button>
            <button id="pmConfirm" style="flex:2; padding:1rem; border-radius:8px; border:none; background:#2e7d32; color:#fff; font-size:1rem; font-weight:700; cursor:pointer;">CONFIRM & PAY</button>
        </div>
    </div>
    </div>

    <script>
    let selectedTableId = null;
    let cart = {};
    let currentOrderId = null;

    const tableSelect = document.getElementById('tableSelect');
    const productsGrid = document.getElementById('productsGrid');
    const categoriesContainer = document.getElementById('categoriesContainer');
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const saveBtn = document.getElementById('saveBtn');
    const clearBtn = document.getElementById('clearBtn');
    const productOverlay = document.getElementById('productOverlay');
    const productSearch = document.getElementById('productSearch');

    let allProducts = {};
    let currentCategory = null;

    // --- VISUAL LOGIC: Toggle the "Fog" Mask ---
    function toggleProductLock() {
        if (!selectedTableId) {
            // No table selected: SHOW FOG, DISABLE SEARCH
            productOverlay.style.display = 'flex';
            productSearch.disabled = true;
        } else {
            // Table selected: HIDE FOG, ENABLE SEARCH
            productOverlay.style.display = 'none';
            productSearch.disabled = false;
        }
    }

    // --- DATA LOGIC ---

    function updateOrderTime(orderId) {
        if (!orderId) return;
        fetch('update_order_time.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        }).catch(err => console.warn('Failed to update order time:', err));
    }

    function saveCart(allowEmpty = false) {
        if (!selectedTableId) {
            alert('Please select a table first');
            return Promise.reject('No table selected');
        }

        // VISUAL FEEDBACK: Disable buttons while saving
        const btns = [saveBtn, checkoutBtn, clearBtn];
        const originalText = saveBtn.textContent;
        btns.forEach(b => b.disabled = true);
        saveBtn.textContent = "Saving...";

        const items = Object.values(cart).map(item => ({
            product_id: item.id,
            quantity: item.qty
        }));

        if (items.length === 0 && !allowEmpty) {
            btns.forEach(b => b.disabled = false);
            saveBtn.textContent = originalText;
            alert('Cart is empty. Add items before saving.');
            return Promise.reject('Cart empty');
        }

        return fetch('save_pos_cart.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ table_id: selectedTableId, items: items })
        })
        .then(r => {
            if(r.redirected) window.location.reload(); // Session Expired check
            return r.json();
        })
        .then(data => {
            if (data.success) {
                currentOrderId = data.order_id;
                updateOrderTime(data.order_id);
                if(!allowEmpty) { 
                    // Optional: alert('Saved!'); 
                }
                return data;
            } else {
                throw new Error(data.error);
            }
        })
        .catch(err => {
            console.error('Failed to save cart:', err);
            if (!allowEmpty) alert('Error saving bill: ' + err.message);
            throw err;
        })
        .finally(() => {
            // ALWAYS Re-enable buttons
            btns.forEach(b => b.disabled = false);
            saveBtn.textContent = originalText;
        });
    }

    function loadCart() {
    if (!selectedTableId) {
        cart = {};
        currentOrderId = null;
        updateCart();
        return;
    }

    fetch('get_pos_cart.php?table_id=' + encodeURIComponent(selectedTableId), { credentials: 'same-origin' })
        .then(r => {
            if(r.redirected) window.location.reload();
            return r.json();
        })
        .then(data => {
            if (data.success) {
                currentOrderId = data.order_id;
                cart = {};
                if (data.items && data.items.length > 0) {
                    data.items.forEach(item => {
                        const itemId = 'product_' + item.product_id;
                        cart[itemId] = {
                            id: item.product_id,
                            name: item.name,
                            price: item.price,
                            qty: item.quantity,
                            served: item.served || 0 // <--- CRITICAL: Save served count
                        };
                    });
                }
                updateCart();
            } else {
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

    function createProductCard(product) {
        const card = document.createElement('div');
        card.className = 'product-card';
        card.innerHTML = `
            <div class="product-name">${product.name}</div>
            <div class="product-price">₱${parseFloat(product.price).toFixed(2)}</div>
        `;
        card.addEventListener('click', () => {
            // If overlay is active (somehow), stop interaction
            if (!selectedTableId) return; 

            card.style.transform = "scale(0.95)";
            setTimeout(() => card.style.transform = "", 100);
            addToCart(product.id, product.name, product.price);
        });
        return card;
    }

    function renderProducts(category) {
        productsGrid.innerHTML = '';
        const items = allProducts[category] || [];
        items.forEach(product => {
            productsGrid.appendChild(createProductCard(product));
        });
    }

    function addToCart(productId, productName, productPrice) {
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
        updateCart();
        updateTableStatus();
    }

    function removeFromCart(itemId) {
    if (cart[itemId]) {
        const servedCount = parseInt(cart[itemId].served) || 0;

        if (servedCount > 0) {
            alert(`🛑 Action Denied: This item has already been served (${servedCount} portions). You cannot remove it from the bill.`);
            return; // Prevent deletion
        }

        delete cart[itemId];
        updateCart();
        if (Object.keys(cart).length === 0) {
            updateTableStatus();
        }
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
            // Inside updateCart() loop
            const servedCount = item.served || 0;
            const statusLabel = servedCount > 0 ? `<br><small style="color:red;">Served: ${servedCount}</small>` : '';

            row.innerHTML = `
                <div class="item-name">${item.name}${statusLabel}</div>
                <div class="qty-control">
                    <button class="qty-btn" onclick="changeQty('${itemId}', -1)">−</button>
                    <input type="text" inputmode="numeric" class="item-qty-input" value="${item.qty}" readonly>
                    <button class="qty-btn" onclick="changeQty('${itemId}', 1)">+</button>
                </div>
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

    function changeQty(itemId, delta) {
    if (cart[itemId]) {
        const item = cart[itemId];
        const newQty = item.qty + delta;
        
        // Use 0 if 'served' isn't loaded yet
        const servedCount = item.served || 0;

        // ERROR CATCHER: Prevent reduction below served count
        if (delta < 0 && newQty < servedCount) {
            alert(`Action Denied: ${servedCount} portion(s) of ${item.name} have already been served to the table.`);
            return; // Stop the function here
        }

        if (newQty > 0) {
            item.qty = newQty;
            updateCart();
        } else {
            if(confirm("Remove " + item.name + "?")) {
                removeFromCart(itemId);
            }
        }
    }
    }

    function clearCart() {
        if (!selectedTableId) return;
        if (Object.keys(cart).length > 0) {
            if (!confirm("Are you sure you want to clear the whole order?")) return;
        }

        cart = {};
        updateCart();
        saveCart(true); 
        if (currentOrderId) updateOrderTime(currentOrderId);
        updateTableStatus();
    }

    function updateTableStatus() {
        if (!selectedTableId) return;
        const hasItems = Object.keys(cart).length > 0;
        const shouldBeOccupied = hasItems ? 1 : 0;
        
        fetch('../toggle_table_status.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: selectedTableId, occupied: shouldBeOccupied })
        }).catch(err => console.error('Failed to update table status:', err));
    }

    function checkout() {
        if (!selectedTableId || Object.keys(cart).length === 0) {
            alert('Please select a table and add items');
            return;
        }
        
        saveCart().then(() => {
            if (!currentOrderId) {
                alert('Error: No order ID generated. Try saving again.');
                return;
            }

            const popup = document.getElementById('paymentPopup');
            const pmTotal = document.getElementById('pmTotal');
            const pmGiven = document.getElementById('pmGiven');
            const pmChange = document.getElementById('pmChange');
            
            if (!popup) return;

            const total = Object.values(cart).reduce((s, it) => s + (it.price * it.qty), 0);
            pmTotal.textContent = '₱' + total.toFixed(2);
            pmGiven.value = total.toFixed(2); 
            pmChange.textContent = '₱0.00';
            
            const cash = document.getElementById('pmCash'); 
            if (cash) cash.checked = true;
            
            popup.style.display = 'flex';
            pmGiven.focus(); 
            pmGiven.select();

            function computeChange() {
                const given = parseFloat(pmGiven.value) || 0;
                const change = Math.max(0, given - total);
                pmChange.textContent = '₱' + change.toFixed(2);
                pmChange.style.color = (given >= total) ? '#2e7d32' : '#c62828';
            }

            pmGiven.oninput = computeChange;

            document.getElementById('pmCancel').onclick = () => { popup.style.display = 'none'; };
            document.getElementById('pmClose').onclick = () => { popup.style.display = 'none'; };

            document.getElementById('pmConfirm').onclick = () => {
                const sel = document.querySelector('input[name="pmMethod"]:checked');
                const method = sel ? sel.value : 'cash';
                const given = parseFloat(pmGiven.value) || 0;
                
                if (given + 0.001 < total) { 
                    alert('Amount given is less than total'); 
                    return; 
                }

                fetch('checkout_order.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        order_id: currentOrderId, 
                        payment_method: method, 
                        amount_paid: given 
                    })
                }).then(r => r.json()).then(data => {
                    if (data && data.success) {
                        alert('Payment Successful!');
                        
                        cart = {};
                        currentOrderId = null;
                        updateCart();
                        
                        tableSelect.value = '';
                        selectedTableId = null;
                        
                        // RE-LOCK the screen
                        toggleProductLock(); 
                        
                        popup.style.display = 'none';
                    } else {
                        alert('Error: ' + (data?.error || 'Unknown error'));
                    }
                }).catch(err => { 
                    console.error('Checkout error', err); 
                    alert('Connection failed'); 
                });
            };
        }).catch(err => {
            console.log("Checkout stopped due to save error");
        });
    }

    tableSelect.addEventListener('change', (e) => {
        const oldTableId = selectedTableId;
        selectedTableId = parseInt(e.target.value) || null;
        
        toggleProductLock(); // <--- TOGGLE FOG
        
        loadCart();
    });

    // Clear Logic
    clearBtn.addEventListener('click', clearCart);
    saveBtn.addEventListener('click', saveCart);
    checkoutBtn.addEventListener('click', checkout);

    // Search Logic
    productSearch.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase().trim();
        if (term === "") {
            renderProducts(currentCategory);
            categoriesContainer.style.display = 'flex'; 
            return;
        }
        categoriesContainer.style.display = 'none';
        productsGrid.innerHTML = '';
        Object.values(allProducts).forEach(categoryItems => {
            categoryItems.forEach(product => {
                if (product.name.toLowerCase().includes(term)) {
                    productsGrid.appendChild(createProductCard(product));
                }
            });
        });
        if (productsGrid.innerHTML === '') {
            productsGrid.innerHTML = '<div style="grid-column: 1/-1; padding: 2rem; color: #999;">No products found.</div>';
        }
    });

    // Init
    loadProducts();
    toggleProductLock(); // Ensure locked on load

        // Function for Quick Cash buttons
    function setCash(amount) {
        const pmGiven = document.getElementById('pmGiven');
        pmGiven.value = amount.toFixed(2);
        
        // Manually trigger the "input" event so change is recalculated
        pmGiven.dispatchEvent(new Event('input'));
    }

    // Logic to highlight the selected payment method box
    document.querySelectorAll('input[name="pmMethod"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Reset both
            document.getElementById('labelCash').style.borderColor = '#ddd';
            document.getElementById('labelGcash').style.borderColor = '#ddd';
            document.getElementById('labelCash').style.background = '#fff';
            document.getElementById('labelGcash').style.background = '#fff';
            
            // Highlight selected
            if(this.checked) {
                const label = this.closest('label');
                label.style.borderColor = '#2e7d32';
                label.style.background = '#f1f8e9';
            }
        });
    });
    </script>
</body>
</html>