<?php
session_start();
include '../login/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle remove from cart
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_from_cart'])) {
    $book_id = $_POST['book_id'];

    // Remove from cart
    $delete_sql = "DELETE FROM carts WHERE user_id = ? AND book_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $user_id, $book_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Redirect with success message
    header("Location: cart.php?removed=1");
    exit();
}

// Handle checkout
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['checkout'])) {
    // Get cart items
    $cart_sql = "SELECT book_id FROM carts WHERE user_id = ?";
    $cart_stmt = $conn->prepare($cart_sql);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    $cart_items = $cart_result->fetch_all(MYSQLI_ASSOC);
    $cart_stmt->close();

    if (count($cart_items) > 0) {
        $borrow_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+14 days'));

        foreach ($cart_items as $item) {
            $book_id = $item['book_id'];

            // Check if book is still available
            $check_sql = "SELECT available_copies FROM books WHERE id = ? AND available_copies > 0";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $book_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $book = $check_result->fetch_assoc();
            $check_stmt->close();

            if ($book) {
                // Insert into borrowings
                $borrow_sql = "INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, status) VALUES (?, ?, ?, ?, 'borrowed')";
                $borrow_stmt = $conn->prepare($borrow_sql);
                $borrow_stmt->bind_param("iiss", $user_id, $book_id, $borrow_date, $due_date);
                $borrow_stmt->execute();
                $borrow_stmt->close();

                // Decrement available copies
                $update_sql = "UPDATE books SET available_copies = available_copies - 1 WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $book_id);
                $update_stmt->execute();
                $update_stmt->close();

                // Remove from cart
                $delete_sql = "DELETE FROM carts WHERE user_id = ? AND book_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("ii", $user_id, $book_id);
                $delete_stmt->execute();
                $delete_stmt->close();
            }
        }

        // Redirect with success message
        header("Location: cart.php?checked_out=1");
        exit();
    } else {
        $error = "Your cart is empty.";
    }
}

// Get cart items with book details
$cart_sql = "SELECT c.book_id, b.title, b.author, b.image_path, b.available_copies
             FROM carts c
             JOIN books b ON c.book_id = b.id
             WHERE c.user_id = ?";
