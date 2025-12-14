<?php
session_start();
include '../login/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login/login.html");
  exit();
}

$user_id = $_SESSION['user_id'];

// Fetch borrowing statistics
$stats_query = "SELECT
    COUNT(*) as total_borrowed,
    SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as currently_borrowed,
    SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
    SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned
    FROM borrowings WHERE user_id = ?";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Fetch borrowing history with book details
$history_query = "
SELECT 
    br.id AS borrowing_id,
    b.title,
    b.author,
    br.borrow_date,
    br.due_date,
    br.return_date,
    br.status,
    br.staff_name
FROM borrowings br
JOIN books b ON br.book_id = b.id
WHERE br.user_id = ?
ORDER BY br.borrow_date DESC
";


$history_stmt = $conn->prepare($history_query);
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <title>My Borrowing History</title>
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
</head>

<body class="bg-background-light dark:bg-background-dark font-display">
  <div class="flex h-screen">
    <div id="backdrop" class="fixed inset-0 bg-black/40 z-40 hidden md:hidden"></div>
    <aside id="sidebar"
      class="fixed inset-y-0 left-0 z-50 w-64 transform -translate-x-full md:translate-x-0 md:static md:flex bg-slate-50 dark:bg-slate-800 flex flex-col border-r border-slate-200 dark:border-slate-700 transition-transform duration-200">
      <div class="h-16 flex items-center px-6 border-b border-slate-200 dark:border-slate-700">
        <span class="material-icons text-primary mr-2">school</span>
        <span class="font-bold text-lg text-slate-800 dark:text-slate-100">Library System</span>
        <button id="menu-close"
          class="md:hidden p-2 text-slate-500 dark:text-slate-300 hover:text-slate-700 dark:hover:text-slate-200 ml-auto">
          <span class="material-icons">close</span>
        </button>
      </div>
      <nav class="flex-1 p-4 space-y-2">
        <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
          href="student-dashboard.php">
          <span class="material-icons mr-3">dashboard</span>
          Dashboard
        </a>
        <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
          href="book-catalog.php">
          <span class="material-icons mr-3">menu_book</span>
          Book Catalog
        </a>
        <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
          href="cart.php">
          <span class="material-icons mr-3">shopping_cart</span>
          My Borrowing
        </a>
        <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
          href="borrowing.php">
          <span class="material-icons mr-3">history</span>
          My Borrowing History
        </a>

      </nav>
      <div class="p-4 flex items-center text-sm text-zinc-500 dark:text-zinc-400">
        <span class="material-icons text-orange-500 mr-2"></span>
        <span class="font-medium text-slate-800 dark:text-slate-100"></span>
      </div>
    </aside>
    <main class="flex-1 flex flex-col">
      <header
        class="h-16 flex items-center justify-between px-8 bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
        <div class="flex items-center md:hidden mr-4">
          <button id="menu-btn" aria-expanded="false"
            class="p-2 text-slate-600 dark:text-slate-300 hover:text-slate-800 dark:hover:text-slate-100"
            aria-label="Open sidebar">
            <span class="material-icons">menu</span>
          </button>
        </div>
        <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Student Dashboard</h1>
        <div class="flex items-center gap-4">
          <div class="text-right">
            <p class="font-medium text-sm text-slate-800 dark:text-slate-100">
              <?php echo htmlspecialchars($_SESSION['username']); ?>
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($_SESSION['email']); ?>
            </p>
          </div>

        </div>
      </header>
      <div class="flex-1 p-8 overflow-y-auto">
        <div class="max-w-7xl mx-auto space-y-8">
          <?php if (!empty($_SESSION['success_message'])): ?>
            <div
              class="mb-6 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
              <span class="material-icons-outlined text-green-600">check_circle</span>
              <span class="font-medium">
                <?= htmlspecialchars($_SESSION['success_message']); ?>
              </span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
          <?php endif; ?>

          <div class="mb-6">
            <h2 class="text-2xl font-bold text-zinc-800 dark:text-zinc-100 flex items-center gap-3">
              <span class="material-icons text-3xl">history</span>
              My Borrowing History
            </h2>
            <p class="text-zinc-500 dark:text-zinc-400 mt-1">
              Complete record of your library borrowing activity with detailed
              information.
            </p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-orange-50 dark:bg-gray-700 p-4 rounded-lg flex items-center justify-between border-l-4 border-orange-400
         transition duration-200 hover:bg-orange-100 dark:hover:bg-gray-600 hover:shadow-md hover:-translate-y-1">
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
                class="material-icons-outlined text-orange-400 bg-orange-100 dark:bg-gray-600 p-2 rounded-full">collections_bookmark</span>
            </div>
            <div class="bg-green-50 dark:bg-gray-700 p-4 rounded-lg flex items-center justify-between border-l-4 border-green-400
         transition duration-200 hover:bg-green-100 dark:hover:bg-gray-600 hover:shadow-md hover:-translate-y-1">
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
                class="material-icons-outlined text-green-400 bg-green-100 dark:bg-gray-600 p-2 rounded-full">autorenew</span>
            </div>
            <div class="bg-red-50 dark:bg-gray-700 p-4 rounded-lg flex items-center justify-between border-l-4 border-red-400
         transition duration-200 hover:bg-red-100 dark:hover:bg-gray-600 hover:shadow-md hover:-translate-y-1">
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
                class="material-icons-outlined text-red-400 bg-red-100 dark:bg-gray-600 p-2 rounded-full">warning_amber</span>
            </div>
            <div class="bg-blue-50 dark:bg-gray-700 p-4 rounded-lg flex items-center justify-between border-l-4 border-blue-400
         transition duration-200 hover:bg-blue-100 dark:hover:bg-gray-600 hover:shadow-md hover:-translate-y-1">
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
                class="material-icons-outlined text-blue-400 bg-blue-100 dark:bg-gray-600 p-2 rounded-full">check_circle_outline</span>
            </div>
          </div>
          <div>
            <h4 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
              Borrowing Records
            </h4>
            <div class="space-y-4">

              <?php while ($row = $history_result->fetch_assoc()): ?>
                <div class="bg-white rounded-lg p-5 shadow hover:shadow-md transition">

                  <div class="flex justify-between items-start gap-4">

                    <!-- LEFT -->
                    <div>
                      <h4 class="font-semibold flex items-center gap-2">
                        <span class="material-icons-outlined text-orange-500">book</span>
                        <?= htmlspecialchars($row['title']) ?>
                      </h4>
                      <p class="text-xs text-gray-500">by <?= htmlspecialchars($row['author']) ?></p>

                      <?php if ($row['status'] === 'overdue'): ?>
                        <p class="text-xs text-red-600 mt-1 font-medium">
                          Fine: $
                          <?php
                          $days = max(0, (new DateTime())->diff(new DateTime($row['due_date']))->days);
                          echo number_format($days * 1.5, 2);
                          ?>
                        </p>
                      <?php endif; ?>
                    </div>

                    <!-- RIGHT -->
                    <div class="flex flex-col items-end gap-2">

                      <span class="text-xs px-3 py-1 rounded-full font-semibold
                    <?php
                      echo match ($row['status']) {
                          'borrowed' => 'bg-orange-100 text-orange-700',
                          'returned' => 'bg-green-100 text-green-700',
                          'overdue' => 'bg-red-100 text-red-700',
                          };
                        ?>">
                        <?= ucfirst($row['status']) ?>
                      </span>

                      <?php if ($row['status'] === 'borrowed'): ?>
                        <form method="POST" action="return-book.php">
                          <input type="hidden" name="borrowing_id" value="<?= $row['borrowing_id']; ?>">

                          <button onclick="return confirm('Return this book?')"
                            class="bg-primary text-white px-4 py-1 rounded-md text-xs flex items-center gap-1 hover:bg-green-700">
                            <span class="material-icons-outlined text-sm">assignment_return</span>
                            Return
                          </button>
                        </form>
                      <?php endif; ?>


                    </div>
                  </div>

                  <!-- DATES -->
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-sm">
                    <div>
                      <p class="text-xs text-gray-500">Borrowed</p>
                      <?= date('M d, Y', strtotime($row['borrow_date'])) ?>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500">Due</p>
                      <?= date('M d, Y', strtotime($row['due_date'])) ?>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500">Returned</p>
                      <?= $row['return_date'] ? date('M d, Y', strtotime($row['return_date'])) : '—' ?>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500">Staff</p>
                      <?= htmlspecialchars($row['staff_name'] ?: 'N/A') ?>
                    </div>
                  </div>

                </div>
              <?php endwhile; ?>
            </div>
          </div>
        </div>
      </div>
      <footer
        class="h-14 flex items-center justify-between px-8 bg-slate-50 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700">
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
      var current = location.pathname.split('/').pop() || 'borrowing.php';
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
$stats_stmt->close();
$history_stmt->close();
$conn->close();
?>