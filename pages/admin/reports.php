<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
session_start();

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

// Set default date range (last 30 days)
$default_start = date('Y-m-d', strtotime('-30 days'));
$default_end = date('Y-m-d');

// Get filter parameters
$start_date = $_GET['start_date'] ?? $default_start;
$end_date = $_GET['end_date'] ?? $default_end;
$report_type = $_GET['report_type'] ?? 'revenue';

// Add one day to end date for inclusive results
$end_date_query = date('Y-m-d', strtotime($end_date . ' +1 day'));

// Prepare the query based on report type
if ($report_type === 'revenue') {
    $sql = "SELECT DATE(entry_time) as date, SUM(amount_paid) as total
            FROM bookings
            WHERE entry_time BETWEEN ? AND ?
            AND payment_status = 'completed'
            GROUP BY DATE(entry_time)
            ORDER BY date";
} elseif ($report_type === 'occupancy') {
    $sql = "SELECT DATE(entry_time) as date, COUNT(*) as total
            FROM bookings
            WHERE entry_time BETWEEN ? AND ?
            GROUP BY DATE(entry_time)
            ORDER BY date";
} elseif ($report_type === 'space_usage') {
    $sql = "SELECT ps.space_type, COUNT(b.id) as bookings, SUM(b.amount_paid) as revenue
            FROM parking_spaces ps
            LEFT JOIN bookings b ON ps.id = b.space_id AND b.entry_time BETWEEN ? AND ?
            GROUP BY ps.space_type
            ORDER BY bookings DESC";
} else {
    $sql = "SELECT DATE(entry_time) as date, SUM(amount_paid) as total
            FROM bookings
            WHERE entry_time BETWEEN ? AND ?
            AND payment_status = 'completed'
            GROUP BY DATE(entry_time)
            ORDER BY date";
}

// Execute the query
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date_query);
$stmt->execute();
$result = $stmt->get_result();

// Get summary stats
$summary_sql = "SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN payment_status = 'completed' THEN amount_paid ELSE 0 END) as total_revenue,
                COUNT(DISTINCT user_id) as unique_users,
                AVG(TIMESTAMPDIFF(HOUR, entry_time, IFNULL(exit_time, NOW()))) as avg_duration
                FROM bookings
                WHERE entry_time BETWEEN ? AND ?";

