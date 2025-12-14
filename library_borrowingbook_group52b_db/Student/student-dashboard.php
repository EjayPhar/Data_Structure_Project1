<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['student', 'user'], true)) {
    header('Location: ../login/login.html');
    exit();
}
include '../login/db_connect.php';
$userId = (int)($_SESSION['user_id'] ?? 0);

// Initialize metrics
$borrowedCount = 0;
$overdueCount = 0;
$daysVisited = 0;
$currentBorrowed = [];

// Books currently borrowed (status = 'borrowed')
if ($stmt = $conn->prepare("SELECT COUNT(*) AS c FROM borrowings WHERE user_id = ? AND status = 'borrowed'")) {
    $stmt->bind_param('i', $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : ['c' => 0];
        $borrowedCount = (int)($row['c'] ?? 0);
    }
    $stmt->close();
}

// Overdue books (status = 'overdue')
if ($stmt = $conn->prepare("SELECT COUNT(*) AS c FROM borrowings WHERE user_id = ? AND status = 'overdue'")) {
    $stmt->bind_param('i', $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : ['c' => 0];
        $overdueCount = (int)($row['c'] ?? 0);
    }
    $stmt->close();
}

// Days visited this month (distinct dates)
if ($stmt = $conn->prepare("SELECT COUNT(DISTINCT check_in_date) AS c FROM attendance WHERE user_id = ? AND check_in_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")) {
    $stmt->bind_param('i', $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : ['c' => 0];
        $daysVisited = (int)($row['c'] ?? 0);
    }
    $stmt->close();
}

