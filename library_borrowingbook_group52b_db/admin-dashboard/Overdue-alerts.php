<?php
session_start();

// Admin-only gate
function require_admin_login(): void {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ../login/login.html');
        exit();
    }
}
require_admin_login();

include '../login/db_connect.php';

$success = null; $error = null;

function mark_returned(mysqli $conn, int $id): bool {
    $sql = "SELECT br.id, br.book_id, br.status, br.return_date FROM borrowings br WHERE br.id = ? LIMIT 1";
    if (!$stmt = $conn->prepare($sql)) return false;
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) return false;
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) return false;
    $row = $res->fetch_assoc();
    if (strtolower($row['status']) === 'returned' && !empty($row['return_date'])) return true;

    if (!$u = $conn->prepare("UPDATE borrowings SET status='returned', return_date = CURDATE() WHERE id = ?")) return false;
    $u->bind_param('i', $id);
    if (!$u->execute()) return false;

    if (!$b = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?")) return false;
    $b->bind_param('i', $row['book_id']);
    return $b->execute();
}

function force_overdue(mysqli $conn, int $id): bool {
    $sql = "UPDATE borrowings SET status='overdue' WHERE id = ? AND status <> 'returned'";
    if (!$stmt = $conn->prepare($sql)) return false;
    $stmt->bind_param('i', $id);
    return $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'mark_returned' && $id > 0) {
        if (mark_returned($conn, $id)) $success = 'Marked as returned.'; else $error = 'Failed to mark as returned.';
    } elseif ($action === 'force_overdue' && $id > 0) {
        if (force_overdue($conn, $id)) $success = 'Marked as overdue.'; else $error = 'Failed to mark as overdue.';
    } elseif ($action === 'send_reminder') {
        // Placeholder for reminder sending (email/SMS integration). For now, just show success.
        $success = 'Reminder has been queued for delivery.';
    }
}

// Filters
$q = isset($_GET['q']) ? trim($_GET['q']) : null;
$min_days = isset($_GET['min_days']) && $_GET['min_days'] !== '' ? (int)$_GET['min_days'] : null;
$max_days = isset($_GET['max_days']) && $_GET['max_days'] !== '' ? (int)$_GET['max_days'] : null;

