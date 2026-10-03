/**
 * Farmer Market Portal - Live Browse & Filtering Script
 */

let searchTimeout = null;

function applyFilters() {
    const grid = document.getElementById('browseProductGrid');
    const countEl = document.getElementById('productResultsCount');
    if (!grid) return;

    // Show loading state
    grid.style.opacity = '0.5';

    const params = new URLSearchParams();
    
    // Search input
    const qInput = document.getElementById('searchInput');
    if (qInput && qInput.value.trim()) params.append('q', qInput.value.trim());

    // Category
    const activeCat = document.querySelector('input[name="filter_category"]:checked');
    if (activeCat && activeCat.value) params.append('category', activeCat.value);

    // Price Max
    const priceMax = document.getElementById('priceMax');
    if (priceMax && priceMax.value) params.append('max_price', priceMax.value);

    // Rating
    const minRating = document.querySelector('input[name="filter_rating"]:checked');
    if (minRating && minRating.value) params.append('rating', minRating.value);

    // Verified Only
    const verifiedOnly = document.getElementById('filterVerifiedOnly');
    if (verifiedOnly && verifiedOnly.checked) params.append('verified_only', '1');

    // Freshness / Expiry
    const freshness = document.querySelector('input[name="filter_freshness"]:checked');
    if (freshness && freshness.value) params.append('freshness', freshness.value);

    // Location
    const locationInput = document.getElementById('locationFilter');
    if (locationInput && locationInput.value.trim()) params.append('location', locationInput.value.trim());

    // Sorting
    const sortSelect = document.getElementById('sortSelect');
    if (sortSelect && sortSelect.value) params.append('sort', sortSelect.value);

    fetch((window.APP_BASE_URL || '') + '/api/search.php?' + params.toString())
        .then(res => res.json())
        .then(data => {
            grid.style.opacity = '1';
            if (data.success) {
                grid.innerHTML = data.html;
                if (countEl) countEl.innerText = `${data.count} Products Found`;
                // Re-attach add to cart listeners
                if (window.attachCartListeners) {
                    window.attachCartListeners();
                }
            } else {
                grid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding: 40px; color:#64748b;">Failed to load products.</div>`;
            }
        })
        .catch(err => {
            grid.style.opacity = '1';
            console.error('Search error:', err);
        });
}

document.addEventListener('DOMContentLoaded', () => {
    // Live search input with debounce
    const qInput = document.getElementById('searchInput');
    if (qInput) {
        qInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(applyFilters, 300);
        });
    }

    // Price range slider
    const priceSlider = document.getElementById('priceMax');
    const priceDisplay = document.getElementById('priceMaxDisplay');
    if (priceSlider && priceDisplay) {
        priceSlider.addEventListener('input', () => {
            priceDisplay.innerText = '৳' + priceSlider.value;
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(applyFilters, 250);
        });
    }

    // Radio & Checkbox filters
    document.querySelectorAll('.filter-trigger').forEach(el => {
        el.addEventListener('change', applyFilters);
    });

    // Location input
    const locationInput = document.getElementById('locationFilter');
    if (locationInput) {
        locationInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(applyFilters, 400);
        });
    }

    // Sort selector
    const sortSelect = document.getElementById('sortSelect');
    if (sortSelect) {
        sortSelect.addEventListener('change', applyFilters);
    }

    // Reset filters button
    const resetBtn = document.getElementById('resetFiltersBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            if (qInput) qInput.value = '';
            if (priceSlider) {
                priceSlider.value = priceSlider.max;
                if (priceDisplay) priceDisplay.innerText = '$' + priceSlider.max;
            }
            if (locationInput) locationInput.value = '';
            const allCat = document.querySelector('input[name="filter_category"][value=""]');
            if (allCat) allCat.checked = true;
            const allRating = document.querySelector('input[name="filter_rating"][value=""]');
            if (allRating) allRating.checked = true;
            const allFresh = document.querySelector('input[name="filter_freshness"][value=""]');
            if (allFresh) allFresh.checked = true;
            const verifiedOnly = document.getElementById('filterVerifiedOnly');
            if (verifiedOnly) verifiedOnly.checked = false;
            applyFilters();
        });
    }
});
