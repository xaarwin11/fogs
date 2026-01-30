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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Manage Products</title>
<link rel="stylesheet" href="<?php echo $base_url; ?>/assets/style.css">
<style>
    .nice-table thead th { text-align:left; padding:0.8rem; border-bottom:2px solid #F2E7D5; color:#6B4226; font-weight: 700; }
    .nice-table tbody td { padding:0.8rem; border-bottom:1px solid #fafafa; }
    .products-card button { margin-left:0.2rem }
    #editor input[type="checkbox"] { transform:scale(1.2); cursor: pointer; }
    #editor h3 { margin-top:0; color: #6B4226; border-bottom: 2px solid #F2E7D5; padding-bottom: 0.5rem; }
    
    /* Improved Modal Styling */
    #editor {
        display:none; position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); 
        background:#fff; padding:2rem; border-radius:12px; width:450px; z-index:999;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2); border: 1px solid #eee;
    }
    .modal-overlay {
        display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
        background:rgba(0,0,0,0.4); z-index:998; backdrop-filter: blur(2px);
    }
    .field-group { margin-bottom: 1rem; }
    .field-group label { display: block; font-weight: 600; margin-bottom: 0.3rem; color: #444; }
</style>
</head>
<body>
<?php require_once __DIR__ . '/../navbar.php'; ?>

<div class="modal-overlay" id="overlay"></div>

<div class="container" style="margin-top: 2rem;">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="color: #6B4226; margin: 0;">Product Inventory</h2>
        <div style="display:flex; gap:0.5rem;">
            <button id="newBtn" class="btn" style="background: #2e7d32; color: white;">+ New Product</button>
            <button id="refreshBtn" class="btn secondary">↻ Refresh</button>
        </div>
    </div>

    <div id="msg" style="margin-bottom:1rem;color:#d00; font-weight: bold;"></div>

    <div class="products-card">
        <table id="productsTable" class="nice-table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th style="text-align:center">KDS</th>
                    <th style="text-align:center">Stock</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div id="editor">
    <h3 id="editorTitle">Edit Product</h3>
    
    <div class="field-group">
        <label>Product Name</label>
        <input id="p_name" class="form-control" placeholder="e.g. Caramel Macchiato">
    </div>
    
    <div class="field-group">
        <label>Category</label>
        <select id="p_cat_id" class="form-control">
            <option value="">-- Select Category --</option>
        </select>
    </div>
    
    <div class="field-group">
        <label>Price (₱)</label>
        <input id="p_price" type="number" step="0.01" class="form-control">
    </div>
    
    <div style="display: flex; gap: 2rem; margin: 1.5rem 0;">
        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
            <input id="p_kds" type="checkbox"> Send to KDS
        </label>
        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
            <input id="p_avail" type="checkbox"> Available
        </label>
    </div>
    
    <div style="text-align:right; border-top: 1px solid #eee; padding-top: 1rem;">
        <button id="cancelBtn" class="btn secondary" style="margin-right: 0.5rem;">Cancel</button>
        <button id="saveBtn" class="btn" style="background: #6B4226; color: white;">Save Changes</button>
    </div>
</div>

<script>
let editingId = null;

// 1. Load categories first so the dropdown is ready
function fetchCategories() {
    return fetch('get_categories.php').then(r => r.json()).then(data => {
        const select = document.getElementById('p_cat_id');
        select.innerHTML = '<option value="">-- Select Category --</option>';
        if(data.success) {
            data.categories.forEach(c => {
                select.innerHTML += `<option value="${c.id}">${c.name}</option>`;
            });
        }
    });
}

// 2. Load the products table
function fetchProducts(){
    fetch('get_products_admin.php').then(async r=>{
        let data = await r.json();
        const tbody = document.querySelector('#productsTable tbody'); 
        tbody.innerHTML='';
        
        (data.products||[]).forEach(p=>{
            const tr=document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${p.name}</strong></td>
                <td><span style="background:#F2E7D5; padding:2px 8px; border-radius:4px; font-size:0.85rem;">${p.category}</span></td>
                <td>₱${parseFloat(p.price).toFixed(2)}</td>
                <td style="text-align:center">${p.kds ? '✅' : '❌'}</td>
                <td style="text-align:center">${p.available ? 'In Stock' : '<span style="color:red">Out</span>'}</td>
                <td style="text-align:right">
                    <button class="btn small edit" data-id="${p.id}" style="background:#6B4226; color:white;">Edit</button> 
                    <button class="btn small del" data-id="${p.id}" style="background:#c62828; color:white;">Delete</button>
                </td>`;
            tbody.appendChild(tr);
        });
        
        document.querySelectorAll('.edit').forEach(b=>b.onclick = e=>{ openEditor(e.target.dataset.id); });
        document.querySelectorAll('.del').forEach(b=>b.onclick = e=>{ if(confirm('Delete this product permanently?')) deleteProduct(e.target.dataset.id); });
    }).catch(err => { document.getElementById('msg').textContent = 'Error: Could not connect to database.'; });
}

function openEditor(id){
    editingId = id || null;
    document.getElementById('editorTitle').textContent = id ? 'Edit Product' : 'Add New Product';
    document.getElementById('editor').style.display='block';
    document.getElementById('overlay').style.display='block';

    if(id){
        fetch('../pos/get_products.php?id='+id).then(r=>r.json()).then(d=>{
            if (d.success) {
                const p = d.product;
                document.getElementById('p_name').value = p.name;
                document.getElementById('p_cat_id').value = p.category_id; // Set dropdown to correct ID
                document.getElementById('p_price').value = p.price;
                document.getElementById('p_kds').checked = !!p.kds;
                document.getElementById('p_avail').checked = !!p.available;
            }
        });
    } else {
        document.getElementById('p_name').value=''; 
        document.getElementById('p_cat_id').value=''; 
        document.getElementById('p_price').value='0.00';
        document.getElementById('p_kds').checked = true; 
        document.getElementById('p_avail').checked = true;
    }
}

function closeEditor(){
    document.getElementById('editor').style.display='none';
    document.getElementById('overlay').style.display='none';
}

function saveProduct(){
    const payload = {
        name: document.getElementById('p_name').value,
        category_id: document.getElementById('p_cat_id').value, // Send ID, not string
        price: document.getElementById('p_price').value,
        kds: document.getElementById('p_kds').checked ? 1 : 0,
        available: document.getElementById('p_avail').checked ? 1 : 0
    };
    
    if(!payload.name || !payload.category_id) {
        alert("Please fill in the name and select a category.");
        return;
    }

    const url = editingId ? 'update_product.php' : 'create_product.php';
    if(editingId) payload.id = editingId;

    fetch(url, { 
        method:'POST', 
        headers:{'Content-Type':'application/json'}, 
        body: JSON.stringify(payload) 
    }).then(r => r.json()).then(d => {
        if (d.success) {
            closeEditor();
            fetchProducts();
        } else {
            alert('Error saving: ' + d.error);
        }
    });
}

function deleteProduct(id){
    fetch('delete_product.php',{ 
        method:'POST', 
        headers:{'Content-Type':'application/json'}, 
        body: JSON.stringify({id:id}) 
    }).then(() => fetchProducts());
}

// Initial Setup
document.getElementById('newBtn').onclick = () => openEditor(null);
document.getElementById('cancelBtn').onclick = closeEditor;
document.getElementById('overlay').onclick = closeEditor;
document.getElementById('saveBtn').onclick = saveProduct;
document.getElementById('refreshBtn').onclick = fetchProducts;

// Start the page
fetchCategories().then(fetchProducts);
</script>
</body>
</html>