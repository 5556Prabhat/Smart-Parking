<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
session_start();

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

// Handle user actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new user
    if (isset($_POST['add_user'])) {
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        // Validate input
        if (empty($username) || empty($email) || empty($password)) {
            $error = "All fields are required";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters";
        } else {
            // Check if username or email already exists
            $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Username or email already exists";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user
                $sql = "INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $username, $email, $hashed_password, $is_admin);
                
                if ($stmt->execute()) {
                    $success = "User added successfully";
                    logActivity($conn, $_SESSION['user_id'], 'add_user', "Added user: $username");
                } else {
                    $error = "Error adding user: " . $conn->error;
                }
            }
        }
    }
    
    // Update user
    if (isset($_POST['update_user'])) {
        $user_id = intval($_POST['user_id']);
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        $new_password = $_POST['new_password'] ?? '';
        
        // Check if username or email already exists for another user
        $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ssi", $username, $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username or email already exists";
        } else {
            // If password field is not empty, update password
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $error = "Password must be at least 6 characters";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET username = ?, email = ?, password = ?, is_admin = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssii", $username, $email, $hashed_password, $is_admin, $user_id);
                }
            } else {
                $sql = "UPDATE users SET username = ?, email = ?, is_admin = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssii", $username, $email, $is_admin, $user_id);
            }
            
            if ($stmt->execute()) {
                $success = "User updated successfully";
                logActivity($conn, $_SESSION['user_id'], 'update_user', "Updated user: $username (ID: $user_id)");
            } else {
                $error = "Error updating user: " . $conn->error;
            }
        }
    }
    
    // Delete user
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        
        // Check if user has bookings
        $check_sql = "SELECT COUNT(*) as booking_count FROM bookings WHERE user_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['booking_count'] > 0) {
            $error = "Cannot delete user with existing bookings";
        } else {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success = "User deleted successfully";
                logActivity($conn, $_SESSION['user_id'], 'delete_user', "Deleted user ID: $user_id");
            } else {
                $error = "Error deleting user: " . $conn->error;
            }
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search/filter
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter_admin = isset($_GET['is_admin']) ? sanitizeInput($_GET['is_admin']) : '';

// Build query
$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_clauses[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= 'ss';
}

if ($filter_admin !== '') {
    $where_clauses[] = "is_admin = ?";
    $params[] = $filter_admin;
    $param_types .= 'i';
}

$where_clause = empty($where_clauses) ? "" : "WHERE " . implode(" AND ", $where_clauses);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM users $where_clause";
$stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$total_records = $stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_records / $limit);

// Get users
$sql = "SELECT * FROM users $where_clause ORDER BY id LIMIT ?, ?";
$stmt = $conn->prepare($sql);

$param_types .= 'ii';
$params[] = $offset;
$params[] = $limit;

