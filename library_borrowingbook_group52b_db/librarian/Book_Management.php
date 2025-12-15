<?php
session_start();
include '../login/db_connect.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login/login.html");
    exit();
}

// Ensure soft-delete columns exist on books table
function books_column_exists($conn, $column) {
    $sql = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'books' AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res) return false;
    $row = $res->fetch_assoc();
    return isset($row['cnt']) && (int)$row['cnt'] > 0;
}

function ensure_books_soft_delete_columns($conn) {
    if (!books_column_exists($conn, 'deleted_at')) {
        $conn->query("ALTER TABLE books ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
    }
    if (!books_column_exists($conn, 'deleted_by')) {
        $conn->query("ALTER TABLE books ADD COLUMN deleted_by VARCHAR(255) NULL DEFAULT NULL");
    }
}

ensure_books_soft_delete_columns($conn);

// Handle POST requests for CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add') {
            // Add new book
            $title = trim($_POST['title']);
            $author = trim($_POST['author']);
            $isbn = trim($_POST['isbn']);
            $category = trim($_POST['category']);
            $description = trim($_POST['description']);
            $copies = (int)$_POST['copies'];

            if (empty($title) || empty($author) || empty($isbn)) {
                $error = "Title, author, and ISBN are required.";
            } else {
                $sql = "INSERT INTO books (title, author, isbn, category, description, total_copies, available_copies) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssii", $title, $author, $isbn, $category, $description, $copies, $copies);
                if ($stmt->execute()) {
                    $success = "Book added successfully.";
                } else {
                    $error = "Error adding book: " . $conn->error;
                }
                $stmt->close();
            }
        } elseif ($action === 'edit') {
            // Edit existing book
            $id = (int)$_POST['id'];
            $title = trim($_POST['title']);
            $author = trim($_POST['author']);
            $isbn = trim($_POST['isbn']);
            $category = trim($_POST['category']);
            $description = trim($_POST['description']);
            $copies = (int)$_POST['copies'];

            if (empty($title) || empty($author) || empty($isbn)) {
                $error = "Title, author, and ISBN are required.";
            } else {
                // Get current book data to calculate the difference
                $get_sql = "SELECT total_copies, available_copies FROM books WHERE id=?";
                $get_stmt = $conn->prepare($get_sql);
                $get_stmt->bind_param("i", $id);
                $get_stmt->execute();
                $get_result = $get_stmt->get_result();
                $current_book = $get_result->fetch_assoc();
                $get_stmt->close();

                if ($current_book) {
                    $old_total = $current_book['total_copies'];
                    $old_available = $current_book['available_copies'];
                    
                    // Calculate the difference in total copies
                    $difference = $copies - $old_total;
                    
                    // Update available copies by the same difference
                    // But ensure available_copies doesn't go below 0
                    $new_available = max(0, $old_available + $difference);
                    
                    $sql = "UPDATE books SET title=?, author=?, isbn=?, category=?, description=?, total_copies=?, available_copies=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssssiis", $title, $author, $isbn, $category, $description, $copies, $new_available, $id);
                    if ($stmt->execute()) {
                        $success = "Book updated successfully.";
                    } else {
                        $error = "Error updating book: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Book not found.";
                }
            }
        } elseif ($action === 'delete') {
            // Soft delete book
            $id = (int)$_POST['id'];
            $staff_name = $_SESSION['username'] ?? 'staff';
            $sql = "UPDATE books SET deleted_at = NOW(), deleted_by = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $staff_name, $id);
            if ($stmt->execute()) {
                $success = "Book deleted successfully.";
            } else {
                $error = "Error deleting book: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build query - exclude soft-deleted books
$sql = "SELECT * FROM books WHERE deleted_at IS NULL";
$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($category_filter) && $category_filter !== 'All Categories') {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

$sql .= " ORDER BY title ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unique categories for filter dropdown - exclude soft-deleted books
$category_sql = "SELECT DISTINCT category FROM books WHERE deleted_at IS NULL ORDER BY category";
$category_result = $conn->query($category_sql);
$categories = [];
while ($row = $category_result->fetch_assoc()) {
    $categories[] = $row['category'];
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Book Management</title>
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
        if (!current) current = 'Book_Management.php';
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
    @keydown.escape.window="isEditModalOpen = false; isDeleteModalOpen = false; isAddModalOpen = false"
    x-data="{ isEditModalOpen: false, isDeleteModalOpen: false, deleteBookTitle: '', isAddModalOpen: false }"
    class="font-display bg-background-light dark:bg-background-dark text-slate-700 dark:text-slate-300"
  >
    <main class="flex h-screen">
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
            href="librarian-dashboard.php"
          >
            <span class="material-icons mr-3">dashboard</span>
            Dashboard
          </a>
          <a
            class="flex items-center px-4 py-2 text-sm font-medium bg-primary text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
            href="Book_Management.php"
          >
            <span class="material-icons mr-3">menu_book</span>
            Book Management
          </a>
          <a
            class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
            href="user-management.php"
          >
            <span class="material-icons mr-3">group</span>
            User Management
          </a>
          <a
            class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
            href="borrow.php"
          >
            <span class="material-icons mr-3">history</span>
            My Borrowing History
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="overdue-alerts.php">
            <span class="material-icons mr-3">warning</span>
            Overdue Alerts
          </a>
         <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="backup_restore.php">
            <span class="material-icons mr-3">backup</span>
            Backup & Restore
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="Attendance-logs.php">
            <span class="material-icons mr-3">event_available</span>
            Attendance Logs
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="change-password.php">
            <span class="material-icons mr-3">lock</span>
            Change Password
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
          <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Librarian Dashboard</h1>
          <div class="flex items-center gap-4">
            <div class="text-right">
              <p class="font-medium text-sm text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
              <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
            </div>
            
          </div>
        </header>
      <div class="flex-1 p-8 overflow-y-auto">
          <div class="bg-white dark:bg-gray-900 rounded-lg p-6">
            <?php if (isset($success)): ?>
              <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                <?php echo htmlspecialchars($success); ?>
              </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
              <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                <?php echo htmlspecialchars($error); ?>
              </div>
            <?php endif; ?>
            <div class="flex justify-between items-center mb-6">
              <div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                  Book Management
                </h3>
                <p class="text-gray-500 dark:text-gray-400 mt-1">
                  Manage library books, availability, and inventory.
                </p>
              </div>
              <div class="flex items-center gap-4">
                <button
                  @click="isAddModalOpen = true"
                  class="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-md hover:opacity-90 transition-opacity"
                >
                  <span class="material-icons-outlined">add</span>
                  Add New Book
                </button>
              </div>
            </div>
            <div
              class="mb-6 p-4 border border-gray-200 dark:border-gray-700 rounded-lg"
            >
              <h3
                class="text-lg font-semibold text-slate-800 dark:text-slate-200"
              >
                Search &amp; Filter
              </h3>
              <br>
              <form method="GET" class="flex items-center gap-4">
                <div class="relative flex-grow">
                  <span
                    class="material-icons-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                    >search</span
                  >
                  <input
                    id="search-input"
                    name="search"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-transparent focus:ring-primary focus:border-primary transition-all hover:border-gray-400 dark:hover:border-gray-500"
                    placeholder="Search by title, author, or ISBN..."
                    type="text"
                    value="<?php echo htmlspecialchars($search); ?>"
                  />
                </div>
                <select
                  id="category-select"
                  name="category"
                  class="w-48 border border-gray-300 dark:border-gray-600 rounded-md bg-transparent focus:ring-primary focus:border-primary transition-all hover:border-gray-400 dark:hover:border-gray-500"
                >
                  <option>All Categories</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                  <?php endforeach; ?>
                </select>
                <select
                  id="status-select"
                  name="status"
                  class="w-48 border border-gray-300 dark:border-gray-600 rounded-md bg-transparent focus:ring-primary focus:border-primary transition-all hover:border-gray-400 dark:hover:border-gray-500"
                >
                  <option>All Status</option>
                  <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                  <option value="borrowed" <?php echo $status_filter === 'borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md hover:opacity-90">Filter</button>
              </form>
            </div>
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                All registered books in the system (<?php echo count($books); ?> books)
              </p>
              <div class="overflow-x-auto">
                <table class="w-full text-left">
                  <thead>
                    <tr
                      class="border-b border-black-200 dark:border-black-700 text-sm text-black-500 dark:text-black-400"
                    >
                      <th class="py-3 px-4 font-medium">Book Details</th>
                      <th class="py-3 px-4 font-medium">Category</th>
                      <th class="py-2 px-9 font-medium">Location</th>
                      <th class="py-3 px-1 font-medium">Availability</th>
                      <th class="py-3 px-8 font-medium">Status</th>
                      <th class="py-3 px-4 font-medium">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($books as $book):
                      $location = 'Section ' . chr(65 + ($book['id'] % 6)) . ', Shelf ' . (($book['id'] % 5) + 1);
                      $status = $book['available_copies'] > 0 ? 'available' : 'borrowed';
                      $status_color = $status === 'available' ? 'orange' : 'green';
                      $availability = $book['available_copies'] . ' / ' . $book['total_copies'];
                    ?>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                      <td class="py-4 px-4">
                        <p class="font-medium text-text-light dark:text-text-dark">
                          <?php echo htmlspecialchars($book['title']); ?>
                        </p>
                        <p class="text-xs text-subtext-light dark:text-subtext-dark">
                          by <?php echo htmlspecialchars($book['author']); ?>
                        </p>
                        <p class="text-xs text-subtext-light dark:text-subtext-dark">
                          ISBN: <?php echo htmlspecialchars($book['isbn']); ?>
                        </p>
                      </td>
                      <td class="py-4 px-2"><?php echo htmlspecialchars($book['category']); ?></td>
                      <td class="py-4 px-2"><?php echo htmlspecialchars($location); ?></td>
                      <td class="py-1 px-7"><?php echo htmlspecialchars($availability); ?></td>
                      <td class="py-4 px-4">
                        <span class="bg-<?php echo $status_color; ?>-500 text-white px-3 py-1 rounded-full text-xs font-semibold">
                          <?php echo htmlspecialchars($status); ?>
                        </span>
                      </td>
                      <td class="py-4 px-4">
                        <div class="flex items-center gap-2">
                          <button
                            @click="isEditModalOpen = true"
                            onclick="editBook(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>', '<?php echo addslashes($book['author']); ?>', '<?php echo addslashes($book['isbn']); ?>', '<?php echo addslashes($book['category']); ?>', '<?php echo addslashes($book['description']); ?>', <?php echo $book['total_copies']; ?>)"
                            class="flex items-center px-1 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium bg-surface-light dark:bg-surface-dark hover:bg-gray-50 dark:hover:bg-gray-700"
                          >
                            <span class="material-icons-outlined text-base">edit</span>
                          </button>
                          <button
                            @click="isDeleteModalOpen = true; deleteBookTitle = '<?php echo addslashes($book['title']); ?>'"
                            onclick="deleteBook(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')"
                            class="flex items-center px-1 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700"
                          >
                            <span class="material-icons-outlined text-base">delete</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
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
      <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
      style="display: none"
      x-show="isEditModalOpen"
      x-transition:enter="ease-out duration-300"
      x-transition:enter-end="opacity-100"
      x-transition:enter-start="opacity-0"
      x-transition:leave="ease-in duration-200"
      x-transition:leave-end="opacity-0"
      x-transition:leave-start="opacity-100"
    >
      <div
      @click.away="isEditModalOpen = false"
      class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-xl p-6 m-4 max-h-[85vh] overflow-y-auto"
      x-show="isEditModalOpen"
      x-transition:enter="ease-out duration-300"
      x-transition:enter-start="opacity-0 scale-95"
      x-transition:enter-end="opacity-100 scale-100"
      x-transition:leave="ease-in duration-200"
      x-transition:leave-start="opacity-100 scale-100"
      x-transition:leave-end="opacity-0 scale-95"
    >
        <div class="flex justify-between items-center mb-6">
          <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
              Edit Book
            </h2>
            <p class="text-gray-500 dark:text-gray-400">
              Update book information.
            </p>
          </div>
          <button
            @click="isEditModalOpen = false"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
          >
            <span class="material-icons-outlined">close</span>
          </button>
        </div>
        <form action="Book_Management.php" method="POST">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" id="edit-id">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="edit-title">Title</label>
              <input class="w-full bg-background-light dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-primary focus:border-primary" id="edit-title" name="title" placeholder="Book title" type="text" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="edit-author">Author</label>
              <input class="w-full bg-background-light dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-primary focus:border-primary" id="edit-author" name="author" placeholder="Author name" type="text" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="edit-isbn">ISBN</label>
              <input class="w-full bg-background-light dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-primary focus:border-primary" id="edit-isbn" name="isbn" placeholder="978-0-123456-78-9" type="text" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="edit-category">Category</label>
              <input class="w-full bg-background-light dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-primary focus:border-primary" id="edit-category" name="category" placeholder="e.g., Computer Science" type="text" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="edit-description">Description</label>
              <textarea class="w-full bg-background-light dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-primary focus:border-primary" id="edit-description" name="description" placeholder="Book description" rows="3"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="edit-copies">Number of copies</label>
              <input class="w-full bg-background-light dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-primary focus:border-primary" id="edit-copies" name="copies" type="number" min="1" required />
            </div>
          </div>
          <div class="flex justify-end gap-4 pt-4">
            <button @click="isEditModalOpen = false" class="px-6 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" type="button">Cancel</button>
            <button class="px-6 py-2 bg-primary text-white rounded-md hover:opacity-90 transition-opacity" type="submit">Update Book</button>
          </div>
        </form>
      </div>
    </div>
    <!-- Add Modal -->
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
      x-show="isAddModalOpen"
      x-transition:enter="ease-out duration-300"
      x-transition:enter-end="opacity-100"
      x-transition:enter-start="opacity-0"
      x-transition:leave="ease-in duration-200"
      x-transition:leave-end="opacity-0"
      x-transition:leave-start="opacity-100"
    >
      <div
        @click.away="isAddModalOpen = false"
        class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-xl p-6 m-4 max-h-[85vh] overflow-y-auto"
        x-show="isAddModalOpen"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
      >
        <div class="flex justify-between items-center mb-6">
          <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
              Add New Book
            </h2>
            <p class="text-gray-500 dark:text-gray-400">
              Add a new book to the library catalog.
            </p>
          </div>
          <button
            @click="isAddModalOpen = false"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
          >
            <span class="material-icons-outlined">close</span>
          </button>
        </div>
        <form action="Book_Management.php" method="POST">
          <input type="hidden" name="action" value="add">
          <div>
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300"
              for="add-title"
              >Title <span class="text-red-500">*</span></label
            >
            <input
              class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary transition-all hover:border-gray-400 dark:hover:border-gray-500"
              id="add-title"
              name="title"
              placeholder="Book title"
              type="text"
              required
            />
          </div>
          <div>
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300"
              for="add-author"
              >Author <span class="text-red-500">*</span></label
            >
            <input
              class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary transition-all hover:border-gray-400 dark:hover:border-gray-500"
              id="add-author"
              name="author"
              placeholder="Author name"
              type="text"
              required
            />
          </div>
          <div>
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300"
              for="add-isbn"
              >ISBN <span class="text-red-500">*</span></label
            >
            <input
              class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary transition-all hover:border-gray-400 dark:hover:border-gray-500"
              id="add-isbn"
              name="isbn"
              placeholder="978-0-123456-78-9"
              type="text"
              required
            />
          </div>
          <div>
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300"
              for="add-category"
              >Category</label
            >
            <input
              class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary transition-all hover:border-gray-400 dark:hover:border-gray-500"
              id="add-category"
              name="category"
              placeholder="e.g. Computer Science"
              type="text"
            />
          </div>
          <div>
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300"
              for="add-description"
              >Description</label
            >
            <textarea
              class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary transition-all hover:border-gray-400 dark:hover:border-gray-500"
              id="add-description"
              name="description"
              placeholder="Book description"
              rows="3"
            ></textarea>
          </div>
          <div>
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300"
              for="add-copies"
              >Number of Copies</label
            >
            <input
              class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary transition-all hover:border-gray-400 dark:hover:border-gray-500"
              id="add-copies"
              min="1"
              name="copies"
              type="number"
              value="1"
              required
            />
          </div>
          <div class="flex justify-end gap-4 pt-4">
            <button
              @click="isAddModalOpen = false"
              class="px-6 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
              type="button"
            >
              Cancel
            </button>
            <button
              class="px-6 py-2 bg-primary text-white rounded-md hover:opacity-90 transition-opacity"
              type="submit"
            >
              Add Book
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- Delete Modal -->
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
      x-show="isDeleteModalOpen"
      x-transition:enter="ease-out duration-300"
      x-transition:enter-end="opacity-100"
      x-transition:enter-start="opacity-0"
      x-transition:leave="ease-in duration-200"
      x-transition:leave-end="opacity-0"
      x-transition:leave-start="opacity-100"
    >
      <div
        @click.away="isDeleteModalOpen = false"
        class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6 m-4"
        x-show="isDeleteModalOpen"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
      >
        <div class="text-center">
          <div class="mb-4">
            <span class="material-icons-outlined text-red-500 text-4xl">warning</span>
          </div>
          <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Are you absolutely sure?</h2>
          <p class="text-gray-600 dark:text-gray-400 mb-6">
            This action cannot be undone. This will permanently delete "<span x-text="deleteBookTitle"></span>" from the library catalog.
          </p>
          <div class="flex justify-center gap-4">
            <button
              @click="isDeleteModalOpen = false"
              class="px-6 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
              type="button"
            >
              Cancel
            </button>
            <form action="Book_Management.php" method="POST" style="display: inline;">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" id="delete-id">
              <button
                class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
                type="submit"
              >
                Delete
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <script>
      function editBook(id, title, author, isbn, category, description, copies) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-title').value = title;
        document.getElementById('edit-author').value = author;
        document.getElementById('edit-isbn').value = isbn;
        document.getElementById('edit-category').value = category;
        document.getElementById('edit-description').value = description;
        document.getElementById('edit-copies').value = copies;
      }

      function deleteBook(id, title) {
        document.getElementById('delete-id').value = id;
        // Alpine.js will handle the modal display
      }
    </script>
    <script
      defer=""
      src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js"
    ></script>
  </body>
</html>
<?php
$conn->close();
?>
