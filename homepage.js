document.addEventListener('DOMContentLoaded', () => {
    
    // --- PART A: INITIALIZATION ---
    const menuWrapper = document.querySelector('.menu-scroll-wrapper');
    const allCards = document.querySelectorAll('.menu-card-scroll');
    
    allCards.forEach((card, index) => {
        card.setAttribute('data-original-order', index);
    });

    // --- PART B: HAMBURGER & SIDE NAV LOGIC ---
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const closeBtn = document.getElementById('close-btn');
    const sideNav = document.getElementById('side-nav');
    const overlay = document.getElementById('overlay');

    const toggleMenu = (isOpen) => {
        sideNav?.classList.toggle('active', isOpen);
        overlay?.classList.toggle('active', isOpen);
        document.body.style.overflow = isOpen ? 'hidden' : 'auto';
    };

    hamburgerBtn?.addEventListener('click', () => toggleMenu(true));
    closeBtn?.addEventListener('click', () => toggleMenu(false));
    overlay?.addEventListener('click', () => toggleMenu(false));


    // --- PART C: REORDER FUNCTION (Sorting) ---
    const reorderCards = () => {
        const cardsArray = Array.from(allCards);
        cardsArray.sort((a, b) => {
            const isFavA = a.querySelector('.btn-heart').classList.contains('active');
            const isFavB = b.querySelector('.btn-heart').classList.contains('active');
            const orderA = parseInt(a.getAttribute('data-original-order'));
            const orderB = parseInt(b.getAttribute('data-original-order'));
            if (isFavA === isFavB) return orderA - orderB;
            return isFavA ? -1 : 1;
        });
        cardsArray.forEach(card => menuWrapper.appendChild(card));
    };


    // --- PART D: CLICK DELEGATION (Add, Minus, & Heart) ---
    document.addEventListener('click', (e) => {
        
        // 1. ADD TO CART LOGIC
        const addBtn = e.target.closest('.btn-add-cart');
        if (addBtn) {
            e.preventDefault();
            const card = addBtn.closest('.menu-card-scroll');
            const name = card.querySelector('h3').innerText;
            const priceText = card.querySelector('.price-badge').innerText;
            const price = parseFloat(priceText.replace('₱', ''));
            const img = card.querySelector('img').src;

            // --- SYNC TO LOCAL STORAGE (ALWAYS DO THIS FOR UI) ---
            const activeKey = (typeof cartKey !== 'undefined') ? cartKey : 'cart_guest';
            let cart = JSON.parse(localStorage.getItem(activeKey)) || [];
            const existingItem = cart.find(item => item.name === name);

            if (existingItem) {
                existingItem.qty += 1;
            } else {
                cart.push({ name, price, img, qty: 1 });
            }

            localStorage.setItem(activeKey, JSON.stringify(cart));
            localStorage.setItem('myCart', JSON.stringify(cart)); // Bridge key

            // --- DATABASE SYNC (Only if Logged In) ---
            if (typeof isLoggedIn !== 'undefined' && isLoggedIn) {
                const formData = new FormData();
                formData.append('name', name);
                formData.append('price', price);
                formData.append('img', img);

                fetch('add_to_cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        updateBadge();
                        showToast(`Saved ${name} to your account!`);
                    }
                })
                .catch(err => console.error("Error saving to DB:", err));
            } else {
                updateBadge();
                showToast(`Added ${name} to guest cart!`);
            }
        }

        // 3. FAVORITE BUTTON LOGIC
        const heartBtn = e.target.closest('.btn-heart');
        if (heartBtn) {
            e.preventDefault();
            heartBtn.classList.toggle('active');
            const icon = heartBtn.querySelector('i');
            if (heartBtn.classList.contains('active')) {
                icon.classList.replace('fa-regular', 'fa-solid');
            } else {
                icon.classList.replace('fa-solid', 'fa-regular');
            }
            reorderCards();
        }
    });

    // --- UTILITIES ---

    function updateBadge() {
        const badge = document.getElementById('cart-badge');
        if (!badge) return;

        const activeKey = (typeof cartKey !== 'undefined') ? cartKey : 'cart_guest';

        if (typeof isLoggedIn !== 'undefined' && isLoggedIn) {
            fetch('get_cart_count.php')
            .then(res => res.json())
            .then(data => {
                const total = data.total_qty || 0;
                badge.innerText = total;
                badge.style.display = total > 0 ? 'flex' : 'none';
            })
            .catch(() => {
                const cart = JSON.parse(localStorage.getItem(activeKey)) || [];
                const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
                badge.innerText = totalItems;
            });
        } else {
            const cart = JSON.parse(localStorage.getItem(activeKey)) || [];
            const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
            badge.innerText = totalItems;
            badge.style.display = totalItems > 0 ? 'flex' : 'none';
        }
    }

    function showToast(message) {
        const toast = document.getElementById('toast');
        if (toast) {
            toast.innerText = message;
            toast.style.visibility = 'visible';
            toast.style.opacity = '1';
            setTimeout(() => { 
                toast.style.visibility = 'hidden'; 
                toast.style.opacity = '0';
            }, 3000);
        }
    }

    window.addEventListener('storage', updateBadge);
    updateBadge(); 
});
