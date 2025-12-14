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

// Filters
$q = isset($_GET['q']) ? trim($_GET['q']) : null;
$type = isset($_GET['type']) && $_GET['type'] !== '' ? strtolower($_GET['type']) : null; // attendance|borrowed|returned|overdue
$date_from = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null; // YYYY-MM-DD
$date_to = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;

// Fetch logs function (unified across attendance and borrowings)
function fetch_logs(mysqli $conn, ?string $q, ?string $type, ?string $df, ?string $dt): array {
    // Build unified log set using UNION ALL
    $union = "(
        SELECT CONCAT(a.check_in_date,' ',a.check_in_time) AS event_time,
               'attendance' AS event_type,
               u.username, u.email,
               NULL AS book_title, NULL AS isbn,
               a.status AS details, NULL AS staff_name
        FROM attendance a
        JOIN users u ON u.id = a.user_id
      )
      UNION ALL
      (
        SELECT CONCAT(br.borrow_date,' 00:00:00') AS event_time,
               'borrowed' AS event_type,
               u.username, u.email,
               b.title AS book_title, b.isbn AS isbn,
               br.status AS details, br.staff_name
        FROM borrowings br
        JOIN users u ON u.id = br.user_id
        JOIN books b ON b.id = br.book_id
      )
      UNION ALL
      (
        SELECT CONCAT(br.return_date,' 00:00:00') AS event_time,
               'returned' AS event_type,
               u.username, u.email,
               b.title AS book_title, b.isbn AS isbn,
               br.status AS details, br.staff_name
        FROM borrowings br
        JOIN users u ON u.id = br.user_id
        JOIN books b ON b.id = br.book_id
        WHERE br.return_date IS NOT NULL
      )
      UNION ALL
      (
        SELECT CONCAT(br.due_date,' 00:00:00') AS event_time,
               'overdue' AS event_type,
               u.username, u.email,
               b.title AS book_title, b.isbn AS isbn,
               br.status AS details, br.staff_name
        FROM borrowings br
        JOIN users u ON u.id = br.user_id
        JOIN books b ON b.id = br.book_id
        WHERE br.status = 'overdue'
      )";

    $sql = "SELECT * FROM (".$union.") logs WHERE 1=1";
    $types = '';
    $params = [];

    if ($q) {
        $like = '%'.$q.'%';
        $sql .= " AND (logs.username LIKE ? OR logs.email LIKE ? OR logs.book_title LIKE ? OR logs.isbn LIKE ?)";
        $types .= 'ssss';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($type) {
        $sql .= " AND logs.event_type = ?";
        $types .= 's';
        $params[] = $type;
    }
    if ($df) {
        $sql .= " AND DATE(logs.event_time) >= ?";
        $types .= 's';
        $params[] = $df;
    }
    if ($dt) {
        $sql .= " AND DATE(logs.event_time) <= ?";
        $types .= 's';
        $params[] = $dt;
    }

    $sql .= " ORDER BY logs.event_time DESC LIMIT 300";

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

$rows = fetch_logs($conn, $q, $type, $date_from, $date_to);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Global Logs</title>
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
            fontFamily: { display: ["Poppins", "sans-serif"] },
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
          <a class="block px-4 py-2 text-sm font-medium" href="Overdue-alerts.php">
            <span class="material-icons align-middle mr-2">warning</span> Overdue Alerts
          </a>
          <a class="block px-4 py-2 text-sm font-medium bg-primary text-white rounded-md" href="Global-logs.php">
            <span class="material-icons align-middle mr-2">analytics</span> Global Logs
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
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Global Logs</h2>
                <p class="text-gray-500 dark:text-gray-400">Unified activity logs across attendance and borrowings.</p>
              </div>
            </div>

            <div class="mb-6 p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
              <h3 class="text-lg font-semibold mb-3">Filters</h3>
              <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3 md:items-end">
                <div class="md:col-span-2 relative">
                  <span class="material-icons-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                  <input name="q" value="<?php echo htmlspecialchars($q ?? ''); ?>" class="w-full pl-10 pr-4 py-2 border rounded bg-transparent focus:ring-primary focus:border-primary" placeholder="Search by user, email, book title, ISBN" />
                </div>
                <div>
                  <label class="block text-xs text-slate-500 mb-1">Type</label>
                  <select name="type" class="w-full px-3 py-2 border rounded bg-transparent focus:ring-primary focus:border-primary">
                    <option value="">All</option>
                    <option value="attendance" <?php echo $type==='attendance'?'selected':''; ?>>Attendance</option>
                    <option value="borrowed" <?php echo $type==='borrowed'?'selected':''; ?>>Borrowed</option>
                    <option value="returned" <?php echo $type==='returned'?'selected':''; ?>>Returned</option>
                    <option value="overdue" <?php echo $type==='overdue'?'selected':''; ?>>Overdue</option>
                  </select>
                </div>
                <div class="md:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs text-slate-500 mb-1">Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from ?? ''); ?>" class="w-full px-3 py-2 border rounded bg-transparent focus:ring-primary focus:border-primary" />
                  </div>
                  <div>
                    <label class="block text-xs text-slate-500 mb-1">Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to ?? ''); ?>" class="w-full px-3 py-2 border rounded bg-transparent focus:ring-primary focus:border-primary" />
                  </div>
                  <div class="flex items-end">
                    <button class="w-full px-4 py-2 border rounded hover:bg-gray-50">Apply</button>
                  </div>
                </div>
              </form>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead>
                  <tr class="border-b border-black-200 dark:border-black-700 text-sm text-black-500 dark:text-black-400">
                    <th class="py-3 px-4 font-medium">Time</th>
                    <th class="py-3 px-4 font-medium">Type</th>
                    <th class="py-3 px-4 font-medium">User</th>
                    <th class="py-3 px-4 font-medium">Book</th>
                    <th class="py-3 px-4 font-medium">Details</th>
                    <th class="py-3 px-4 font-medium">Staff</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="py-6 px-4 text-center text-gray-500">No logs found.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($rows as $r): ?>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                      <td class="py-4 px-4 whitespace-nowrap"><?php echo htmlspecialchars($r['event_time']); ?></td>
                      <td class="py-4 px-4 capitalize"><?php echo htmlspecialchars($r['event_type']); ?></td>
                      <td class="py-4 px-4">
                        <p class="font-medium text-text-light dark:text-text-dark"><?php echo htmlspecialchars($r['username']); ?></p>
                        <p class="text-xs text-subtext-light dark:text-subtext-dark"><?php echo htmlspecialchars($r['email']); ?></p>
                      </td>
                      <td class="py-4 px-4">
                        <?php if (!empty($r['book_title'])): ?>
                          <p class="font-medium text-text-light dark:text-text-dark"><?php echo htmlspecialchars($r['book_title']); ?></p>
                          <p class="text-xs text-subtext-light dark:text-subtext-dark">ISBN: <?php echo htmlspecialchars($r['isbn']); ?></p>
                        <?php else: ?>
                          <span class="text-slate-400">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="py-4 px-4"><?php echo htmlspecialchars($r['details'] ?? ''); ?></td>
                      <td class="py-4 px-4"><?php echo htmlspecialchars($r['staff_name'] ?? ''); ?></td>
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
