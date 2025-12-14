<?php
session_start();

// Require admin session
function require_admin_login(): void {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ../login/login.html');
        exit();
    }
}
require_admin_login();

include '../login/db_connect.php';

// Ensure soft-delete columns exist on books table
function books_column_exists(mysqli $conn, string $column): bool {
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
function ensure_books_soft_delete_columns(mysqli $conn): void {
    if (!books_column_exists($conn, 'deleted_at')) {
        $conn->query("ALTER TABLE books ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
    }
    if (!books_column_exists($conn, 'deleted_by')) {
        $conn->query("ALTER TABLE books ADD COLUMN deleted_by VARCHAR(255) NULL DEFAULT NULL");
    }
}
ensure_books_soft_delete_columns($conn);

// CRUD helpers
function add_book(mysqli $conn, array $data): bool {
    $sql = "INSERT INTO books (title, author, isbn, category, description, image_path, total_copies, available_copies) VALUES (?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $title = trim($data['title'] ?? '');
    $author = trim($data['author'] ?? '');
    $isbn = trim($data['isbn'] ?? '');
    $category = trim($data['category'] ?? '');
    $description = trim($data['description'] ?? '');
    $image_path = trim($data['image_path'] ?? '');
    $total_copies = max(1, (int)($data['total_copies'] ?? 1));
    $available_copies = ($data['available_copies'] === '' || !isset($data['available_copies'])) ? $total_copies : max(0, (int)$data['available_copies']);
    $stmt->bind_param('ssssssii', $title, $author, $isbn, $category, $description, $image_path, $total_copies, $available_copies);
    return $stmt->execute();
}
function update_book(mysqli $conn, int $id, array $data): bool {
    $sql = "UPDATE books SET title=?, author=?, isbn=?, category=?, description=?, image_path=?, total_copies=?, available_copies=? WHERE id=? AND deleted_at IS NULL";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $title = trim($data['title'] ?? '');
    $author = trim($data['author'] ?? '');
    $isbn = trim($data['isbn'] ?? '');
    $category = trim($data['category'] ?? '');
    $description = trim($data['description'] ?? '');
    $image_path = trim($data['image_path'] ?? '');
    $total_copies = max(1, (int)($data['total_copies'] ?? 1));
    $available_copies = max(0, (int)($data['available_copies'] ?? 0));
    $stmt->bind_param('ssssssiii', $title, $author, $isbn, $category, $description, $image_path, $total_copies, $available_copies, $id);
    return $stmt->execute();
}
function soft_delete_book(mysqli $conn, int $id, string $adminName): bool {
    $sql = "UPDATE books SET deleted_at = NOW(), deleted_by = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('si', $adminName, $id);
    return $stmt->execute();
}

// Fetch filters and data
function categories(mysqli $conn): array {
    $list = [];
    $sql = "SELECT DISTINCT category FROM books WHERE deleted_at IS NULL ORDER BY category ASC";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            if (!empty($r['category'])) $list[] = $r['category'];
        }
    }
    return $list;
}
function fetch_books(mysqli $conn, ?string $q = null, ?string $cat = null): array {
    $sql = "SELECT id, title, author, isbn, category, total_copies, available_copies, image_path FROM books WHERE deleted_at IS NULL";
    $types = '';
    $params = [];
    if ($q) {
        $like = '%'.$q.'%';
        $sql .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
        $types .= 'sss';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($cat) {
        $sql .= " AND category = ?";
        $types .= 's';
        $params[] = $cat;
    }
    $sql .= ' ORDER BY title ASC';

    if ($types) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }
    if (!$res) return [];
    return $res->fetch_all(MYSQLI_ASSOC);
}

// Handle actions
$success = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        if (add_book($conn, $_POST)) $success = 'Book added successfully.'; else $error = 'Failed to add book.';
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && update_book($conn, $id, $_POST)) $success = 'Book updated successfully.'; else $error = 'Failed to update book.';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $adminName = $_SESSION['username'] ?? 'admin';
        if ($id > 0 && soft_delete_book($conn, $id, $adminName)) $success = 'Book deleted (soft) successfully.'; else $error = 'Failed to delete book.';
    }
}