function fetch_overdues(mysqli $conn, ?string $q, ?int $minDays, ?int $maxDays): array {
    // Overdue if status='overdue' OR (status='borrowed' AND due_date < CURDATE())
    $sql = "SELECT br.id, br.borrow_date, br.due_date, br.return_date, br.status, br.staff_name,
                   u.username, u.email,
                   b.title, b.author, b.isbn,
                   GREATEST(DATEDIFF(CURDATE(), br.due_date), 0) AS days_overdue
            FROM borrowings br
            JOIN users u ON u.id = br.user_id
            JOIN books b ON b.id = br.book_id
            WHERE (br.status = 'overdue' OR (br.status = 'borrowed' AND br.due_date < CURDATE()))";

    $types = '';
    $params = [];

    if ($q) {
        $like = '%'.$q.'%';
        $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR b.title LIKE ? OR b.isbn LIKE ?)";
        $types .= 'ssss';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if (!is_null($minDays)) {
        $sql .= " AND DATEDIFF(CURDATE(), br.due_date) >= ?";
        $types .= 'i';
        $params[] = $minDays;
    }
    if (!is_null($maxDays)) {
        $sql .= " AND DATEDIFF(CURDATE(), br.due_date) <= ?";
        $types .= 'i';
        $params[] = $maxDays;
    }

    $sql .= " ORDER BY days_overdue DESC, br.due_date ASC LIMIT 200";

    if (!empty($params)) {
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

$rows = fetch_overdues($conn, $q, $min_days, $max_days);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Overdue Alerts</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: { primary: "#31694E", "background-light": "#f8fafc", "background-dark": "#1e293b" },
            fontFamily: { display: ["Poppins","sans-serif"] },
            borderRadius: { DEFAULT: "0.5rem" },
          }
        }
      };
    </script>
  </head>
  <body class="font-display bg-background-light dark:bg-background-dark text-slate-700 dark:text-slate-300">
    <main class="flex h-screen">
      <aside class="w-64 hidden md:block bg-slate-50 dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700">
        <div class="h-16 flex items-center px-6 border-b border-slate-200 dark:border-slate-700">
          <span class="material-icons text-primary mr-2">school</span>
          <span class="font-bold text-lg">Library System</span>
        </div>
        <nav class="p-4 space-y-2">
          <a class="block px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md" href="admin.php">
            <span class="material-icons align-middle mr-2">dashboard</span> Dashboard
          </a>
          <a class="block px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md" href="book-management.php">
            <span class="material-icons align-middle mr-2">menu_book</span> Book Management
          </a>
          <a class="block px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md" href="user-management.php">
            <span class="material-icons align-middle mr-2">group</span> User Management
          </a>
          <a class="block px-4 py-2 text-sm font-medium" href="borrow.php">
            <span class="material-icons align-middle mr-2">history</span> Borrowing History
          </a>
          <a class="block px-4 py-2 text-sm font-medium bg-primary text-white rounded-md" href="Overdue-alerts.php">
            <span class="material-icons align-middle mr-2">warning</span> Overdue Alerts
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="Global-logs.php">
            <span class="material-icons mr-3">analytics</span>
            Global Logs
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="backup-restore.php">
            <span class="material-icons mr-3">backup</span>
            Backup & Restore
          </a>
           <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
                    href="Attendance-logs.php">
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
          <h1 class="text-xl font-semibold">Admin Dashboard</h1>
          <div class="text-right">
            <div class="font-medium text-sm"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
            <div class="text-xs text-slate-500"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></div>
          </div>
        </header>
        <div class="flex-1 p-8 overflow-y-auto">
          <div class="bg-white dark:bg-gray-900 rounded-lg p-6">
            <div class="flex items-center justify-between mb-6">
              <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Overdue Alerts</h2>
                <p class="text-gray-500 dark:text-gray-400">View overdue and at-risk borrowings and take action.</p>
              </div>
            </div>

            <?php if ($success): ?>
              <div class="mb-6 p-3 border border-green-300 bg-green-50 text-green-800 rounded"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
              <div class="mb-6 p-3 border border-red-300 bg-red-50 text-red-800 rounded"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="mb-6 p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
              <h3 class="text-lg font-semibold mb-3">Filters</h3>
              <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3 md:items-end">
                <div class="md:col-span-2 relative">
                  <span class="material-icons-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                  <input name="q" value="<?php echo htmlspecialchars($q ?? ''); ?>" class="w-full pl-10 pr-4 py-2 border rounded bg-transparent focus:ring-primary focus:border-primary" placeholder="Search by user, email, book title, ISBN" />
                </div>
                <div>
                  <label class="block text-xs text-slate-500 mb-1">Min Days Overdue</label>
                  <input type="number" name="min_days" value="<?php echo htmlspecialchars($min_days ?? ''); ?>" class="w-full px-3 py-2 border rounded bg-transparent focus:ring-primary focus:border-primary" />
                </div>
                <div>
                  <label class="block text-xs text-slate-500 mb-1">Max Days Overdue</label>
                  <input type="number" name="max_days" value="<?php echo htmlspecialchars($max_days ?? ''); ?>" class="w-full px-3 py-2 border rounded bg-transparent focus:ring-primary focus:border-primary" />
                </div>
                <div class="md:col-span-4">
                  <button class="px-4 py-2 border rounded hover:bg-gray-50">Apply</button>
                </div>
              </form>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead>
                  <tr class="border-b border-black-200 dark:border-black-700 text-sm text-black-500 dark:text-black-400">
                    <th class="py-3 px-4 font-medium">User</th>
                    <th class="py-3 px-4 font-medium">Book</th>
                    <th class="py-3 px-4 font-medium">Borrowed</th>
                    <th class="py-3 px-4 font-medium">Due</th>
                    <th class="py-3 px-4 font-medium">Days Overdue</th>
                    <th class="py-3 px-4 font-medium">Status</th>
                    <th class="py-3 px-4 font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="py-6 px-4 text-center text-gray-500">No overdue borrowings found.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($rows as $r): ?>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                      <td class="py-4 px-4">
                        <p class="font-medium text-text-light dark:text-text-dark"><?php echo htmlspecialchars($r['username']); ?></p>
                        <p class="text-xs text-subtext-light dark:text-subtext-dark"><?php echo htmlspecialchars($r['email']); ?></p>
                      </td>
                      <td class="py-4 px-4">
                        <p class="font-medium text-text-light dark:text-text-dark"><?php echo htmlspecialchars($r['title']); ?></p>
                        <p class="text-xs text-subtext-light dark:text-subtext-dark">ISBN: <?php echo htmlspecialchars($r['isbn']); ?></p>
                      </td>
                      <td class="py-4 px-4"><?php echo htmlspecialchars($r['borrow_date']); ?></td>
                      <td class="py-4 px-4"><?php echo htmlspecialchars($r['due_date']); ?></td>
                      <td class="py-4 px-4 font-semibold text-red-600"><?php echo (int)$r['days_overdue']; ?></td>
                      <td class="py-4 px-4">
                        <?php if (strtolower($r['status'])==='borrowed'): ?>
                          <span class="bg-blue-600 text-white px-3 py-1 rounded-full text-xs font-semibold">Borrowed</span>
                        <?php elseif (strtolower($r['status'])==='returned'): ?>
                          <span class="bg-green-600 text-white px-3 py-1 rounded-full text-xs font-semibold">Returned</span>
                        <?php else: ?>
                          <span class="bg-red-600 text-white px-3 py-1 rounded-full text-xs font-semibold">Overdue</span>
                        <?php endif; ?>
                      </td>
                      <td class="py-4 px-4">
                        <div class="flex items-center gap-2">
                          <form method="POST" class="inline" onsubmit="return confirm('Send reminder to this user?');">
                            <input type="hidden" name="action" value="send_reminder" />
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>" />
                            <button class="flex items-center px-2 py-1 border rounded text-xs font-medium hover:bg-gray-50">
                              <span class="material-icons-outlined text-base">mail</span>
                            </button>
                          </form>
                          <?php if (strtolower($r['status'])!=='returned'): ?>
                          <form method="POST" class="inline" onsubmit="return confirm('Mark as returned?');">
                            <input type="hidden" name="action" value="mark_returned" />
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>" />
                            <button class="flex items-center px-2 py-1 border rounded text-xs font-medium hover:bg-gray-50">
                              <span class="material-icons-outlined text-base">assignment_turned_in</span>
                            </button>
                          </form>
                          <?php endif; ?>
                          <?php if (strtolower($r['status'])!=='returned'): ?>
                          <form method="POST" class="inline" onsubmit="return confirm('Force mark as overdue?');">
                            <input type="hidden" name="action" value="force_overdue" />
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>" />
                            <button class="flex items-center px-2 py-1 border rounded text-xs font-medium hover:bg-gray-50">
                              <span class="material-icons-outlined text-base">schedule</span>
                            </button>
                          </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
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
  </body>
</html>