$stmt = $conn->prepare($summary_sql);
$stmt->bind_param("ss", $start_date, $end_date_query);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - ParkSmart Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../../includes/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-chart-bar"></i> Reports</h1>
                <p>Analyze parking data and performance metrics</p>
            </div>
            
            <div class="report-controls">
                <form action="" method="GET" class="report-filter-form">
                    <div class="form-group">
                        <label for="report_type">Report Type</label>
                        <select id="report_type" name="report_type" onchange="this.form.submit()">
                            <option value="revenue" <?php echo ($report_type === 'revenue') ? 'selected' : ''; ?>>Revenue</option>
                            <option value="occupancy" <?php echo ($report_type === 'occupancy') ? 'selected' : ''; ?>>Occupancy</option>
                            <option value="space_usage" <?php echo ($report_type === 'space_usage') ? 'selected' : ''; ?>>Space Usage</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="reports.php" class="btn btn-secondary">Reset</a>
                </form>
            </div>
            
            <div class="report-summary">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Total Bookings</h3>
                        <p class="summary-value"><?php echo $summary['total_bookings']; ?></p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Total Revenue</h3>
                        <p class="summary-value"><?php echo formatCurrency($summary['total_revenue']); ?></p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Unique Users</h3>
                        <p class="summary-value"><?php echo $summary['unique_users']; ?></p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Avg. Duration</h3>
                        <p class="summary-value"><?php echo round($summary['avg_duration'], 1); ?> hours</p>
                    </div>
                </div>
            </div>
            
            <div class="report-visualization">
                <div class="chart-container">
                    <canvas id="reportChart"></canvas>
                </div>
            </div>
            
            <div class="report-data">
                <h2><?php echo ucfirst($report_type); ?> Data</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <?php if ($report_type === 'space_usage'): ?>
                                    <th>Space Type</th>
                                    <th>Total Bookings</th>
                                    <th>Total Revenue</th>
                                <?php else: ?>
                                    <th>Date</th>
                                    <th><?php echo ($report_type === 'revenue') ? 'Revenue' : 'Bookings'; ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <?php if ($report_type === 'space_usage'): ?>
                                            <td><?php echo getSpaceTypeLabel($row['space_type']); ?></td>
                                            <td><?php echo $row['bookings']; ?></td>
                                            <td><?php echo formatCurrency($row['revenue']); ?></td>
                                        <?php else: ?>
                                            <td><?php echo formatDateTime($row['date'], 'M d, Y'); ?></td>
                                            <td><?php echo ($report_type === 'revenue') ? formatCurrency($row['total']) : $row['total']; ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <?php if ($report_type === 'space_usage'): ?>
                                        <td colspan="3" class="text-center">No data available for the selected period</td>
                                    <?php else: ?>
                                        <td colspan="2" class="text-center">No data available for the selected period</td>
                                    <?php endif; ?>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="report-actions">
                    <button class="btn btn-primary" onclick="printReport()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <button class="btn btn-secondary" onclick="exportCSV()">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        // Prepare data for chart
        const ctx = document.getElementById('reportChart').getContext('2d');
        
        <?php if ($report_type === 'space_usage'): ?>
            const labels = [
                <?php 
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "'" . getSpaceTypeLabel($row['space_type']) . "',";
                    }
                ?>
            ];
            
            const data = [
                <?php 
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo $row['bookings'] . ",";
                    }
                ?>
            ];
            
            const revenueData = [
                <?php 
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo $row['revenue'] . ",";
                    }
                ?>
            ];
            
            const myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Bookings',
                        data: data,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Revenue',
                        data: revenueData,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Bookings'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Revenue ($)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        <?php else: ?>
            const labels = [
                <?php 
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "'" . formatDateTime($row['date'], 'M d') . "',";
                    }
                ?>
            ];
            
            const data = [
                <?php 
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo $row['total'] . ",";
                    }
                ?>
            ];
            
            const myChart = new Chart(ctx, {
                type: '<?php echo ($report_type === 'revenue') ? 'line' : 'bar'; ?>',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '<?php echo ($report_type === 'revenue') ? 'Revenue ($)' : 'Bookings'; ?>',
                        data: data,
                        backgroundColor: '<?php echo ($report_type === 'revenue') ? 'rgba(75, 192, 192, 0.2)' : 'rgba(54, 162, 235, 0.6)'; ?>',
                        borderColor: '<?php echo ($report_type === 'revenue') ? 'rgba(75, 192, 192, 1)' : 'rgba(54, 162, 235, 1)'; ?>',
                        borderWidth: 2,
                        tension: 0.1,
                        fill: <?php echo ($report_type === 'revenue') ? 'true' : 'false'; ?>
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        <?php endif; ?>
        
        // Print report function
        function printReport() {
            window.print();
        }
        
        // Export to CSV function
        function exportCSV() {
            const rows = document.querySelectorAll('.data-table tbody tr');
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Add header row
            <?php if ($report_type === 'space_usage'): ?>
                csvContent += "Space Type,Total Bookings,Total Revenue\n";
            <?php else: ?>
                csvContent += "Date,<?php echo ($report_type === 'revenue') ? 'Revenue' : 'Bookings'; ?>\n";
            <?php endif; ?>
            
            // Add data rows
            rows.forEach(row => {
                const cols = row.querySelectorAll('td');
                let rowData = [];
                
                cols.forEach(col => {
                    // Escape quotes and wrap in quotes
                    rowData.push(`"${col.innerText.replace(/"/g, '""')}"`);
                });
                
                csvContent += rowData.join(',') + '\n';
            });
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', `parking_${<?php echo json_encode($report_type); ?>}_report.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
