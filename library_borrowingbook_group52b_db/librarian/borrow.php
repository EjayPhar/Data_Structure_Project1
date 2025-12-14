<?php
session_start();
include '../login/db_connect.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login/login.html");
    exit();
}

// Function to get borrowing history for the user
function getBorrowingHistory($conn, $user_id) {
    $sql = "SELECT b.id, bk.title, bk.author, b.borrow_date, b.due_date, b.return_date, b.status, b.staff_name
            FROM borrowings b
            JOIN books bk ON b.book_id = bk.id
            WHERE b.user_id = ?
            ORDER BY b.borrow_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $records;
}

// Function to get borrowing stats
function getBorrowingStats($conn, $user_id) {
    $stats = [
        'total_borrowed' => 0,
        'currently_borrowed' => 0,
        'overdue' => 0,
        'returned' => 0
    ];

    // Total borrowed
    $sql = "SELECT COUNT(*) as total FROM borrowings WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_borrowed'] = $result->fetch_assoc()['total'];
    $stmt->close();

    // Currently borrowed
    $sql = "SELECT COUNT(*) as total FROM borrowings WHERE user_id = ? AND status = 'borrowed'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['currently_borrowed'] = $result->fetch_assoc()['total'];
    $stmt->close();

    // Overdue
    $sql = "SELECT COUNT(*) as total FROM borrowings WHERE user_id = ? AND status = 'borrowed' AND due_date < CURDATE()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['overdue'] = $result->fetch_assoc()['total'];
    $stmt->close();

    // Returned
    $sql = "SELECT COUNT(*) as total FROM borrowings WHERE user_id = ? AND status = 'returned'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['returned'] = $result->fetch_assoc()['total'];
    $stmt->close();

    return $stats;
}

// Get data
$user_id = $_SESSION['user_id'];
$borrowing_records = getBorrowingHistory($conn, $user_id);
$stats = getBorrowingStats($conn, $user_id);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My Borrowing History</title>
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
  </head>
  <body class="bg-background-light dark:bg-background-dark font-display">
    <div class="flex h-screen">
      <div id="backdrop" class="fixed inset-0 bg-black/40 z-40 hidden md:hidden"></div>
      <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 transform -translate-x-full md:translate-x-0 md:static md:flex bg-slate-50 dark:bg-slate-800 flex flex-col border-r border-slate-200 dark:border-slate-700 transition-transform duration-200">
        <div class="h-16 flex items-center px-6 border-b border-slate-200 dark:border-slate-700">
          <span class="material-icons text-primary mr-2">school</span>
          <span class="font-bold text-lg text-slate-800 dark:text-slate-100">Library System</span>
          <button id="menu-close" class="md:hidden p-2 text-slate-500 dark:text-slate-300 hover:text-slate-700 dark:hover:text-slate-200 ml-auto">
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
          <a
            class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
            href="user-management.php"
          >
            <span class="material-icons mr-3">group</span>
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
        <div class="p-4 flex items-center text-sm text-zinc-500 dark:text-zinc-400">
          <span class="material-icons text-orange-500 mr-2"></span>
          <span class="font-medium text-slate-800 dark:text-slate-100"></span>
        </div>
      </aside>
      <main class="flex-1 flex flex-col">
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
          <div class="max-w-7xl mx-auto space-y-8">
            <div class="mb-6">
              <h2
                class="text-2xl font-bold text-zinc-800 dark:text-zinc-100 flex items-center gap-3"
              >
                <span class="material-icons text-3xl">history</span>
                My Borrowing History
              </h2>
              <p class="text-zinc-500 dark:text-zinc-400 mt-1">
                Complete record of your library borrowing activity with detailed
                information.
              </p>
            </div>
            <div
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8"
          >
            <div
              class="bg-orange-50 dark:bg-gray-700 p-4 rounded-lg flex items-center justify-between border-l-4 border-orange-400
         transition duration-200 hover:bg-orange-100 dark:hover:bg-gray-600 hover:shadow-md hover:-translate-y-1"
            >
              <div>
                <p class="text-gray-500 dark:text-gray-400 text-sm">
                  Total Borrowed
                </p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white">
                  <?php echo $stats['total_borrowed']; ?>
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">All time</p>
              </div>
              <span
                class="material-icons-outlined text-orange-400 bg-orange-100 dark:bg-gray-600 p-2 rounded-full"
                >collections_bookmark</span
              >
            </div>
            <div
              class="bg-green-50 dark:bg-gray-700 p-4 rounded-lg flex items-center justify-between border-l-4 border-green-400
         transition duration-200 hover:bg-green-100 dark:hover:bg-gray-600 hover:shadow-md hover:-translate-y-1"
>
              <div>
                <p class="text-gray-500 dark:text-gray-400 text-sm">
                  Currently Borrowed
                </p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white">
                  <?php echo $stats['currently_borrowed']; ?>
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Active books
                </p>
              </div>
              <span
                class="material-icons-outlined text-green-400 bg-green-100 dark:bg-gray-600 p-2 rounded-full"
                >autorenew</span
              >
            </div>
            <div
              class="bg-red-50 dark:bg-gray-700 p-4 rounded-lg flex items-center justify-between border-l-4 border-red-400
         transition duration-200 hover:bg-red-100 dark:hover:bg-gray-600 hover:shadow-md hover:-translate-y-1"