$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$users = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - ParkSmart Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-body">
    <?php include '../../includes/header.php'; ?>

    <div class="admin-container">
        <div class="admin-sidebar">
            <h3>Admin Menu</h3>
            <ul>
                <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="spaces.php"><i class="fas fa-parking"></i> Parking Spaces</a></li>
                <li><a href="bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a></li>
                <li><a href="users.php" class="active"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../../includes/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-users"></i> User Management</h1>
                <button class="btn btn-primary" onclick="toggleAddModal()">
                    <i class="fas fa-user-plus"></i> Add New User
                </button>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <div class="filter-bar">
                <form action="" method="GET" class="filter-form">
                    <div class="filter-group">
                        <input type="text" name="search" placeholder="Search username or email..." value="<?php echo $search; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <select name="is_admin">
                            <option value="">All Users</option>
                            <option value="1" <?php echo ($filter_admin === '1') ? 'selected' : ''; ?>>Admins Only</option>
                            <option value="0" <?php echo ($filter_admin === '0') ? 'selected' : ''; ?>>Regular Users</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="users.php" class="btn btn-secondary">Reset</a>
                </form>
            </div>
            
            <div class="data-overview">
                <div class="data-card">
                    <div class="data-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="data-content">
                        <h3>Total Users</h3>
                        <p class="data-value"><?php echo $total_records; ?></p>
                    </div>
                </div>
                
                <?php 
                // Get admin count
                $sql = "SELECT COUNT(*) as count FROM users WHERE is_admin = 1";
                $result = $conn->query($sql);
                $admin_count = $result->fetch_assoc()['count'];
                
                // Get regular user count
                $sql = "SELECT COUNT(*) as count FROM users WHERE is_admin = 0";
                $result = $conn->query($sql);
                $regular_count = $result->fetch_assoc()['count'];
                
                // Get recent user count
                $sql = "SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                $result = $conn->query($sql);
                $recent_count = $result->fetch_assoc()['count'];
                ?>
                
                <div class="data-card primary">
                    <div class="data-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="data-content">
                        <h3>Admin Users</h3>
                        <p class="data-value"><?php echo $admin_count; ?></p>
                    </div>
                </div>
                
                <div class="data-card success">
                    <div class="data-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="data-content">
                        <h3>Regular Users</h3>
                        <p class="data-value"><?php echo $regular_count; ?></p>
                    </div>
                </div>
                
                <div class="data-card info">
                    <div class="data-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="data-content">
                        <h3>New Users (30d)</h3>
                        <p class="data-value"><?php echo $recent_count; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo $user['username']; ?></td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="badge badge-admin">Admin</span>
                                        <?php else: ?>
                                            <span class="badge badge-user">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDateTime($user['created_at']); ?></td>
                                    <td class="actions">
                                        <button class="btn-icon edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): // Prevent self-deletion ?>
                                            <button class="btn-icon delete-btn" onclick="confirmDelete(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php echo createPagination($page, $total_pages, 'users.php'); ?>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal" id="addUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New User</h2>
                <span class="close" onclick="toggleAddModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_admin" name="is_admin">
                            <label for="is_admin">Administrator Access</label>
                        </div>
                    </div>
                    
                    <div class="form-group text-right">
                        <button type="button" class="btn btn-secondary" onclick="toggleAddModal()">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal" id="editUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User</h2>
                <span class="close" onclick="toggleEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    
                    <div class="form-group">
                        <label for="edit_username">Username</label>
                        <input type="text" id="edit_username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password (leave blank to keep current)</label>
                        <input type="password" id="new_password" name="new_password">
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="edit_is_admin" name="is_admin">
                            <label for="edit_is_admin">Administrator Access</label>
                        </div>
                    </div>
                    
                    <div class="form-group text-right">
                        <button type="button" class="btn btn-secondary" onclick="toggleEditModal()">Cancel</button>
                        <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <span class="close" onclick="toggleDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                <form action="" method="POST" id="delete-form">
                    <input type="hidden" id="delete_user_id" name="user_id">
                    
                    <div class="form-group text-right">
                        <button type="button" class="btn btn-secondary" onclick="toggleDeleteModal()">Cancel</button>
                        <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        // Modal functionality
        const addModal = document.getElementById('addUserModal');
        const editModal = document.getElementById('editUserModal');
        const deleteModal = document.getElementById('deleteModal');
        
        function toggleAddModal() {
            addModal.classList.toggle('show-modal');
        }
        
        function toggleEditModal() {
            editModal.classList.toggle('show-modal');
        }
        
        function toggleDeleteModal() {
            deleteModal.classList.toggle('show-modal');
        }
        
        // Edit user modal
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_is_admin').checked = user.is_admin == 1;
            
            toggleEditModal();
        }
        
        // Delete confirmation
        function confirmDelete(userId) {
            document.getElementById('delete_user_id').value = userId;
            toggleDeleteModal();
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target === addModal) {
                toggleAddModal();
            } else if (event.target === editModal) {
                toggleEditModal();
            } else if (event.target === deleteModal) {
                toggleDeleteModal();
            }
        };
        
        // Password confirmation validation
        document.getElementById('password').addEventListener('input', validatePasswords);
        document.getElementById('confirm_password').addEventListener('input', validatePasswords);
        
        function validatePasswords() {
            const password = document.getElementById('password');
            const confirm = document.getElementById('confirm_password');
            
            if (confirm.value && password.value !== confirm.value) {
                confirm.setCustomValidity("Passwords don't match");
            } else {
                confirm.setCustomValidity('');
            }
        }
    </script>
</body>
</html>