// Currently borrowed books list (borrowed or overdue)
if ($stmt = $conn->prepare("SELECT b.title, b.author, br.due_date, br.status FROM borrowings br JOIN books b ON br.book_id = b.id WHERE br.user_id = ? AND br.status IN ('borrowed','overdue') ORDER BY br.due_date ASC")) {
    $stmt->bind_param('i', $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $currentBorrowed = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Student Dashboard</title>
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
    <script>
      // Auto highlight active sidebar link
      (function () {
        var links = document.querySelectorAll('aside nav a');
        var current = location.pathname.split('/').pop();
        if (!current) current = 'student-dashboard.html';
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
            class="flex items-center px-4 py-2 text-sm font-medium bg-primary text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
            href="student-dashboard.php"
          >
            <span class="material-icons mr-3">dashboard</span>
            Dashboard
          </a>
          <a
            class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
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
              <p class="font-medium text-sm text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></p>
              <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
            </div>
            <button onclick="window.location.href='../login/logout.php'" 
    class="ml-4 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
    <span class="material-icons">logout</span>
</button>
          </div>
        </header>
        <main class="flex-1 p-8 overflow-y-auto">
          <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
              Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Student'); ?>!
            </h2>
            <p class="text-gray-500 dark:text-gray-400">
              Here's your library overview for today.
            </p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div
              class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border-l-4 border-orange-400
         transition duration-200 hover:bg-orange-50 dark:hover:bg-gray-700 hover:shadow-md hover:-translate-y-1"
>
              <div class="flex justify-between items-start">
                <p class="text-gray-500 dark:text-gray-400">Books Borrowed</p>
                <span class="material-icons text-orange-400">book</span>
              </div>
              <p class="text-4xl font-bold mt-2 text-gray-800 dark:text-white">
                <?php echo (int)$borrowedCount; ?>
              </p>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                Currently borrowed
              </p>
            </div>
            <div
              class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border-l-4 border-teal-400
         transition duration-200 hover:bg-teal-50 dark:hover:bg-gray-700 hover:shadow-md hover:-translate-y-1"
>
              <div class="flex justify-between items-start">
                <p class="text-gray-500 dark:text-gray-400">Days Visited</p>
                <span class="material-icons text-teal-400">calendar_today</span>
              </div>
              <p class="text-4xl font-bold mt-2 text-gray-800 dark:text-white">
                <?php echo (int)$daysVisited; ?>
              </p>
              <p class="text-sm text-gray-500 dark:text-gray-400">This month</p>
            </div>
            <div
              class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border-l-4 border-red-400
              transition duration-200 hover:bg-red-50 dark:hover:bg-gray-700 hover:shadow-md hover:-translate-y-1">
              <div class="flex justify-between items-start">
                <p class="text-gray-500 dark:text-gray-400">Overdue Books</p>
                <span class="material-icons text-red-400">warning</span>
              </div>
              <p class="text-4xl font-bold mt-2 text-gray-800 dark:text-white">
                <?php echo (int)$overdueCount; ?>
              </p>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                Need attention
              </p>
            </div>
          </div>
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div
              class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border-t-4 border-orange-400"
            >
              <h3
                class="text-xl font-semibold mb-1 text-gray-800 dark:text-white"
              >
                Currently Borrowed Books
              </h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                Books you need to return
              </p>
              <div class="space-y-4">
              <?php if (empty($currentBorrowed)): ?>
                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                  <p class="text-sm text-gray-500 dark:text-gray-400">No currently borrowed books.</p>
                </div>
              <?php else: ?>
              <?php foreach ($currentBorrowed as $row): ?>
                <div class="flex justify-between items-center <?php echo ($row['status'] === 'overdue') ? 'bg-red-50' : 'bg-orange-50'; ?> dark:bg-gray-700/50 p-4 rounded-lg">
                  <div>
                    <p class="font-semibold text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($row['title']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">by <?php echo htmlspecialchars($row['author']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Due: <?php echo htmlspecialchars($row['due_date']); ?></p>
                  </div>
                  <span class="text-xs font-semibold px-3 py-1 <?php echo ($row['status'] === 'overdue') ? 'bg-red-600' : 'bg-orange-500'; ?> text-white rounded-full">
                    <?php echo ucfirst($row['status']); ?>
                  </span>
                </div>
              <?php endforeach; ?>
              <?php endif; ?>
              </div>
            </div>
            <div
              class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border-t-4 border-teal-400"
            >
              <h3
                class="text-xl font-semibold mb-1 text-gray-800 dark:text-white"
              >
                Quick Actions
              </h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                Common tasks
              </p>
              <div class="space-y-4">
                <a
                  class="flex items-center gap-4 p-4 rounded-lg bg-orange-50 hover:bg-orange-100 dark:bg-gray-700/50 dark:hover:bg-gray-700"
                  href="book-catalog.php"
                >
                  <div
                    class="p-2 bg-orange-100 dark:bg-orange-900/50 rounded-full"
                  >
                    <span class="material-icons text-orange-500">search</span>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">
                      Search Books
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      Find books in our catalog
                    </p>
                  </div>
                </a>
                <a
                  class="flex items-center gap-4 p-4 rounded-lg bg-green-50 hover:bg-green-100 dark:bg-gray-700/50 dark:hover:bg-gray-700"
                  href="cart.php"
                >
                  <div
                    class="p-2 bg-green-100 dark:bg-green-900/50 rounded-full"
                  >
                    <span class="material-icons text-green-500">shopping_cart</span>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">
                      My Borrowing
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      For borrowing books
                    </p>
                  </div>
                </a>
                  <a
                    class="flex items-center gap-4 p-4 rounded-lg bg-yellow-50 hover:bg-yellow-100 dark:bg-gray-700/50 dark:hover:bg-gray-700"
                    href="borrowing.php"
                  >
                  <div
                    class="p-2 bg-yellow-100 dark:bg-yellow-900/50 rounded-full"
                  >
                    <span class="material-icons text-yellow-500">history</span>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">
                      View History
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      Check your borrowing history
                    </p>
                  </div>
                </a>
              </div>
            </div>
          </div>
        </main>
      <footer class="h-14 flex items-center justify-between px-8 bg-slate-50 dark:bg-gray-600 border-t border-slate-200 dark:border-gray-700">
          <div class="text-sm">© 2025 OMSC Library</div>
          <div class="text-sm text-slate-500 space-x-4">
            <a href="/privacy.html" class="hover:text-primary">Privacy</a>
            <a href="/terms.html" class="hover:text-primary">Terms</a>
          </div>
        </footer>
      </div>
    </div>
  </body>
</html>