$cart_stmt = $conn->prepare($cart_sql);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart_items = $cart_result->fetch_all(MYSQLI_ASSOC);
$cart_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My Cart - Library System</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link
      href="https://fonts.googleapis.com/icon?family=Material+Icons"
      rel="stylesheet"
    />
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              primary: "#31694E",
              "background-light": "#f8fafc",
              "background-dark": "#1e293b",
            },
            fontFamily: {
              display: ["Poppins", "sans-serif"],
            },
            borderRadius: {
              DEFAULT: "0.5rem",
            },
          },
        },
      };
    </script>
  </head>
  <body
    class="font-display bg-background-light dark:bg-background-dark text-slate-700 dark:text-slate-300"
  >
    <div class="flex h-screen">
      <div id="backdrop" class="fixed inset-0 bg-black/40 z-40 hidden md:hidden"></div>
      <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 transform -translate-x-full md:translate-x-0 md:static md:flex bg-slate-50 dark:bg-slate-800 flex-col border-r border-slate-200 dark:border-slate-700 transition-transform duration-200">
        <div>
          <div class="h-16 flex items-center px-6 border-b border-slate-200 dark:border-slate-700">
            <span class="material-icons text-primary mr-2">school</span>
            <span class="font-bold text-lg text-slate-800 dark:text-slate-100">Library System</span>
            <button id="menu-close" class="md:hidden p-2 text-slate-500 dark:text-slate-300 hover:text-slate-700 dark:hover:text-slate-200">
              <span class="material-icons">close</span>
            </button>
          </div>
          <nav class="flex-1 p-4 space-y-2">
            <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="student-dashboard.php">
              <span class="material-icons mr-3">dashboard</span>
              Dashboard
            </a>
            <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="book-catalog.php">
              <span class="material-icons mr-3">menu_book</span>
              Book Catalog
            </a>
            <!-- Equipment Catalog removed from cart sidebar -->
            <a class="flex items-center px-4 py-2 text-sm font-medium bg-primary text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="cart.php">
              <span class="material-icons mr-3">shopping_cart</span>
              My Borrowing
            </a>


            <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="borrowing.php">
              <span class="material-icons mr-3">history</span>
              My Borrowing History
            </a>

          </nav>
        </div>
        <div class="p-4 flex items-center text-sm text-zinc-500 dark:text-zinc-400">
          <span class="material-icons text-orange-500 mr-2"></span>
          <span class="font-medium text-slate-800 dark:text-slate-100"></span>
        </div>
      </aside>
      <main class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 flex items-center justify-between px-8 bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
          <div class="flex items-center md:hidden mr-4">
            <button id="menu-btn" aria-expanded="false" class="p-2 text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100" aria-label="Open sidebar">
              <span class="material-icons">menu</span>
            </button>
          </div>
          <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Student Dashboard</h1>
          <div class="flex items-center gap-4">
            <div class="text-right">
              <p class="font-medium text-sm text-slate-800 dark:text-slate-100"><?php echo $_SESSION['username']; ?></p>
              <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo $_SESSION['email']; ?></p>
            </div>
            
          </div>
        </header>
        <div class="flex-1 overflow-y-auto p-8">
          <div class="max-w-7xl mx-auto">
            <?php if (isset($_GET['removed']) && $_GET['removed'] == '1'): ?>
              <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                Book removed from cart successfully!
              </div>
            <?php endif; ?>
            <?php if (isset($_GET['checked_out']) && $_GET['checked_out'] == '1'): ?>
              <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                Books checked out successfully! You can view your borrowing history.
              </div>
            <?php endif; ?>
            <div class="mb-6">
              <div class="flex items-center gap-3 mb-1">
                <span class="material-icons text-3xl text-primary">shopping_cart</span>
                <h2 class="text-2xl font-bold text-slate-800 dark:text-slate-100">My Borrowed</h2>
              </div>
              <p class="text-slate-500 dark:text-slate-400">Review and checkout your selected items.</p>
            </div>
            <div class="grid grid-cols-0 lg:grid-cols-0">
              <div class="lg:col-span-2">
                <section class="bg-white dark:bg-slate-800 p-6 rounded shadow-sm border border-slate-200 dark:border-slate-700">
                  <div class="flex justify-between items-center mb-2">
                    <div class="flex items-center gap-4">
                      <span class="material-icons text-3xl text-slate-700 dark:text-slate-300">shopping_cart</span>
                      <div>
                        <h3 class="text-2xl font-semibold text-slate-800 dark:text-slate-100">My Cart</h3>
                        <p class="text-slate-500 dark:text-slate-400"></p>
                      </div>
                    </div>
                    <div class="bg-gray-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-sm font-medium px-3 py-1 rounded-full"><?php echo count($cart_items); ?> total items</div>
                  </div>

                  <?php if (count($cart_items) > 0): ?>
                    <div class="space-y-4 mt-6">
                      <?php foreach ($cart_items as $item): ?>
                        <div class="flex items-center gap-4 p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
                          <img
                            alt="Book cover"
                            class="w-16 h-20 object-cover rounded shadow-md flex-shrink-0"
                            src="../<?php echo htmlspecialchars($item['image_path']); ?>"
                          />
                          <div class="flex-1">
                            <h4 class="font-semibold text-lg text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($item['title']); ?></h4>
                            <p class="text-slate-600 dark:text-slate-400">by <?php echo htmlspecialchars($item['author']); ?></p>
                            <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo $item['available_copies']; ?> copies available</p>
                          </div>
                          <form method="POST" class="flex-shrink-0">
                            <input type="hidden" name="book_id" value="<?php echo $item['book_id']; ?>">
                            <button type="submit" name="remove_from_cart"
                              class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors duration-200 flex items-center gap-2">
                              <span class="material-icons text-sm">delete</span>
                              Remove
                            </button>
                          </form>
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                      <div class="flex justify-between items-center">
                        <div>
                          <p class="text-lg font-semibold text-slate-800 dark:text-slate-100">Ready to borrow?</p>
                          <p class="text-slate-600 dark:text-slate-400">Proceed to checkout to complete your borrowing request.</p>
                        </div>
                        <form method="POST" class="inline">
                          <button type="submit" name="checkout" class="px-6 py-3 bg-primary hover:bg-primary/90 text-white rounded-lg transition-colors duration-200 flex items-center gap-2">
                            <span class="material-icons">check_circle</span>
                            Proceed to Checkout
                          </button>
                        </form>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="flex flex-col items-center justify-center py-24 text-center border-t border-slate-200 dark:border-slate-700 mt-6">
                      <span class="material-icons text-7xl text-slate-400 dark:text-slate-500 mb-4">production_quantity_limits</span>
                      <h4 class="text-xl font-medium text-slate-800 dark:text-slate-200">Your cart is empty</h4>
                      <p class="text-slate-500 dark:text-slate-400 mt-1">Add books from the catalog to get started.</p>
                    </div>
                  <?php endif; ?>
                </section>
              </div>
              <div>
                <div class="">
                  <div class="space-y-4">
                <div>
                  </div>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
         <footer class="h-14 flex items-center justify-between px-8 bg-slate-50 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700">
          <div class="text-sm">© 2025 OMSC Library</div>
          <div class="text-sm text-slate-500 space-x-4">
            <a href="/privacy.html" class="hover:text-primary">Privacy</a>
            <a href="/terms.html" class="hover:text-primary">Terms</a>
          </div>
        </footer>
      </main>
    </div>
    <script>
      // Sidebar toggle for small screens and auto highlight
      (function () {
        var btn = document.getElementById('menu-btn');
        var closeBtn = document.getElementById('menu-close');
        var sidebar = document.getElementById('sidebar');
        var backdrop = document.getElementById('backdrop');
        var navLinks = document.querySelectorAll('#sidebar nav a');

        function showSidebar() {
          sidebar.classList.remove('-translate-x-full');
          sidebar.classList.add('translate-x-0');
          backdrop.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
          if (btn) btn.setAttribute('aria-expanded', 'true');
        }
        function hideSidebar() {
          sidebar.classList.add('-translate-x-full');
          sidebar.classList.remove('translate-x-0');
          backdrop.classList.add('hidden');
          document.body.style.overflow = '';
          if (btn) btn.setAttribute('aria-expanded', 'false');
        }

        document.addEventListener('keydown', function (ev) {
          if (ev.key === 'Escape' && window.innerWidth < 768) hideSidebar();
        });

        if (btn && sidebar && backdrop) btn.addEventListener('click', showSidebar);
        if (closeBtn && sidebar && backdrop) closeBtn.addEventListener('click', hideSidebar);
        if (backdrop) backdrop.addEventListener('click', hideSidebar);
        if (navLinks && navLinks.length) {
          navLinks.forEach(function (a) {
            a.addEventListener('click', function () {
              if (window.innerWidth < 768) hideSidebar();
            });
          });
        }

        // Auto highlight
        var current = location.pathname.split('/').pop() || 'cart.php';
        navLinks.forEach(function (a) {
          var href = a.getAttribute('href');
          if (!href) return;
          var name = href.split('/').pop();
          if (name === current) {
            a.classList.add('bg-primary', 'text-white');
            a.setAttribute('aria-current', 'page');
          } else {
            a.classList.remove('bg-primary', 'text-white');
            a.removeAttribute('aria-current');
          }
        });
      })();
    </script>
  </body>
</html>