>
              <div>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Overdue</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white">
                  <?php echo $stats['overdue']; ?>
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Need attention
                </p>
              </div>
              <span
                class="material-icons-outlined text-red-400 bg-red-100 dark:bg-gray-600 p-2 rounded-full"
                >warning_amber</span
              >
            </div>
            <div
              class="bg-blue-50 dark:bg-gray-700 p-4 rounded-lg flex items-center justify-between border-l-4 border-blue-400
         transition duration-200 hover:bg-blue-100 dark:hover:bg-gray-600 hover:shadow-md hover:-translate-y-1"
>
              <div>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Returned</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white">
                  <?php echo $stats['returned']; ?>
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Completed
                </p>
              </div>
              <span
                class="material-icons-outlined text-blue-400 bg-blue-100 dark:bg-gray-600 p-2 rounded-full"
                >check_circle_outline</span
              >
            </div>
            </div>
            <div>
            <h4
              class="text-xl font-semibold mb-4 text-gray-900 dark:text-white"
            >
              Borrowing Records
            </h4>
            <div class="space-y-4">
              <?php foreach ($borrowing_records as $record): ?>
              <div
                class="bg-surface-light dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-lg p-4
         transition duration-200 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:shadow-md hover:-translate-y-1"
>
                <div class="flex justify-between items-start mb-3">
                  <div>
                    <h5
                      class="font-semibold text-gray-900 dark:text-white flex items-center"
                    >
                      <span
                        class="material-icons-outlined text-lg mr-2 text-orange-500"
                        >book</span
                      ><?php echo htmlspecialchars($record['title']); ?>
                    </h5>
                    <p
                      class="text-xs text-muted-light dark:text-muted-dark mt-1"
                    >
                      by <?php echo htmlspecialchars($record['author']); ?>
                    </p>
                    <?php if ($record['status'] == 'borrowed' && strtotime($record['due_date']) < time()): ?>
                    <p
                      class="text-xs text-red-600 dark:text-red-400 mt-1 font-medium"
                    >
                      Fine: $<?php echo number_format((time() - strtotime($record['due_date'])) / 86400 * 0.50, 2); // Assuming $0.50 per day ?>
                    </p>
                    <?php endif; ?>
                  </div>
                  <span
                    class="text-xs font-semibold 
                    <?php 
                    if ($record['status'] == 'borrowed') {
                        if (strtotime($record['due_date']) < time()) {
                            echo 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300';
                        } else {
                            echo 'bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-300';
                        }
                    } else {
                        echo 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300';
                    }
                    ?> py-1 px-3 rounded-full"
                    ><?php 
                    if ($record['status'] == 'borrowed') {
                        if (strtotime($record['due_date']) < time()) {
                            echo 'Overdue (+' . floor((time() - strtotime($record['due_date'])) / 86400) . 'd)';
                        } else {
                            echo 'Borrowed';
                        }
                    } else {
                        echo 'Returned';
                    }
                    ?></span
                  >
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                  <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded">
                    <p
                      class="text-xs text-orange-600 dark:text-orange-400 font-medium"
                    >
                      Borrowed
                    </p>
                    <p
                      class="font-medium text-gray-800 dark:text-gray-200 mt-1"
                    >
                      <?php echo date('M d, Y', strtotime($record['borrow_date'])); ?>
                    </p>
                  </div>
                  <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded">
                    <p
                      class="text-xs text-blue-600 dark:text-blue-400 font-medium"
                    >
                      Due Date
                    </p>
                    <p
                      class="font-medium text-gray-800 dark:text-gray-200 mt-1"
                    >
                      <?php echo date('M d, Y', strtotime($record['due_date'])); ?>
                    </p>
                  </div>
                  <div class="bg-gray-100 dark:bg-gray-700/30 p-3 rounded">
                    <p
                      class="text-xs text-green-500 dark:text-green-400 font-medium"
                    >
                      Returned
                    </p>
                    <p
                      class="font-medium text-gray-800 dark:text-gray-200 mt-1"
                    >
                      <?php echo $record['return_date'] ? date('M d, Y', strtotime($record['return_date'])) : 'Not returned'; ?>
                    </p>
                  </div>
                  <div class="bg-purple-50 dark:bg-purple-900/20 p-3 rounded">
                    <p
                      class="text-xs text-purple-600 dark:text-purple-400 font-medium"
                    >
                      Staff
                    </p>
                    <p
                      class="font-medium text-gray-800 dark:text-gray-200 mt-1"
                    >
                      <?php echo htmlspecialchars($record['staff_name'] ?: 'N/A'); ?>
                    </p>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
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
      // Sidebar toggle + auto highlight for borrowing page
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
          if (ev.key === 'Escape' && window.innerWidth < 768) {
            hideSidebar();
          }
        });

        if (btn && sidebar && backdrop) {
          btn.addEventListener('click', function () {
            showSidebar();
          });
        }

        if (closeBtn && sidebar && backdrop) {
          closeBtn.addEventListener('click', function () {
            hideSidebar();
          });
        }

        if (backdrop) {
          backdrop.addEventListener('click', function () {
            hideSidebar();
          });
        }

        if (navLinks && navLinks.length) {
          navLinks.forEach(function (a) {
            a.addEventListener('click', function () {
              if (window.innerWidth < 768) hideSidebar();
            });
          });
        }

        // Auto highlight active sidebar link
        var current = location.pathname.split('/').pop() || 'borrow.php';
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
<?php
$conn->close();
?>
