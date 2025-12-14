<?php
session_start();
include '../login/db_connect.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login/login.html");
    exit();
}

// Ensure soft-delete columns exist on books table to avoid runtime errors
// This is idempotent and only runs if columns are missing.
function books_column_exists(mysqli $conn, string $column): bool {
    $sql = "SELECT COUNT(*) AS cnt\n            FROM INFORMATION_SCHEMA.COLUMNS\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = 'books'\n              AND COLUMN_NAME = ?";
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
        // Add deleted_at column
        $conn->query("ALTER TABLE books ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
    }
    if (!books_column_exists($conn, 'deleted_by')) {
        // Add deleted_by column
        $conn->query("ALTER TABLE books ADD COLUMN deleted_by VARCHAR(255) NULL DEFAULT NULL");
    }
}
ensure_books_soft_delete_columns($conn);

// Function to restore a book
function restore_book($book_id, $conn) {
    $sql = "UPDATE books SET deleted_at = NULL, deleted_by = NULL WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    return $stmt->execute();
}

// Function to restore a user
function restore_user($user_id, $conn) {
    $sql = "UPDATE users SET status = 'active' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// Function to permanently delete a book
function delete_book_permanent($book_id, $conn) {
    $sql = "DELETE FROM books WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    return $stmt->execute();
}

// Function to permanently delete a user
function delete_user_permanent($user_id, $conn) {
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'restore_book') {
            $book_id = (int)$_POST['book_id'];
            if (restore_book($book_id, $conn)) {
                $success = "Book restored successfully.";
            } else {
                $error = "Error restoring book: " . $conn->error;
            }
        } elseif ($action === 'restore_user') {
            $user_id = (int)$_POST['user_id'];
            if (restore_user($user_id, $conn)) {
                $success = "User restored successfully.";
            } else {
                $error = "Error restoring user: " . $conn->error;
            }
        } elseif ($action === 'delete_book_permanent') {
            $book_id = (int)$_POST['book_id'];
            if (delete_book_permanent($book_id, $conn)) {
                $success = "Book permanently deleted.";
            } else {
                $error = "Error deleting book: " . $conn->error;
            }
        } elseif ($action === 'delete_user_permanent') {
            $user_id = (int)$_POST['user_id'];
            if (delete_user_permanent($user_id, $conn)) {
                $success = "User permanently deleted.";
            } else {
                $error = "Error deleting user: " . $conn->error;
            }
        }
    }
}

// Fetch deleted books
$deleted_books_sql = "SELECT id, title, author, isbn, category, total_copies, available_copies, deleted_at, deleted_by FROM books WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC";
$deleted_books_result = $conn->query($deleted_books_sql);
$deleted_books = $deleted_books_result->fetch_all(MYSQLI_ASSOC);

// Fetch deleted users
$deleted_users_sql = "SELECT id, username, email, role, status, last_active FROM users WHERE status = 'disabled' ORDER BY last_active DESC";
$deleted_users_result = $conn->query($deleted_users_sql);
$deleted_users = $deleted_users_result->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Backup & Restore - Library System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
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
    <style>
        .material-symbols-outlined {
            font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
            font-size: 20px;
        }
        .tab-indicator {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 2px;
            width: 50%;
            background-color: #16a34a;
            transition: transform 0.3s ease-in-out;
        }
    </style>
