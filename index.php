<?php
session_start();
include 'includes/db.php';

try {
    // Fetch menu items from the database
    $stmt = $pdo->query("SELECT mi.*, u.name as restaurant_name 
                       FROM menu_items mi 
                       JOIN users u ON mi.restaurant_id = u.id 
                       WHERE mi.availability = 1");
    $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($menuItems)) {
        error_log("No menu items found in database.");
    } else {
        foreach ($menuItems as &$item) {
            $item['id'] = (int)$item['id'];
            $item['price'] = (float)$item['price'];
            $item['prep_time'] = (int)$item['prep_time'];
            $item['restaurant_id'] = (int)$item['restaurant_id'];
        }
    }

    // Fetch restaurants for the "Restaurants" link
    $stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'restaurant'");
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recent orders for logged-in users
    $orders = [];
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
        try {
            $stmt = $pdo->prepare("
                SELECT o.*, u.name as restaurant_name 
                FROM orders o 
                LEFT JOIN users u ON o.restaurant_id = u.id 
                WHERE o.user_id = ? 
                ORDER BY o.created_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched " . count($orders) . " recent orders for user ID $user_id on index.php");
        } catch (PDOException $e) {
            error_log("Error fetching orders for user ID $user_id on index.php: " . $e->getMessage());
            $orders = [];
        }
    }

    // Sync cart from POST if present
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_items'])) {
        $cart = json_decode($_POST['cart_items'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $_SESSION['cart'] = $cart;
            error_log("Cart synced to session from POST: " . json_encode($_SESSION['cart']));
            header("Location: checkout.php");
            exit;
        } else {
            error_log("Failed to decode cart items: " . json_last_error_msg());
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $menuItems = [];
    $restaurants = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>TasteKart - Online Food Ordering</title>
    <style>
        <?php echo file_get_contents('style.css'); ?>
        /* Override menu-grid to display items in rows */
        .menu-grid {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1rem;
            justify-content: center;
        }
        .menu-card {
            display: flex;
            flex-direction: row;
            align-items: center;
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 5px 15px var(--shadow);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            max-width: 100%;
            width: calc(50% - 0.5rem); /* Two items per row with gap adjustment */
        }
        .menu-card img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 15px 0 0 15px;
        }
        .menu-card-content {
            flex: 1;
            padding: 1rem;
        }
        .menu-price-preptime {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
        }
        .add-to-cart-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        .add-to-cart-btn:hover {
            background: var(--primary-dark);
        }
        .order-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; }
        .order-history { display: none; }
        .order-history.active { display: block; }
        .toggle-button { background: var(--primary-color, #d32f2f); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 25px; font-weight: 700; cursor: pointer; margin-bottom: 1rem; }
        .toggle-button:hover { background: var(--primary-dark, #b71c1c); }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo">TasteKart</a>
        <nav>
            <a href="#restaurants">Restaurants</a>
            <a href="#menu">Menu</a>
            <a href="offers.php">Offers</a>
            <a href="order_history.php">Order History</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'restaurant'): ?>
                    <a href="restaurant_admin.php">Dashboard</a>
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Dashboard</a>
                <?php endif; ?>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <button class="btn-login" onclick="window.location.href='login.php'">Login</button>
                <button class="btn-register" onclick="window.location.href='register.php'">Register</button>
            <?php endif; ?>
            <button id="cart-toggle" aria-label="View Cart" title="Cart">
                🛒 <span id="cart-count">0</span>
            </button>
        </nav>
    </header>

    <section class="hero" aria-label="Hero section with promotional content">
        <div class="hero-text">
            <h1>Delicious meals delivered to your doorstep</h1>
            <p>Discover your favorite restaurants, order food online, and get delivery fast and fresh.</p>
            <button class="btn-order" id="explore-menu-btn">Order Now</button>
        </div>
        <div class="hero-image">
            <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Delicious meal with food and drinks" />
        </div>
    </section>

    <section class="search-section" aria-label="Food search and categories">
        <form class="search-bar" id="search-form" role="search" aria-label="Search for meals or restaurants">
            <input type="search" id="search-input" placeholder="রেস্টুরেন্ট বা খাবার খুঁজুন..." aria-label="Search input" />
            <button type="submit" aria-label="Search button">Search</button>
        </form>
        <select id="filter-restaurant" class="category" style="padding: 10px 26px; margin-bottom: 1rem;">
            <option value="">সব রেস্টুরেন্ট</option>
            <?php foreach ($restaurants as $restaurant): ?>
                <option value="<?php echo htmlspecialchars($restaurant['name']); ?>"><?php echo htmlspecialchars($restaurant['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="categories" role="list" aria-label="Food categories filter">
            <div class="category active" data-category="all" role="listitem" tabindex="0">All</div>
            <div class="category" data-category="pizza" role="listitem" tabindex="0">Pizza</div>
            <div class="category" data-category="burgers" role="listitem" tabindex="0">Burgers</div>
            <div class="category" data-category="asian" role="listitem" tabindex="0">Asian</div>
            <div class="category" data-category="desserts" role="listitem" tabindex="0">Desserts</div>
            <div class="category" data-category="beverages" role="listitem" tabindex="0">Beverages</div>
        </div>
    </section>

    <section class="menu-section" id="menu" aria-label="Menu items">
        <div class="menu-header">
            <h2>Popular Dishes</h2>
        </div>
        <div class="menu-grid" id="menu-grid">
            <!-- Menu cards will be populated by JS -->
        </div>
    </section>

    <?php if (isset($_SESSION['user_id'])): ?>
        <section class="dashboard-section" style="max-width: 1200px; margin: 3rem auto; padding: 1rem;">
            <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary-color, #d32f2f);">Recent Orders</h2>
            <button class="toggle-button" onclick="toggleOrderHistory()">View Orders</button>
            <div class="order-history" id="order-history">
                <div class="order-grid">
                    <?php if (empty($orders)): ?>
                        <p style="text-align: center; color: #999;">You have no recent orders.</p>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="menu-card">
                                <h3>Order #<?php echo htmlspecialchars($order['id']); ?></h3>
                                <p>Restaurant: <?php echo htmlspecialchars($order['restaurant_name'] ?? 'Unknown Restaurant'); ?></p>
                                <p>Total: ৳<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                                <p>Status: <?php echo htmlspecialchars($order['status'] ?? 'Unknown'); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php else: ?>
        <section class="dashboard-section" style="max-width: 1200px; margin: 3rem auto; padding: 1rem; text-align: center;">
            <p style="color: #666;">Please <a href="login.php">log in</a> to view your orders.</p>
        </section>
    <?php endif; ?>

    <aside id="cart-sidebar" aria-label="Shopping Cart" aria-hidden="true">
        <header>
            <span>Cart</span>
            <button id="close-cart" aria-label="Close cart sidebar">×</button>
        </header>
        <div id="cart-items" tabindex="0">
            <p style="padding: 20px; text-align: center; color: #999;">Your cart is empty</p>
        </div>
        <div id="cart-total" aria-live="polite" aria-atomic="true">
            <span>Total:</span>
            <span id="cart-total-price">৳0.00</span>
        </div>
        <button id="checkout-btn" aria-label="Proceed to checkout">Checkout</button>
    </aside>

    <footer>
        © 2025 TasteKart. All rights reserved. Designed inspired by Tuhin.
    </footer>

    <script>
        const menuItems = <?php echo json_encode($menuItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; ?>;
        let cart = [];

        try {
            const storedCart = localStorage.getItem('cart');
            cart = storedCart ? JSON.parse(storedCart) : [];
        } catch (error) {
            console.error('Error parsing cart from localStorage:', error);
            cart = [];
        }

        const menuGrid = document.getElementById('menu-grid');
        const categories = document.querySelectorAll('.category');
        const filterRestaurant = document.getElementById('filter-restaurant');
        const cartCount = document.getElementById('cart-count');
        const cartSidebar = document.getElementById('cart-sidebar');
        const cartItemsContainer = document.getElementById('cart-items');
        const cartTotalPrice = document.getElementById('cart-total-price');
        const cartToggleBtn = document.getElementById('cart-toggle');
        const closeCartBtn = document.getElementById('close-cart');
        const checkoutBtn = document.getElementById('checkout-btn');
        const searchForm = document.getElementById('search-form');
        const searchInput = document.getElementById('search-input');
        const exploreMenuBtn = document.getElementById('explore-menu-btn');

        if (!menuGrid || !cartCount || !cartItemsContainer || !cartTotalPrice || !cartSidebar || !cartToggleBtn || !closeCartBtn || !checkoutBtn || !searchForm || !searchInput || !exploreMenuBtn) {
            console.error('Critical DOM elements missing. Cannot proceed.');
            alert('একটি ত্রুটি ঘটেছে। পৃষ্ঠাটি সঠিকভাবে লোড হয়নি। দয়া করে আবার চেষ্টা করুন।');
            throw new Error('Missing critical DOM elements');
        }

        let activeCategory = 'all';
        let activeRestaurant = '';

        function renderMenuItems() {
            try {
                if (!Array.isArray(menuItems)) {
                    console.error('menuItems is not an array:', menuItems);
                    menuGrid.innerHTML = '<p style="text-align:center; color:#999;">একটি ত্রুটি ঘটেছে। মেনু আইটেম লোড করা যায়নি।</p>';
                    return;
                }
                if (menuItems.length === 0) {
                    console.warn('menuItems is empty');
                    menuGrid.innerHTML = '<p style="text-align:center; color:#999;">কোন আইটেম পাওয়া যায়নি। দয়া করে রেস্টুরেন্টে আইটেম যোগ করুন।</p>';
                    return;
                }

                let filteredItems = menuItems;
                if (activeCategory !== 'all') {
                    filteredItems = filteredItems.filter(item => item.category.toLowerCase() === activeCategory);
                }
                if (activeRestaurant) {
                    filteredItems = filteredItems.filter(item => item.restaurant_name.toLowerCase() === activeRestaurant.toLowerCase());
                }
                const searchTerm = searchInput.value.trim().toLowerCase();
                if (searchTerm) {
                    filteredItems = filteredItems.filter(item =>
                        item.name.toLowerCase().includes(searchTerm) ||
                        item.restaurant_name.toLowerCase().includes(searchTerm) ||
                        item.category.toLowerCase().includes(searchTerm)
                    );
                }

                if (filteredItems.length === 0) {
                    menuGrid.innerHTML = '<p style="text-align:center; color:#999;">কোন আইটেম পাওয়া যায়নি।</p>';
                    return;
                }

                const cardsHtml = filteredItems.map(item => {
                    if (!item.id) {
                        console.warn('Item missing id:', item);
                        return '';
                    }
                    return `
                        <article class="menu-card" tabindex="0" aria-label="${item.name}. মূল্য ৳${item.price}">
                            <img src="${item.image || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'}" alt="${item.name}" />
                            <div class="menu-card-content">
                                <h3 class="menu-title">${item.name}</h3>
                                <p class="menu-desc">From: ${item.restaurant_name}</p>
                                <div class="menu-price-preptime">
                                    <span>৳${parseFloat(item.price).toFixed(2)}</span>
                                    <span>${item.prep_time} মিনিট</span>
                                </div>
                                <button class="add-to-cart-btn" data-id="${item.id}">কার্টে যোগ করুন</button>
                            </div>
                        </article>
                    `;
                }).join('');
                menuGrid.innerHTML = cardsHtml;

                const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
                if (addToCartButtons.length === 0) {
                    console.warn('No Add to Cart buttons found. Check menu item rendering.');
                }
                addToCartButtons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        const id = parseInt(btn.getAttribute('data-id'));
                        if (isNaN(id)) {
                            console.error('Invalid item ID:', btn.getAttribute('data-id'));
                            alert('একটি ত্রুটি ঘটেছে। আইটেম আইডি সঠিক নয়।');
                            return;
                        }
                        addToCart(id);
                    });
                });
            } catch (error) {
                console.error('Error rendering menu:', error);
                menuGrid.innerHTML = '<p style="text-align:center; color:#999;">একটি ত্রুটি ঘটেছে। দয়া করে আবার চেষ্টা করুন।</p>';
            }
        }

        categories.forEach(cat => {
            cat.addEventListener('click', () => {
                categories.forEach(c => c.classList.remove('active'));
                cat.classList.add('active');
                activeCategory = cat.dataset.category;
                renderMenuItems();
            });
            cat.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    cat.click();
                }
            });
        });

        filterRestaurant.addEventListener('change', () => {
            activeRestaurant = filterRestaurant.value;
            renderMenuItems();
        });

        function addToCart(itemId) {
            try {
                console.log('Attempting to add item with ID:', itemId);
                const item = menuItems.find(m => m.id === itemId);
                if (!item) {
                    console.warn('Item not found for ID:', itemId, 'Available IDs:', menuItems.map(m => m.id));
                    alert('আইটেমটি পাওয়া যায়নি। দয়া করে আবার চেষ্টা করুন।');
                    return;
                }
                console.log('Found item:', item);
                const existing = cart.find(c => c.id === itemId);
                if (existing) {
                    existing.qty++;
                } else {
                    cart.push({ id: itemId, qty: 1, price: parseFloat(item.price), name: item.name, restaurant_id: item.restaurant_id });
                }
                try {
                    localStorage.setItem('cart', JSON.stringify(cart));
                    console.log('Cart updated in localStorage:', cart);
                } catch (error) {
                    console.error('Error saving cart to localStorage:', error);
                }
                updateCartUI();
                openCart();
            } catch (error) {
                console.error('Error in addToCart:', error);
                alert('কার্টে যোগ করতে একটি ত্রুটি ঘটেছে।');
            }
        }

        function updateCartUI() {
            try {
                cartCount.textContent = cart.reduce((acc, c) => acc + c.qty, 0) || 0;
                if (cart.length === 0) {
                    cartItemsContainer.innerHTML = '<p style="padding: 20px; text-align: center; color: #999;">আপনার কার্ট খালি।</p>';
                    cartTotalPrice.textContent = '৳0.00';
                    return;
                }
                let cartHtml = '';
                cart.forEach(item => {
                    cartHtml += `
                        <div class="cart-item" role="listitem" aria-label="${item.qty} x ${item.name}, মূল্য ৳${(item.price * item.qty).toFixed(2)}">
                            <div class="cart-item-info">
                                <div class="cart-item-name">${item.name}</div>
                                <div class="cart-item-qty">পরিমাণ: ${item.qty}</div>
                            </div>
                            <div>
                                <div class="cart-item-price">৳${(item.price * item.qty).toFixed(2)}</div>
                                <button class="remove-btn" data-id="${item.id}" style="background:none; border:none; color:red; cursor:pointer;">Remove</button>
                            </div>
                        </div>
                    `;
                });
                cartItemsContainer.innerHTML = cartHtml;
                const totalPrice = cart.reduce((sum, i) => sum + i.price * i.qty, 0);
                cartTotalPrice.textContent = '৳' + totalPrice.toFixed(2);

                const removeButtons = document.querySelectorAll('.remove-btn');
                removeButtons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        const id = parseInt(btn.getAttribute('data-id'));
                        if (isNaN(id)) {
                            console.error('Invalid item ID for remove:', btn.getAttribute('data-id'));
                            return;
                        }
                        cart = cart.filter(c => c.id !== id);
                        try {
                            localStorage.setItem('cart', JSON.stringify(cart));
                        } catch (error) {
                            console.error('Error saving cart to localStorage:', error);
                        }
                        updateCartUI();
                    });
                });
            } catch (error) {
                console.error('Error in updateCartUI:', error);
                cartItemsContainer.innerHTML = '<p style="padding: 20px; text-align: center; color: #999;">কার্ট আপডেট করতে একটি ত্রুটি ঘটেছে।</p>';
            }
        }

        function openCart() {
            try {
                cartSidebar.classList.add('active');
                cartSidebar.setAttribute('aria-hidden', 'false');
                cartSidebar.focus();
            } catch (error) {
                console.error('Error in openCart:', error);
            }
        }

        function closeCart() {
            try {
                cartSidebar.classList.remove('active');
                cartSidebar.setAttribute('aria-hidden', 'true');
                cartToggleBtn.focus();
            } catch (error) {
                console.error('Error in closeCart:', error);
            }
        }

        cartToggleBtn.addEventListener('click', () => {
            if (cartSidebar.classList.contains('active')) {
                closeCart();
            } else {
                openCart();
            }
        });

        closeCartBtn.addEventListener('click', closeCart);

        checkoutBtn.addEventListener('click', () => {
            try {
                if (cart.length === 0) {
                    alert('আপনার কার্ট খালি। আইটেম যোগ করুন।');
                    return;
                }
                if (!<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>) {
                    alert('চেকআউট করতে লগইন করুন।');
                    window.location.href = 'login.php';
                    return;
                }
                // Submit cart data to checkout.php via POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'checkout.php';
                const cartInput = document.createElement('input');
                cartInput.type = 'hidden';
                cartInput.name = 'cart_items';
                cartInput.value = JSON.stringify(cart);
                form.appendChild(cartInput);
                document.body.appendChild(form);
                form.submit();
            } catch (error) {
                console.error('Error in checkout:', error);
                alert('চেকআউট প্রক্রিয়ায় একটি ত্রুটি ঘটেছে।');
            }
        });

        searchForm.addEventListener('submit', e => {
            e.preventDefault();
            renderMenuItems();
        });

        exploreMenuBtn.addEventListener('click', () => {
            document.getElementById('menu').scrollIntoView({ behavior: 'smooth' });
        });

        function toggleOrderHistory() {
            const orderHistory = document.getElementById('order-history');
            orderHistory.classList.toggle('active');
        }

        try {
            renderMenuItems();
            updateCartUI();
        } catch (error) {
            console.error('Error during initial load:', error);
        }
    </script>
</body>
</html>