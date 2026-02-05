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
    $stmt = $mysqli->prepare('SELECT id, table_number, table_type FROM `tables` ORDER BY table_number ASC');
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $tables[] = [
                'id' => (int)$row['id'], 
                'table_number' => $row['table_number'],
                'type' => $row['table_type']
            ];
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
    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>/assets/logo.png">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>POS - Point of Sale</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal2-container { z-index: 20000 !important; }
        .products-section { position: relative !important; overflow: hidden; }
        .products-overlay {
            position: absolute; inset: 0; background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(3px); z-index: 50; display: flex;
            flex-direction: column; align-items: center; justify-content: center;
            border-radius: 8px; color: #555; font-weight: bold; font-size: 1.1rem; text-align: center;
        }
        .products-overlay span {
            background: #fff; padding: 10px 20px; border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid #eee;
        }
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        
        /* New Print Button Style */
        .printBillBtn {
            background-color: #607d8b; /* Blue-Grey */
            color: white;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .printBillBtn:hover {
            background-color: #546e7a;
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
            <div class="table-select-container" style="margin-bottom: 1rem; padding: 10px; background: #f9f9f9; border-radius: 8px;">
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <label style="flex: 1; cursor: pointer; text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 5px; background: #e8f5e9;" id="btnDineIn">
                        <input type="radio" name="orderType" value="physical" checked style="display: none;"> 🍽️ Dine-in
                    </label>
                    <label style="flex: 1; cursor: pointer; text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 5px; background: #fff;" id="btnTakeOut">
                        <input type="radio" name="orderType" value="virtual" style="display: none;"> 🥡 Take-out
                    </label>
                </div>

                <select id="tableSelect" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc;">
                    <option value="">-- Choose Table --</option>
                    <?php foreach ($tables as $t): ?>
                        <option value="<?php echo $t['id']; ?>" data-type="<?php echo $t['type']; ?>">
                            <?php echo ($t['type'] == 'virtual' ? 'Takeout ' : 'Table ') . htmlspecialchars($t['table_number']); ?>
                        </option>
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
                <button class="printBillBtn" id="printBillBtn">Print Bill</button>
                <button class="checkout-btn" id="checkoutBtn">Checkout</button>
                <button class="clear-btn" id="clearBtn">Clear Cart</button>
            </div>
        </div>
    </div>

    <div id="paymentPopup" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; z-index:1000;">
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
                <div style="display:flex; gap:8px; margin-bottom:1.5rem; flex-wrap:wrap; justify-content: center;">
                    <button type="button" class="quick-cash" onclick="setCash(100)">₱100</button>
                    <button type="button" class="quick-cash" onclick="setCash(200)">₱200</button>
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
    // --- Printing Function ---
    // Updated triggerPrinting for better error handling
function triggerPrinting(orderId, mode = 'all') {
    if (!orderId) return;

    // We use GET params to match the clean logic in print_order.php
    fetch(`print_order.php?order_id=${orderId}&type=${mode}`, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            console.log(`Print (${mode}) success`);
        } else {
            Swal.fire('Printer Error', data.message, 'error');
        }
    })
    .catch(err => {
        console.error('Printer request failed:', err);
    });
}



    // --- State Variables ---
    let selectedTableId = null;
    let cart = {}; 
    let currentOrderId = null;
    let currentBillAmount = 0; 

    // --- DOM Elements ---
    const tableSelect = document.getElementById('tableSelect');
    const productsGrid = document.getElementById('productsGrid');
    const categoriesContainer = document.getElementById('categoriesContainer');
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const saveBtn = document.getElementById('saveBtn');
    const printBillBtn = document.getElementById('printBillBtn'); // The new button
    const clearBtn = document.getElementById('clearBtn');
    const productOverlay = document.getElementById('productOverlay');
    const productSearch = document.getElementById('productSearch');
    const btnDineIn = document.getElementById('btnDineIn');
    const btnTakeOut = document.getElementById('btnTakeOut');

    let allProducts = {};
    let currentCategory = null;

    // --- Helpers ---
    function toggleProductLock() {
        if (!selectedTableId) {
            productOverlay.style.display = 'flex';
            productSearch.disabled = true;
        } else {
            productOverlay.style.display = 'none';
            productSearch.disabled = false;
        }
    }

    function updateOrderTime(orderId) {
        if (!orderId) return;
        fetch('update_order_time.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        }).catch(err => console.warn('Failed to update order time:', err));
    }

    // --- CORE LOGIC: Save Cart ---
    function saveCart(allowEmpty = false) {
        if (typeof allowEmpty !== 'boolean') allowEmpty = false;
        if (!selectedTableId) {
            Swal.fire({ icon: 'info', title: 'Table Required', text: 'Select a table first.' });
            return Promise.reject('No table selected');
        }

        let validItems = [];
        for (let key in cart) {
            if (cart[key] && cart[key].qty > 0) {
                validItems.push({ product_id: cart[key].id, quantity: cart[key].qty });
            }
        }

        if (validItems.length === 0 && !allowEmpty) {
            Swal.fire({ icon: 'warning', title: 'Empty Bill', text: 'Please add products first.' });
            return Promise.reject('Cart empty'); 
        }

        saveBtn.disabled = true;
        return fetch('save_pos_cart.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ table_id: selectedTableId, items: validItems })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                currentOrderId = data.order_id;
                updateOrderTime(data.order_id);
                
                // --- AUTO PRINT TO KITCHEN HERE ---
                triggerPrinting(data.order_id, 'kitchen');
                
                if(!allowEmpty) {
                    Swal.fire({ 
                        icon: 'success', 
                        title: 'Saved & Sent to Kitchen', 
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 
                    });
                }
                return data;
            } else {
                throw new Error(data.error);
            }
        })
        .catch(err => {
            if (err !== 'Cart empty') Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            throw err;
        })
        .finally(() => { saveBtn.disabled = false; });
    }

    function loadCart() {
        if (!selectedTableId) { 
            cart = {}; currentOrderId = null; updateCart(); 
            return; 
        }
        fetch('get_pos_cart.php?table_id=' + encodeURIComponent(selectedTableId), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    currentOrderId = data.order_id;
                    cart = {};
                    (data.items || []).forEach(item => {
                        cart['product_' + item.product_id] = {
                            id: item.product_id, 
                            name: item.name, 
                            price: parseFloat(item.price), 
                            qty: parseInt(item.quantity), 
                            served: parseInt(item.served || 0)
                        };
                    });
                } else {
                    cart = {}; currentOrderId = null;
                }
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
            });
    }

    function renderCategories() {
        categoriesContainer.innerHTML = '';
        Object.keys(allProducts).forEach(cat => {
            const btn = document.createElement('button');
            btn.className = 'category-tab' + (cat === currentCategory ? ' active' : '');
            btn.textContent = cat;
            
            // Fixed: Standard event listener
            btn.addEventListener('click', function() {
                currentCategory = cat;
                // Update active state
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
        card.onclick = () => {
            if (selectedTableId) addToCart(product.id, product.name, product.price);
        };
        return card;
    }

    function renderProducts(category) {
        productsGrid.innerHTML = '';
        const items = allProducts[category] || [];
        items.forEach(p => productsGrid.appendChild(createProductCard(p)));
    }

    function addToCart(id, name, price) {
        const itemId = 'product_' + id;
        if (cart[itemId]) cart[itemId].qty++;
        else cart[itemId] = { id, name, price: parseFloat(price), qty: 1 };
        updateCart();
        updateTableStatus();
    }

    function removeFromCart(itemId) {
        if (cart[itemId] && (cart[itemId].served || 0) > 0) {
            alert("Action Denied: Item already served.");
            return;
        }
        Swal.fire({ title: 'Remove item?', icon: 'warning', showCancelButton: true }).then(res => {
            if (res.isConfirmed) {
                delete cart[itemId];
                updateCart();
                saveCart(true); // Save empty state
                updateTableStatus();
            }
        });
    }

    function updateCart() {
        cartItems.innerHTML = ''; 
        let total = 0;
        Object.entries(cart).forEach(([id, it]) => {
            const line = it.price * it.qty;
            total += line;
            const row = document.createElement('div');
            row.className = 'cart-item';
            row.innerHTML = `
                <div class="item-name">${it.name}${it.served > 0 ? `<br><small style="color:red;">Served: ${it.served}</small>` : ''}</div>
                <div class="qty-control">
                    <button class="qty-btn" onclick="changeQty('${id}', -1)">−</button>
                    <input type="text" class="item-qty-input" value="${it.qty}" readonly>
                    <button class="qty-btn" onclick="changeQty('${id}', 1)">+</button>
                </div>
                <div class="item-price">₱${line.toFixed(2)}</div>
                <button class="item-remove" onclick="removeFromCart('${id}')">×</button>
            `;
            cartItems.appendChild(row);
        });
        cartTotal.textContent = '₱' + total.toFixed(2);
        if (Object.keys(cart).length === 0) cartItems.innerHTML = '<div class="empty-cart">No items in cart</div>';
    }

    // Explicit global scope for inline onclicks
    window.changeQty = function(id, d) {
        if (cart[id]) {
            const newQty = cart[id].qty + d;
            if (d < 0 && newQty < (cart[id].served || 0)) {
                alert("Cannot reduce below served qty.");
                return;
            }
            if (newQty > 0) {
                cart[id].qty = newQty;
                updateCart();
            } else {
                removeFromCart(id);
            }
        }
    };
    window.removeFromCart = removeFromCart;
    window.setCash = function(a) { 
        document.getElementById('pmGiven').value = a.toFixed(2); 
        updateChange(); 
    };

    function updateTableStatus() {
        if (!selectedTableId) return;
        fetch('../toggle_table_status.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: selectedTableId, occupied: Object.keys(cart).length > 0 ? 1 : 0 })
        });
    }

    // --- EVENT LISTENERS ---

    // 1. Table Selection
    tableSelect.addEventListener('change', (e) => {
        selectedTableId = parseInt(e.target.value) || null;
        cart = {}; 
        currentOrderId = null; 
        toggleProductLock(); 
        loadCart();
    });

    // 2. Buttons
    saveBtn.addEventListener('click', () => saveCart(false));
    
    // NEW PRINT BILL BUTTON
    printBillBtn.addEventListener('click', () => {
        if (!selectedTableId || Object.keys(cart).length === 0) return;
        
        // Save first to ensure backend has latest items
        saveCart(false).then(data => {
            // Then trigger Bill Print
            triggerPrinting(data.order_id, 'bill');
        });
    });

    checkoutBtn.addEventListener('click', () => {
        if (!selectedTableId || Object.keys(cart).length === 0) return;
        saveCart(false).then(() => {
            currentBillAmount = Object.values(cart).reduce((s, it) => s + (it.price * it.qty), 0);
            document.getElementById('pmTotal').textContent = '₱' + currentBillAmount.toFixed(2);
            document.getElementById('pmGiven').value = currentBillAmount.toFixed(2);
            document.getElementById('paymentPopup').style.display = 'flex';
            updateChange();
        });
    });

    clearBtn.addEventListener('click', () => {
        Swal.fire({ title: 'Clear cart?', icon: 'warning', showCancelButton: true }).then(res => {
            if (res.isConfirmed) {
                cart = {}; 
                updateCart(); 
                saveCart(true); 
                updateTableStatus();
            }
        });
    });

    // 3. Search
    productSearch.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase().trim();
        if (!term) {
            renderProducts(currentCategory);
            categoriesContainer.style.display = 'flex';
            return;
        }
        categoriesContainer.style.display = 'none';
        productsGrid.innerHTML = '';
        Object.values(allProducts).forEach(cat => cat.forEach(p => {
            if (p.name.toLowerCase().includes(term)) productsGrid.appendChild(createProductCard(p));
        }));
    });

    // 4. Payment
    function updateChange() {
        const given = parseFloat(document.getElementById('pmGiven').value) || 0;
        const diff = given - currentBillAmount;
        const lbl = document.getElementById('pmChange');
        lbl.textContent = (diff < 0 ? '-₱' : '₱') + Math.abs(diff).toFixed(2);
        lbl.style.color = diff < 0 ? '#d32f2f' : '#2e7d32';
    }
    document.getElementById('pmGiven').addEventListener('input', updateChange);
    
    document.getElementById('pmClose').onclick = 
    document.getElementById('pmCancel').onclick = () => {
        document.getElementById('paymentPopup').style.display = 'none';
    };

    document.getElementById('pmConfirm').onclick = () => {
        const method = document.querySelector('input[name="pmMethod"]:checked').value;
        const given = parseFloat(document.getElementById('pmGiven').value || 0);
        
        if (given < (currentBillAmount - 0.01)) {
            Swal.fire('Error', 'Insufficient amount', 'warning');
            return;
        }
        
        fetch('checkout_order.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: currentOrderId, payment_method: method, amount_paid: given })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.error);
            Swal.fire('Success', 'Payment Complete', 'success');
            document.getElementById('paymentPopup').style.display = 'none';
            
            // Reset UI
            selectedTableId = null;
            cart = {};
            currentOrderId = null;
            tableSelect.value = "";
            filterTables(); // Refresh table list
        })
        .catch(err => Swal.fire('Error', err.message, 'error'));
    };

    // 5. Table Type Filtering (Top Buttons)
    const radios = document.querySelectorAll('input[name="orderType"]');
    function filterTables() {
        const type = document.querySelector('input[name="orderType"]:checked').value;
        
        // Update Button Styles
        btnDineIn.style.background = (type === 'physical') ? '#e8f5e9' : '#fff';
        btnTakeOut.style.background = (type === 'virtual') ? '#e8f5e9' : '#fff';

        // Filter Dropdown
        const options = tableSelect.querySelectorAll('option');
        options.forEach(opt => {
            if (opt.value === "") return; // Skip placeholder
            const tType = opt.getAttribute('data-type');
            opt.style.display = (tType === type) ? 'block' : 'none';
        });

        // Reset Selection if hidden
        if (selectedTableId) {
             const currentOpt = tableSelect.querySelector(`option[value="${selectedTableId}"]`);
             if (currentOpt && currentOpt.style.display === 'none') {
                 tableSelect.value = "";
                 selectedTableId = null;
                 cart = {};
                 updateCart();
                 toggleProductLock();
             }
        }
    }
    
    radios.forEach(r => r.addEventListener('change', filterTables));

    // --- Init ---
    loadProducts();
    toggleProductLock();
    filterTables();

    </script>
</body>
</html>