// Filters
$q = isset($_GET['q']) ? trim($_GET['q']) : null;
$cat = isset($_GET['cat']) && $_GET['cat'] !== '' ? trim($_GET['cat']) : null;
$cats = categories($conn);
$books = fetch_books($conn, $q, $cat);

// Build a quick map of overdue books by book_id
$overdueMap = [];
if ($res = $conn->query("SELECT book_id, COUNT(*) c FROM borrowings WHERE status='overdue' GROUP BY book_id")) {
    while ($r = $res->fetch_assoc()) { $overdueMap[(int)$r['book_id']] = (int)$r['c']; }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Book Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet" />
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
        if (!current) current = 'book-management.php';
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
    <main class="flex h-screen" x-data="{ isEditModalOpen: false, isDeleteModalOpen: false, isAddModalOpen: false, deleteBookTitle: '' }" @keydown.escape.window="isEditModalOpen=false; isDeleteModalOpen=false; isAddModalOpen=false">
      <div id="backdrop" class="fixed inset-0 bg-black/40 z-40 hidden md:hidden"></div>
      <aside id="sidebar"
        class="fixed inset-y-0 left-0 z-50 w-64 transform -translate-x-full md:translate-x-0 md:static md:flex bg-slate-50 dark:bg-slate-800 flex flex-col border-r border-slate-200 dark:border-slate-700 transition-transform duration-200"
      >
        <div class="h-16 flex items-center px-6 border-b border-slate-200 dark:border-slate-700">
          <span class="material-icons text-primary mr-2">school</span>
          <span class="font-bold text-lg text-slate-800 dark:text-slate-100">Library System</span>
          <button id="menu-close" class="md:hidden p-2 text-slate-500 dark:text-slate-300 hover:text-slate-700 dark:hover:text-slate-200 ml-auto">
            <span class="material-icons">close</span>
          </button>
        </div>
        <nav class="flex-1 p-4 space-y-2">
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="admin.php">
            <span class="material-icons mr-3">dashboard</span>
            Dashboard
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium bg-primary text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="book-management.php">
            <span class="material-icons mr-3">menu_book</span>
            Book Management
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="user-management.php">
            <span class="material-icons mr-3">group</span>
            User Management
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="borrow.php">
            <span class="material-icons mr-3">history</span>
            Borrowing History
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="Overdue-alerts.php">
              <span class="material-icons mr-3">warning</span>
              Overdue Alerts
            </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="Global-logs.html">
            <span class="material-icons mr-3">analytics</span>
            Global Logs
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="backup-restore.php">
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
          <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Admin Dashboard</h1>
          <div class="flex items-center gap-4">
            <div class="text-right">
              <p class="font-medium text-sm text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></p>
              <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
            </div>
            
          </div>
        </header>
        <div class="flex-1 p-8 overflow-y-auto">
          <div class="bg-white dark:bg-gray-900 rounded-lg p-6">
            <div class="flex justify-between items-center mb-6">
              <div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Admin Book Management</h3>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Manage books and catalog.</p>
              </div>
              <div class="flex items-center gap-4">
                <form method="POST" class="hidden"></form>
                <button class="flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" onclick="alert('Feature not implemented: Bulk Import');">
                  <span class="material-icons-outlined">upload_file</span>
                  Bulk Import
                </button>
                <button class="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-md hover:opacity-90 transition-opacity" onclick="document.getElementById('addBookForm').scrollIntoView({behavior:'smooth'});">
                  <span class="material-icons-outlined">add</span>
                  Add New Book
                </button>
              </div>
            </div>

            <?php if ($success): ?>
              <div class="mb-6 p-3 border border-green-300 bg-green-50 text-green-800 rounded"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
              <div class="mb-6 p-3 border border-red-300 bg-red-50 text-red-800 rounded"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="mb-6 p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
              <h4 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Book Database</h4>
              <form method="GET" class="flex items-center gap-4">
                <div class="relative flex-grow">
                  <span class="material-icons-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                  <input name="q" value="<?php echo htmlspecialchars($q ?? ''); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-transparent focus:ring-primary focus:border-primary transition-all hover:border-gray-400 dark:hover:border-gray-500" placeholder="Search by title, author, or ISBN..." type="text" />
                </div>
                <select name="cat" class="w-48 border border-gray-300 dark:border-gray-600 rounded-md bg-transparent focus:ring-primary focus:border-primary transition-all hover:border-gray-400 dark:hover:border-gray-500">
                  <option value="">All Categories</option>
                  <?php foreach ($cats as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($cat === $c ? 'selected' : ''); ?>><?php echo htmlspecialchars($c); ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">Apply</button>
              </form>
            </div>
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">All active books in the catalog</p>
              <div class="overflow-x-auto">
                <table class="w-full text-left">
                  <thead>
                    <tr class="border-b border-black-200 dark:border-black-700 text-sm text-black-500 dark:text-black-400">
                      <th class="py-3 px-4 font-medium">Book Details</th>
                      <th class="py-3 px-4 font-medium">Category</th>
                      <th class="py-2 px-9 font-medium">Location</th>
                      <th class="py-3 px-1 font-medium">Availability</th>
                      <th class="py-3 px-8 font-medium">Status</th>
                      <th class="py-3 px-4 font-medium">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($books)): ?>
                      <tr><td colspan="6" class="py-6 px-4 text-center text-gray-500">No books found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($books as $b): ?>
                      <?php
                        $status = 'available';
                        if (($overdueMap[$b['id']] ?? 0) > 0) $status = 'overdue';
                        elseif ((int)$b['available_copies'] <= 0) $status = 'borrowed';
                      ?>
                      <tr class="border-b border-gray-200 dark:border-gray-700" data-status="<?php echo $status; ?>" data-category="<?php echo htmlspecialchars($b['category'] ?? ''); ?>">
                        <td class="py-4 px-4">
                          <p class="font-medium text-text-light dark:text-text-dark"><?php echo htmlspecialchars($b['title']); ?></p>
                          <p class="text-xs text-subtext-light dark:text-subtext-dark">by <?php echo htmlspecialchars($b['author']); ?></p>
                          <p class="text-xs text-subtext-light dark:text-subtext-dark">ISBN: <?php echo htmlspecialchars($b['isbn']); ?></p>
                        </td>
                        <td class="py-4 px-2"><?php echo htmlspecialchars($b['category']); ?></td>
                        <td class="py-4 px-2">N/A</td>
                        <td class="py-1 px-7"><?php echo (int)$b['available_copies']; ?> / <?php echo (int)$b['total_copies']; ?></td>
                        <td class="py-4 px-4">
                          <?php if ($status === 'available'): ?>
                            <span class="bg-green-600 text-white px-3 py-1 rounded-full text-xs font-semibold">available</span>
                          <?php elseif ($status === 'borrowed'): ?>
                            <span class="bg-red-600 text-white px-3 py-1 rounded-full text-xs font-semibold">borrowed</span>
                          <?php else: ?>
                            <span class="bg-red-700 text-white px-3 py-1 rounded-full text-xs font-semibold">overdue</span>
                          <?php endif; ?>
                        </td>
                        <td class="py-4 px-4">
                          <div class="flex items-center gap-2">
                            <button type="button"
                              class="flex items-center px-1 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium bg-surface-light dark:bg-surface-dark hover:bg-gray-50 dark:hover:bg-gray-700"
                              onclick="openEditFromButton(this)"
                              data-id="<?php echo (int)$b['id']; ?>"
                              data-title="<?php echo htmlspecialchars($b['title'], ENT_QUOTES); ?>"
                              data-author="<?php echo htmlspecialchars($b['author'], ENT_QUOTES); ?>"
                              data-isbn="<?php echo htmlspecialchars($b['isbn'], ENT_QUOTES); ?>"
                              data-category="<?php echo htmlspecialchars($b['category'], ENT_QUOTES); ?>"
                              data-image_path="<?php echo htmlspecialchars($b['image_path'], ENT_QUOTES); ?>"
                              data-total_copies="<?php echo (int)$b['total_copies']; ?>"
                              data-available_copies="<?php echo (int)$b['available_copies']; ?>">
                                <span class="material-icons-outlined text-base">edit</span>
                            </button>
                            <form method="POST" onsubmit="return confirm('Soft-delete this book?')">
                              <input type="hidden" name="action" value="delete" />
                              <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>" />
                              <button class="flex items-center px-1 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700">
                                <span class="material-icons-outlined text-base">delete</span>
                              </button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Add Book Form -->
            <div class="mt-10" id="addBookForm">
              <h4 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Add New Book</h4>
              <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add" />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title <span class="text-red-500">*</span></label>
                    <input name="title" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Author <span class="text-red-500">*</span></label>
                    <input name="author" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ISBN</label>
                    <input name="isbn" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                    <input name="category" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                  </div>
                  <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Image Path</label>
                    <input name="image_path" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                  </div>
                  <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea name="description" rows="3" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary"></textarea>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Total Copies</label>
                    <input type="number" min="1" name="total_copies" value="1" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Available Copies</label>
                    <input type="number" min="0" name="available_copies" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                  </div>
                </div>
                <div class="flex justify-end gap-4 pt-2">
                  <button class="px-6 py-2 bg-primary text-white rounded-md hover:opacity-90 transition-opacity" type="submit">Add Book</button>
                </div>
              </form>
            </div>

          </div>

        <!-- Edit Modal -->
        <div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-xl p-6 m-4 max-h-[85vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
              <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Book</h2>
                <p class="text-gray-500 dark:text-gray-400">Update book information.</p>
              </div>
              <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                <span class="material-icons-outlined">close</span>
              </button>
            </div>
            <form method="POST" class="space-y-4">
              <input type="hidden" name="action" value="update" />
              <input type="hidden" name="id" id="edit_id" />
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                  <input id="edit_title" name="title" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Author</label>
                  <input id="edit_author" name="author" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ISBN</label>
                  <input id="edit_isbn" name="isbn" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                  <input id="edit_category" name="category" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                </div>
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Image Path</label>
                  <input id="edit_image_path" name="image_path" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Total Copies</label>
                  <input id="edit_total_copies" type="number" min="1" name="total_copies" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Available Copies</label>
                  <input id="edit_available_copies" type="number" min="0" name="available_copies" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 bg-transparent focus:outline-none focus:ring-primary focus:border-primary" />
                </div>
              </div>
              <div class="flex justify-end gap-4 pt-4">
                <button type="button" onclick="closeEditModal()" class="px-6 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-md hover:opacity-90 transition-opacity">Update Book</button>
              </div>
            </form>
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
      </div>
    </main>
    <script>
      function openEditFromButton(btn) {
        var d = btn.dataset;
        document.getElementById('edit_id').value = d.id || '';
        document.getElementById('edit_title').value = d.title || '';
        document.getElementById('edit_author').value = d.author || '';
        document.getElementById('edit_isbn').value = d.isbn || '';
        document.getElementById('edit_category').value = d.category || '';
        document.getElementById('edit_image_path').value = d.image_path || '';
        document.getElementById('edit_total_copies').value = d.total_copies || 1;
        document.getElementById('edit_available_copies').value = d.available_copies || 0;
        document.getElementById('editModal').classList.remove('hidden');
      }
      function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
      }
      document.addEventListener('keydown', function(ev){ if (ev.key === 'Escape') closeEditModal(); });
    </script>
    <script defer src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js"></script>
  </body>
</html>
