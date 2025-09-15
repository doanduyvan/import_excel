<?php
/************ DB CONFIG (chỉnh lại cho phù hợp) ************/
$DB_HOST = '127.0.0.1';
$DB_NAME = 'odin';
$DB_USER = 'odin';
$DB_PASS = 'Odin123456@';
$DB_CHARSET = 'utf8mb4';
/***********************************************************/

$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) {
    http_response_code(500);
    echo "DB connection failed.";
    exit;
}

// Helper escape
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Lấy tham số filter
$fullname = isset($_GET['fullname']) ? trim($_GET['fullname']) : "";

// Lấy datalist: chỉ fullname có phát sinh sales (distinct)
$sqlNames = "
    SELECT DISTINCT u.fullname
    FROM users u
    INNER JOIN customer_account ca ON ca.user_id = u.id
    INNER JOIN customers cu        ON cu.customer_account_id = ca.id
    INNER JOIN sales sa            ON sa.customer_id = cu.id
    WHERE u.fullname IS NOT NULL AND u.fullname <> ''
    ORDER BY u.fullname ASC
";
$names = $pdo->query($sqlNames)->fetchAll();

// Base query chính
$sql = "
SELECT
    u.fullname,
    cu.customer_code,
    cu.customer_name,
    sa.order_number,
    sa.commercial_quantity,
    sa.invoice_confirmed_date,
    sa.expiry_date,
    va.item_short_description
FROM users u
INNER JOIN customer_account ca ON ca.user_id = u.id
INNER JOIN customers cu        ON cu.customer_account_id = ca.id
INNER JOIN sales sa            ON sa.customer_id = cu.id
INNER JOIN variants_sales vs   ON vs.sale_id = sa.id
INNER JOIN variants va         ON va.id = vs.variant_id
";

$params = [];
if ($fullname !== "") {
    $sql .= " WHERE u.fullname LIKE :fullname";
    $params[':fullname'] = "%{$fullname}%";
}

// Sort: ngày mới nhất trước, NULL xuống cuối
$sql .= " ORDER BY sa.invoice_confirmed_date IS NULL, sa.invoice_confirmed_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Sales Report</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{padding:1.25rem}
    .small-muted{font-size:.95rem;color:#6c757d}
  </style>
</head>
<body>
<div class="container">
  <h3 class="mb-3">Sales Report</h3>

  <!-- Form filter (GET) -->
  <form id="filterForm" method="get" class="row g-2 mb-3" autocomplete="off">
    <div class="col-sm-6 col-md-4">
      <input list="fullnames" id="fullname" name="fullname"
             value="<?= h($fullname) ?>" class="form-control"
             placeholder="Nhập hoặc chọn fullname...">
      <datalist id="fullnames">
        <?php foreach ($names as $n): ?>
          <option value="<?= h($n['fullname']) ?>"></option>
        <?php endforeach; ?>
      </datalist>
      <div class="small-muted mt-1">Gõ/chọn tên để lọc, trang tự động cập nhật.</div>
    </div>
    <div class="col-sm-6 col-md-4 d-flex gap-2">
      <button type="button" id="btnReset" class="btn btn-outline-secondary">Reset</button>
    </div>
  </form>

  <div class="mb-2 text-muted">Tổng: <strong><?= count($rows) ?></strong> bản ghi</div>

  <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th>Fullname</th>
          <th>Customer Code</th>
          <th>Customer Name</th>
          <th>Order Number</th>
          <th class="text-end">Quantity</th>
          <th>Invoice Date</th>
          <th>Expiry Date</th>
          <th>Item</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted">Không có dữ liệu</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h($r['fullname']) ?></td>
            <td><?= h($r['customer_code']) ?></td>
            <td><?= h($r['customer_name']) ?></td>
            <td><?= h($r['order_number']) ?></td>
            <td class="text-end"><?= h($r['commercial_quantity']) ?></td>
            <td><?= h($r['invoice_confirmed_date']) ?></td>
            <td><?= h($r['expiry_date']) ?></td>
            <td><?= h($r['item_short_description']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Debounce để tránh submit quá dày khi gõ nhanh
function debounce(fn, delay){
  let t; return function(...args){
    clearTimeout(t); t = setTimeout(()=>fn.apply(this,args), delay);
  }
}

const form = document.getElementById('filterForm');
const input = document.getElementById('fullname');
const resetBtn = document.getElementById('btnReset');

// Tự động submit khi gõ/chọn datalist (delay 300ms)
input.addEventListener('input', debounce(()=> { form.submit(); }, 300));

// Reset về trạng thái mặc định
resetBtn.addEventListener('click', ()=>{
  input.value = '';
  form.submit();
});
</script>
</body>
</html>
