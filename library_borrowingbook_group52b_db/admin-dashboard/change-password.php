<?php
session_start();

// Assume user is logged in, get user_id from session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Connect to DB (adjust credentials as needed)
$conn = new mysqli('localhost', 'root', '', 'library_borrowingbook_group52b_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user details
$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$name = $user['username'];
$email = $user['email'];
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Process form
    $current_password = $_POST['current-password'];
    $new_password = $_POST['new-password'];
    $confirm_password = $_POST['confirm-password'];

    // Get current password hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($current_password, $row['password'])) {
            if ($new_password === $confirm_password && strlen($new_password) >= 6) {
                $hashed_new = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_new, $user_id);
                if ($update_stmt->execute()) {
                    $message = "<div class='text-green-600 dark:text-green-400'>Password changed successfully.</div>";
                } else {
                    $message = "<div class='text-red-600 dark:text-red-400'>Error updating password.</div>";
                }
                $update_stmt->close();
            } else {
                $message = "<div class='text-red-600 dark:text-red-400'>New passwords do not match or must be at least 6 characters.</div>";
            }
        } else {
            $message = "<div class='text-red-600 dark:text-red-400'>Current password is incorrect.</div>";
        }
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Change Password - Library System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
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
  <body class="font-display bg-background-light dark:bg-background-dark text-slate-700 dark:text-slate-300">
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
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="admin.php">
            <span class="material-icons mr-3">dashboard</span>
            Dashboard
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="book-management.php">
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
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="Overdue-alerts.php">
            <span class="material-icons mr-3">warning</span>
            Overdue Alerts
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
        <div class="p-4 flex items-center text-sm text-zinc-500 dark:text-zinc-400">
          <span class="material-icons text-orange-500 mr-2"></span>
          <span class="font-medium text-slate-800 dark:text-slate-100"></span>
        </div>
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
              <p class="font-medium text-sm text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($name); ?></p>
              <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($email); ?></p>
            </div>
          </div>
        </header>
        <div class="flex-1 overflow-y-auto p-8">
          <div class="max-w-4xl mx-auto">
            <div class="flex items-center gap-4 mb-2">
              <span
                class="material-icons-outlined text-3xl text-slate-600 dark:text-slate-300"
                >lock_open</span
              >
              <h2 class="text-2xl font-bold text-slate-900 dark:text-white">
                Change Password
              </h2>
            </div>
            <p class="text-slate-500 dark:text-slate-400 mb-8">
              Update your account password to keep your account secure.
            </p>
            <div class="space-y-8">
              <div
                class="bg-white dark:bg-slate-900/50 p-8 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm"
              >
                <div class="flex items-center gap-3 mb-2">
                  <span class="material-icons-outlined text-primary"
                    >shield</span
                  >
                  <h3
                    class="text-xl font-semibold text-slate-800 dark:text-slate-100"
                  >
                    Update Password
                  </h3>
                </div>
                <p class="text-slate-500 dark:text-slate-400 mb-6">
                  Choose a strong password to protect your account
                </p>
                <?php echo $message; ?>
                <form class="space-y-6" method="POST" action="">
                  <div>
                    <label
                      class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                      for="current-password"
                      >Current Password *</label
                    >
                    <div class="relative">
                      <input
                        class="w-full bg-[#fff8ed] dark:bg-slate-800 border-transparent focus:border-primary focus:ring-primary rounded-lg transition-colors"
                        id="current-password"
                        name="current-password"
                        placeholder="Enter your current password"
                        type="password"
                        required
                      />
                      <button
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        type="button"
                      >
                        
                      </button>
                    </div>
                  </div>
                  <div>
                    <label
                      class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                      for="new-password"
                      >New Password *</label
                    >
                    <div class="relative">
                      <input
                        class="w-full bg-[#fff8ed] dark:bg-slate-800 border-transparent focus:border-primary focus:ring-primary rounded-lg transition-colors"
                        id="new-password"
                        name="new-password"
                        placeholder="Enter new password (min. 6 characters)"
                        type="password"
                        required
                      />
                      <button
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        type="button"
                      >
                       
                      </button>
                    </div>
                  </div>
                  <div>
                    <label
                      class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                      for="confirm-password"
                      >Confirm New Password *</label
                    >
                    <div class="relative">
                      <input
                        class="w-full bg-[#fff8ed] dark:bg-slate-800 border-transparent focus:border-primary focus:ring-primary rounded-lg transition-colors"
                        id="confirm-password"
                        name="confirm-password"
                        placeholder="Confirm your new password"
                        type="password"
                        required
                      />
                      <button
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        type="button"
                      >
                        
                      </button>
                    </div>
                  </div>
                  <button
                    class="w-full bg-primary hover:bg-green-700 text-white font-semibold py-3 px-4 rounded-lg flex items-center justify-center gap-2 transition-colors shadow-md"
                    type="submit"
                  >
                    <span class="material-icons-outlined">task_alt</span>
                    Change Password
                  </button>
                </form>
              </div>
                        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-6">
              <h3 class="text-lg font-medium text-slate-800 dark:text-slate-100 mb-3">Password Security Tips</h3>
              <div class="grid md:grid-cols-2 gap-6 text-sm text-slate-600 dark:text-slate-300">
                <div>
                  <p class="font-medium text-green-600 dark:text-green-400 mb-2">Use a strong password</p>
                  <ul class="list-disc list-inside space-y-1">
                    <li>At least 8 characters long</li>
                    <li>Mix of letters, numbers, and symbols</li>
                    <li>Avoid common words or phrases</li>
                    <li>Don't use personal information</li>
                  </ul>
                </div>
                <div>
                  <p class="font-medium text-blue-600 dark:text-blue-400 mb-2">Security reminders</p>
                  <ul class="list-disc list-inside space-y-1">
                    <li>Never share your password</li>
                    <li>Log out from public computers</li>
                    <li>Change password if compromised</li>
                    <li>Use different passwords for different accounts</li>
                  </ul>
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

        // Auto highlight active nav link
        var current = location.pathname.split('/').pop() || 'change-password.php';
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