<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
session_start();

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

// Handle space actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new space
    if (isset($_POST['add_space'])) {
        $space_number = sanitizeInput($_POST['space_number']);
        $space_type = sanitizeInput($_POST['space_type']);
        $hourly_rate = floatval($_POST['hourly_rate']);
        $status = sanitizeInput($_POST['status']);
        
        // Check if space number already exists
        $check_sql = "SELECT id FROM parking_spaces WHERE space_number = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $space_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Space number already exists";
        } else {
            $sql = "INSERT INTO parking_spaces (space_number, space_type, hourly_rate, status) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssds", $space_number, $space_type, $hourly_rate, $status);
            
            if ($stmt->execute()) {
                $success = "Parking space added successfully";
                logActivity($conn, $_SESSION['user_id'], 'add_space', "Added parking space: $space_number");
            } else {
                $error = "Error adding parking space: " . $conn->error;
            }
        }
    }
    
    // Update space
    if (isset($_POST['update_space'])) {
        $space_id = intval($_POST['space_id']);
        $space_number = sanitizeInput($_POST['space_number']);
        $space_type = sanitizeInput($_POST['space_type']);
        $hourly_rate = floatval($_POST['hourly_rate']);
        $status = sanitizeInput($_POST['status']);
        
        // Check if space number already exists for another space
        $check_sql = "SELECT id FROM parking_spaces WHERE space_number = ? AND id != ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("si", $space_number, $space_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Space number already exists";
        } else {
            $sql = "UPDATE parking_spaces SET space_number = ?, space_type = ?, hourly_rate = ?, status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdsi", $space_number, $space_type, $hourly_rate, $status, $space_id);
            
            if ($stmt->execute()) {
                $success = "Parking space updated successfully";
                logActivity($conn, $_SESSION['user_id'], 'update_space', "Updated parking space: $space_number (ID: $space_id)");
            } else {
                $error = "Error updating parking space: " . $conn->error;
            }
        }
    }
    
    // Delete space
    if (isset($_POST['delete_space'])) {
        $space_id = intval($_POST['space_id']);
        
        // Check if space has bookings
        $check_sql = "SELECT COUNT(*) as booking_count FROM bookings WHERE space_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $space_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['booking_count'] > 0) {
            $error = "Cannot delete space with existing bookings";
        } else {
            $sql = "DELETE FROM parking_spaces WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $space_id);
            
            if ($stmt->execute()) {
                $success = "Parking space deleted successfully";
                logActivity($conn, $_SESSION['user_id'], 'delete_space', "Deleted parking space ID: $space_id");
            } else {
                $error = "Error deleting parking space: " . $conn->error;
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
$filter_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query
$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_clauses[] = "space_number LIKE ?";
    $params[] = "%$search%";
    $param_types .= 's';
}

if (!empty($filter_type)) {
    $where_clauses[] = "space_type = ?";
    $params[] = $filter_type;
    $param_types .= 's';
}

if (!empty($filter_status)) {
    $where_clauses[] = "status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

$where_clause = empty($where_clauses) ? "" : "WHERE " . implode(" AND ", $where_clauses);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM parking_spaces $where_clause";
$stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$total_records = $stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_records / $limit);

// Get spaces
$sql = "SELECT * FROM parking_spaces $where_clause ORDER BY space_number LIMIT ?, ?";
$stmt = $conn->prepare($sql);

$param_types .= 'ii';
$params[] = $offset;
$params[] = $limit;

$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$spaces = $stmt->get_result();

// Get space types and statuses for dropdowns
$space_types = getSpaceTypes();
$space_statuses = getSpaceStatuses();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Spaces - ParkSmart Admin</title>
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
                <li><a href="spaces.php" class="active"><i class="fas fa-parking"></i> Parking Spaces</a></li>
                <li><a href="bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../../includes/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-parking"></i> Manage Parking Spaces</h1>
                <button class="btn btn-primary" onclick="toggleAddModal()">
                    <i class="fas fa-plus"></i> Add New Space
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
                        <input type="text" name="search" placeholder="Search space number..." value="<?php echo $search; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <select name="type">
                            <option value="">All Types</option>
                            <?php foreach ($space_types as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo ($filter_type === $type) ? 'selected' : ''; ?>>
                                    <?php echo getSpaceTypeLabel($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="status">
                            <option value="">All Statuses</option>
                            <?php foreach ($space_statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo ($filter_status === $status) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="spaces.php" class="btn btn-secondary">Reset</a>
                </form>
            </div>
            
            <div class="data-overview">
                <div class="data-card">
                    <div class="data-icon">
                        <i class="fas fa-parking"></i>
                    </div>
                    <div class="data-content">
                        <h3>Total Spaces</h3>
                        <p class="data-value"><?php echo $total_records; ?></p>
                    </div>
                </div>
                
                <?php 
                // Get counts by status
                foreach ($space_statuses as $status) {
                    $sql = "SELECT COUNT(*) as count FROM parking_spaces WHERE status = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $status);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count = $result->fetch_assoc()['count'];
                    
                    $icon_class = ($status === 'available') ? 'fas fa-check-circle' : 
                                 (($status === 'occupied') ? 'fas fa-car' : 'fas fa-tools');
                    
                    $card_class = ($status === 'available') ? 'success' : 
                                 (($status === 'occupied') ? 'primary' : 'warning');
                ?>
                <div class="data-card <?php echo $card_class; ?>">
                    <div class="data-icon">
                        <i class="<?php echo $icon_class; ?>"></i>
                    </div>
                    <div class="data-content">
                        <h3><?php echo ucfirst($status); ?></h3>
                        <p class="data-value"><?php echo $count; ?></p>
                    </div>
                </div>
                <?php } ?>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Space Number</th>
                            <th>Type</th>
                            <th>Hourly Rate</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($spaces->num_rows > 0): ?>
                            <?php while ($space = $spaces->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $space['id']; ?></td>
                                    <td><?php echo $space['space_number']; ?></td>
                                    <td><?php echo getSpaceTypeLabel($space['space_type']); ?></td>
                                    <td><?php echo formatCurrency($space['hourly_rate']); ?></td>
                                    <td><?php echo getStatusLabel($space['status']); ?></td>
                                    <td class="actions">
                                        <button class="btn-icon edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($space)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon delete-btn" onclick="confirmDelete(<?php echo $space['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No parking spaces found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php echo createPagination($page, $total_pages, 'spaces.php'); ?>
        </div>
    </div>
    
    <!-- Add Space Modal -->
    <div class="modal" id="addSpaceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Parking Space</h2>
                <span class="close" onclick="toggleAddModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="space_number">Space Number</label>
                        <input type="text" id="space_number" name="space_number" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="space_type">Space Type</label>
                        <select id="space_type" name="space_type" required>
                            <?php foreach ($space_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo getSpaceTypeLabel($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="hourly_rate">Hourly Rate ($)</label>
                        <input type="number" id="hourly_rate" name="hourly_rate" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <?php foreach ($space_statuses as $status): ?>
                                <option value="<?php echo $status; ?>"><?php echo ucfirst($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group text-right">
                        <button type="button" class="btn btn-secondary" onclick="toggleAddModal()">Cancel</button>
                        <button type="submit" name="add_space" class="btn btn-primary">Add Space</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Space Modal -->
    <div class="modal" id="editSpaceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Parking Space</h2>
                <span class="close" onclick="toggleEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <input type="hidden" id="edit_space_id" name="space_id">
                    
                    <div class="form-group">
                        <label for="edit_space_number">Space Number</label>
                        <input type="text" id="edit_space_number" name="space_number" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_space_type">Space Type</label>
                        <select id="edit_space_type" name="space_type" required>
                            <?php foreach ($space_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo getSpaceTypeLabel($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_hourly_rate">Hourly Rate ($)</label>
                        <input type="number" id="edit_hourly_rate" name="hourly_rate" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" required>
                            <?php foreach ($space_statuses as $status): ?>
                                <option value="<?php echo $status; ?>"><?php echo ucfirst($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group text-right">
                        <button type="button" class="btn btn-secondary" onclick="toggleEditModal()">Cancel</button>
                        <button type="submit" name="update_space" class="btn btn-primary">Update Space</button>
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
                <p>Are you sure you want to delete this parking space? This action cannot be undone.</p>
                <form action="" method="POST" id="delete-form">
                    <input type="hidden" id="delete_space_id" name="space_id">
                    
                    <div class="form-group text-right">
                        <button type="button" class="btn btn-secondary" onclick="toggleDeleteModal()">Cancel</button>
                        <button type="submit" name="delete_space" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        // Modal functionality
        const addModal = document.getElementById('addSpaceModal');
        const editModal = document.getElementById('editSpaceModal');
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
        
        // Edit space modal
        function openEditModal(space) {
            document.getElementById('edit_space_id').value = space.id;
            document.getElementById('edit_space_number').value = space.space_number;
            document.getElementById('edit_space_type').value = space.space_type;
            document.getElementById('edit_hourly_rate').value = space.hourly_rate;
            document.getElementById('edit_status').value = space.status;
            
            toggleEditModal();
        }
        
        // Delete confirmation
        function confirmDelete(spaceId) {
            document.getElementById('delete_space_id').value = spaceId;
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
    </script>
</body>
</html>
