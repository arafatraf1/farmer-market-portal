/**
 * Farmer Market Portal - Order Delivery Tracking Script
 */

function updateOrderStatus(orderId, newStatus, reason = '') {
    const formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('status', newStatus);
    if (reason) formData.append('cancellation_reason', reason);

    return fetch((window.APP_BASE_URL || '') + '/api/orders.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Order status updated', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.error || 'Failed to update order status', 'error');
        }
    })
    .catch(err => {
        showToast('Network error updating order', 'error');
        console.error(err);
    });
}

function confirmOrderReceived(orderId) {
    if (!confirm('Are you sure you have received this delivery in good condition?')) {
        return;
    }

    const formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('status', 'completed');

    fetch((window.APP_BASE_URL || '') + '/api/orders.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Order marked as Completed! Please rate the farmer.', 'success');
            // If review modal is present on the page, trigger it
            if (document.getElementById('reviewModal')) {
                openModal('reviewModal');
            } else {
                setTimeout(() => window.location.reload(), 1000);
            }
        } else {
            showToast(data.error || 'Could not complete order', 'error');
        }
    });
}