</head>
<body class="font-display bg-background-light dark:bg-background-dark text-slate-700 dark:text-slate-300"
    x-data="{ activeTab: 'books', showRestoreModal: false, restoreUserName: '', showRestoreBookModal: false, restoreBookName: '', showDeleteBookModal: false, deleteBookName: '', showDeleteUserModal: false, deleteUserName: '' }">
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
                    <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="librarian-dashboard.php">
                        <span class="material-icons mr-3">dashboard</span>
                        Dashboard
                    </a>
                    <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="Book_Management.php">
                        <span class="material-icons mr-3">menu_book</span>
                        Book Management
                    </a>
                    <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="user-management.php">
                        <span class="material-icons mr-3">people</span>
                        User Management
                    </a>
                    <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="borrow.php">
                        <span class="material-icons mr-3">history</span>
                        Borrowing History
                    </a>
                    <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="overdue-alerts.php">
                        <span class="material-icons mr-3">warning</span>
                        Overdue Alerts
                    </a>
                    <a class="flex items-center px-4 py-2 text-sm font-medium bg-primary text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="backup_restore.php">
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
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Librarian Dashboard</h1>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="font-medium text-sm text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                    </div>
                </div>
            </header>
            <div class="flex-1 overflow-y-auto p-8">
                <div class="max-w-7xl mx-auto">
                    <h3 class="text-2xl font-bold text-stone-800 dark:text-stone-200">Backup &amp; Restore</h3>
                    <p class="mt-1 text-muted-light dark:text-muted-dark">Restore deleted users and books back to the active system.</p>
                    <?php if (isset($success)): ?>
                        <div class="mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <div class="mt-6">
                        <div class="relative border-b border-stone-200 dark:border-stone-700">
                            <div class="flex">
                                <button :class="{ 'text-primary font-semibold': activeTab === 'users', 'text-muted-light dark:text-muted-dark': activeTab !== 'users' }" @click="activeTab = 'users'" class="flex-1 py-3 text-center text-sm transition-colors duration-300 flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined !text-base">people</span>
                                    Deleted Users (<?php echo count($deleted_users); ?>)
                                </button>
                                <button :class="{ 'text-primary font-semibold': activeTab === 'books', 'text-muted-light dark:text-muted-dark': activeTab !== 'books' }" @click="activeTab = 'books'" class="flex-1 py-3 text-center text-sm transition-colors duration-300 flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined !text-base">auto_stories</span>
                                    Deleted Books (<?php echo count($deleted_books); ?>)
                                </button>
                            </div>
                            <div :style="{ transform: activeTab === 'users' ? 'translateX(0%)' : 'translateX(100%)' }" class="tab-indicator"></div>
                        </div>
                    </div>
                    <div class="mt-6 p-4 bg-surface-light dark:bg-surface-dark rounded-lg border border-border-light dark:border-border-dark flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-muted-light dark:text-muted-dark">filter_alt</span>
                            <span class="font-medium text-sm">Search &amp; Filter</span>
                        </div>
                        <div class="flex-1 relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted-light dark:text-muted-dark">search</span>
                            <input :placeholder="activeTab === 'books' ? 'Search by title, author, or ISBN...' : 'Search by name or email...'" class="w-full pl-10 pr-4 py-2 text-sm bg-background-light dark:bg-background-dark border border-stone-200 dark:border-stone-700 rounded-md focus:ring-primary focus:border-primary" type="text" />
                        </div>
                        <div>
                            <select class="text-sm bg-background-light dark:bg-background-dark border border-stone-200 dark:border-stone-700 rounded-md focus:ring-primary focus:border-primary">
                                <option x-show="activeTab === 'books'" selected>All Categories</option>
                                <option x-show="activeTab === 'books'">Programming</option>
                                <option x-show="activeTab === 'books'">Database</option>
                                <option x-show="activeTab === 'users'" selected>All Roles</option>
                                <option x-show="activeTab === 'users'">Student</option>
                                <option x-show="activeTab === 'users'">Librarian</option>
                                <option x-show="activeTab === 'users'">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 bg-surface-light dark:bg-surface-dark rounded-lg border border-border-light dark:border-border-dark p-6">
                        <div x-show="activeTab === 'books'">
                            <h4 class="text-lg font-semibold text-stone-800 dark:text-stone-200">Deleted Books (<?php echo count($deleted_books); ?>)</h4>
                            <p class="mt-1 text-sm text-muted-light dark:text-muted-dark">Books that have been deleted can be restored back to the active catalog.</p>
                            <div class="mt-4 -mx-6">
                                <table class="min-w-full divide-y divide-border-light dark:divide-border-dark">
                                    <thead class="bg-stone-50 dark:bg-stone-800/50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">Book Details</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">Category</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">Status</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">Copies</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">Deleted Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">Deleted By</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-surface-light dark:bg-surface-dark divide-y divide-border-light dark:divide-border-dark">
                                        <?php foreach ($deleted_books as $book): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-stone-900 dark:text-stone-100"><?php echo htmlspecialchars($book['title']); ?></div>
                                                <div class="text-xs text-muted-light dark:text-muted-dark">by <?php echo htmlspecialchars($book['author']); ?></div>
                                                <div class="text-xs text-muted-light dark:text-muted-dark">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></div>
                                            </td>
                                            <td class="px-2 py-4 whitespace-nowrap text-sm text-stone-600 dark:text-stone-300"><?php echo htmlspecialchars($book['category']); ?></td>
                                            <td class="px-3 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800"><?php echo $book['available_copies'] > 0 ? 'available' : 'borrowed'; ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-stone-600 dark:text-stone-300"><?php echo htmlspecialchars($book['total_copies']); ?></div>
                                                <div class="text-xs text-muted-light dark:text-muted-dark">total</div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-stone-600 dark:text-stone-300"><?php echo htmlspecialchars($book['deleted_at']); ?></td>
                                            <td class="px-5 py-4 whitespace-nowrap text-sm text-stone-600 dark:text-stone-300"><?php echo htmlspecialchars($book['deleted_by']); ?></td>
                                            <td class="px-1 py-3 whitespace-nowrap">
                                                <div class="flex gap-2">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="restore_book">
                                                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                        <button type="submit" class="px-4 py-2 text-sm font-medium border border-stone-300 dark:border-stone-600 rounded-md hover:bg-stone-100 dark:hover:bg-stone-700/50">Restore</button>
                                                    </form>
                                                    <button @click="showDeleteBookModal = true; deleteBookName = '<?php echo addslashes($book['title']); ?>'" onclick="setDeleteBookId(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')" class="flex items-center px-1 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700">
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
                        <div x-cloak x-show="activeTab === 'users'">
                            <h4 class="text-lg font-semibold text-stone-800 dark:text-stone-200">Deleted Users (<?php echo count($deleted_users); ?>)</h4>
                            <p class="mt-1 text-sm text-muted-light dark:text-muted-dark">Users that have been deleted can be restored back to the active system.</p>
                            <div class="mt-4 -mx-6">
                                <table class="min-w-full divide-y divide-border-light dark:divide-border-dark">
                                    <thead class="bg-stone-50 dark:bg-stone-800/50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">User</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">Role</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">Status</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">Deleted Date</th>
                                            <th class="px-8 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">Deleted By</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-light dark:text-muted-dark uppercase tracking-wider" scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-surface-light dark:bg-surface-dark divide-y divide-border-light dark:divide-border-dark">
                                        <?php foreach ($deleted_users as $user): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-stone-900 dark:text-stone-100"><?php echo htmlspecialchars($user['username']); ?></div>
                                                <div class="text-xs text-muted-light dark:text-muted-dark"><?php echo htmlspecialchars($user['email']); ?></div>
                                            </td>
                                            <td class="px-3 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800"><?php echo htmlspecialchars($user['role']); ?></span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800"><?php echo htmlspecialchars($user['status']); ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-stone-600 dark:text-stone-300"><?php echo htmlspecialchars($user['last_active']); ?></td>
                                            <td class="px-7 py-4 whitespace-nowrap text-sm text-stone-600 dark:text-stone-300">N/A</td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="flex gap-2">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="restore_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="px-4 py-2 text-sm font-medium border border-stone-300 dark:border-stone-600 rounded-md hover:bg-stone-100 dark:hover:bg-stone-700/50">Restore</button>
                                                    </form>
                                                    <button @click="showDeleteUserModal = true; deleteUserName = '<?php echo addslashes($user['username']); ?>'" onclick="setDeleteUserId(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')" class="flex items-center px-1 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700">
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

    <!-- Delete Book Modal -->
    <div x-show="showDeleteBookModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-slate-800 rounded-lg p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-stone-800 dark:text-stone-200 mb-4">Permanently Delete Book</h3>
            <p class="text-sm text-stone-600 dark:text-stone-300 mb-4">
                You are about to permanently delete "<span x-text="deleteBookName" class="font-medium"></span>" from the system. This action cannot be undone and will completely remove the book from all records.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="delete_book_permanent">
                <input type="hidden" name="book_id" id="delete-book-id">
                <div class="flex gap-3">
                    <button @click="showDeleteBookModal = false" class="flex-1 px-4 py-2 text-sm font-medium border border-stone-300 dark:border-stone-600 rounded-md hover:bg-stone-50 dark:hover:bg-stone-700/50">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 text-sm font-medium bg-red-600 text-white rounded-md hover:bg-red-700">Confirm Action</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div x-show="showDeleteUserModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-slate-800 rounded-lg p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-stone-800 dark:text-stone-200 mb-4">Permanently Delete User</h3>
            <p class="text-sm text-stone-600 dark:text-stone-300 mb-4">
                You are about to permanently delete <span x-text="deleteUserName" class="font-medium"></span> from the system. This action cannot be undone and will completely remove the user from all records.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="delete_user_permanent">
                <input type="hidden" name="user_id" id="delete-user-id">
                <div class="flex gap-3">
                    <button @click="showDeleteUserModal = false" class="flex-1 px-4 py-2 text-sm font-medium border border-stone-300 dark:border-stone-600 rounded-md hover:bg-stone-50 dark:hover:bg-stone-700/50">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 text-sm font-medium bg-red-600 text-white rounded-md hover:bg-red-700">Confirm Action</button>
                </div>
            </form>
        </div>
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
            var current = location.pathname.split('/').pop() || 'backup_restore.php';
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

        function setDeleteBookId(id, name) {
            document.getElementById('delete-book-id').value = id;
        }

        function setDeleteUserId(id, name) {
            document.getElementById('delete-user-id').value = id;
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
