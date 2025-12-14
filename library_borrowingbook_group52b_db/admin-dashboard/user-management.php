<?php
session_start();

function require_admin_login(): void {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ../login/login.html');
        exit();
    }
}
require_admin_login();

include '../login/db_connect.php';

function normalize_role_for_db(string $role): string {
    $role = strtolower(trim($role));
    if ($role === 'student') return 'user';
    if (in_array($role, ['admin', 'staff', 'user'], true)) return $role;
    return 'user';
}
function display_role(string $dbRole): string {
    $r = strtolower($dbRole);
    if ($r === 'user') return 'Student';
    if ($r === 'staff') return 'Librarian';
    if ($r === 'admin') return 'Admin';
    return ucfirst($r);
}

function fetch_users(mysqli $conn, ?string $q = null, ?string $role = null, ?string $status = null): array {
    $sql = "SELECT id, username, email, role, status FROM users WHERE 1=1";
    $types = '';
    $params = [];
    if ($q) {
        $like = '%'.$q.'%';
        $sql .= " AND (username LIKE ? OR email LIKE ?)";
        $types .= 'ss';
        $params[] = $like; $params[] = $like;
    }
    if ($role) {
        $sql .= " AND role = ?";
        $types .= 's';
        $params[] = normalize_role_for_db($role);
    }
    if ($status) {
        $sql .= " AND status = ?";
        $types .= 's';
        $params[] = strtolower($status);
    }
    $sql .= ' ORDER BY username ASC';

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

function add_user(mysqli $conn, array $data, ?string &$error = null): bool {
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $role = normalize_role_for_db($data['role'] ?? 'user');
    $status = strtolower(trim($data['status'] ?? 'active'));
    if ($username === '' || $email === '' || $password === '') {
        $error = 'Username, Email and Password are required.';
        return false;
    }
    // unique email
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'Email already exists.';
            return false;
        }
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO users (username, email, password, role, status) VALUES (?,?,?,?,?)');
    if (!$stmt) { $error = 'Failed to prepare insert.'; return false; }
    $stmt->bind_param('sssss', $username, $email, $hash, $role, $status);
    return $stmt->execute();
}

function update_user(mysqli $conn, int $id, array $data, ?string &$error = null): bool {
    if ($id <= 0) { $error = 'Invalid user ID.'; return false; }
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $role = normalize_role_for_db($data['role'] ?? 'user');
    $status = strtolower(trim($data['status'] ?? 'active'));
    $new_password = $data['new_password'] ?? '';

    if ($username === '' || $email === '') { $error = 'Username and Email are required.'; return false; }

    if ($new_password !== '') {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET username=?, email=?, role=?, status=?, password=? WHERE id=?');
        if (!$stmt) { $error = 'Failed to prepare update.'; return false; }
        $stmt->bind_param('sssssi', $username, $email, $role, $status, $hash, $id);
        return $stmt->execute();
    } else {
        $stmt = $conn->prepare('UPDATE users SET username=?, email=?, role=?, status=? WHERE id=?');
        if (!$stmt) { $error = 'Failed to prepare update.'; return false; }
        $stmt->bind_param('ssssi', $username, $email, $role, $status, $id);
        return $stmt->execute();
    }
}

function toggle_user_status(mysqli $conn, int $id, string $to): bool {
    $to = strtolower($to) === 'disabled' ? 'disabled' : 'active';
    $stmt = $conn->prepare('UPDATE users SET status=? WHERE id=?');
    if (!$stmt) return false;
    $stmt->bind_param('si', $to, $id);
    return $stmt->execute();
}

function delete_user(mysqli $conn, int $id): bool {
    $stmt = $conn->prepare('DELETE FROM users WHERE id=?');
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    return $stmt->execute();
}

