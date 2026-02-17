<?php
require_once __DIR__ . '/../../db.php';
?>
<script>
    window.FOGS_BASE_URL = "<?php echo $base_url; ?>";
</script>
<script src="<?php echo $base_url; ?>/assets/autolock.js"></script>

<?php
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
$categoryTypes = []; 
$discounts = [];

try {
    $mysqli = get_db_conn();
    
    // 1. Fetch Tables
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
        $stmt->close();
    }

    // 2. Fetch Active Discounts (Now includes target_type)
    $d_res = $mysqli->query("SELECT * FROM discounts WHERE is_active = 1");
    if ($d_res) {
        while($d = $d_res->fetch_assoc()) {
            $discounts[] = $d;
        }
    }

    // 3. FETCH CATEGORY DEFINITIONS
    $colCheck = $mysqli->query("SHOW COLUMNS FROM `categories` LIKE 'cat_type'");
    $hasTypeCol = $colCheck && $colCheck->num_rows > 0;
    
    $c_sql = $hasTypeCol ? "SELECT name, cat_type FROM categories" : "SELECT name FROM categories";
    $c_res = $mysqli->query($c_sql);
    if ($c_res) {
        while($c = $c_res->fetch_assoc()) {
            $categoryTypes[$c['name']] = $hasTypeCol ? $c['cat_type'] : 'food';
        }
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
        
        /* --- NEW EDIT MODE STYLES --- */
        .option-card {
            border: 1px solid #ddd; padding: 10px; margin: 5px; border-radius: 8px;
            cursor: pointer; transition: all 0.2s; position: relative; display: block; text-align: left;
        }
        .option-card:hover { background: #f9f9f9; }
        .option-card.selected {
            border: 2px solid #2e7d32 !important; 
            background-color: #e8f5e9;            
            color: #1b5e20;
        }
        .item-name-clickable {
            cursor: pointer; text-decoration: underline; text-decoration-style: dotted; text-decoration-color: #999;
        }
        .item-name-clickable:hover { color: #1976d2; }
        
        /* Modal Buttons */
        .modal-btn {
            display: block; width: 100%; padding: 15px; margin-bottom: 10px;
            border: none; border-radius: 8px; font-size: 1rem; font-weight: bold;
            cursor: pointer; text-align: left; transition: transform 0.1s;
            color: white;
            position: relative;
        }
        .modal-btn:active { transform: scale(0.98); }
        .modal-btn small { display: block; margin-top: 4px; font-weight: normal; font-size: 0.8rem; }
        .swal-cat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; text-align: left; max-height: 200px; overflow-y: auto; margin-top: 10px; }
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
            
            <div class="cart-total" style="font-size: 1rem; line-height: 1.5;">
                Total: <span id="cartTotal">₱0.00</span>
            </div>
            
            <div class="cart-actions">
                <button onclick="openDiscountWizard()" style="grid-column: span 2; background: #ff9800; color: white; border: none; padding: 10px; border-radius: 6px; font-weight: bold; cursor: pointer; margin-bottom: 5px;">
                    % Discounts & Promos
                </button>
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
                    <label style="cursor:pointer;">
                        <input type="radio" name="pmMethod" value="cash" checked style="display:none;">
                        <span class="pm-method-content" style="border:2px solid #ddd; text-align:center;">
                            <strong>💵 Cash</strong>
                        </span>
                    </label>
                    <label style="cursor:pointer;">
                        <input type="radio" name="pmMethod" value="gcash" style="display:none;">
                        <span class="pm-method-content" style="border:2px solid #ddd; text-align:center;">
                            <strong>📱 GCash</strong>
                        </span>
                    </label>
                </div>
                <div style="margin-bottom:1rem;">
                    <label style="font-weight:600; color:#444;">Amount Received</label>
                    <input id="pmGiven" type="number" step="0.01" style="width:90%; padding:1rem; font-size:1.5rem; border:2px solid #eee; border-radius:8px; margin-top:5px; text-align:right;" placeholder="0.00">
                </div>
                <div style="display:flex; gap:8px; margin-bottom:1.5rem; flex-wrap:wrap; justify-content: center;">
                    <button type="button" class="quick-cash" onclick="setCash(1)">₱1</button>
                    <button type="button" class="quick-cash" onclick="setCash(100)">₱100</button>
                    <button type="button" class="quick-cash" onclick="setCash(300)">₱300</button>
                    <button type="button" class="quick-cash" onclick="setCash(500)">₱500</button>
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
    let pendingItem = null; 
    let editingUniqueKey = null; 
    
    // Global Discount State
    let orderDiscount = 0;
    let orderDiscountNote = '';
    let currentGrandTotal = 0;

    // --- DOM Elements ---
    const dbDiscounts = <?php echo json_encode($discounts); ?>;
    const dbCategoryTypes = <?php echo json_encode($categoryTypes); ?>;
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

    // --- Helper: Get Category Info + Type ---
    function getCategoryInfo(prodId) {
        for (let catName in allProducts) {
            let product = allProducts[catName].find(p => p.id == prodId);
            if (product) {
                let dbType = dbCategoryTypes[catName] || 'food';
                if (!dbCategoryTypes[catName]) {
                    let lowerCat = catName.toLowerCase();
                    const drinkKeywords = ['drink', 'beverage', 'beer', 'alcohol', 'wine', 'liquor', 'coffee', 'tea', 'juice'];
                    if (drinkKeywords.some(k => lowerCat.includes(k))) {
                        dbType = 'drink';
                    }
                }
                return { name: catName, type: dbType };
            }
        }
        return { name: 'Other', type: 'food' };
    }

    // --- (KEEP EXISTING HELPERS: triggerPrinting, toggleProductLock, updateOrderTime) ---
    function triggerPrinting(orderId, mode = 'all') {
        if (!orderId) return;
        fetch(`print_order.php?order_id=${orderId}&type=${mode}`, { method: 'GET', credentials: 'same-origin' })
        .catch(err => console.error('Printer request failed:', err));
    }

    function toggleProductLock() {
        productOverlay.style.display = !selectedTableId ? 'flex' : 'none';
        productSearch.disabled = !selectedTableId;
    }

    function updateOrderTime(orderId) {
        if (!orderId) return;
        fetch('update_order_time.php', {
            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        }).catch(err => console.warn('Failed to update order time:', err));
    }

    // --- EDIT & VARIATION LOGIC (Keep existing) ---
    window.prepareEditCartItem = function(uniqueKey) {
        const item = cart[uniqueKey];
        if (!item) return;
        if(item.served > 0) Swal.fire('Note', 'This item has already been served to the kitchen.', 'info');
        editingUniqueKey = uniqueKey; 
        showVariationPicker(item.productId, item.name, item); 
    };

    function showVariationPicker(productId, productName, editItem = null) {
        fetch(`get_variations.php?product_id=${productId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.sizes || data.sizes.length === 0) {
                    if (data.modifiers && data.modifiers.length > 0) {
                        pendingItem = { pId: productId, pName: productName, sId: null, sName: null, sPrice: data.base_price || 0 };
                        showModifierPicker(productId, productName, null, null, data.base_price || 0, editItem);
                    } else {
                        addToCart(productId, productName, data.base_price || 0);
                    }
                    return;
                }
                let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top:10px;">';
                data.sizes.forEach(s => {
                    const isSelected = editItem && editItem.variationId == s.id ? 'selected' : '';
                    html += `
                        <div class="option-card size-card ${isSelected}" onclick="handleSizeClick(this, ${productId}, '${productName.replace(/'/g, "\\'")}', '${s.id}', '${s.name.replace(/'/g, "\\'")}', ${s.price})">
                            <strong>${s.name}</strong><br>₱${parseFloat(s.price).toFixed(2)}
                            <input type="radio" name="size_opt" value="${s.id}" ${isSelected ? 'checked' : ''} style="display:none;">
                        </div>`;
                });
                html += '</div>';
                window.currentEditItemContext = editItem; 
                Swal.fire({ 
                    title: editItem ? 'Edit Size' : 'Select Size', 
                    html: html, showConfirmButton: false, showCancelButton: true
                }).then((res) => {
                    if(res.dismiss) { editingUniqueKey = null; window.currentEditItemContext = null; }
                });
            });
    }

    window.handleSizeClick = function(el, pId, pName, sId, sName, sPrice) {
        document.querySelectorAll('.size-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
        const editItem = window.currentEditItemContext; 
        showModifierPicker(pId, pName, sId, sName, sPrice, editItem);
    };

    function showModifierPicker(pId, pName, sId, sName, sPrice, editItem = null) {
        pendingItem = { pId, pName, sId, sName, sPrice };
        fetch(`get_variations.php?product_id=${pId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.modifiers || data.modifiers.length === 0) {
                    finishItemProcess([]); 
                    return;
                }
                let html = '<div style="text-align: left; max-height: 250px; overflow-y: auto;">';
                data.modifiers.forEach(m => {
                    let isChecked = ''; let isSelectedClass = '';
                    if (editItem && editItem.modifiers) {
                        const exists = editItem.modifiers.find(em => em.id == m.id);
                        if (exists) { isChecked = 'checked'; isSelectedClass = 'selected'; }
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
                    html: html, showCancelButton: true, confirmButtonText: editItem ? 'Update Item' : 'Add to Cart',
                    preConfirm: () => {
                        return Array.from(document.querySelectorAll('.mod-check:checked')).map(cb => ({
                            id: cb.value, name: cb.dataset.name, price: parseFloat(cb.dataset.price)
                        }));
                    }
                }).then(res => { 
                    if (res.isConfirmed) finishItemProcess(res.value);
                    else editingUniqueKey = null; 
                });
            });
    }

    window.toggleModClass = function(el) {
        const cb = el.querySelector('input[type="checkbox"]');
        cb.checked = !cb.checked; 
        if (cb.checked) el.classList.add('selected'); else el.classList.remove('selected');
    };

    function finishItemProcess(selectedMods) {
        const p = pendingItem;
        const extraPrice = selectedMods.reduce((sum, m) => sum + m.price, 0);
        if (editingUniqueKey) {
            const oldItem = cart[editingUniqueKey];
            oldItem.variationId = p.sId; oldItem.variationName = p.sName;
            oldItem.basePrice = parseFloat(p.sPrice); oldItem.modifiers = selectedMods;
            oldItem.modifierTotal = extraPrice;
            updateCart(); updateTableStatus(); editingUniqueKey = null; window.currentEditItemContext = null;
        } else {
            addToCart(p.pId, p.pName, p.sPrice, p.sId, p.sName, selectedMods, extraPrice);
        }
        Swal.close();
    }

    function addToCart(prodId, prodName, basePrice, varId = null, varName = null, modifiers = [], modTotal = 0, servedQty = 0, dbItemId = null) {
        if (!modifiers) modifiers = [];
        const modIdString = modifiers.map(m => m.id).sort().join('-');
        const uniqueKey = `p${prodId}_v${varId || 0}_m${modIdString || 0}`;

        if (cart[uniqueKey]) {
            if(dbItemId === null) cart[uniqueKey].qty++;
        } else {
            const catInfo = getCategoryInfo(prodId);
            cart[uniqueKey] = {
                order_item_id: dbItemId,
                productId: prodId,
                name: prodName,
                variationId: varId, variationName: varName,
                basePrice: parseFloat(basePrice),
                modifierTotal: parseFloat(modTotal),
                category: catInfo.name,      
                categoryType: catInfo.type, 
                discountAmount: 0,
                discountNote: '',
                modifiers: modifiers,
                qty: 1,
                served: servedQty
            };
        }
        updateCart();
        updateTableStatus();
    }

    // --- CORE UPDATE CART ---
    function updateCart() {
        cartItems.innerHTML = ''; 
        let subtotal = 0;
        let totalItemDiscount = 0;

        Object.entries(cart).forEach(([key, it]) => {
            const unitPrice = it.basePrice + it.modifierTotal;
            const itemSubtotal = unitPrice * it.qty;
            const lineDiscount = parseFloat(it.discountAmount || 0); 
            const finalLineTotal = itemSubtotal - lineDiscount;
            
            subtotal += itemSubtotal; 
            totalItemDiscount += lineDiscount;

            let displayName = it.name;
            if(it.variationName) displayName += ` <span style="color:#666;">(${it.variationName})</span>`;
            
            let modString = '';
            if(it.modifiers && it.modifiers.length > 0) {
                modString = '<br><small style="color:#2e7d32;">+ ' + it.modifiers.map(m => m.name).join(', ') + '</small>';
            }

            let priceHTML = `₱${finalLineTotal.toFixed(2)}`;
            if (lineDiscount > 0) {
                priceHTML = `
                    <div style="text-align:right;">
                        <small style="text-decoration: line-through; color: #999;">₱${itemSubtotal.toFixed(2)}</small><br>
                        <span style="color: #d32f2f; font-weight: bold; font-size: 0.8rem;">-₱${lineDiscount.toFixed(2)}</span><br>
                        <b style="color: #2e7d32;">₱${finalLineTotal.toFixed(2)}</b>
                    </div>
                `;
            }

            let itemNoteHtml = '';
            if (it.discountNote && it.discountNote.trim() !== '') {
                itemNoteHtml = `<br><small style="color: #d32f2f; font-weight: bold;">[${it.discountNote}]</small>`;
            }

            const row = document.createElement('div');
            row.className = 'cart-item';
            row.innerHTML = `
                <div class="item-name item-name-clickable" onclick="prepareEditCartItem('${key}')">
                    ${displayName}
                    ${modString}
                    ${itemNoteHtml}
                </div>
                <div class="qty-control">
                    <button class="qty-btn" onclick="changeQty('${key}', -1)">−</button>
                    <input type="text" class="item-qty-input" value="${it.qty}" readonly>
                    <button class="qty-btn" onclick="changeQty('${key}', 1)">+</button>
                </div>
                <div class="item-price" onclick="applyItemDiscount('${key}')" style="min-width: 60px;">
                    ${priceHTML}
                </div>
                <button class="item-remove" onclick="removeFromCart('${key}')">×</button>
            `;
            cartItems.appendChild(row);
        });

        // --- GLOBAL DISCOUNT CALCULATION ---
        const billSubtotal = subtotal - totalItemDiscount; 
        currentGrandTotal = billSubtotal - orderDiscount;
        if(currentGrandTotal < 0) currentGrandTotal = 0;

        let summaryHTML = `<div style="font-size: 0.9rem; color: #666; text-align: right;">Subtotal: ₱${subtotal.toFixed(2)}</div>`;
        if (totalItemDiscount > 0) {
            summaryHTML += `<div style="font-size: 0.9rem; color: #d32f2f; text-align: right;">Item Disc: -₱${totalItemDiscount.toFixed(2)}</div>`;
        }
        if (orderDiscount > 0) {
            summaryHTML += `
                <div style="font-size: 0.9rem; color: #d32f2f; text-align: right; font-weight:bold; border-bottom: 1px dashed #ccc; padding-bottom:5px; margin-bottom:5px;">
                    ${orderDiscountNote || 'Global Discount'}: -₱${orderDiscount.toFixed(2)}
                </div>`;
        }
        summaryHTML += `<div style="font-size: 1.6rem; font-weight: bold; text-align: right; margin-top: 5px; color: #1b5e20;">Total: ₱${currentGrandTotal.toFixed(2)}</div>`;
        cartTotal.innerHTML = summaryHTML;

        if (Object.keys(cart).length === 0) cartItems.innerHTML = '<div class="empty-cart">Select a table and add items</div>';
    }

    // ============================================
    //         UPDATED DISCOUNT LOGIC
    // ============================================

    window.openDiscountWizard = function() {
        const keys = Object.keys(cart);
        if (keys.length === 0) return Swal.fire('Error', 'Cart is empty', 'error');

        // 1. Generate Database Discount Buttons
        let dbBtnsHtml = '';
        if (dbDiscounts && dbDiscounts.length > 0) {
            dbDiscounts.forEach(d => {
                const valLabel = d.type === 'percent' ? `${d.value}%` : `₱${d.value}`;
                let scopeLabel = d.target_type === 'all' ? 'Entire Bill' : d.target_type;
                if(d.target_type === 'highest') scopeLabel = 'Highest Item';
                
                dbBtnsHtml += `
                    <button onclick="applyDbDiscount(${d.id})" class="modal-btn" style="background:#607d8b;">
                        🏷️ ${d.name} <span style="float:right; opacity:0.8;">${valLabel}</span>
                        <small style="color:#cfd8dc; text-transform:uppercase;">Applies to: ${scopeLabel}</small>
                    </button>
                `;
            });
        } else {
            dbBtnsHtml = '<div style="color:#999; text-align:center; padding:10px;">No presets found.</div>';
        }

        Swal.fire({
            title: 'Select Discount',
            html: `
                <div style="display: flex; flex-direction:column; gap: 8px; max-height: 450px; overflow-y: auto;">
                    ${dbBtnsHtml}
                    <hr style="width:100%; border:0; border-top:1px dashed #ccc; margin: 5px 0;">
                    
                    <button onclick="showGlobalDiscountInput()" class="modal-btn" style="background:#e91e63;">
                        ❤️ Manual / Custom
                        <small style="color:white; opacity:0.8;">Custom Amount or Category Select</small>
                    </button>

                    <button onclick="executeSeniorDiscount()" class="modal-btn" style="background:#ff9800;">
                        👴 Senior/PWD (1 Food+1 Drink)
                        <small style="color:white; opacity:0.8;">Auto-calculate complex rule</small>
                    </button>

                    <button onclick="clearAllDiscounts()" class="modal-btn" style="background:#f44336; margin-top:10px;">
                        ❌ Clear All Discounts
                    </button>
                </div>
            `,
            showConfirmButton: false, showCancelButton: true, cancelButtonText: 'Close'
        });
    };

    // --- Apply DB Discount based on Target Type ---
    window.applyDbDiscount = function(dId) {
        const d = dbDiscounts.find(x => x.id == dId);
        if(!d) return;

        // Reset previous discounts to avoid conflicts
        clearLocalDiscounts();

        const val = parseFloat(d.value);
        const isPercent = (d.type === 'percent');
        const note = d.name;

        // CASE 1: CUSTOM (Triggers the manual category selector with preset value)
        if (d.target_type === 'custom') {
            Swal.close();
            // Pass the preset values to the custom wizard
            return showGlobalDiscountInput(val, d.type, note);
        }

        // CASE 2: ALL (Global Order Discount)
        if (d.target_type === 'all') {
            let runningTotal = 0;
            Object.values(cart).forEach(it => { runningTotal += (it.basePrice + it.modifierTotal) * it.qty; });
            orderDiscount = isPercent ? (runningTotal * (val / 100)) : val;
            orderDiscountNote = note;
        }

        // CASE 3: HIGHEST (Single costliest item)
        else if (d.target_type === 'highest') {
            let maxPrice = -1; let maxKey = null;
            Object.entries(cart).forEach(([k, it]) => {
                const unitTotal = it.basePrice + it.modifierTotal;
                if (unitTotal > maxPrice) { maxPrice = unitTotal; maxKey = k; }
            });
            if (maxKey) {
                // Apply discount to the line (or 1 unit? Usually logic implies 1 unit if 'Highest Item')
                // Here we apply to the *line total* of that item for simplicity, or we can cap it.
                // Let's apply standard logic: Value off the Item's Line Total
                const lineTotal = cart[maxKey].qty * maxPrice;
                cart[maxKey].discountAmount = isPercent ? (lineTotal * (val / 100)) : val;
                cart[maxKey].discountNote = note;
            }
        }

        // CASE 4: FOOD or DRINK (Category Type)
        else if (d.target_type === 'food' || d.target_type === 'drink') {
            Object.keys(cart).forEach(k => {
                const it = cart[k];
                if (it.categoryType === d.target_type) {
                    const lineTotal = (it.basePrice + it.modifierTotal) * it.qty;
                    cart[k].discountAmount = isPercent ? (lineTotal * (val / 100)) : val;
                    cart[k].discountNote = note;
                }
            });
        }

        updateCart();
        Swal.close();
        Swal.fire({ icon: 'success', title: 'Applied', text: `${note} applied successfully`, timer: 1000, showConfirmButton: false });
    };

    // --- Manual / Custom Discount Wizard ---
    window.showGlobalDiscountInput = function(preVal=null, preType='percent', preNote='') {
        // Build Category Checkboxes
        let catChecks = '';
        Object.keys(allProducts).forEach(cat => {
            catChecks += `
                <label style="display:flex; align-items:center; font-size:0.9rem;">
                    <input type="checkbox" class="swal-cat-check" value="${cat}"> &nbsp; ${cat}
                </label>`;
        });

        Swal.fire({
            title: 'Custom Discount',
            html: `
                <div style="text-align:left; font-weight:bold; margin-bottom:5px;">1. Value</div>
                <div style="display:flex; gap:10px; margin-bottom:15px;">
                    <input type="number" id="gDiscVal" class="swal2-input" placeholder="Amount" value="${preVal||''}" style="margin:0; flex:1;">
                    <select id="gDiscType" class="swal2-input" style="margin:0; width:100px;">
                        <option value="percent" ${preType==='percent'?'selected':''}>%</option>
                        <option value="fixed" ${preType!=='percent'?'selected':''}>₱</option>
                    </select>
                </div>
                
                <div style="text-align:left; font-weight:bold; margin-bottom:5px;">2. Reason</div>
                <input type="text" id="gDiscNote" class="swal2-input" placeholder="Reason (e.g. Promo)" value="${preNote}" style="margin:0; margin-bottom:15px; width:100%;">

                <div style="text-align:left; font-weight:bold; margin-bottom:5px;">3. Target Scope</div>
                <div style="display:flex; gap:10px; margin-bottom:10px;">
                    <label style="flex:1; padding:10px; border:1px solid #ddd; border-radius:6px; cursor:pointer;">
                        <input type="radio" name="gScope" value="all" checked onchange="document.getElementById('catSelectArea').style.display='none'"> 
                        Entire Bill
                    </label>
                    <label style="flex:1; padding:10px; border:1px solid #ddd; border-radius:6px; cursor:pointer;">
                        <input type="radio" name="gScope" value="cats" onchange="document.getElementById('catSelectArea').style.display='grid'"> 
                        Specific Categories
                    </label>
                </div>
                
                <div id="catSelectArea" class="swal-cat-grid" style="display:none; border:1px solid #eee; padding:10px; border-radius:6px;">
                    ${catChecks}
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Apply Discount',
            preConfirm: () => {
                const val = parseFloat(document.getElementById('gDiscVal').value);
                const type = document.getElementById('gDiscType').value;
                const note = document.getElementById('gDiscNote').value || 'Custom';
                const scope = document.querySelector('input[name="gScope"]:checked').value;
                
                if(!val) return Swal.showValidationMessage('Enter a value');
                
                let selectedCats = [];
                if(scope === 'cats') {
                    document.querySelectorAll('.swal-cat-check:checked').forEach(c => selectedCats.push(c.value));
                    if(selectedCats.length === 0) return Swal.showValidationMessage('Select at least one category');
                }
                
                return { val, type, note, scope, selectedCats };
            }
        }).then(res => {
            if (res.isConfirmed) {
                clearLocalDiscounts();
                const { val, type, note, scope, selectedCats } = res.value;

                if (scope === 'all') {
                    // Apply to Order Global
                    let runningTotal = 0;
                    Object.values(cart).forEach(it => { runningTotal += (it.basePrice + it.modifierTotal) * it.qty; });
                    orderDiscount = (type === 'percent') ? (runningTotal * (val / 100)) : val;
                    orderDiscountNote = note;
                } else {
                    // Apply to Specific Categories (Item Level)
                    Object.keys(cart).forEach(k => {
                        const it = cart[k];
                        if (selectedCats.includes(it.category)) {
                            const lineTotal = (it.basePrice + it.modifierTotal) * it.qty;
                            cart[k].discountAmount = (type === 'percent') ? (lineTotal * (val / 100)) : val;
                            cart[k].discountNote = note;
                        }
                    });
                }
                updateCart();
            }
        });
    };

    function clearLocalDiscounts() {
        Object.keys(cart).forEach(k => {
            cart[k].discountAmount = 0;
            cart[k].discountNote = '';
            // Safety clear
            cart[k].discountValue = 0; 
            if(cart[k].notes) cart[k].notes = ''; 
        });
        orderDiscount = 0;
        orderDiscountNote = '';
    }

    // Senior Discount (Complex 1 Food + 1 Drink Logic)
    window.executeSeniorDiscount = function() {
        const keys = Object.keys(cart);
        let foodKey = null; let maxFoodPrice = 0;
        let drinkKey = null; let maxDrinkPrice = 0;

        clearLocalDiscounts();

        keys.forEach(k => {
            const it = cart[k];
            const unitPrice = it.basePrice + it.modifierTotal;
            if (it.categoryType === 'drink') {
                if (unitPrice > maxDrinkPrice) { maxDrinkPrice = unitPrice; drinkKey = k; }
            } else {
                if (unitPrice > maxFoodPrice) { maxFoodPrice = unitPrice; foodKey = k; }
            }
        });

        let applied = false;
        if (foodKey) {
            // Apply 20% to the single food item line (assuming qty 1, if qty > 1 logic might need split, keeping simple)
            cart[foodKey].discountAmount = (cart[foodKey].basePrice + cart[foodKey].modifierTotal) * 0.20;
            cart[foodKey].discountNote = "Senior/PWD (Food)";
            applied = true;
        }
        if (drinkKey) {
            cart[drinkKey].discountAmount = (cart[drinkKey].basePrice + cart[drinkKey].modifierTotal) * 0.20;
            cart[drinkKey].discountNote = "Senior/PWD (Drink)";
            applied = true;
        }

        updateCart();
        Swal.close(); 
        if(applied) Swal.fire('Applied', '20% applied to 1 Food and/or 1 Drink item.', 'success');
        else Swal.fire('No Match', 'Add food or drink items first.', 'warning');
    };

    // Item Manual Discount (Single Item Click)
    window.applyItemDiscount = function(key) {
        const item = cart[key];
        const itemSubtotal = (item.basePrice + item.modifierTotal) * item.qty;

        Swal.fire({
            title: 'Apply Item Discount',
            html: `
                <div style="text-align: left; font-size: 0.9rem; color: #666; margin-bottom: 10px;">
                    Item: <strong>${item.name}</strong><br>
                    Subtotal: <strong>₱${itemSubtotal.toFixed(2)}</strong>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <select id="discType" class="swal2-input" style="margin:0; flex: 1;">
                        <option value="percent">% Percentage</option>
                        <option value="fixed">₱ Fixed Amount</option>
                    </select>
                    <input type="number" id="discValue" class="swal2-input" style="margin:0; flex: 1;" placeholder="Value">
                </div>
                <input type="text" id="discNote" class="swal2-input" style="margin:0; width: 90%;" placeholder="Reason (Optional)" value="${item.discountNote || ''}">
            `,
            showCancelButton: true, confirmButtonText: 'Apply',
            preConfirm: () => {
                const type = document.getElementById('discType').value;
                const val = parseFloat(document.getElementById('discValue').value) || 0;
                const note = document.getElementById('discNote').value;
                let finalDiscount = (type === 'percent') ? itemSubtotal * (val / 100) : val;
                if (finalDiscount > itemSubtotal) finalDiscount = itemSubtotal;
                return { finalDiscount, note };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                cart[key].discountAmount = result.value.finalDiscount;
                cart[key].discountNote = result.value.note;     
                updateCart();
            }
        });
    };

    window.clearAllDiscounts = function() {
        clearLocalDiscounts();
        updateCart();
        Swal.close();
    };

    // ... (Keep the rest of the file: loadCart, saveCart, loadProducts, events) ...
    // The previous implementation of these functions is fine, just ensure loadCart maps 'discount_note' correctly.
    
    function loadCart() {
        if (!selectedTableId) { 
            cart = {}; currentOrderId = null; 
            orderDiscount = 0; orderDiscountNote = '';
            updateCart(); return; 
        }
        fetch('get_pos_cart.php?table_id=' + encodeURIComponent(selectedTableId))
            .then(r => r.json())
            .then(data => {
                cart = {}; 
                if (data.success) {
                    currentOrderId = data.order_id;
                    orderDiscount = parseFloat(data.order_discount || 0);
                    orderDiscountNote = data.order_discount_note || '';

                    (data.items || []).forEach(item => {
                        const mods = item.modifiers || [];
                        const modString = mods.map(m => m.id).sort().join('-');
                        const uKey = `p${item.product_id}_v${item.variation_id || item.size_id || 0}_m${modString || 0}_db${item.order_item_id}`; 
                        const catInfo = getCategoryInfo(item.product_id);

                        cart[uKey] = {
                            order_item_id: item.order_item_id,
                            productId: item.product_id, 
                            name: item.name, 
                            variationId: item.variation_id || item.size_id || null,
                            variationName: item.variation_name || item.variationName || null,
                            basePrice: parseFloat(item.base_price || item.basePrice || 0),
                            modifierTotal: parseFloat(item.modifier_total || item.modifierTotal || 0),
                            discountAmount: parseFloat(item.discount_amount || item.discountAmount || 0),
                            discountNote: item.discount_note || item.discountNote || '', 
                            modifiers: mods,
                            qty: parseInt(item.quantity || item.qty || 0),
                            served: parseInt(item.served || 0),
                            category: catInfo.name,     
                            categoryType: catInfo.type, 
                            unique_key: uKey 
                        };
                    });
                } else { currentOrderId = null; orderDiscount = 0; orderDiscountNote = ''; }
                updateCart();
            });
    }

    // (KEEP THE REST OF THE FILE EXACTLY AS BEFORE: saveCart, loadProducts, etc.)
    // ...
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
                discount_note: it.discountNote || '', 
                modifiers: it.modifiers
            });
        }
        if (validItems.length === 0 && !allowEmpty) return Promise.reject('Cart empty'); 

        saveBtn.disabled = true;
        return fetch('save_pos_cart.php', {
            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                table_id: selectedTableId, 
                items: validItems,
                order_discount: orderDiscount,
                order_discount_note: orderDiscountNote
            })
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
            
            // VISUAL TWEAK: Show "OPEN PRICE" if price is 0
            const priceDisplay = parseFloat(p.price) > 0 
                ? '₱' + parseFloat(p.price).toFixed(2) 
                : '<span style="color:#e67e22; font-weight:bold;">OPEN PRICE</span>';

            card.innerHTML = `<div class="product-name">${p.name}</div>
                              <div class="product-price">
                                  ${parseInt(p.has_variation) === 1 ? 'Starts at ' : ''}${priceDisplay}
                              </div>`;
            
            card.onclick = () => {
                editingUniqueKey = null; 
                
                // --- NEW LOGIC: CHECK FOR ZERO/OPEN PRICE ---
                if (parseFloat(p.price) <= 0) {
                    Swal.fire({
                        title: 'Enter Price',
                        text: `Set price for ${p.name}`,
                        input: 'number',
                        inputAttributes: { min: 1, step: '0.01' },
                        showCancelButton: true,
                        confirmButtonText: 'Add to Order',
                        confirmButtonColor: '#6B4226',
                        preConfirm: (value) => {
                            if (!value || parseFloat(value) <= 0) {
                                Swal.showValidationMessage('Please enter a valid amount');
                            }
                            return parseFloat(value);
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Pass the CUSTOM VALUE as the base price
                            addToCart(p.id, p.name, result.value, null, null, [], 0);
                        }
                    });
                    return; // Stop here so we don't trigger the normal add
                }
                // ---------------------------------------------

                const needsPicker = parseInt(p.has_variation) === 1 || parseInt(p.has_modifiers) === 1;
                if (needsPicker) showVariationPicker(p.id, p.name);
                else addToCart(p.id, p.name, p.price, null, null, [], 0);
            };
            productsGrid.appendChild(card);
        });
    }

    // --- EVENTS & UTILS (Keep Existing) ---
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
            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
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
            document.getElementById('pmTotal').textContent = '₱' + currentGrandTotal.toFixed(2);
            document.getElementById('pmGiven').value = currentGrandTotal.toFixed(2);
            document.getElementById('paymentPopup').style.display = 'flex';
            updateChange();
        });
    };

    clearBtn.onclick = () => {
        Swal.fire({ title: 'Clear cart?', icon: 'warning', showCancelButton: true }).then(res => {
            if (res.isConfirmed) {
                cart = {}; orderDiscount=0; orderDiscountNote='';
                updateCart(); saveCart(true); updateTableStatus();
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
                    editingUniqueKey = null; 
                    if (parseInt(p.has_variation) === 1) showVariationPicker(p.id, p.name);
                    else addToCart(p.id, p.name, p.price, null, null, [], 0);
                };
                productsGrid.appendChild(card);
            }
        }));
    };

    function updateChange() {
        const given = parseFloat(document.getElementById('pmGiven').value) || 0;
        const diff = given - currentGrandTotal;
        const lbl = document.getElementById('pmChange');
        
        // Format with absolute value for the display
        lbl.textContent = (diff < 0 ? '-₱' : '₱') + Math.abs(diff).toFixed(2);
        lbl.style.color = diff < 0 ? '#d32f2f' : '#2e7d32';
    }

    // FIX: This now adds to the current value instead of overwriting it
    window.setCash = (amount) => {
        const input = document.getElementById('pmGiven');
        let current = parseFloat(input.value) || 0;
        input.value = (current + amount).toFixed(2);
        updateChange();
    };

    // FIX: Reset input to 0 when opening the checkout
    checkoutBtn.onclick = () => {
        if (!selectedTableId || Object.keys(cart).length === 0) return;
        
        saveCart(false).then(() => {
            document.getElementById('pmTotal').textContent = '₱' + currentGrandTotal.toFixed(2);
            
            // Start at 0 so the +100, +500 buttons work additively
            const amountInput = document.getElementById('pmGiven');
            amountInput.value = ""; 
            
            document.getElementById('paymentPopup').style.display = 'flex';
            updateChange();
            
            setTimeout(() => amountInput.focus(), 100);
        });
    };

    // Add a "Clear" button function if you want to reset the input quickly
    window.clearCash = () => {
        document.getElementById('pmGiven').value = "";
        updateChange();
    };

    document.getElementById('pmCancel').onclick = () => {
        document.getElementById('paymentPopup').style.display = 'none';
    };

    document.getElementById('pmConfirm').onclick = () => {
        const selectedMethod = document.querySelector('input[name="pmMethod"]:checked');
        const method = selectedMethod ? selectedMethod.value : 'cash';
        const given = parseFloat(document.getElementById('pmGiven').value || 0);

        if (given < (currentGrandTotal - 0.01)) {
            Swal.fire('Error', 'Insufficient amount', 'warning');
            return;
        }

        fetch('checkout_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                order_id: currentOrderId, 
                payment_method: method, 
                amount_paid: given 
            })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.error);
            
            Swal.fire('Success', 'Payment Complete', 'success').then(() => {
                // Reset everything
                document.getElementById('paymentPopup').style.display = 'none';
                selectedTableId = null;
                cart = {};
                currentOrderId = null;
                orderDiscount = 0;
                if(tableSelect) tableSelect.value = "0";
                updateCart();
                filterTables();
            });
        })
        .catch(err => Swal.fire('Error', err.message, 'error'));
    };

    document.querySelectorAll('input[name="orderType"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (tableSelect) {
                tableSelect.value = "0"; 
                selectedTableId = null; cart = {}; updateCart(); toggleProductLock();     
            }
            filterTables();
        });
    });

    function filterTables() {
        const type = document.querySelector('input[name="orderType"]:checked').value;
        
        // Update button colors
        btnDineIn.style.background = (type === 'physical') ? '#e8f5e9' : '#fff';
        btnTakeOut.style.background = (type === 'virtual') ? '#e8f5e9' : '#fff';

        tableSelect.querySelectorAll('option').forEach(opt => {
            if (opt.value === "0") return;
            
            const isCorrectType = opt.getAttribute('data-type') === type;
            // You could also check a 'data-status' attribute here if you fetch it
            opt.style.display = isCorrectType ? 'block' : 'none';
        });
    }

    

    loadProducts();
    toggleProductLock();
    filterTables();
</script>
</body>
</html>