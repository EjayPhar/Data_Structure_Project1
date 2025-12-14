<?php
session_start();
include '../login/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle add to cart
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_to_cart'])) {
    $book_id = $_POST['book_id'];

    // Check if book is available
    $check_sql = "SELECT available_copies FROM books WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $book_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $book = $check_result->fetch_assoc();
    $check_stmt->close();

    if ($book && $book['available_copies'] > 0) {
        // Check if already in cart
        $cart_check_sql = "SELECT id FROM carts WHERE user_id = ? AND book_id = ?";
        $cart_check_stmt = $conn->prepare($cart_check_sql);
        $cart_check_stmt->bind_param("ii", $user_id, $book_id);
        $cart_check_stmt->execute();
        $cart_check_stmt->store_result();

        if ($cart_check_stmt->num_rows == 0) {
            // Add to cart
            $insert_sql = "INSERT INTO carts (user_id, book_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ii", $user_id, $book_id);
            $insert_stmt->execute();
            $insert_stmt->close();

            header("Location: cart.php");
            exit();
        } else {
            $error = "Book is already in your cart.";
        }
        $cart_check_stmt->close();
    } else {
        $error = "Book is not available.";
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : 'All Categories';
$status = isset($_GET['status']) ? $_GET['status'] : 'All Status';

// Build query - exclude soft-deleted books
$query = "SELECT * FROM books WHERE deleted_at IS NULL";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ? OR category LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

if ($category != 'All Categories') {
    $query .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($status == 'Available') {
    $query .= " AND available_copies > 0";
} elseif ($status == 'Borrowed') {
    $query .= " AND available_copies = 0";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get cart count
$cart_count_sql = "SELECT COUNT(*) as count FROM carts WHERE user_id = ?";
$cart_count_stmt = $conn->prepare($cart_count_sql);
$cart_count_stmt->bind_param("i", $user_id);
$cart_count_stmt->execute();
$cart_count_result = $cart_count_stmt->get_result();
$cart_count = $cart_count_result->fetch_assoc()['count'];
$cart_count_stmt->close();

// Get available books count
$available_count = 0;
$total_books = count($books);
foreach ($books as $book) {
    if ($book['available_copies'] > 0) {
        $available_count++;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Book Catalog</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link
      href="https://fonts.googleapis.com/icon?family=Material+Icons"
      rel="stylesheet"
    />
      <link
      href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined"
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
    <script>
      // Auto highlight active sidebar link
      (function () {
        var links = document.querySelectorAll('aside nav a');
        var current = location.pathname.split('/').pop();
        if (!current) current = 'book-catalog.php';
        links.forEach(function (a) {
          var href = a.getAttribute('href');
          if (!href) return;
          var name = href.split('/').pop();
          if (!name) return;
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
    <script>
      // Sidebar toggle for small screens (show/hide with backdrop)
      (function () {
        var btn = document.getElementById('menu-btn');
        var closeBtn = document.getElementById('menu-close');
        var sidebar = document.querySelector('aside');
        var backdrop = document.getElementById('backdrop');

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
      })();
    </script>
  </head>
  <body
    class="font-display bg-background-light dark:bg-background-dark text-slate-700 dark:text-slate-300"
  >
    <div class="flex h-screen">
      <div id="backdrop" class="fixed inset-0 bg-black/40 z-40 hidden md:hidden"></div>
      <aside id="sidebar"
        class="fixed inset-y-0 left-0 z-50 w-64 transform -translate-x-full md:translate-x-0 md:static md:flex bg-slate-50 dark:bg-slate-800 flex flex-col border-r border-slate-200 dark:border-slate-700 transition-transform duration-200"
      >
        <div
          class="h-16 flex items-center px-6 border-b border-slate-200 dark:border-slate-700"
        >
          <span class="material-icons text-primary mr-2">school</span>
          <span class="font-bold text-lg text-slate-800 dark:text-slate-100"
            >Library System</span
          >
          <button id="menu-close" class="md:hidden p-2 text-slate-500 dark:text-slate-300 hover:text-slate-700 dark:hover:text-slate-200 ml-auto">
            <span class="material-icons">close</span>
          </button>
        </div>
        <nav class="flex-1 p-4 space-y-2">
          <a
            class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
            href="student-dashboard.php"
          >
            <span class="material-icons mr-3">dashboard</span>
            Dashboard
          </a>
          <a
            class="flex items-center px-4 py-2 text-sm font-medium bg-primary text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
            href="book-catalog.php"
          >
            <span class="material-icons mr-3">menu_book</span>
            Book Catalog
          </a>
          <a
            class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
            href="cart.php"
          >
            <span class="material-icons mr-3">shopping_cart</span>
            My Borrowing
          </a>

          <a
            class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
            href="borrowing.php"
          >
            <span class="material-icons mr-3">history</span>
            My Borrowing History
          </a>

        </nav>
      </aside>
      <div class="flex-1 flex flex-col">
        <header class="h-16 flex items-center justify-between px-8 bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
          <div class="flex items-center md:hidden mr-4">
            <button id="menu-btn" aria-expanded="false" class="p-2 text-slate-600 dark:text-slate-300 hover:text-slate-800 dark:hover:text-slate-100" aria-label="Open sidebar">
              <span class="material-icons">menu</span>
            </button>
          </div>
          <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Student Dashboard</h1>
          <div class="flex items-center gap-4">
            <div class="text-right">
              <p class="font-medium text-sm text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
              <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
            </div>
            
          </div>
        </header>
        <main class="flex-1 p-8 overflow-y-auto">
          <div class="mb-8">
            <h2 class="text-2xl font-bold text-slate-800 dark:text-slate-100">
              Book Catalog
            </h2>
            <p class="text-slate-500 dark:text-slate-400">
              Search and browse available books in our library.
            </p>
          </div>
          <?php if (isset($error)): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
              <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>
          <div class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-8">
            <div class="flex items-center gap-3 mb-4">
              <span class="material-icons text-primary">search</span>
              <h4 class="text-lg font-semibold text-slate-800 dark:text-white">
                Search &amp; Discover
              </h4>
            </div>
            <p class="text-slate-500 dark:text-slate-400 mb-6">
              Find exactly what you're looking for with our advanced search tools.
            </p>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <input
                id="search-input"
                name="search"
                class="col-span-1 md:col-span-1 bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 rounded-lg focus:ring-primary focus:border-primary"
                placeholder="Search by title, author, ISBN, Book ID..."
                type="text"
                value="<?php echo htmlspecialchars($search); ?>"
                onkeydown="if(event.key=='Enter') this.form.submit()"
              />
              <select
                id="category-select"
                name="category"
                class="col-span-1 md:col-span-1 bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 rounded-lg focus:ring-primary focus:border-primary"
                onchange="this.form.submit()"
              >
                <option value="All Categories">All Categories</option>
                <option value="Computer Science" <?php if ($category == 'Computer Science') echo 'selected'; ?>>Computer Science</option>
                <option value="Programming" <?php if ($category == 'Programming') echo 'selected'; ?>>Programming</option>
                <option value="Mathematics" <?php if ($category == 'Mathematics') echo 'selected'; ?>>Mathematics</option>
                <option value="Physics" <?php if ($category == 'Physics') echo 'selected'; ?>>Physics</option>
                <option value="History" <?php if ($category == 'History') echo 'selected'; ?>>History</option>
                <option value="Biography" <?php if ($category == 'Biography') echo 'selected'; ?>>Biography</option>
                <option value="Philosophy of Science" <?php if ($category == 'Philosophy of Science') echo 'selected'; ?>>Philosophy of Science</option>
              </select>
              <select
                id="status-select"
                name="status"
                class="col-span-1 md:col-span-1 bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 rounded-lg focus:ring-primary focus:border-primary"
                onchange="this.form.submit()"
              >
                <option value="All Status">All Status</option>
                <option value="Available" <?php if ($status == 'Available') echo 'selected'; ?>>Available</option>
                <option value="Borrowed" <?php if ($status == 'Borrowed') echo 'selected'; ?>>Borrowed</option>
              </select>
              <button type="submit" class="col-span-1 md:col-span-1 bg-primary text-white font-medium py-2 px-4 rounded-lg hover:bg-primary/90 focus:ring-2 focus:ring-primary focus:ring-offset-2">
                Search
              </button>
            </form>
            <br>
            <div class="flex justify-between items-center mb-6">
              <div>
                <br>
                <h4 class="font-bold text-lg"><?php echo $total_books; ?> Books Found</h4>
                <p
                  class="text-sm text-text-light-secondary dark:text-dark-secondary"
                >
                  Total collection: <?php echo $total_books; ?> books
                </p>
              </div>
              <div class="flex items-center gap-4">
                <span class="text-green-600 font-semibold">Available: <?php echo $available_count; ?></span>
                <a href="cart.php" class="flex items-center gap-2 px-4 py-2 rounded-md border border-border-light dark:border-border-dark text-sm bg-surface-light dark:bg-surface-dark hover:bg-slate-100 dark:hover:bg-slate-700">
                  <span class="material-icons-outlined text-base">shopping_cart</span>
                  <?php echo $cart_count; ?> in cart
                </a>
              </div>
            </div>
            <div
              class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-6"
            >
              <?php foreach ($books as $book): ?>
                <?php
                  $status_class = $book['available_copies'] > 0 ? 'available' : 'borrowed';
                  $status_color = $book['available_copies'] > 0 ? 'green' : 'red';
                  $status_text = $book['available_copies'] > 0 ? 'available' : 'borrowed';
                  $left_copies = $book['available_copies'];
                ?>
                <div
                  class="cursor-pointer group bg-surface-light dark:bg-surface-dark rounded-lg overflow-hidden border border-border-light dark:border-border-dark transition-all duration-300"
                  onclick="openModal('<?php echo $book['id']; ?>')"
                  data-category="<?php echo htmlspecialchars($book['category']); ?>"
                  data-status="<?php echo $status_class; ?>"
                >
                  <div class="relative">
                    <img
                      alt="<?php echo htmlspecialchars($book['title']); ?>"
                      class="w-full h-40 object-cover"
                      src="../<?php echo htmlspecialchars($book['image_path']); ?>"
                    />
                    <div class="absolute top-2 right-2 flex items-center gap-1.5">
                      <span
                        class="text-xs font-semibold text-slate-700 bg-slate-200/80 backdrop-blur-sm px-2 py-1 rounded-full"
                        ><?php echo $left_copies; ?> left</span
                      >
                      <span
                        class="text-xs font-semibold text-<?php echo $status_color; ?>-800 bg-<?php echo $status_color; ?>-200/80 backdrop-blur-sm px-2 py-1 rounded-full"
                        ><?php echo $status_text; ?></span
                      >
                    </div>
                  </div>
                  <div class="p-4">
                    <h5 class="font-semibold text-sm truncate">
                      <?php echo htmlspecialchars($book['title']); ?>
                    </h5>
                    <p
                      class="text-xs text-text-light-secondary dark:text-dark-secondary mb-2"
                    >
                      by <?php echo htmlspecialchars($book['author']); ?>
                    </p>
                    <span
                      class="text-xs text-<?php echo strtolower($book['category'] == 'Computer Science' ? 'yellow' : ($book['category'] == 'Programming' ? 'orange' : ($book['category'] == 'Mathematics' ? 'teal' : ($book['category'] == 'Physics' ? 'blue' : ($book['category'] == 'History' ? 'rose' : 'purple'))))); ?>-600 dark:text-<?php echo strtolower($book['category'] == 'Computer Science' ? 'yellow' : ($book['category'] == 'Programming' ? 'orange' : ($book['category'] == 'Mathematics' ? 'teal' : ($book['category'] == 'Physics' ? 'blue' : ($book['category'] == 'History' ? 'rose' : 'purple'))))); ?>-400 font-medium"
                    >
                      <?php echo htmlspecialchars($book['category']); ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if (empty($books)): ?>
              <div class="text-center py-8 text-slate-500 dark:text-slate-400">
                <p>No books found matching your criteria.</p>
              </div>
            <?php endif; ?>
          </div>
        </main>
        <!-- Modals will be generated here -->
        <div id="modals-container">
          <?php foreach ($books as $book): ?>
            <?php
              $status_class = $book['available_copies'] > 0 ? 'available' : 'borrowed';
              $status_color = $book['available_copies'] > 0 ? 'green' : 'red';
              $location = 'Section ' . chr(65 + ($book['id'] % 6)) . ', Shelf ' . (($book['id'] % 5) + 1);
            ?>
            <div
              class="fixed inset-0 bg-black/60 backdrop-blur-sm flex justify-center items-center p-4 transition-opacity duration-300 opacity-0 pointer-events-none"
              id="bookDetailModal<?php echo $book['id']; ?>"
              style="z-index: 50"
            >
              <div>
                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg">
                  <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                      <div class="flex items-start gap-4">
                        <img
                          alt="<?php echo htmlspecialchars($book['title']); ?>"
                          class="w-16 h-20 object-cover rounded shadow-md flex-shrink-0"
                          src="../<?php echo htmlspecialchars($book['image_path']); ?>"
                        />
                        <div>
                          <span
                            class="text-xs font-semibold text-<?php echo $status_color; ?>-800 bg-<?php echo $status_color; ?>-200 dark:bg-<?php echo $status_color; ?>-900/50 dark:text-<?php echo $status_color; ?>-300 px-2 py-0.5 rounded-full mb-1 inline-block"
                            ><?php echo $status_text; ?></span
                          >
                          <h3
                            class="text-lg font-bold text-text-light-primary dark:text-dark-primary"
                          >
                            <?php echo htmlspecialchars($book['title']); ?>
                          </h3>
                          <p
                            class="text-sm text-text-light-secondary dark:text-dark-secondary"
                          >
                            by <?php echo htmlspecialchars($book['author']); ?>
                          </p>
                        </div>
                      </div>
                      <button
                          class="text-text-light-secondary dark:text-dark-secondary hover:text-gray-400 dark:hover:text-gray-400"
                          onclick="closeModal('<?php echo $book['id']; ?>')"
                          >
                        <span class="material-icons-outlined">close</span>
                      </button>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                      <div class="bg-amber-100 dark:bg-amber-900/40 p-4 rounded-lg">
                        <p
                          class="text-xs font-medium text-amber-700 dark:text-amber-300 mb-1"
                        >
                          Availability
                        </p>
                        <p
                          class="font-bold text-text-light-primary dark:text-dark-primary"
                        >
                          <?php echo $book['available_copies']; ?> of <?php echo $book['total_copies']; ?>
                          <br>
                          <span class="font-normal text-sm">copies available</span>
                        </p>
                      </div>
                      <div class="bg-amber-100 dark:bg-amber-900/40 p-4 rounded-lg">
                        <p
                          class="text-xs font-medium text-amber-700 dark:text-amber-300 mb-1"
                        >
                          Location
                        </p>
                        <p
                          class="font-bold text-text-light-primary dark:text-dark-primary"
                        >
                          <?php echo $location; ?>
                          <br>
                          <span class="font-normal text-sm">in library</span>
                        </p>
                      </div>
                    </div>
                    <div
                      class="bg-slate-100 dark:bg-slate-800/50 p-4 rounded-lg mb-4 space-y-3"
                    >
                      <h4
                        class="text-sm font-semibold text-text-light-secondary dark:text-dark-secondary"
                      >
                        Book Information
                      </h4>
                      <div class="grid grid-cols-3 gap-x-4 gap-y-2 text-sm">
                        <span
                          class="text-text-light-secondary dark:text-dark-secondary col-span-1"
                          >Book ID:</span
                        >
                        <span
                          class="font-medium text-text-light-primary dark:text-dark-primary col-span-2"
                        ><?php echo $book['id']; ?></span>
                        <span
                          class="text-text-light-secondary dark:text-dark-secondary col-span-1"
                          >ISBN:</span
                        >
                        <span
                          class="font-medium text-text-light-primary dark:text-dark-primary col-span-2 text-right"
                          ><?php echo htmlspecialchars($book['isbn']); ?></span
                        >
                        <span
                          class="text-text-light-secondary dark:text-dark-secondary col-span-1 "
                          >Category:</span
                        >
                        <span
                          class="font-medium text-text-light-primary dark:text-dark-primary col-span-2 text-right"
                          ><?php echo htmlspecialchars($book['category']); ?></span
                        >
                      </div>
                    </div>
                    <div class="bg-amber-100 dark:bg-amber-900/40 p-4 rounded-lg">
                      <h4
                        class="text-sm font-semibold text-amber-700 dark:text-amber-300 mb-1"
                      >
                        Description
                      </h4>
                      <p class="text-sm text-text-light-primary dark:text-dark-primary">
                        <?php echo htmlspecialchars($book['description']); ?>
                      </p>
                    </div>
                  </div>
                  <div class="p-6 border-t border-slate-200 dark:border-slate-800">
                    <?php if ($book['available_copies'] > 0): ?>
                      <form method="POST">
                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                        <button type="submit" name="add_to_cart"
                          class="w-full bg-gradient-to-r from-green-500 to-teal-500 text-white font-bold py-3 px-4 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300 flex items-center justify-center gap-2"
                        >
                          <span class="material-icons-outlined">add_shopping_cart</span>
                          Borrow Book
                        </button>
                      </form>
                    <?php else: ?>
                      <button disabled
                        class="w-full bg-gray-400 text-white font-bold py-3 px-4 rounded-lg cursor-not-allowed flex items-center justify-center gap-2"
                      >
                        <span class="material-icons-outlined">block</span>
                        Not Available
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <footer class="h-14 flex items-center justify-between px-8 bg-slate-50 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700">
          <div class="text-sm">© 2025 OMSC Library</div>
          <div class="text-sm text-slate-500 space-x-4">
            <a href="/privacy.html" class="hover:text-primary">Privacy</a>
            <a href="/terms.html" class="hover:text-primary">Terms</a>
          </div>
        </footer>
      </div>
    </div>
    <script>
      // Modal functions
      function openModal(bookId) {
        const modal = document.getElementById('bookDetailModal' + bookId);
        if (modal) {
          modal.classList.remove('opacity-0', 'pointer-events-none');
          modal.querySelector('div').classList.remove('scale-95');
        }
      }

      function closeModal(bookId) {
        const modal = document.getElementById('bookDetailModal' + bookId);
        if (modal) {
          modal.classList.add('opacity-0');
          modal.querySelector('div').classList.add('scale-95');
          setTimeout(() => {
            modal.classList.add('pointer-events-none');
          }, 300);
        }
      }

      // Close modal on outside click and Escape key
      document.addEventListener('click', function(event) {
        if (event.target.classList.contains('fixed')) {
          const modal = event.target;
          const bookId = modal.id.replace('bookDetailModal', '');
          closeModal(bookId);
        }
      });

      document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
          // Close all open modals
          document.querySelectorAll('[id^="bookDetailModal"]').forEach(modal => {
            if (!modal.classList.contains('pointer-events-none')) {
              const bookId = modal.id.replace('bookDetailModal', '');
              closeModal(bookId);
            }
          });
        }
      });
    </script>
  </body>
</html>