$success = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        if (add_user($conn, $_POST, $error)) { $success = 'User added successfully.'; }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)($_SESSION['user_id'] ?? 0) && strtolower($_POST['status'] ?? '') === 'disabled') {
            $error = 'You cannot disable your own account.';
        } else {
            if (update_user($conn, $id, $_POST, $error)) { $success = 'User updated successfully.'; }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $to = $_POST['to'] ?? 'active';
        if ($id === (int)($_SESSION['user_id'] ?? 0)) {
            $error = 'You cannot change your own status.';
        } else {
            if (toggle_user_status($conn, $id, $to)) { $success = 'User status updated.'; } else { $error = 'Failed to update status.'; }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)($_SESSION['user_id'] ?? 0)) {
            $error = 'You cannot delete your own account.';
        } else {
            if (delete_user($conn, $id)) { $success = 'User deleted successfully.'; } else { $error = 'Failed to delete user.'; }
        }
    }
}

$q = isset($_GET['q']) ? trim($_GET['q']) : null;
$fRole = isset($_GET['role']) && $_GET['role'] !== '' ? $_GET['role'] : null;
$fStatus = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
$users = fetch_users($conn, $q, $fRole, $fStatus);

$roleOptions = ['admin' => 'Admin', 'staff' => 'Librarian', 'student' => 'Student'];
$statusOptions = ['active' => 'Active', 'disabled' => 'Disabled'];
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>User Management</title>
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
          },
        },
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
          <a class="block px-4 py-2 text-sm font-medium bg-primary text-white rounded-md" href="user-management.php">
            <span class="material-icons align-middle mr-2">group</span> User Management
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="borrow.php">
            <span class="material-icons align-middle mr-2">history</span> Borrowing History
          </a>
          <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md" href="Overdue-alerts.php">
              <span class="material-icons mr-3">warning</span>
              Overdue Alerts
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
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">User Management</h2>
                <p class="text-gray-500 dark:text-gray-400">Manage students, librarians, and system access.</p>
              </div>
              <button class="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-md hover:opacity-90" onclick="openAddUser()">
                <span class="material-icons-outlined">add</span>
                Add New User
              </button>
            </div>

            <?php if ($success): ?>
              <div class="mb-6 p-3 border border-green-300 bg-green-50 text-green-800 rounded"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
              <div class="mb-6 p-3 border border-red-300 bg-red-50 text-red-800 rounded"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="mb-6 p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
              <h3 class="text-lg font-semibold mb-3">User Database</h3>
              <form method="GET" class="flex flex-col md:flex-row gap-3 md:items-end">
                <div class="flex-1 relative">
                  <span class="material-icons-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                  <input name="q" value="<?php echo htmlspecialchars($q ?? ''); ?>" class="w-full pl-10 pr-4 py-2 border rounded bg-transparent focus:ring-primary focus:border-primary" placeholder="Search by username or email" />
                </div>
                <div>
                  <label class="block text-xs text-slate-500 mb-1">Role</label>
                  <select name="role" class="px-3 py-2 border rounded bg-transparent focus:ring-primary focus:border-primary">
                    <option value="">All</option>
                    <?php foreach ($roleOptions as $rv => $rl): ?>
                      <option value="<?php echo $rv; ?>" <?php echo ($fRole===$rv?'selected':''); ?>><?php echo $rl; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="block text-xs text-slate-500 mb-1">Status</label>
                  <select name="status" class="px-3 py-2 border rounded bg-transparent focus:ring-primary focus:border-primary">
                    <option value="">All</option>
                    <?php foreach ($statusOptions as $sv => $sl): ?>
                      <option value="<?php echo $sv; ?>" <?php echo ($fStatus===$sv?'selected':''); ?>><?php echo $sl; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <button class="px-4 py-2 border rounded hover:bg-gray-50">Apply</button>
                </div>
              </form>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead>
                  <tr class="border-b border-black-200 dark:border-black-700 text-sm text-black-500 dark:text-black-400">
                    <th class="py-3 px-4 font-medium">User</th>
                    <th class="py-3 px-8 font-medium">Role</th>
                    <th class="py-2 px-4 font-medium">Join Date</th>
                    <th class="py-3 px-3 font-medium">Last Active</th>
                    <th class="py-3 px-5 font-medium">Status</th>
                    <th class="py-3 px-4 font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($users)): ?>
                    <tr><td colspan="4" class="py-6 px-4 text-center text-gray-500">No users found.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($users as $u): ?>
                  <tr class="border-b border-gray-200 dark:border-gray-700">
                  <td class="py-4 px-4">
                  <p class="font-medium text-text-light dark:text-text-dark"><?php echo htmlspecialchars($u['username']); ?></p>
                  <p class="text-xs text-subtext-light dark:text-subtext-dark"><?php echo htmlspecialchars($u['email']); ?></p>
                  </td>
                  <td class="py-4 px-4"><?php echo display_role($u['role']); ?></td>
                  <td class="py-4 px-4">N/A</td>
                  <td class="py-4 px-4">N/A</td>
                  <td class="py-4 px-4">
                  <?php if (strtolower($u['status'])==='active'): ?>
                  <span class="bg-green-600 text-white px-3 py-1 rounded-full text-xs font-semibold">Active</span>
                  <?php else: ?>
                  <span class="bg-red-600 text-white px-3 py-1 rounded-full text-xs font-semibold">Disabled</span>
                  <?php endif; ?>
                  </td>
                  <td class="py-4 px-4">
                  <div class="flex items-center gap-2">
                  <button type="button" class="flex items-center px-1 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium bg-surface-light dark:bg-surface-dark hover:bg-gray-50 dark:hover:bg-gray-700"
                  onclick="openEditUser(this)"
                  data-id="<?php echo (int)$u['id']; ?>"
                  data-username="<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>"
                  data-email="<?php echo htmlspecialchars($u['email'], ENT_QUOTES); ?>"
                  data-role="<?php echo htmlspecialchars($u['role'], ENT_QUOTES); ?>"
                  data-status="<?php echo htmlspecialchars($u['status'], ENT_QUOTES); ?>">
                  <span class="material-icons-outlined text-base">edit</span>
                  </button>
                  <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                  <form method="POST" onsubmit="return confirm('Change status for this user?');" class="inline">
                  <input type="hidden" name="action" value="toggle_status" />
                  <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>" />
                  <input type="hidden" name="to" value="<?php echo strtolower($u['status'])==='active'?'disabled':'active'; ?>" />
                  <button class="flex items-center px-1 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium hover:bg-gray-50 dark:hover:bg-gray-700">
                  <span class="material-icons-outlined text-base"><?php echo strtolower($u['status'])==='active'?'block':'check_circle'; ?></span>
                  </button>
                  </form>
                  <form method="POST" onsubmit="return confirm('Delete this user? This action cannot be undone.');" class="inline">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>" />
                  <button class="flex items-center px-1 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700">
                  <span class="material-icons-outlined text-base">delete</span>
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

            <div id="addUser" class="mt-10">
              <h3 class="text-lg font-semibold mb-3">Add User</h3>
              <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="add" />
                <div>
                  <label class="block text-sm font-medium">Username</label>
                  <input name="username" required class="mt-1 block w-full border rounded px-3 py-2" />
                </div>
                <div>
                  <label class="block text-sm font-medium">Email</label>
                  <input name="email" type="email" required class="mt-1 block w-full border rounded px-3 py-2" />
                </div>
                <div>
                  <label class="block text-sm font-medium">Password</label>
                  <input name="password" type="password" required class="mt-1 block w-full border rounded px-3 py-2" />
                </div>
                <div>
                  <label class="block text-sm font-medium">Role</label>
                  <select name="role" class="mt-1 block w-full border rounded px-3 py-2">
                    <?php foreach ($roleOptions as $rv => $rl): ?>
                      <option value="<?php echo $rv; ?>"><?php echo $rl; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium">Status</label>
                  <select name="status" class="mt-1 block w-full border rounded px-3 py-2">
                    <?php foreach ($statusOptions as $sv => $sl): ?>
                      <option value="<?php echo $sv; ?>"><?php echo $sl; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="md:col-span-2">
                  <button class="px-4 py-2 bg-primary text-white rounded">Create User</button>
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

    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-xl p-6 m-4 max-h-[85vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
          <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Add New User</h2>
            <p class="text-gray-500 dark:text-gray-400">Create a new user account with specific permissions.</p>
          </div>
          <button onclick="closeAddUser()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"><span class="material-icons-outlined">close</span></button>
        </div>
        <form method="POST" class="space-y-4">
          <input type="hidden" name="action" value="add" />
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
              <input name="username" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 bg-transparent focus:ring-primary focus:border-primary" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
              <input name="email" type="email" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 bg-transparent focus:ring-primary focus:border-primary" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
              <input name="password" type="password" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 bg-transparent focus:ring-primary focus:border-primary" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
              <select name="role" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 bg-transparent focus:ring-primary focus:border-primary">
                <option value="student">Student</option>
                <option value="staff">Librarian</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
              <select name="status" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 bg-transparent focus:ring-primary focus:border-primary">
                <option value="active">Active</option>
                <option value="disabled">Disabled</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-4 pt-4">
            <button type="button" onclick="closeAddUser()" class="px-6 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">Cancel</button>
            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-md hover:opacity-90 transition-opacity">Add User</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-xl p-6 m-4 max-h-[85vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
          <div>
            <h2 class="text-2xl font-bold">Edit User</h2>
            <p class="text-slate-500 text-sm">Update user information.</p>
          </div>
          <button onclick="closeEditUser()" class="text-gray-400 hover:text-gray-600"><span class="material-icons-outlined">close</span></button>
        </div>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <input type="hidden" name="action" value="update" />
          <input type="hidden" name="id" id="eu_id" />
          <div>
            <label class="block text-sm font-medium">Username</label>
            <input id="eu_username" name="username" required class="mt-1 block w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium">Email</label>
            <input id="eu_email" name="email" type="email" required class="mt-1 block w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium">Role</label>
            <select id="eu_role" name="role" class="mt-1 block w-full border rounded px-3 py-2">
              <?php foreach ($roleOptions as $rv => $rl): ?>
                <option value="<?php echo $rv; ?>"><?php echo $rl; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium">Status</label>
            <select id="eu_status" name="status" class="mt-1 block w-full border rounded px-3 py-2">
              <?php foreach ($statusOptions as $sv => $sl): ?>
                <option value="<?php echo $sv; ?>"><?php echo $sl; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium">New Password (optional)</label>
            <input id="eu_new_password" name="new_password" type="password" class="mt-1 block w-full border rounded px-3 py-2" placeholder="Leave blank to keep current password" />
          </div>
          <div class="md:col-span-2 flex justify-end gap-3">
            <button type="button" onclick="closeEditUser()" class="px-4 py-2 border rounded">Cancel</button>
            <button class="px-4 py-2 bg-primary text-white rounded">Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <script>
      function openAddUser(){
        const m = document.getElementById('addUserModal');
        m.classList.remove('hidden');
        m.classList.add('flex');
      }
      function closeAddUser(){
        const m = document.getElementById('addUserModal');
        m.classList.add('hidden');
        m.classList.remove('flex');
      }
      function openEditUser(btn){
        const d = btn.dataset;
        document.getElementById('eu_id').value = d.id || '';
        document.getElementById('eu_username').value = d.username || '';
        document.getElementById('eu_email').value = d.email || '';
        // Map DB role back to UI role
        let role = (d.role || '').toLowerCase();
        if (role === 'user') role = 'student';
        document.getElementById('eu_role').value = role;
        document.getElementById('eu_status').value = (d.status || '').toLowerCase();
        document.getElementById('eu_new_password').value = '';
        const m = document.getElementById('editUserModal');
        m.classList.remove('hidden');
        m.classList.add('flex');
      }
      function closeEditUser(){
        const m = document.getElementById('editUserModal');
        m.classList.add('hidden');
        m.classList.remove('flex');
      }
      document.addEventListener('keydown', function(ev){ if(ev.key==='Escape') closeEditUser();});
    </script>
  </body>
</html>
