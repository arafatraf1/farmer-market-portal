/**
 * Farmer Market Portal - Cart System JS
 */

function updateCartCountBadge(count) {
    const badges = document.querySelectorAll('.cart-badge');
    badges.forEach(b => {
        b.innerText = count;
        b.style.display = count > 0 ? 'flex' : 'none';
    });
}

function addToCart(productId, quantity = 1) {
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('product_id', productId);
    formData.append('quantity', quantity);

    return fetch((window.APP_BASE_URL || '') + '/api/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateCartCountBadge(data.cart_count);
            showToast(data.message, 'success');
        } else {
            showToast(data.error || 'Could not add to cart', 'error');
        }
        return data;
    })
    .catch(err => {
        showToast('Error adding item to cart', 'error');
        console.error(err);
    });
}

function updateCartItem(productId, newQty) {
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('product_id', productId);
    formData.append('quantity', newQty);

    return fetch((window.APP_BASE_URL || '') + '/api/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateCartCountBadge(data.cart_count);
            // If on cart page, refresh or update row
            if (window.location.pathname.includes('cart.php')) {
                window.location.reload();
            }
        } else {
            showToast(data.error || 'Could not update quantity', 'error');
        }
        return data;
    });
}

function removeCartItem(productId) {
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('product_id', productId);

    return fetch((window.APP_BASE_URL || '') + '/api/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateCartCountBadge(data.cart_count);
            showToast('Item removed from cart', 'info');
            if (window.location.pathname.includes('cart.php')) {
                window.location.reload();
            }
        }
    });
}

window.attachCartListeners = function() {
    document.querySelectorAll('.btn-add-to-cart').forEach(btn => {
        btn.onclick = function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-product-id');
            const qtyInput = document.getElementById(`qty_${id}`);
            const qty = qtyInput ? parseInt(qtyInput.value) : 1;
            addToCart(id, qty);
        };
    });
};

document.addEventListener('DOMContentLoaded', () => {
    window.attachCartListeners();
});
