<?php
session_start();
include '../login/db_connect.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login/login.html");
    exit();
}

// Handle POST requests for user management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'edit') {
            // Edit user
            $id = (int)$_POST['id'];
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $role = trim($_POST['role']);

            if (empty($username) || empty($email)) {
                $error = "Username and email are required.";
            } else {
                $sql = "UPDATE users SET username=?, email=?, role=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $username, $email, $role, $id);
                if ($stmt->execute()) {
                    $success = "User updated successfully.";
                } else {
                    $error = "Error updating user: " . $conn->error;
                }
                $stmt->close();
            }
        }
}
}

// Handle search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';

// Build query
$sql = "SELECT id, username, email, role FROM users WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($role_filter) && $role_filter !== 'All Roles') {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

$sql .= " ORDER BY username ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unique roles for filter dropdown
$role_sql = "SELECT DISTINCT role FROM users ORDER BY role";
$role_result = $conn->query($role_sql);
$roles = [];
while ($row = $role_result->fetch_assoc()) {
    $roles[] = $row['role'];
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>User Management</title>
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
        if (!current) current = 'user-management.php';
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
    <style>
      .material-icons { font-size: 20px; vertical-align: middle; }
    </style>
  </head>
  <body class="font-display bg-background-light dark:bg-background-dark text-slate-700 dark:text-slate-300">
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
            href="librarian-dashboard.php"
          >
            <span class="material-icons mr-3">dashboard</span>
            Dashboard
          </a>
          <a
            class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
            href="Book_Management.php"
          >
            <span class="material-icons mr-3">menu_book</span>
            Book Management
          </a>
          <a
            class="flex items-center px-4 py-2 text-sm font-medium bg-primary text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
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
        <div class="flex-1 overflow-y-auto p-8">
          <div class="flex items-center gap-4 mb-2">
            
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
              User Management
            </h1>
          </div>
          <p class="text-slate-500 dark:text-slate-400 mb-8">
            View and manage user accounts and information.
          </p>
          <div
            class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-slate-200 dark:border-slate-700 mb-8"
          >
            <div class="flex items-center gap-2 mb-4">
              <span class="material-icons text-slate-500 dark:text-slate-400"
                >filter_alt</span
              >
              <h3
                class="text-lg font-semibold text-slate-800 dark:text-slate-200"
              >
                Search &amp; Filter
              </h3>
            </div>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="relative col-span-1 md:col-span-1">
                <span
                  class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                  >search</span
                >
                <input
                  id="searchInput"
                  name="search"
                  class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded bg-background-light dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:ring-primary focus:border-primary"
                  placeholder="Search by name or email..."
                  type="text"
                  value="<?php echo htmlspecialchars($search); ?>"
                />
              </div>
              <div class="col-span-1">
                <select
                  id="roleFilter"
                  name="role"
                  class="w-full py-2 px-4 border border-slate-300 dark:border-slate-600 rounded bg-background-light dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:ring-primary focus:border-primary"
                >
                  <option>All Roles</option>
                  <?php foreach ($roles as $role): ?>
                    <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $role_filter === $role ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($role)); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-span-1 md:col-span-3 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:opacity-90">Filter</button>
              </div>
            </form>
          </div>
          <div
            class="bg-white dark:bg-gray-800 rounded-lg border border-slate-200 dark:border-slate-700"
          >
            <div class="p-6">
              <h3
                class="text-lg font-semibold text-slate-800 dark:text-slate-200"
              >
                Users (<?php echo count($users); ?>)
              </h3>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead class="border-b border-slate-200 dark:border-slate-700">
                  <tr>
                    <th
                      class="px-6 py-3 text-sm font-semibold text-slate-500 dark:text-slate-400"
                    >
                      User
                    </th>
                    <th
                      class="px-10 py-3 text-sm font-semibold text-slate-500 dark:text-slate-400"
                    >
                      Role
                    </th>
                    <th
                      class="px-20 py-5 text-sm font-semibold text-slate-500 dark:text-slate-400"
                    >
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $user): ?>
                  <tr class="border-b border-slate-200 dark:border-slate-700">
                    <td class="px-6 py-4">
                      <p class="font-medium text-slate-900 dark:text-white">
                        <?php echo htmlspecialchars($user['username']); ?>
                      </p>
                      <p class="text-sm text-slate-500 dark:text-slate-400">
                        <?php echo htmlspecialchars($user['email']); ?>
                      </p>
                    </td>
                    <td class="px-6 py-4">
                      <span
                      class="bg-orange-500 text-white px-3 py-1 rounded-full text-xs font-semibold"
                      ><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span
                    >
                    </td>
                    <td class="px-20 py-5">
                      <div class="flex items-center gap-2">
                        <button
                          onclick="openModal(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>', '<?php echo addslashes($user['email']); ?>', '<?php echo addslashes($user['role']); ?>')"
                          class="p-2 border border-slate-300 dark:border-slate-600 rounded text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700"
                        >
                          <span class="material-icons" style="font-size: 16px" 
                            >edit</span
                          >
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
        <footer class="h-14 flex items-center justify-between px-8 bg-slate-50 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700">
          <div class="text-sm">© 2025 OMSC Library</div>
          <div class="text-sm text-slate-500 space-x-4">
            <a href="/privacy.html" class="hover:text-primary">Privacy</a>
            <a href="/terms.html" class="hover:text-primary">Terms</a>
          </div>
        </footer>
      </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg max-w-md w-full mx-4">
        <div class="p-6">
          <h2 class="text-xl font-semibold text-slate-800 dark:text-slate-200 mb-4">Edit User</h2>
          <p class="text-slate-600 dark:text-slate-400 mb-6">Update user information.</p>
          <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="mb-4">
              <label for="edit-username" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Full name</label>
              <input type="text" id="edit-username" name="username" placeholder="Enter full name" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded bg-background-light dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:ring-primary focus:border-primary" required>
            </div>
            <div class="mb-4">
              <label for="edit-email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Email</label>
              <input type="email" id="edit-email" name="email" placeholder="user@university.edu" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded bg-background-light dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:ring-primary focus:border-primary" required>
            </div>
            <div class="mb-6">
              <label for="edit-role" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Role</label>
              <select id="edit-role" name="role" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded bg-background-light dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:ring-primary focus:border-primary" required>
                <option value="student">Student</option>
                <option value="librarian">Librarian</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div class="flex justify-end gap-4">
              <button type="button" onclick="closeModal()" class="px-4 py-2 bg-slate-300 dark:bg-slate-600 text-slate-700 dark:text-slate-300 rounded hover:bg-slate-400 dark:hover:bg-slate-500">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark">Update user</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
      function openModal(id, username, email, role) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-username').value = username;
        document.getElementById('edit-email').value = email;
        document.getElementById('edit-role').value = role;
        document.getElementById('editUserModal').classList.remove('hidden');
      }

      function closeModal() {
        document.getElementById('editUserModal').classList.add('hidden');
      }
    </script>
  </body>
</html>
<?php
$conn->close();
?>
