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
        
        .printBillBtn {
            background-color: #607d8b; color: white; border: none; padding: 0.75rem 1rem;
            border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s;
        }
        .printBillBtn:hover { background-color: #546e7a; }
        .discount-tag { color: #d32f2f; font-size: 0.8rem; display: block; }
        .item-price { cursor: pointer; transition: color 0.2s; }
        .item-price:hover { color: #2e7d32; text-decoration: underline; }

        /* --- NEW EDIT MODE STYLES --- */
        .option-card {
            border: 1px solid #ddd; padding: 10px; margin: 5px; border-radius: 8px;
            cursor: pointer; transition: all 0.2s; position: relative; display: block; text-align: left;
        }
        .option-card:hover { background: #f9f9f9; }
        .option-card.selected {
            border: 2px solid #2e7d32 !important; /* Green Border */
            background-color: #e8f5e9;             /* Light Green BG */
            color: #1b5e20;
        }
        .item-name-clickable {
            cursor: pointer; text-decoration: underline; text-decoration-style: dotted; text-decoration-color: #999;
        }
        .item-name-clickable:hover { color: #1976d2; }
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
                    <option value="0">-- Choose Table --</option>
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
    // --- State Variables ---
    let selectedTableId = null;
    let cart = {}; 
    let currentOrderId = null;
    let currentBillAmount = 0; 
    let pendingItem = null; 
    let editingUniqueKey = null; // Tracks which item is being edited

    // --- DOM Elements ---
    const tableSelect = document.getElementById('tableSelect');
    const productsGrid = document.getElementById('productsGrid');
    const categoriesContainer = document.getElementById('categoriesContainer');
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const saveBtn = document.getElementById('saveBtn');
    const printBillBtn = document.getElementById('printBillBtn');
    const clearBtn = document.getElementById('clearBtn');
    const productOverlay = document.getElementById('productOverlay');
    const productSearch = document.getElementById('productSearch');
    const btnDineIn = document.getElementById('btnDineIn');
    const btnTakeOut = document.getElementById('btnTakeOut');

    let allProducts = {};
    let currentCategory = null;

    // --- Printing ---
    function triggerPrinting(orderId, mode = 'all') {
        if (!orderId) return;
        fetch(`print_order.php?order_id=${orderId}&type=${mode}`, { method: 'GET', credentials: 'same-origin' })
        .catch(err => console.error('Printer request failed:', err));
    }

    // --- Helpers ---
    function toggleProductLock() {
        productOverlay.style.display = !selectedTableId ? 'flex' : 'none';
        productSearch.disabled = !selectedTableId;
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

    // --- NEW: EDIT & VARIATION LOGIC ---

    // 1. Prepare to Edit (Called when clicking item name in cart)
    window.prepareEditCartItem = function(uniqueKey) {
        const item = cart[uniqueKey];
        if (!item) return;

        // Prevent editing served items if policy dictates, or just warn
        if(item.served > 0) {
            Swal.fire('Note', 'This item has already been served to the kitchen.', 'info');
        }

        editingUniqueKey = uniqueKey; // Set Global Edit Flag
        showVariationPicker(item.productId, item.name, item); // Pass item context
    };

    // 2. Size/Variation Picker (Handles both New and Edit)
    function showVariationPicker(productId, productName, editItem = null) {
        fetch(`get_variations.php?product_id=${productId}`)
            .then(r => r.json())
            .then(data => {
                // If no sizes, check if we need to show modifier picker anyway (for base products with mods)
                if (!data.success || !data.sizes || data.sizes.length === 0) {
                    // It's a simple product, go straight to modifiers or add
                    pendingItem = { pId: productId, pName: productName, sId: null, sName: null, sPrice: data.base_price || 0 };
                    showModifierPicker(productId, productName, null, null, data.base_price || 0, editItem);
                    return;
                }

                // Build HTML for Sizes
                let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top:10px;">';
                data.sizes.forEach(s => {
                    // Check if this size is the one currently selected (Green Border Logic)
                    const isSelected = editItem && editItem.variationId == s.id ? 'selected' : '';
                    
                    html += `
                        <div class="option-card size-card ${isSelected}" onclick="handleSizeClick(this, ${productId}, '${productName.replace(/'/g, "\\'")}', ${s.id}, '${s.name.replace(/'/g, "\\'")}', ${s.price})">
                            <strong>${s.name}</strong><br>₱${parseFloat(s.price).toFixed(2)}
                            <input type="radio" name="size_opt" value="${s.id}" ${isSelected ? 'checked' : ''} style="display:none;">
                        </div>`;
                });
                html += '</div>';

                // We need to attach the editItem to the next step, so we store it temporarily or pass it
                // Since SweetAlert is async, we use the global pending or just relies on click
                window.currentEditItemContext = editItem; 

                Swal.fire({ 
                    title: editItem ? 'Edit Size' : 'Select Size', 
                    html: html, 
                    showConfirmButton: false, 
                    showCancelButton: true,
                    didOpen: () => {
                        // Optional: Scroll to selected
                    }
                }).then((res) => {
                    if(res.dismiss) { editingUniqueKey = null; window.currentEditItemContext = null; }
                });
            });
    }

    // Helper for Size Click
    window.handleSizeClick = function(el, pId, pName, sId, sName, sPrice) {
        // Visual Selection
        document.querySelectorAll('.size-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');

        // Proceed to Modifiers
        const editItem = window.currentEditItemContext; // Retrieve context
        showModifierPicker(pId, pName, sId, sName, sPrice, editItem);
    };

    // 3. Modifier Picker
    function showModifierPicker(pId, pName, sId, sName, sPrice, editItem = null) {
        pendingItem = { pId, pName, sId, sName, sPrice };

        fetch(`get_variations.php?product_id=${pId}`)
            .then(r => r.json())
            .then(data => {
                // If no modifiers, finish immediately
                if (!data.modifiers || data.modifiers.length === 0) {
                    finishItemProcess([]); 
                    return;
                }

                let html = '<div style="text-align: left; max-height: 250px; overflow-y: auto;">';
                data.modifiers.forEach(m => {
                    // Check if modifier is in existing item
                    let isChecked = '';
                    let isSelectedClass = '';
                    if (editItem && editItem.modifiers) {
                        const exists = editItem.modifiers.find(em => em.id == m.id);
                        if (exists) {
                            isChecked = 'checked';
                            isSelectedClass = 'selected';
                        }
                    }

                    html += `
                        <label class="option-card mod-card ${isSelectedClass}" onclick="toggleModClass(this)">
                            <div style="display:flex; justify-content:space-between;">
                                <span>
                                    <input type="checkbox" class="mod-check" value="${m.id}" data-name="${m.name}" data-price="${m.price}" ${isChecked} style="display:none;"> 
                                    ${m.name}
                                </span>
                                <span style="color: #2e7d32;">+₱${m.price}</span>
                            </div>
                        </label>`;
                });
                html += '</div>';

                Swal.fire({
                    title: editItem ? 'Edit Add-ons' : 'Add-ons?',
                    html: html,
                    showCancelButton: true,
                    confirmButtonText: editItem ? 'Update Item' : 'Add to Cart',
                    preConfirm: () => {
                        return Array.from(document.querySelectorAll('.mod-check:checked')).map(cb => ({
                            id: cb.value,
                            name: cb.dataset.name,
                            price: parseFloat(cb.dataset.price)
                        }));
                    }
                }).then(res => { 
                    if (res.isConfirmed) {
                        finishItemProcess(res.value);
                    } else {
                        editingUniqueKey = null; // Reset if cancelled
                    }
                });
            });
    }

    // Helper for visual toggle
    // --- Updated Visual Toggle for Modifiers ---
    window.toggleModClass = function(el) {
        const cb = el.querySelector('input[type="checkbox"]');
        
        // 1. Toggle the checkbox state first
        cb.checked = !cb.checked; 
        
        // 2. Sync the Green Border to the checkbox state
        if (cb.checked) {
            el.classList.add('selected');
        } else {
            el.classList.remove('selected');
        }
        
        // Small haptic/log check to ensure it's working
        console.log("Modifier " + cb.dataset.name + " is now: " + (cb.checked ? "ACTIVE" : "INACTIVE"));
    };

    // 4. Final Processing (Decides: Add New or Update Existing)
    function finishItemProcess(selectedMods) {
        const p = pendingItem;
        const extraPrice = selectedMods.reduce((sum, m) => sum + m.price, 0);

        if (editingUniqueKey) {
            // --- UPDATE EXISTING ITEM ---
            const oldItem = cart[editingUniqueKey];
            
            // Construct new key just in case, or keep old key? 
            // Better to keep logic consistent. If size changes, it's technically a "different" key in our structure,
            // BUT we want to preserve the DB ID.
            
            // 1. Update the data
            oldItem.variationId = p.sId;
            oldItem.variationName = p.sName;
            oldItem.basePrice = parseFloat(p.sPrice);
            oldItem.modifiers = selectedMods;
            oldItem.modifierTotal = extraPrice;
            
            // 2. Visual feedback
            updateCart();
            updateTableStatus();
            
            editingUniqueKey = null; // Done editing
            window.currentEditItemContext = null;

        } else {
            // --- ADD NEW ITEM ---
            addToCart(p.pId, p.pName, p.sPrice, p.sId, p.sName, selectedMods, extraPrice);
        }
        
        Swal.close();
    }

    // --- CORE CART LOGIC ---

    function addToCart(prodId, prodName, basePrice, varId = null, varName = null, modifiers = [], modTotal = 0, existingNotes = '', servedQty = 0, dbItemId = null) {
        const modIdString = modifiers.map(m => m.id).sort().join('-');
        const uniqueKey = `p${prodId}_v${varId || 0}_m${modIdString || 0}`;

        if (cart[uniqueKey]) {
            if(dbItemId === null) cart[uniqueKey].qty++;
        } else {
            cart[uniqueKey] = {
                order_item_id: dbItemId,
                productId: prodId,
                name: prodName,
                variationId: varId,
                variationName: varName,
                basePrice: parseFloat(basePrice),
                modifierTotal: parseFloat(modTotal),
                discountAmount: 0,
                modifiers: modifiers,
                qty: 1,
                notes: existingNotes,
                served: servedQty
            };
        }
        updateCart();
        updateTableStatus();
    }

    function updateCart() {
        cartItems.innerHTML = ''; 
        let totalBill = 0;

        Object.entries(cart).forEach(([key, it]) => {
            const unitPrice = it.basePrice + it.modifierTotal;
            const subtotal = unitPrice * it.qty;
            const lineTotal = subtotal - it.discountAmount; 
            totalBill += lineTotal;

            let displayName = it.name;
            if(it.variationName) displayName += ` <span style="color:#666;">(${it.variationName})</span>`;
            
            let modString = '';
            if(it.modifiers.length > 0) {
                modString = '<br><small style="color:#2e7d32;">+ ' + it.modifiers.map(m => m.name).join(', ') + '</small>';
            }

            const row = document.createElement('div');
            row.className = 'cart-item';
            row.innerHTML = `
                <div class="item-name item-name-clickable" onclick="prepareEditCartItem('${key}')" title="Click to Edit">
                    ${displayName}
                    ${modString}
                    ${it.served > 0 ? `<br><small style="color:red;">Served: ${it.served}</small>` : ''}
                    ${it.notes ? `<br><small style="color:#888;">Note: ${it.notes}</small>` : ''}
                </div>
                <div class="qty-control">
                    <button class="qty-btn" onclick="changeQty('${key}', -1)">−</button>
                    <input type="text" class="item-qty-input" value="${it.qty}" readonly>
                    <button class="qty-btn" onclick="changeQty('${key}', 1)">+</button>
                </div>
                <div class="item-price" onclick="applyItemDiscount('${key}')" title="Click to Discount">
                    ₱${lineTotal.toFixed(2)}
                    ${it.discountAmount > 0 ? `<span class="discount-tag">(-₱${it.discountAmount.toFixed(2)})</span>` : ''}
                </div>
                <button class="item-remove" onclick="removeFromCart('${key}')">×</button>
            `;
            cartItems.appendChild(row);
        });

        cartTotal.textContent = '₱' + totalBill.toFixed(2);
        if (Object.keys(cart).length === 0) cartItems.innerHTML = '<div class="empty-cart">Select a table and add items</div>';
    }

    // --- DISCOUNT, LOAD, SAVE (Existing Logic) ---
    window.applyItemDiscount = function(key) {
        const item = cart[key];
        Swal.fire({
            title: 'Apply Discount',
            text: `Total: ₱${((item.basePrice + item.modifierTotal) * item.qty).toFixed(2)}`,
            input: 'number',
            inputValue: item.discountAmount,
            showCancelButton: true
        }).then((result) => {
            if (result.isConfirmed) {
                let val = parseFloat(result.value);
                if(isNaN(val) || val < 0) val = 0;
                cart[key].discountAmount = val;
                updateCart();
            }
        });
    };

    function loadCart() {
        if (!selectedTableId) { cart = {}; currentOrderId = null; updateCart(); return; }
        fetch('get_pos_cart.php?table_id=' + encodeURIComponent(selectedTableId))
            .then(r => r.json())
            .then(data => {
                cart = {}; 
                if (data.success) {
                    currentOrderId = data.order_id;
                    (data.items || []).forEach(item => {
                        const mods = item.modifiers || [];
                        // We use a simplified key generation for loaded items to avoid conflicts
                        // Or we can rely on order_item_id mainly
                        const modString = mods.map(m => m.id).sort().join('-');
                        const uKey = `p${item.product_id}_v${item.size_id || 0}_m${modString || 0}_db${item.order_item_id}`; 
                        
                        cart[uKey] = {
                            order_item_id: item.order_item_id,
                            productId: item.product_id, 
                            name: item.name, 
                            variationId: item.size_id || null,
                            variationName: item.variation_name || null,
                            basePrice: parseFloat(item.base_price || item.price),
                            modifierTotal: parseFloat(item.modifier_total || 0),
                            discountAmount: parseFloat(item.discount_amount || 0),
                            modifiers: mods,
                            qty: parseInt(item.quantity), 
                            notes: item.notes || '',
                            served: parseInt(item.served || 0),
                            unique_key: uKey // Explicitly store key
                        };
                    });
                } else { currentOrderId = null; }
                updateCart();
            });
    }

    function saveCart(allowEmpty = false) {
        if (!selectedTableId) return Promise.reject('No table selected');
        let validItems = [];
        for (let key in cart) {
            let it = cart[key];
            validItems.push({ 
                unique_key: key,
                order_item_id: it.order_item_id || null, 
                product_id: it.productId, 
                variation_id: it.variationId,
                quantity: it.qty,
                base_price: it.basePrice,
                modifier_total: it.modifierTotal,
                discount_amount: it.discountAmount,
                modifiers: it.modifiers,
                notes: it.notes
            });
        }
        if (validItems.length === 0 && !allowEmpty) return Promise.reject('Cart empty'); 

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
                if (data.sync_map) {
                    for (const [uKey, dbId] of Object.entries(data.sync_map)) {
                        if (cart[uKey]) cart[uKey].order_item_id = dbId;
                    }
                }
                updateOrderTime(data.order_id);
                triggerPrinting(data.order_id, 'kitchen');
                return data;
            } else { throw new Error(data.error); }
        })
        .finally(() => { saveBtn.disabled = false; });
    }

    // --- PRODUCT RENDERING ---
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
            btn.onclick = () => {
                currentCategory = cat;
                document.querySelectorAll('.category-tab').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                renderProducts(cat);
            };
            categoriesContainer.appendChild(btn);
        });
    }

    function renderProducts(category) {
        productsGrid.innerHTML = '';
        (allProducts[category] || []).forEach(p => {
            const card = document.createElement('div');
            card.className = 'product-card';
            card.innerHTML = `<div class="product-name">${p.name}</div><div class="product-price">${parseInt(p.has_variation) === 1 ? 'Starts at ' : ''}₱${parseFloat(p.price).toFixed(2)}</div>`;
            card.onclick = () => {
                if (!selectedTableId) return;
                editingUniqueKey = null; // Clear edit flag for new adds
                if (parseInt(p.has_variation) === 1) {
                    showVariationPicker(p.id, p.name);
                } else {
                    addToCart(p.id, p.name, p.price, null, null, [], 0);
                }
            };
            productsGrid.appendChild(card);
        });
    }

    // --- EVENTS & UTILS ---
    window.changeQty = function(key, d) {
        if (cart[key]) {
            const newQty = cart[key].qty + d;
            if (d < 0 && newQty < (cart[key].served || 0)) {
                Swal.fire('Error', 'Cannot reduce below served quantity.', 'warning');
                return;
            }
            if (newQty > 0) { cart[key].qty = newQty; updateCart(); } 
            else { removeFromCart(key); }
        }
    };

    window.removeFromCart = function(key) {
        if (cart[key] && (cart[key].served || 0) > 0) {
            Swal.fire('Denied', 'Item already served.', 'error');
            return;
        }
        Swal.fire({ title: 'Remove item?', icon: 'warning', showCancelButton: true }).then(res => {
            if (res.isConfirmed) {
                delete cart[key];
                updateCart();
                saveCart(true); 
                updateTableStatus();
            }
        });
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

    tableSelect.addEventListener('change', (e) => {
        selectedTableId = parseInt(e.target.value) || null;
        cart = {}; currentOrderId = null;
        toggleProductLock(); 
        loadCart();
    });

    saveBtn.onclick = () => saveCart(false).then(() => {
        Swal.fire({ icon: 'success', title: 'Saved & Sent', toast: true, position: 'top-end', timer: 1500, showConfirmButton: false });
    });

    printBillBtn.onclick = () => {
        if (!selectedTableId || Object.keys(cart).length === 0) return;
        saveCart(false).then(data => triggerPrinting(data.order_id, 'bill'));
    };

    checkoutBtn.onclick = () => {
        if (!selectedTableId || Object.keys(cart).length === 0) return;
        saveCart(false).then(() => {
            currentBillAmount = Object.values(cart).reduce((s, it) => {
                return s + ((it.basePrice + it.modifierTotal) * it.qty) - it.discountAmount;
            }, 0);
            document.getElementById('pmTotal').textContent = '₱' + currentBillAmount.toFixed(2);
            document.getElementById('pmGiven').value = currentBillAmount.toFixed(2);
            document.getElementById('paymentPopup').style.display = 'flex';
            updateChange();
        });
    };

    clearBtn.onclick = () => {
        Swal.fire({ title: 'Clear cart?', icon: 'warning', showCancelButton: true }).then(res => {
            if (res.isConfirmed) {
                cart = {}; updateCart(); saveCart(true); updateTableStatus();
            }
        });
    };

    productSearch.oninput = (e) => {
        const term = e.target.value.toLowerCase().trim();
        if (!term) { renderProducts(currentCategory); categoriesContainer.style.display = 'flex'; return; }
        categoriesContainer.style.display = 'none';
        productsGrid.innerHTML = '';
        Object.values(allProducts).forEach(cat => cat.forEach(p => {
            if (p.name.toLowerCase().includes(term)) {
                const card = document.createElement('div');
                card.className = 'product-card';
                card.innerHTML = `<div class="product-name">${p.name}</div><div class="product-price">₱${p.price}</div>`;
                card.onclick = () => {
                    editingUniqueKey = null; // New item add
                    if (parseInt(p.has_variation) === 1) showVariationPicker(p.id, p.name);
                    else addToCart(p.id, p.name, p.price, null, null, [], 0);
                };
                productsGrid.appendChild(card);
            }
        }));
    };

    function updateChange() {
        const given = parseFloat(document.getElementById('pmGiven').value) || 0;
        const diff = given - currentBillAmount;
        const lbl = document.getElementById('pmChange');
        lbl.textContent = (diff < 0 ? '-₱' : '₱') + Math.abs(diff).toFixed(2);
        lbl.style.color = diff < 0 ? '#d32f2f' : '#2e7d32';
    }

    document.getElementById('pmGiven').oninput = updateChange;
    window.setCash = (a) => { document.getElementById('pmGiven').value = a.toFixed(2); updateChange(); };
    document.getElementById('pmClose').onclick = document.getElementById('pmCancel').onclick = () => {
        document.getElementById('paymentPopup').style.display = 'none';
    };

    document.getElementById('pmConfirm').onclick = () => {
        const method = document.querySelector('input[name="pmMethod"]:checked').value;
        const given = parseFloat(document.getElementById('pmGiven').value || 0);
        if (given < (currentBillAmount - 0.01)) { Swal.fire('Error', 'Insufficient amount', 'warning'); return; }
        
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
            selectedTableId = null; cart = {}; currentOrderId = null;
            tableSelect.value = ""; toggleProductLock(); filterTables(); updateCart();
        })
        .catch(err => Swal.fire('Error', err.message, 'error'));
    };

    // --- NEW: Reset Table on Order Type Change ---
    document.querySelectorAll('input[name="orderType"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (tableSelect) {
                tableSelect.value = "0"; // Reset table selection
                selectedTableId = null;  // Reset internal state
                cart = {};               // Clear cart visually (optional, safer to clear)
                updateCart();
                toggleProductLock();     // Re-lock products
            }
            filterTables();
        });
    });

    function filterTables() {
        const type = document.querySelector('input[name="orderType"]:checked').value;
        btnDineIn.style.background = (type === 'physical') ? '#e8f5e9' : '#fff';
        btnTakeOut.style.background = (type === 'virtual') ? '#e8f5e9' : '#fff';
        tableSelect.querySelectorAll('option').forEach(opt => {
            if (opt.value === "0") return;
            opt.style.display = (opt.getAttribute('data-type') === type) ? 'block' : 'none';
        });
    }

    // Init
    loadProducts();
    toggleProductLock();
    filterTables();
</script>
</body>
</html>