<?php
// الصفحة الرئيسية - لوحة التحكم
session_start();
require_once 'config/db_connect.php';
require_once 'includes/auth_check.php';

$page_title = 'لوحة التحكم';
$css_file = 'dashboard.css';
$js_file = 'dashboard.js';
$current_module = 'dashboard';

include 'includes/header.php';

// إحصائيات النظام
$stats = [];

// إجمالي المبيعات اليوم
$stmt = $db->prepare("SELECT SUM(total_amount) as total FROM sales WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$stats['today_sales'] = $stmt->fetch()['total'] ?? 0;

// عدد الفواتير اليوم
$stmt = $db->prepare("SELECT COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$stats['today_invoices'] = $stmt->fetch()['count'];

// الأدوية منخفضة المخزون
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM stock s 
    WHERE s.quantity <= s.reorder_level AND s.quantity > 0
");
$stmt->execute();
$stats['low_stock'] = $stmt->fetch()['count'];

// الأدوية القريبة من انتهاء الصلاحية
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM stock s 
    WHERE s.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
    AND s.expiry_date > CURDATE()
");
$stmt->execute();
$stats['expiring_soon'] = $stmt->fetch()['count'];

// مبيعات الأسبوع
$stmt = $db->prepare("
    SELECT DATE(created_at) as date, SUM(total_amount) as total 
    FROM sales 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute();
$weekly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// أحدث الفواتير
$stmt = $db->prepare("
    SELECT s.*, u.full_name as cashier_name 
    FROM sales s 
    LEFT JOIN users u ON s.created_by = u.id 
    ORDER BY s.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// الأدوية الأكثر مبيعاً
$stmt = $db->prepare("
    SELECT m.name, SUM(si.quantity) as total_sold 
    FROM sales_items si
    JOIN medicines m ON si.medicine_id = m.id
    WHERE si.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY m.id 
    ORDER BY total_sold DESC 
    LIMIT 10
");
$stmt->execute();
$top_selling = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard">
    <!-- إحصائيات سريعة -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card blue">
                <i class="fa fa-shopping-cart fa-2x"></i>
                <div class="stat-value"><?php echo number_format($stats['today_sales'], 2); ?> <?php echo CURRENCY_SYMBOL; ?></div>
                <div class="stat-label">مبيعات اليوم</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card green">
                <i class="fa fa-file-invoice fa-2x"></i>
                <div class="stat-value"><?php echo $stats['today_invoices']; ?></div>
                <div class="stat-label">فواتير اليوم</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card orange">
                <i class="fa fa-exclamation-triangle fa-2x"></i>
                <div class="stat-value"><?php echo $stats['low_stock']; ?></div>
                <div class="stat-label">أدوية منخفضة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card red">
                <i class="fa fa-clock fa-2x"></i>
                <div class="stat-value"><?php echo $stats['expiring_soon']; ?></div>
                <div class="stat-label">تنتهي قريباً</div>
            </div>
        </div>
    </div>

    <!-- الصف الثاني: الرسوم البيانية والقوائم -->
    <div class="row">
        <!-- مبيعات الأسبوع -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-chart-line"></i> مبيعات الأسبوع
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <!-- الإجراءات السريعة -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-bolt"></i> إجراءات سريعة
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="modules/sales/pos.php" class="btn btn-primary btn-lg">
                            <i class="fa fa-plus-circle"></i> فاتورة جديدة
                        </a>
                        <a href="modules/inventory/medicines.php" class="btn btn-success">
                            <i class="fa fa-medkit"></i> إضافة أدوية
                        </a>
                        <a href="modules/patients/patients.php" class="btn btn-info">
                            <i class="fa fa-user-plus"></i> إضافة مريض
                        </a>
                        <a href="modules/reports/financial.php" class="btn btn-warning">
                            <i class="fa fa-chart-bar"></i> تقرير مالي
                        </a>
                        <a href="modules/inventory/expiry_alerts.php" class="btn btn-danger">
                            <i class="fa fa-exclamation-triangle"></i> تنبيهات الصلاحية
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- الصف الثالث: الجداول -->
    <div class="row mt-4">
        <!-- أحدث الفواتير -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-receipt"></i> أحدث الفواتير
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>رقم الفاتورة</th>
                                    <th>المبلغ</th>
                                    <th>الطريقة</th>
                                    <th>الكاشير</th>
                                    <th>الوقت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_sales as $sale): ?>
                                <tr>
                                    <td>
                                        <a href="modules/sales/invoice_details.php?id=<?php echo $sale['id']; ?>">
                                            <?php echo $sale['invoice_number']; ?>
                                        </a>
                                    </td>
                                    <td class="text-success"><?php echo number_format($sale['total_amount'], 2); ?> <?php echo CURRENCY_SYMBOL; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $sale['payment_method'] == 'cash' ? 'success' : 
                                                 ($sale['payment_method'] == 'card' ? 'primary' : 'info'); 
                                        ?>">
                                            <?php echo $sale['payment_method']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $sale['cashier_name']; ?></td>
                                    <td><?php echo date('H:i', strtotime($sale['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الأدوية الأكثر مبيعاً -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-star"></i> الأدوية الأكثر مبيعاً
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>الدواء</th>
                                    <th>الكمية المباعة</th>
                                    <th>الإيرادات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_selling as $item): ?>
                                <tr>
                                    <td><?php echo $item['name']; ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $item['total_sold']; ?></span>
                                    </td>
                                    <td class="text-success">
                                        <?php 
                                        $revenue = $item['total_sold'] * 10; // افتراضي
                                        echo number_format($revenue, 2) . ' ' . CURRENCY_SYMBOL; 
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- معلومات النظام -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-info-circle"></i> معلومات النظام
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="info-item">
                                <i class="fa fa-database text-primary"></i>
                                <strong>إصدار النظام:</strong> 1.0.0
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-item">
                                <i class="fa fa-calendar text-success"></i>
                                <strong>تاريخ اليوم:</strong> 
                                <span id="current-date-display"><?php echo date('Y-m-d'); ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-item">
                                <i class="fa fa-clock text-warning"></i>
                                <strong>الوقت الحالي:</strong> 
                                <span id="current-time-display"><?php echo date('H:i:s'); ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-item">
                                <i class="fa fa-user text-info"></i>
                                <strong>المستخدم:</strong> 
                                <?php echo $_SESSION['username']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// الرسم البياني لمبيعات الأسبوع
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($weekly_sales, 'date')); ?>,
        datasets: [{
            label: 'المبيعات',
            data: <?php echo json_encode(array_column($weekly_sales, 'total')); ?>,
            borderColor: '#3498db',
            backgroundColor: 'rgba(52, 152, 219, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value + ' <?php echo CURRENCY_SYMBOL; ?>';
                    }
                }
            }
        }
    }
});

// تحديث الوقت
function updateDateTime() {
    const now = new Date();
    document.getElementById('current-time-display').textContent = 
        now.toLocaleTimeString('ar-SA');
    document.getElementById('current-date-display').textContent = 
        now.toLocaleDateString('ar-SA');
}
setInterval(updateDateTime, 1000);
updateDateTime();
</script>

<?php
include 'includes/footer.php';
?>