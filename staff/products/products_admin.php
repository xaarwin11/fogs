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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Manage Products</title>
<link rel="stylesheet" href="/fogs/assets/style.css">
<style>
.nice-table thead th{ text-align:left; padding:0.6rem 0.6rem; border-bottom:1px solid #eee; color:#333; }
.nice-table tbody td{ padding:0.5rem 0.6rem; border-bottom:1px solid #fafafa; }
.products-card button{ margin-left:0.4rem }
#editor input[type="checkbox"]{ transform:scale(1.1); margin-right:0.3rem }
#editor h3{ margin-top:0; }
</style>
</head>
<body>
<?php require_once __DIR__ . '/../navbar.php'; ?>
<div class="container">
    <h2>Products</h2>
    <div id="msg" style="margin-bottom:1rem;color:#d00;"></div>
    <div style="display:flex; gap:0.5rem; align-items:center; margin-bottom:1rem;">
        <button id="newBtn" class="btn">+ New Product</button>
        <button id="refreshBtn" class="btn secondary">↻ Refresh</button>
    </div>

    <div class="products-card" style="background:#fff; padding:1rem; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
        <table id="productsTable" class="nice-table" style="width:100%; border-collapse:collapse;">
            <thead><tr><th style="width:35%">Name</th><th style="width:18%">Category</th><th style="width:12%">Price</th><th style="width:10%">Kitchen</th><th style="width:10%">Available</th><th style="width:15%">Actions</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div id="editor" style="display:none; position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); background:#fff; padding:1rem; border:1px solid #ddd; border-radius:8px; width:420px; z-index:999;">
    <h3 id="editorTitle">Edit Product</h3>
    <div style="margin-bottom:0.6rem;"><label>Name</label><br><input id="p_name" style="width:100%; padding:0.4rem; box-sizing:border-box;"></div>
    <div style="margin-bottom:0.6rem;"><label>Category</label><br><input id="p_cat" style="width:100%; padding:0.4rem; box-sizing:border-box;"></div>
    <div style="margin-bottom:0.6rem;"><label>Price</label><br><input id="p_price" type="number" step="0.01" style="width:100%; padding:0.4rem; box-sizing:border-box;"></div>
    <div style="margin-bottom:0.6rem; display:flex; align-items:center; gap:0.6rem;"><label><input id="p_kds" type="checkbox"> Kitchen</label></div>
    <div style="margin-bottom:0.6rem; display:flex; align-items:center; gap:0.6rem;"><label><input id="p_avail" type="checkbox"> Available</label></div>
    <div style="text-align:right;"><button id="saveBtn" class="btn">Save</button> <button id="cancelBtn" class="btn secondary">Cancel</button></div>
</div>

<script>
let editingId = null;
function fetchProducts(){
    fetch('get_products_admin.php', { credentials: 'same-origin' }).then(async r=>{
        let data;
        try { data = await r.json(); } catch(e) { data = { success:false, error: 'Invalid JSON from server' }; }
        const tbody = document.querySelector('#productsTable tbody'); tbody.innerHTML='';
        (data.products||[]).forEach(p=>{
            const tr=document.createElement('tr');
            const kdsChecked = p.kds ? 'checked' : '';
            const availChecked = p.available ? 'checked' : '';
            tr.innerHTML = `<td style="padding:0.6rem 0">${p.name}</td><td>${p.category}</td><td>₱ ${parseFloat(p.price).toFixed(2)}</td><td style="text-align:center"><input type="checkbox" disabled ${kdsChecked}></td><td style="text-align:center"><input type="checkbox" disabled ${availChecked}></td><td><button class="btn small edit" data-id="${p.id}">Edit</button> <button class="btn small del" data-id="${p.id}">Delete</button></td>`;
            tbody.appendChild(tr);
        });
        document.querySelectorAll('.edit').forEach(b=>b.onclick = e=>{ const id=e.target.dataset.id; openEditor(id); });
        document.querySelectorAll('.del').forEach(b=>b.onclick = e=>{ if(confirm('Delete product?')) deleteProduct(e.target.dataset.id); });
    }).catch(err => { console.error('fetchProducts failed', err); document.getElementById('msg').textContent = 'Failed to load products'; });
}

function openEditor(id){
    editingId = id || null;
    document.getElementById('editorTitle').textContent = id ? 'Edit Product' : 'New Product';
    if(id){
        fetch('../pos/get_products.php?id='+id, { credentials: 'same-origin' }).then(async r=>{
            let d;
            try { d = await r.json(); } catch(e){ alert('Failed to load product (invalid response)'); return; }
            if (!d || !d.success) { alert('Failed to load product: ' + (d.error || d.detail || 'unknown')); return; }
            const p=d.product;
            document.getElementById('p_name').value=p.name;
            document.getElementById('p_cat').value=p.category;
            document.getElementById('p_price').value=p.price;
            document.getElementById('p_kds').checked = p.kds ? true : false;
            document.getElementById('p_avail').checked = p.available ? true : false;
            document.getElementById('editor').style.display='block';
        }).catch((err)=>{ console.error('get_product error', err); alert('Failed to load product'); });
    } else {
        document.getElementById('p_name').value=''; document.getElementById('p_cat').value=''; document.getElementById('p_price').value='0.00'; document.getElementById('p_kds').checked = true; document.getElementById('p_avail').checked = true;
        document.getElementById('editor').style.display='block';
    }
}
function closeEditor(){ document.getElementById('editor').style.display='none'; }

function saveProduct(){
    const payload = {
        name: document.getElementById('p_name').value,
        category: document.getElementById('p_cat').value,
        price: document.getElementById('p_price').value,
        kds: document.getElementById('p_kds').checked ? 1 : 0,
        available: document.getElementById('p_avail').checked ? 1 : 0
    };
    const url = editingId ? 'update_product.php' : 'create_product.php';
    if(editingId) payload.id = editingId;
    fetch(url, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
        .then(async r => {
            let d;
            try { d = await r.json(); } catch(e) { d = { success:false, error:'Invalid server response' }; }
            if (!r.ok || !d || !d.success) {
                const msg = (d && (d.error || d.detail)) || ('HTTP ' + r.status + ' ' + r.statusText);
                alert('Save failed: ' + msg);
                console.error('saveProduct failed', d, r);
                return;
            }
            closeEditor(); fetchProducts();
        }).catch(err => { console.error('saveProduct error', err); alert('Save failed: network error'); });
}
function deleteProduct(id){
    fetch('delete_product.php',{ method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id:id}) })
        .then(async r => {
            let d;
            try { d = await r.json(); } catch(e){ d = { success:false, error:'Invalid server response' }; }
            if (!r.ok || !d || !d.success) {
                alert('Delete failed: ' + (d && (d.error || d.detail) ? (d.error || d.detail) : ('HTTP ' + r.status)) );
                console.error('deleteProduct failed', d, r);
                return;
            }
            fetchProducts();
        }).catch(err => { console.error('deleteProduct error', err); alert('Delete failed: network error'); });
}

document.getElementById('newBtn').addEventListener('click', ()=>openEditor(null));
document.getElementById('cancelBtn').addEventListener('click', closeEditor);
document.getElementById('saveBtn').addEventListener('click', saveProduct);
document.getElementById('refreshBtn').addEventListener('click', fetchProducts);
fetchProducts();
</script>
</body>
</html>