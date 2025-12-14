<?php
session_start();
include '../login/db_connect.php';

// Check if user is logged in (allow staff and student)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'student', 'admin'])) {
    header("Location: ../login/login.html");
    exit();
}
$is_student = ($_SESSION['role'] === 'student');
$current_user_id = (int)$_SESSION['user_id'];

// Function to get attendance records
function getAttendanceRecords($conn, $period = 'week', $onlyUserId = null) {
    $where = [];
    $params = [];
    $types = '';

    if ($period == 'week') {
        $where[] = "a.check_in_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($period == 'month') {
        $where[] = "a.check_in_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($period == 'year') {
        $where[] = "a.check_in_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
    }

    if (!is_null($onlyUserId)) {
        $where[] = "a.user_id = ?";
        $types .= 'i';
        $params[] = $onlyUserId;
    }

    $sql = "SELECT a.id, u.username, u.email, a.check_in_date, a.check_in_time, a.status\n            FROM attendance a\n            JOIN users u ON a.user_id = u.id";

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= " ORDER BY a.check_in_date DESC, a.check_in_time DESC";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            return [];
        }
        $result = $stmt->get_result();
        if (!$result) return [];
    } else {
        $result = $conn->query($sql);
        if (!$result) return [];
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    if (!is_array($rows)) return [];
    return $rows;
}

// Function to get attendance stats
// If $onlyUserId is provided, count that user's visits; otherwise count distinct visitors.
function getAttendanceStats($conn, $period = 'week', $onlyUserId = null) {
    $stats = [
        'total_visitors' => 0,
        'week_visitors' => 0,
        'month_visitors' => 0,
        'year_visitors' => 0
    ];

    if (!is_null($onlyUserId)) {
        // Per-user visit counts
        $sql = "SELECT COUNT(*) AS total FROM attendance WHERE check_in_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND user_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $onlyUserId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : ['total' => 0];
                $stats['week_visitors'] = isset($row['total']) ? (int)$row['total'] : 0;
            }
        }

        $sql = "SELECT COUNT(*) AS total FROM attendance WHERE check_in_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND user_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $onlyUserId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : ['total' => 0];
                $stats['month_visitors'] = isset($row['total']) ? (int)$row['total'] : 0;
            }
        }

        $sql = "SELECT COUNT(*) AS total FROM attendance WHERE check_in_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY) AND user_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $onlyUserId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : ['total' => 0];
                $stats['year_visitors'] = isset($row['total']) ? (int)$row['total'] : 0;
            }
        }
    } else {
        // Distinct visitors across all users
        $sql = "SELECT COUNT(DISTINCT user_id) as total FROM attendance WHERE check_in_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $result = $conn->query($sql);
        $row = $result ? $result->fetch_assoc() : ['total' => 0];
        $stats['week_visitors'] = isset($row['total']) ? (int)$row['total'] : 0;

        $sql = "SELECT COUNT(DISTINCT user_id) as total FROM attendance WHERE check_in_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $result = $conn->query($sql);
        $row = $result ? $result->fetch_assoc() : ['total' => 0];
        $stats['month_visitors'] = isset($row['total']) ? (int)$row['total'] : 0;

        $sql = "SELECT COUNT(DISTINCT user_id) as total FROM attendance WHERE check_in_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
        $result = $conn->query($sql);
        $row = $result ? $result->fetch_assoc() : ['total' => 0];
        $stats['year_visitors'] = isset($row['total']) ? (int)$row['total'] : 0;
    }

    $stats['total_visitors'] = $stats[$period . '_visitors'];
    return $stats;
}

// Get data
$period = isset($_GET['period']) ? $_GET['period'] : 'week';
$attendance_records = getAttendanceRecords($conn, $period, $is_student ? $current_user_id : null);
$stats = getAttendanceStats($conn, $period, $is_student ? $current_user_id : null);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My Attendance - Library System</title>
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
            if (!current) current = 'Attendance-logs.php';
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
        .material-icons {
            font-size: 20px;
            vertical-align: middle;
        }
    </style>
