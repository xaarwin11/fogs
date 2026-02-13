<?php
require_once __DIR__ . '/../../db.php';?>
<script src="<?php echo $base_url; ?>/assets/autolock.js"></script>
<?php
session_start();
if (empty($_SESSION['user_id'])) { header("Location: $base_url/index.php"); exit; }
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager'])) { header("Location: $base_url/index.php"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="<?php echo $base_url; ?>/assets/logo.png">
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Manage Products</title>
<link rel="stylesheet" href="<?php echo $base_url; ?>/assets/style.css">
<style>
    /* Clean Table Styling */
    .nice-table thead th { text-align:left; padding:0.8rem; border-bottom:2px solid #F2E7D5; color:#6B4226; font-weight: 700; }
    .nice-table tbody td { padding:0.8rem; border-bottom:1px solid #fafafa; }
    .nice-table tbody tr:hover { background: #fffcf5; }
    
    /* Pro Modal Styling */
    #editor {
        display:none; position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); 
        background:#fff; width:650px; max-width:95%; max-height:90vh; overflow-y:auto;
        z-index:1000; border-radius:12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        flex-direction: column;
    }
    .modal-overlay {
        display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
        background:rgba(0,0,0,0.5); z-index:999; backdrop-filter: blur(3px);
    }
    
    /* Tabs Navigation */
    .tab-header { display:flex; border-bottom:1px solid #ddd; background:#f9f9f9; border-radius: 12px 12px 0 0; }
    .tab-btn { flex:1; padding:1rem; border:none; background:none; cursor:pointer; font-weight:600; color:#666; transition:all 0.2s; }
    .tab-btn:hover { background: #eee; }
    .tab-btn.active { border-bottom:3px solid #6B4226; color:#6B4226; background:#fff; }
    
    .tab-content { display:none; padding:1.5rem; flex: 1; overflow-y: auto; }
    .tab-content.active { display:block; }

    /* Inputs & Rows */
    .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
    .var-row, .mod-row { display:flex; gap:10px; margin-bottom:10px; align-items:center; }
    .remove-row { color:#c62828; cursor:pointer; font-weight:bold; padding:0 10px; font-size: 1.2rem; }
    
    .badge-cat { background:#e3f2fd; color:#1565c0; padding:4px 8px; border-radius:4px; font-size:0.8rem; margin-right:5px; border: 1px solid #bbdefb; }
</style>
</head>
<body>
<?php require_once __DIR__ . '/../navbar.php'; ?>

<div class="modal-overlay" id="overlay"></div>

<div class="container" style="margin-top: 2rem;">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="color: #6B4226; margin: 0;">Product Inventory</h2>
            <small style="color: #888;">Manage items, variations, and modifiers</small>
        </div>
        <div style="display:flex; gap:0.5rem;">
            <button id="newBtn" class="btn" style="background: #2e7d32; color: white;">+ New Product</button>
            <button id="refreshBtn" class="btn secondary">↻ Refresh</button>
        </div>
    </div>

    <div class="products-card">
        <table id="productsTable" class="nice-table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Base Price</th>
                    <th style="text-align:center">Variations</th>
                    <th style="text-align:center">Stock</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div id="editor">
    <div class="tab-header">
        <button class="tab-btn active" onclick="switchTab('basic')">1. Basic Info</button>
        <button class="tab-btn" onclick="switchTab('variations')">2. Variations</button>
        <button class="tab-btn" onclick="switchTab('modifiers')">3. Modifiers</button>
    </div>

    <div id="tab-basic" class="tab-content active">
        <div style="margin-bottom:1rem;">
            <label style="font-weight:600; display:block; margin-bottom:5px;">Product Name</label>
            <input id="p_name" class="form-control" placeholder="e.g. Iced Latte">
        </div>
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:1rem;">
            <div>
                <label style="font-weight:600; display:block; margin-bottom:5px;">Category</label>
                <select id="p_cat_id" class="form-control" onchange="previewCategoryModifiers()"></select>
            </div>
            <div>
                <label style="font-weight:600; display:block; margin-bottom:5px;">Base Price (₱)</label>
                <input id="p_price" type="number" step="0.01" class="form-control" placeholder="0.00">
            </div>
        </div>

        <div style="display: flex; gap: 2rem; margin-top: 1rem; padding:15px; background:#fafafa; border-radius:8px; border: 1px solid #eee;">
            <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                <input id="p_kds" type="checkbox"> Send to Kitchen (KDS)
            </label>
            <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                <input id="p_avail" type="checkbox" checked> Available
            </label>
        </div>
    </div>

    <div id="tab-variations" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h3 style="margin:0; font-size: 1.1rem;">Size / Types</h3>
            <label style="cursor:pointer; color:#6B4226; font-weight:600;">
                <input type="checkbox" id="has_variations_toggle" onchange="toggleVariations()"> Has Variations?
            </label>
        </div>

        <div id="variations_panel" style="display:none;">
            <div style="background:#fff3e0; padding:10px; border-radius:6px; margin-bottom:15px; font-size:0.9rem; border: 1px solid #ffe0b2;">
                ℹ️ <strong>Tip:</strong> Use this for "Small, Medium, Large". The base price above will be ignored if variations exist.
            </div>
            
            <div id="variation_list"></div>
            
            <button type="button" class="btn small" style="margin-top:10px; border:1px dashed #999; color:#555; width:100%; background: #fdfdfd;" onclick="addVariationRow()">
                + Add Variation Row
            </button>
        </div>
        <div id="no_variations_msg" style="text-align:center; padding:3rem; color:#999; border: 2px dashed #eee; border-radius: 8px;">
            This product is sold as a single item (No sizes).
        </div>
    </div>

    <div id="tab-modifiers" class="tab-content">
        <div style="margin-bottom:1.5rem;">
            <label style="font-weight:600; display:block; margin-bottom:8px; color:#1565c0;">Linked Category Modifiers</label>
            <div id="cat_mod_preview" style="font-size:0.9rem; color:#666; padding:12px; background:#e3f2fd; border-radius:6px; min-height: 40px;">
                <em>Select a category on Tab 1 to see global add-ons...</em>
            </div>
        </div>

        <div>
            <label style="font-weight:600; display:block; margin-bottom:5px;">Product-Specific Modifiers</label>
            <small style="display:block; margin-bottom: 10px; color: #888;">Add-ons available ONLY for this item.</small>
            <div id="modifier_list"></div>
            <button type="button" class="btn small" style="margin-top:10px; border:1px dashed #999; color:#555; width:100%; background: #fdfdfd;" onclick="addModifierRow()">
                + Add Custom Modifier
            </button>
        </div>
    </div>

    <div style="text-align:right; padding: 1rem; background:#f9f9f9; border-top: 1px solid #eee; border-radius: 0 0 12px 12px;">
        <button onclick="closeEditor()" class="btn secondary" style="margin-right: 0.5rem;">Cancel</button>
        <button onclick="saveProductFull()" class="btn" style="background: #6B4226; color: white; padding: 0.6rem 1.5rem;">Save Product</button>
    </div>
</div>

<script>
let editingId = null;
let categoryModifiersMap = {}; 

// --- TABS & UI ---
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tabName).style.display = 'block';
    
    // Simple tab highlighting
    const buttons = document.querySelectorAll('.tab-btn');
    if(tabName==='basic') buttons[0].classList.add('active');
    if(tabName==='variations') buttons[1].classList.add('active');
    if(tabName==='modifiers') buttons[2].classList.add('active');
}

function toggleVariations() {
    const isOn = document.getElementById('has_variations_toggle').checked;
    document.getElementById('variations_panel').style.display = isOn ? 'block' : 'none';
    document.getElementById('no_variations_msg').style.display = isOn ? 'none' : 'block';
}

function addVariationRow(name='', price='') {
    const div = document.createElement('div');
    div.className = 'var-row';
    div.innerHTML = `
        <input type="text" placeholder="Size Name (e.g. Large)" class="form-control v-name" value="${name}">
        <input type="number" step="0.01" placeholder="Price" class="form-control v-price" value="${price}">
        <span class="remove-row" onclick="this.parentElement.remove()" title="Remove">×</span>
    `;
    document.getElementById('variation_list').appendChild(div);
}

function addModifierRow(name='', price='') {
    const div = document.createElement('div');
    div.className = 'mod-row';
    div.innerHTML = `
        <input type="text" placeholder="Add-on Name" class="form-control m-name" value="${name}">
        <input type="number" step="0.01" placeholder="Price" class="form-control m-price" value="${price}">
        <span class="remove-row" onclick="this.parentElement.remove()" title="Remove">×</span>
    `;
    document.getElementById('modifier_list').appendChild(div);
}

// --- DATA HANDLING ---
async function init() {
    await fetchCategories(); 
    fetchProducts();

    // ADD THIS LINE: It links the button click to the openEditor function
    document.getElementById('newBtn').onclick = () => openEditor(null);
    
    // Optional: Also link the Refresh button while you're at it
    document.getElementById('refreshBtn').onclick = () => fetchProducts();
}

function fetchCategories() {
    // We call a new backend script that returns categories AND their global modifiers
    return fetch('get_categories_with_mods.php').then(r => r.json()).then(data => {
        const select = document.getElementById('p_cat_id');
        select.innerHTML = '<option value="">-- Select Category --</option>';
        categoryModifiersMap = {};
        
        if(data.success) {
            data.categories.forEach(c => {
                select.innerHTML += `<option value="${c.id}">${c.name}</option>`;
                categoryModifiersMap[c.id] = c.modifiers || []; 
            });
        }
    });
}

function previewCategoryModifiers() {
    const catId = document.getElementById('p_cat_id').value;
    const container = document.getElementById('cat_mod_preview');
    
    if (!catId || !categoryModifiersMap[catId] || categoryModifiersMap[catId].length === 0) {
        container.innerHTML = '<em>No global modifiers linked to this category.</em>';
        return;
    }

    const mods = categoryModifiersMap[catId];
    container.innerHTML = mods.map(m => `<span class="badge-cat">${m.name} (+₱${parseFloat(m.price).toFixed(2)})</span>`).join(' ');
}

function fetchProducts(){
    // Re-using your existing get_products_admin.php is fine!
    fetch('get_products_admin.php').then(r=>r.json()).then(data => {
        const tbody = document.querySelector('#productsTable tbody'); 
        tbody.innerHTML='';
        // Inside products.php -> fetchProducts() function
        (data.products || []).forEach(p => {
            // Match the property name we just added to the PHP above
            const hasVar = parseInt(p.has_variation) === 1; 
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${p.name}</strong></td>
                <td><span class="badge-cat">${p.category}</span></td>
                <td>₱${parseFloat(p.price).toFixed(2)}</td>
                <td style="text-align:center">${hasVar ? '✅' : '-'}</td> 
                <td style="text-align:center">${p.available ? 'In Stock' : 'Out'}</td>
                <td style="text-align:right">
                    <button class="btn small" onclick="openEditor(${p.id})">Edit</button> 
                    <button class="btn small secondary" style="color:red" onclick="deleteProduct(${p.id})">Delete</button>
                </td>`;
            tbody.appendChild(tr);
        });
    });
}

// --- EDIT LOGIC ---
function openEditor(id){
    editingId = id || null;
    document.getElementById('editor').style.display='flex';
    document.getElementById('overlay').style.display='block';
    switchTab('basic'); 
    
    // Clear dynamic lists
    document.getElementById('variation_list').innerHTML = '';
    document.getElementById('modifier_list').innerHTML = '';
    
    if(id){
        // Fetch Details using new endpoint
        fetch(`get_product_full.php?id=${id}`).then(r=>r.json()).then(d=>{
            if (d.success) {
                const p = d.product;
                document.getElementById('p_name').value = p.name;
                document.getElementById('p_cat_id').value = p.category_id;
                document.getElementById('p_price').value = p.price;
                // Handle legacy or missing columns gracefully
                document.getElementById('p_kds').checked = (p.kds == 1);
                document.getElementById('p_avail').checked = (p.available == 1);
                
                // Variations
                const hasVar = (p.has_variation == 1);
                document.getElementById('has_variations_toggle').checked = hasVar;
                toggleVariations();
                
                if(d.variations) {
                    d.variations.forEach(v => addVariationRow(v.name, v.price));
                }

                // Update Preview of Category Mods
                previewCategoryModifiers(); 

                // Load Product-Specific Mods
                if(d.modifiers) {
                    d.modifiers.forEach(m => addModifierRow(m.name, m.price));
                }
            }
        });
    } else {
        // NEW MODE
        document.getElementById('p_name').value=''; 
        document.getElementById('p_cat_id').value=''; 
        document.getElementById('p_price').value='0.00';
        document.getElementById('has_variations_toggle').checked = false;
        document.getElementById('p_kds').checked = true;
        document.getElementById('p_avail').checked = true;
        toggleVariations();
        document.getElementById('cat_mod_preview').innerHTML = '<em>Select a category on Tab 1...</em>';
    }
}

function closeEditor(){
    document.getElementById('editor').style.display='none';
    document.getElementById('overlay').style.display='none';
}

// --- SAVE LOGIC ---
function saveProductFull(){
    const payload = {
        id: editingId,
        name: document.getElementById('p_name').value,
        category_id: document.getElementById('p_cat_id').value,
        price: document.getElementById('p_price').value,
        kds: document.getElementById('p_kds').checked ? 1 : 0,
        available: document.getElementById('p_avail').checked ? 1 : 0,
        has_variation: document.getElementById('has_variations_toggle').checked ? 1 : 0,
        
        // Arrays for child tables
        variations: [],
        modifiers: []
    };

    if(!payload.name || !payload.category_id) {
        alert("Product Name and Category are required!");
        return;
    }

    // Harvest Vars
    if(payload.has_variation){
        document.querySelectorAll('#variation_list .var-row').forEach(row => {
            const n = row.querySelector('.v-name').value;
            const p = row.querySelector('.v-price').value;
            if(n) payload.variations.push({name:n, price:p});
        });
    }

    // Harvest Mods
    document.querySelectorAll('#modifier_list .mod-row').forEach(row => {
        const n = row.querySelector('.m-name').value;
        const p = row.querySelector('.m-price').value;
        if(n) payload.modifiers.push({name:n, price:p});
    });

    // Send to NEW backend script
    fetch('save_product_full.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    }).then(r=>r.json()).then(d=>{
        if(d.success){
            closeEditor();
            fetchProducts();
        } else {
            alert('Error: ' + d.error);
        }
    });
}

function deleteProduct(id){
    if(confirm('Delete this product?')) {
        fetch('delete_product.php',{ 
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id}) 
        }).then(()=>fetchProducts());
    }
}

init();
</script>
</body>
</html>