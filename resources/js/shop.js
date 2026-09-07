/**
 * Micro Webshop Cart - client side state, localStorage persisted
 * Cart = [{ serviceId, priceId, name, price, priceLabel, quantity }]
 */
const CART_KEY = 'marocloi_shop_cart';
const MAX_QTY = 99;
const MIN_QTY = 1;

function getCart() {
    try {
        const raw = localStorage.getItem(CART_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch { return []; }
}

function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCartBadge();
    renderMiniCartIfPresent();
}

function updateCartBadge() {
    const badge = document.getElementById('shop-cart-badge');
    if (!badge) return;
    const cart = getCart();
    const count = cart.reduce((s, i) => s + i.quantity, 0);
    if (count === 0) {
        badge.classList.add('hidden');
    } else {
        badge.textContent = String(count);
        badge.classList.remove('hidden');
        badge.classList.add('bump');
        setTimeout(() => badge.classList.remove('bump'), 200);
    }
}

function cartTotal(cart) {
    return cart.reduce((s, i) => s + i.price * i.quantity, 0);
}

function cartSubtotal(cart) {
    return cartTotal(cart);
}

function addToCart({ serviceId, priceId, name, price, priceLabel }) {
    if (!priceId || !/^price_[A-Za-z0-9]+$/.test(priceId)) {
        alert('Invalid product. Please refresh and try again.');
        return;
    }
    let cart = getCart();
    const existing = cart.find(c => c.priceId === priceId);
    if (existing) {
        if (existing.quantity >= MAX_QTY) {
            alert('Maximum quantity reached (99).');
            return;
        }
        existing.quantity += 1;
    } else {
        cart.push({ serviceId, priceId, name, price: parseFloat(price), priceLabel, quantity: 1 });
    }
    saveCart(cart);
    // Show mini cart
    const mini = document.getElementById('shop-mini-cart');
    if (mini) mini.classList.remove('hidden');
}

function updateQuantity(priceId, quantity) {
    let qty = parseInt(quantity, 10);
    if (isNaN(qty) || qty < MIN_QTY) qty = MIN_QTY;
    if (qty > MAX_QTY) qty = MAX_QTY;
    let cart = getCart();
    const item = cart.find(c => c.priceId === priceId);
    if (!item) return;
    item.quantity = qty;
    saveCart(cart);
    renderCartPageIfPresent();
    renderCheckoutIfPresent();
}

function removeFromCart(priceId) {
    let cart = getCart();
    cart = cart.filter(c => c.priceId !== priceId);
    saveCart(cart);
    renderCartPageIfPresent();
    renderCheckoutIfPresent();
    renderMiniCartIfPresent();
}

function clearCart() {
    localStorage.removeItem(CART_KEY);
    updateCartBadge();
}

function renderMiniCartIfPresent() {
    const container = document.getElementById('shop-mini-cart');
    const itemsEl = document.getElementById('shop-mini-cart-items');
    const totalEl = document.getElementById('shop-mini-cart-total-value');
    if (!container || !itemsEl || !totalEl) return;
    const cart = getCart();
    if (cart.length === 0) {
        container.classList.add('hidden');
        return;
    }
    container.classList.remove('hidden');
    itemsEl.innerHTML = cart.map(item => `
        <div class="flex justify-between text-sm">
            <span class="text-gray-700">${escapeHtml(item.name)} <span class="text-gray-400">× ${item.quantity}</span></span>
            <span class="font-semibold text-gray-900">${(item.price * item.quantity).toFixed(0)} MAD</span>
        </div>
    `).join('');
    totalEl.textContent = cartTotal(cart).toFixed(0) + ' MAD';
}

function renderCartPageIfPresent() {
    const emptyEl = document.getElementById('shop-cart-empty');
    const fullEl = document.getElementById('shop-cart-full');
    const itemsEl = document.getElementById('shop-cart-items');
    const subtotalEl = document.getElementById('shop-cart-subtotal');
    const totalEl = document.getElementById('shop-cart-total');
    if (!emptyEl || !fullEl || !itemsEl) return;

    const cart = getCart();
    if (cart.length === 0) {
        emptyEl.classList.remove('hidden');
        fullEl.classList.add('hidden');
        return;
    }
    emptyEl.classList.add('hidden');
    fullEl.classList.remove('hidden');

    itemsEl.innerHTML = cart.map(item => `
        <div class="flex items-center gap-4 p-4">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 truncate">${escapeHtml(item.name)}</p>
                <!-- <p class="text-xs text-gray-500">${escapeHtml(item.priceLabel || (item.price + ' MAD'))} · ${escapeHtml(item.priceId)}</p> -->
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="shop-qty-minus rounded-lg border border-gray-200 bg-white px-2 py-1 text-sm font-semibold text-gray-700 hover:bg-gray-50" data-price-id="${escapeHtml(item.priceId)}">−</button>
                <input type="number" min="1" max="99" value="${item.quantity}" data-price-id="${escapeHtml(item.priceId)}" class="shop-qty-input w-14 rounded-lg border border-gray-200 bg-gray-50 px-2 py-1 text-center text-sm font-semibold text-gray-900 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                <button type="button" class="shop-qty-plus rounded-lg border border-gray-200 bg-white px-2 py-1 text-sm font-semibold text-gray-700 hover:bg-gray-50" data-price-id="${escapeHtml(item.priceId)}">+</button>
            </div>
            <span class="w-20 text-right text-sm font-bold text-gray-900">${(item.price * item.quantity).toFixed(0)} MAD</span>
            <button type="button" class="shop-remove text-xs font-semibold text-red-600 hover:text-red-700" data-price-id="${escapeHtml(item.priceId)}">✕</button>
        </div>
    `).join('');

    const total = cartTotal(cart);
    if (subtotalEl) subtotalEl.textContent = total.toFixed(0) + ' MAD';
    if (totalEl) totalEl.textContent = total.toFixed(0) + ' MAD';

    // attach listeners
    itemsEl.querySelectorAll('.shop-qty-minus').forEach(btn => {
        btn.addEventListener('click', () => {
            const pid = btn.dataset.priceId;
            const c = getCart().find(x => x.priceId === pid);
            if (c) updateQuantity(pid, c.quantity - 1);
        });
    });
    itemsEl.querySelectorAll('.shop-qty-plus').forEach(btn => {
        btn.addEventListener('click', () => {
            const pid = btn.dataset.priceId;
            const c = getCart().find(x => x.priceId === pid);
            if (c) updateQuantity(pid, c.quantity + 1);
        });
    });
    itemsEl.querySelectorAll('.shop-qty-input').forEach(input => {
        input.addEventListener('change', () => updateQuantity(input.dataset.priceId, input.value));
        input.addEventListener('input', () => {
            let v = parseInt(input.value, 10);
            if (isNaN(v) || v < 1) v = 1;
            if (v > 99) v = 99;
            // don't save until change, just clamp UI
            input.value = v;
        });
    });
    itemsEl.querySelectorAll('.shop-remove').forEach(btn => {
        btn.addEventListener('click', () => removeFromCart(btn.dataset.priceId));
    });
}

function renderCheckoutIfPresent() {
    const emptyEl = document.getElementById('shop-checkout-empty');
    const contentEl = document.getElementById('shop-checkout-content');
    const itemsEl = document.getElementById('shop-checkout-items');
    const subtotalEl = document.getElementById('shop-checkout-subtotal');
    const totalEl = document.getElementById('shop-checkout-total');
    if (!emptyEl || !contentEl || !itemsEl) return;
    const cart = getCart();
    if (cart.length === 0) {
        emptyEl.classList.remove('hidden');
        contentEl.classList.add('hidden');
        return;
    }
    emptyEl.classList.add('hidden');
    contentEl.classList.remove('hidden');
    itemsEl.innerHTML = cart.map(item => `
        <div class="flex justify-between text-sm">
            <span class="text-gray-700">${escapeHtml(item.name)} <span class="text-gray-400">× ${item.quantity}</span></span>
            <span class="font-semibold text-gray-900">${(item.price * item.quantity).toFixed(0)} MAD</span>
        </div>
    `).join('');
    const total = cartTotal(cart);
    if (subtotalEl) subtotalEl.textContent = total.toFixed(0) + ' MAD';
    if (totalEl) totalEl.textContent = total.toFixed(0) + ' MAD';

    // Update pay button label with total
    const label = document.getElementById('shop-checkout-label');
    if (label) {
        // Keep original but append total if not already
        const base = label.dataset.base || label.textContent;
        label.dataset.base = base;
        label.textContent = base + ' — ' + total.toFixed(0) + ' MAD';
    }
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    updateCartBadge();
    renderMiniCartIfPresent();
    renderCartPageIfPresent();
    renderCheckoutIfPresent();

    // Add to cart buttons
    document.querySelectorAll('.shop-add-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            addToCart({
                serviceId: btn.dataset.serviceId,
                priceId: btn.dataset.priceId,
                name: btn.dataset.name,
                price: btn.dataset.price,
                priceLabel: btn.dataset.priceLabel,
            });
            // brief feedback
            const original = btn.textContent;
            btn.textContent = '✓ Added';
            btn.disabled = true;
            setTimeout(() => {
                btn.textContent = original;
                btn.disabled = false;
            }, 900);
        });
    });

    // Cart form helpers (phone/whatsapp/call_time) – copied from old legal-aid
    const phoneInput = document.getElementById('shop-phone');
    const whatsappInput = document.getElementById('shop-whatsapp');
    const copyWhatsappBtn = document.getElementById('copy-phone-to-whatsapp');
    const callTimeBtn = document.getElementById('call-time-btn');
    const callTimeOptions = document.getElementById('call-time-options');
    const callTimeValue = document.getElementById('shop-call-time');
    const callTimeLabel = document.getElementById('call-time-label');
    const callTimeChevron = document.getElementById('call-time-chevron');

    function stripNonNumeric(el) {
        const raw = el.value;
        const cleaned = raw.replace(/[^0-9+]/g, '').replace(/(?!^)\+/g, '');
        if (cleaned !== raw) el.value = cleaned;
    }
    if (phoneInput) phoneInput.addEventListener('input', function() { stripNonNumeric(this); });
    if (whatsappInput) whatsappInput.addEventListener('input', function() { stripNonNumeric(this); });
    if (phoneInput && copyWhatsappBtn && whatsappInput) {
        const syncCopy = () => { copyWhatsappBtn.disabled = !phoneInput.value; };
        phoneInput.addEventListener('input', syncCopy);
        copyWhatsappBtn.addEventListener('click', () => { whatsappInput.value = phoneInput.value; syncCopy(); });
        syncCopy();
    }
    if (callTimeBtn && callTimeOptions && callTimeValue && callTimeLabel) {
        function setCallTimeOpen(open) {
            callTimeOptions.classList.toggle('hidden', !open);
            callTimeBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (callTimeChevron) callTimeChevron.style.transform = open ? 'rotate(180deg)' : '';
            callTimeBtn.classList.toggle('border-blue-500', open);
            callTimeBtn.classList.toggle('bg-white', open);
            callTimeBtn.classList.toggle('ring-1', open);
            callTimeBtn.classList.toggle('ring-blue-500', open);
        }
        callTimeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            setCallTimeOpen(callTimeOptions.classList.contains('hidden'));
        });
        callTimeOptions.querySelectorAll('li').forEach(li => {
            li.addEventListener('click', function() {
                callTimeValue.value = this.dataset.value;
                callTimeLabel.textContent = this.textContent;
                callTimeLabel.classList.toggle('text-gray-400', !this.dataset.value);
                callTimeLabel.classList.toggle('text-gray-900', !!this.dataset.value);
                callTimeOptions.querySelectorAll('li').forEach(el => el.classList.remove('bg-blue-50','text-blue-600','font-semibold'));
                if (this.dataset.value) this.classList.add('bg-blue-50','text-blue-600','font-semibold');
                setCallTimeOpen(false);
                callTimeBtn.focus();
                const err = document.getElementById('shop-call-time-error');
                if (err) { err.textContent=''; err.classList.add('hidden'); }
            });
        });
        document.addEventListener('click', () => setCallTimeOpen(false));
        document.addEventListener('keydown', (e) => { if (e.key==='Escape') setCallTimeOpen(false); });
    }

    // Cart -> Stripe Checkout (CIN collected inside Stripe via custom_fields)
    const cartCheckoutBtn = document.getElementById('shop-cart-checkout-btn');
    const cartCheckoutSpinner = document.getElementById('shop-cart-checkout-spinner');
    const cartCheckoutMessage = document.getElementById('shop-cart-checkout-message');
    if (cartCheckoutBtn) {
        cartCheckoutBtn.addEventListener('click', async () => {
            const cfg = window.ShopCartConfig;
            if (!cfg || !cfg.checkoutUrl) {
                alert('Checkout not configured');
                return;
            }
            const cart = getCart();
            if (!cart || cart.length === 0) {
                if (cartCheckoutMessage) {
                    cartCheckoutMessage.textContent = cfg.messages.cartEmpty || 'Your cart is empty.';
                    cartCheckoutMessage.className = 'mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800';
                    cartCheckoutMessage.classList.remove('hidden');
                } else {
                    alert(cfg.messages.cartEmpty || 'Your cart is empty.');
                }
                return;
            }
            // Collect customer form
            const fullNameEl = document.getElementById('shop-full-name');
            const emailEl = document.getElementById('shop-email');
            const phoneEl = document.getElementById('shop-phone');
            const whatsappEl = document.getElementById('shop-whatsapp');
            const caseDescEl = document.getElementById('shop-case-description');
            const callTimeEl = document.getElementById('shop-call-time');
            const fields = [
                {el: fullNameEl, errId: 'shop-full-name-error'},
                {el: emailEl, errId: 'shop-email-error'},
                {el: phoneEl, errId: 'shop-phone-error'},
                {el: whatsappEl, errId: 'shop-whatsapp-error'},
                {el: caseDescEl, errId: 'shop-case-description-error'},
                {el: callTimeEl, errId: 'shop-call-time-error'},
            ];
            // Clear previous errors
            fields.forEach(f => { const e=document.getElementById(f.errId); if(e){ e.textContent=''; e.classList.add('hidden'); }});
            if (cartCheckoutMessage) { cartCheckoutMessage.textContent=''; cartCheckoutMessage.className='hidden'; }

            let hasError = false;
            const full_name = fullNameEl ? fullNameEl.value.trim() : '';
            const email = emailEl ? emailEl.value.trim() : '';
            const phone = phoneEl ? phoneEl.value.trim() : '';
            const whatsapp = whatsappEl ? whatsappEl.value.trim() : '';
            const case_description = caseDescEl ? caseDescEl.value.trim() : '';
            const call_time = callTimeEl ? callTimeEl.value.trim() : '';

            if (!full_name) { const e=document.getElementById('shop-full-name-error'); if(e){ e.textContent='Full name is required.'; e.classList.remove('hidden'); } hasError=true; }
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { const e=document.getElementById('shop-email-error'); if(e){ e.textContent='Valid email is required.'; e.classList.remove('hidden'); } hasError=true; }
            if (!phone || !/^\+?0?[1-9][0-9]{7,14}$/.test(phone.replace(/[^0-9+]/g,''))) { const e=document.getElementById('shop-phone-error'); if(e){ e.textContent=cfg.messages.phoneInvalid || 'Invalid phone.'; e.classList.remove('hidden'); } hasError=true; }
            if (whatsapp && !/^\+?0?[1-9][0-9]{7,14}$/.test(whatsapp.replace(/[^0-9+]/g,''))) { const e=document.getElementById('shop-whatsapp-error'); if(e){ e.textContent=cfg.messages.whatsappInvalid || 'Invalid WhatsApp.'; e.classList.remove('hidden'); } hasError=true; }
            if (!case_description || case_description.length < 100) { const e=document.getElementById('shop-case-description-error'); if(e){ e.textContent='Case description is required (min 100 characters).'; e.classList.remove('hidden'); } hasError=true; }
            if (!call_time) { const e=document.getElementById('shop-call-time-error'); if(e){ e.textContent='Please select a call time.'; e.classList.remove('hidden'); } hasError=true; }
            if (hasError) return;

            cartCheckoutBtn.disabled = true;
            cartCheckoutBtn.classList.add('opacity-60', 'cursor-not-allowed');
            if (cartCheckoutSpinner) cartCheckoutSpinner.classList.remove('hidden');

            const items = cart.map(i => ({ price_id: i.priceId, quantity: i.quantity }));
            try {
                const res = await fetch(cfg.checkoutUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': cfg.csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ items, full_name, email, phone, whatsapp: whatsapp || null, case_description, call_time }),
                });
                let data = {};
                try { data = await res.json(); } catch {}
                if (!res.ok) {
                    const msg = data.message || cfg.messages.genericError || 'Could not start payment.';
                    if (data.errors) {
                        Object.entries(data.errors).forEach(([k, msgs]) => {
                            const map = {full_name:'shop-full-name-error', email:'shop-email-error', phone:'shop-phone-error', whatsapp:'shop-whatsapp-error', case_description:'shop-case-description-error', call_time:'shop-call-time-error'};
                            const errId = map[k];
                            if (errId) { const e=document.getElementById(errId); if(e){ e.textContent=msgs[0]; e.classList.remove('hidden'); } }
                            if (!map[k] && cartCheckoutMessage) { cartCheckoutMessage.textContent = msgs[0]; cartCheckoutMessage.className='mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800'; cartCheckoutMessage.classList.remove('hidden'); }
                        });
                        if (!data.errors.phone && !data.errors.whatsapp && !data.errors.email && !data.errors.full_name && !data.errors.case_description && !data.errors.call_time && cartCheckoutMessage) {
                            cartCheckoutMessage.textContent = msg;
                            cartCheckoutMessage.className = 'mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800';
                            cartCheckoutMessage.classList.remove('hidden');
                        }
                    } else if (cartCheckoutMessage) {
                        cartCheckoutMessage.textContent = msg;
                        cartCheckoutMessage.className = 'mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800';
                        cartCheckoutMessage.classList.remove('hidden');
                    } else {
                        alert(msg);
                    }
                    cartCheckoutBtn.disabled = false;
                    cartCheckoutBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                    if (cartCheckoutSpinner) cartCheckoutSpinner.classList.add('hidden');
                    return;
                }
                const url = data.url;
                if (!url) throw new Error(cfg.messages.genericError);
                // Persist customer info for success page / retry
                try { localStorage.setItem('marocloi_shop_customer', JSON.stringify({full_name, email, phone, whatsapp, case_description, call_time})); } catch {}
                window.location.href = url;
            } catch (err) {
                const msg = (err && err.message) || cfg.messages.networkError || 'Network error';
                if (cartCheckoutMessage) {
                    cartCheckoutMessage.textContent = msg;
                    cartCheckoutMessage.className = 'mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800';
                    cartCheckoutMessage.classList.remove('hidden');
                } else {
                    alert(msg);
                }
                cartCheckoutBtn.disabled = false;
                cartCheckoutBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                if (cartCheckoutSpinner) cartCheckoutSpinner.classList.add('hidden');
            }
        });
    }
});

// Expose for checkout.js
window.ShopCart = { getCart, saveCart, clearCart, cartTotal, updateCartBadge };