</head>
<body class="font-display bg-background-light dark:bg-background-dark text-slate-700 dark:text-slate-300">
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
                    href="admin.php">
                    <span class="material-icons mr-3">dashboard</span>
                    Dashboard
                </a>
                <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
                    href="book-management.php">
                    <span class="material-icons mr-3">menu_book</span>
                    Book Management
                </a>
                <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
                    href="user-management.php">
                    <span class="material-icons mr-3">group</span>
                    User Management
                </a>
                <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
                    href="borrow.php">
                    <span class="material-icons mr-3">shopping_cart</span>
                    My Borrowing
                </a>

                <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
                    href="Overdue-alerts.php">
                    <span class="material-icons mr-3">warning</span>
                    Overdue Alerts
                </a>

                <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
                    href="backup-restore.php">
                    <span class="material-icons mr-3">backup</span>
                    Backup & Restore
                </a>

                <a class="flex items-center px-4 py-2 text-sm font-medium bg-primary text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
                    href="Attendance-logs.php">
                    <span class="material-icons mr-3">event_available</span>
                    Attendance Logs
                </a>
                <a class="flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white rounded-md transform transition-all duration-150 hover:translate-x-1 hover:shadow-md"
                    href="change-password.php">
                    <span class="material-icons mr-3">lock</span>
                    Change Password
                </a>
            </nav>
        </aside>
        <div class="flex-1 flex flex-col">
            <header
                class="h-16 flex items-center justify-between px-8 bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center md:hidden mr-4">
                    <button id="menu-btn" aria-expanded="false"
                        class="p-2 text-slate-600 dark:text-slate-300 hover:text-slate-800 dark:hover:text-slate-100"
                        aria-label="Open sidebar">
                        <span class="material-icons">menu</span>
                    </button>
                </div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100"><?php echo $is_student ? 'Student Dashboard' : 'Admin Dashboard'; ?></h1>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="font-medium text-sm text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                    </div>
                </div>
            </header>
            <div class="flex-1 overflow-y-auto p-8 bg-background-light dark:bg-background-dark">
            <div class="max-w-6xl mx-auto space-y-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="material-icons-outlined text-gray-700 dark:text-gray-300">calendar_today</span>
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $is_student ? 'My Attendance' : 'Attendance Logs'; ?></h2>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400"><?php echo $is_student ? 'View your attendance history.' : 'Monitor student visits and library usage patterns.'; ?></p>
                    </div>
                    <button id="export-btn"
                        class="flex items-center gap-2 px-4 py-2 border border-border-light dark:border-gray-600 bg-white dark:bg-surface-dark rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
                        <span class="material-icons-outlined text-lg">download</span>
                        Export Logs
                    </button>
                </div>
                <div
                    class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2"><?php echo $is_student ? 'My Visits' : 'Total Visitors'; ?></h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">View visitor statistics by time period</p>
                    <div class="bg-[#FFF8E7] dark:bg-[#3d3d3d] p-1 rounded-lg flex mb-6">
                        <button id="week-btn"
                            class="flex-1 py-1.5 px-4 rounded-md bg-white dark:bg-surface-dark shadow-sm text-sm font-medium text-gray-800 dark:text-white transition-all">Week</button>
                        <button id="month-btn"
                            class="flex-1 py-1.5 px-4 rounded-md text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white transition-colors">Month</button>
                        <button id="year-btn"
                            class="flex-1 py-1.5 px-4 rounded-md text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white transition-colors">Year</button>
                    </div>
                    <div
                        class="border border-border-light dark:border-border-dark rounded-xl p-8 flex flex-col items-center justify-center text-center">
                        <div class="flex items-center gap-2 text-primary mb-2">
                            <span class="material-icons-outlined text-xl">person</span>
                            <span id="period-text" class="text-sm font-medium">This Week</span>
                        </div>
                        <div id="visitor-count" class="text-4xl font-bold text-gray-800 dark:text-white mb-1"><?php echo $stats['total_visitors']; ?></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide"><?php echo $is_student ? 'Total visits' : 'Total visitors'; ?></div>
                    </div>
                </div>
                <div
                    class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark shadow-sm p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-icons-outlined text-gray-700 dark:text-gray-300">filter_alt</span>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Filter Logs</h3>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="flex-1 relative">
                            <span class="material-icons-outlined absolute left-3 top-2.5 text-gray-400">search</span>
                            <input
                                class="w-full pl-10 pr-4 py-2 bg-[#FFF8E7] dark:bg-[#3d3d3d] border-none rounded-lg text-sm text-gray-700 dark:text-gray-200 placeholder-gray-400 focus:ring-2 focus:ring-primary focus:outline-none"
                                placeholder="Search by student name or ID..." type="text" />
                        </div>
                        <div class="w-full sm:w-48 relative">
                            <select
                                class="w-full pl-4 pr-10 py-2 bg-[#FFF8E7] dark:bg-[#3d3d3d] border-none rounded-lg text-sm text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-primary focus:outline-none appearance-none cursor-pointer">
                                <option>Today</option>
                                <option>Yesterday</option>
                                <option>Last 7 Days</option>
                                <option>This Month</option>
                            </select>
                            <span
                                class="material-icons-outlined absolute right-3 top-2.5 text-gray-400 pointer-events-none text-sm">expand_more</span>
                        </div>
                    </div>
                </div>
                <div
                    class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark shadow-sm p-6">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Attendance Records</h3>
                        <p class="text-sm text-primary font-medium mt-1">Showing <?php echo count($attendance_records); ?> records</p>
                    </div>
                    <div class="space-y-4">
                        <?php foreach ($attendance_records as $record): ?>
                        <div
                            class="group bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between hover:shadow-md dark:hover:bg-[#363636] transition-all cursor-pointer">
                            <div class="flex items-start gap-4">
                                <div
                                    class="text-center pr-4 border-r border-border-light dark:border-border-dark min-w-[60px]">
                                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-200"><?php echo date('M d', strtotime($record['check_in_date'])); ?></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo date('D', strtotime($record['check_in_date'])); ?></div>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="material-icons-outlined text-gray-400 text-sm">person</span>
                                        <span class="font-medium text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($record['username']); ?></span>
                                        <span
                                            class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded-full border border-gray-200 dark:border-gray-600"><?php echo htmlspecialchars($record['email']); ?></span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 pl-6">Check-in: <?php echo date('H:i', strtotime($record['check_in_time'])); ?></div>
                                </div>
                            </div>
                            <div class="mt-4 sm:mt-0 text-right pl-6">
                                <div class="text-sm font-medium text-gray-800 dark:text-gray-200"><?php echo ucfirst($record['status']); ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Library attendance</div>
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
        </div>
    </div>
    <script>
        // Sidebar toggle for small screens (show/hide with backdrop)
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
        })();

        // Attendance period toggle
        (function () {
            const weekBtn = document.getElementById('week-btn');
            const monthBtn = document.getElementById('month-btn');
            const yearBtn = document.getElementById('year-btn');
            const periodSpan = document.getElementById('period-text');
            const countDiv = document.getElementById('visitor-count');

            function setActive(btn) {
                [weekBtn, monthBtn, yearBtn].forEach(b => {
                    b.classList.remove('bg-white', 'dark:bg-surface-dark', 'shadow-sm', 'text-gray-800', 'dark:text-white');
                    b.classList.add('text-gray-600', 'dark:text-gray-400', 'hover:text-gray-800', 'dark:hover:text-white', 'transition-colors');
                });
                btn.classList.add('bg-white', 'dark:bg-surface-dark', 'shadow-sm', 'text-gray-800', 'dark:text-white');
                btn.classList.remove('text-gray-600', 'dark:text-gray-400', 'hover:text-gray-800', 'dark:hover:text-white', 'transition-colors');
            }

            weekBtn.addEventListener('click', () => {
                setActive(weekBtn);
                periodSpan.textContent = 'This Week';
                window.location.href = '?period=week';
            });

            monthBtn.addEventListener('click', () => {
                setActive(monthBtn);
                periodSpan.textContent = 'This Month';
                window.location.href = '?period=month';
            });

            yearBtn.addEventListener('click', () => {
                setActive(yearBtn);
                periodSpan.textContent = 'This Year';
                window.location.href = '?period=year';
            });

            // Set initial active button based on URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const period = urlParams.get('period') || 'week';
            if (period === 'month') setActive(monthBtn);
            else if (period === 'year') setActive(yearBtn);
            else setActive(weekBtn);
        })();

        // Export logs functionality
        (function () {
            const exportBtn = document.getElementById('export-btn');

            exportBtn.addEventListener('click', () => {
                // Get current date for filename
                const now = new Date();
                const dateStr = now.toISOString().split('T')[0]; // YYYY-MM-DD format
                const filename = `attendance-logs-${dateStr}.csv`;

                // Prepare CSV header
                let csvContent = 'Date,Day,Student Name,Student Email,Check-in Time,Status\n';

                // Get all attendance records
                const records = document.querySelectorAll('.group.bg-white, .group.bg-surface-dark');
                records.forEach(record => {
                    const date = record.querySelector('.text-sm.font-semibold')?.textContent || '';
                    const day = record.querySelector('.text-xs.text-gray-500')?.textContent || '';
                    const name = record.querySelector('.font-medium.text-gray-800, .font-medium.text-gray-200')?.textContent || '';
                    const email = record.querySelector('.text-xs.bg-gray-100, .text-xs.bg-gray-700')?.textContent || '';
                    const time = record.querySelector('.text-xs.text-gray-500.pl-6')?.textContent.replace('Check-in: ', '') || '';
                    const status = record.querySelector('.text-sm.font-medium.text-gray-800, .text-sm.font-medium.text-gray-200')?.textContent || '';

                    // Clean up the data
                    const cleanDate = date.replace(/\s+/g, ' ').trim();
                    const cleanDay = day.trim();
                    const cleanName = name.trim();
                    const cleanEmail = email.replace(/[()]/g, '').trim();
                    const cleanTime = time.trim();
                    const cleanStatus = status.trim();

                    // Add row to CSV
                    csvContent += `"${cleanDate}","${cleanDay}","${cleanName}","${cleanEmail}","${cleanTime}","${cleanStatus}"\n`;
                });

                // Create and download the file
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        })();
    </script>
</body>

</html>
<?php
$conn->close();
?>
