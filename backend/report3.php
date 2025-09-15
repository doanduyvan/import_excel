<?php
/************ DB CONFIG (chỉnh lại cho phù hợp) ************/
$DB_HOST = '127.0.0.1';
$DB_NAME = 'odin';
$DB_USER = 'odin';
$DB_PASS = 'Odin123456@';
$DB_CHARSET = 'utf8mb4';
/***********************************************************/

// (Tùy chọn) Bật lỗi tạm thời để debug 500 trong môi trường dev
// xong việc nhớ comment 2 dòng sau lại:
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

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

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$validDate = fn($d) => $d !== "" && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;

// --------- Lấy tham số filter ---------
$fullname   = isset($_GET['fullname']) ? trim($_GET['fullname']) : "";
$customer   = isset($_GET['customer_name']) ? trim($_GET['customer_name']) : "";
$item       = isset($_GET['item_short_description']) ? trim($_GET['item_short_description']) : "";
$date_from  = isset($_GET['date_from']) ? trim($_GET['date_from']) : "";
$date_to    = isset($_GET['date_to']) ? trim($_GET['date_to']) : "";
$year       = isset($_GET['year']) ? trim($_GET['year']) : "";
$quarter    = isset($_GET['quarter']) ? trim($_GET['quarter']) : "";

// --------- Server-side fallback cho Năm/Quý -> from/to nếu JS không điền ---------
if ($year !== "") {
    $qMap = [
        "1" => ["01-01", "03-31"],
        "2" => ["04-01", "06-30"],
        "3" => ["07-01", "09-30"],
        "4" => ["10-01", "12-31"],
    ];
    if ($quarter !== "" && isset($qMap[$quarter])) {
        if (!$validDate($date_from)) $date_from = $year . "-" . $qMap[$quarter][0];
        if (!$validDate($date_to))   $date_to   = $year . "-" . $qMap[$quarter][1];
    } else {
        if (!$validDate($date_from)) $date_from = $year . "-01-01";
        if (!$validDate($date_to))   $date_to   = $year . "-12-31";
    }
}

// --------- Helper: áp dụng from/to vào SQL ---------
function appendDateRangeFilters(&$sql, &$params, $date_from, $date_to, $validDate) {
    if ($validDate($date_from)) { $sql .= " AND sa.invoice_confirmed_date >= :date_from"; $params[':date_from'] = $date_from; }
    if ($validDate($date_to))   { $sql .= " AND sa.invoice_confirmed_date <= :date_to";   $params[':date_to']   = $date_to; }
}

/* ==================== DATALIST PHỤ THUỘC (tôn trọng các filter khác + from/to) ==================== */
// 1) FULLNAME (phụ thuộc customer, item, from/to)
$sqlFullnames = "
    SELECT DISTINCT u.fullname
    FROM users u
    INNER JOIN customer_account ca ON ca.user_id = u.id
    INNER JOIN customers cu        ON cu.customer_account_id = ca.id
    INNER JOIN sales sa            ON sa.customer_id = cu.id
    INNER JOIN variants_sales vs   ON vs.sale_id = sa.id
    INNER JOIN variants va         ON va.id = vs.variant_id
    WHERE u.fullname IS NOT NULL AND u.fullname <> ''
";
$paramsNames = [];
if ($customer !== "") { $sqlFullnames .= " AND cu.customer_name LIKE :dl_customer_name"; $paramsNames[':dl_customer_name'] = "%{$customer}%"; }
if ($item !== "")     { $sqlFullnames .= " AND va.item_short_description LIKE :dl_item";  $paramsNames[':dl_item'] = "%{$item}%"; }
appendDateRangeFilters($sqlFullnames, $paramsNames, $date_from, $date_to, $validDate);
$sqlFullnames .= " ORDER BY u.fullname ASC LIMIT 200";
$stmt = $pdo->prepare($sqlFullnames); $stmt->execute($paramsNames); $fullnames = $stmt->fetchAll();

// 2) CUSTOMER NAME (phụ thuộc fullname, item, from/to)
$sqlCustomers = "
    SELECT DISTINCT cu.customer_name
    FROM customers cu
    INNER JOIN sales sa            ON sa.customer_id = cu.id
    INNER JOIN variants_sales vs   ON vs.sale_id = sa.id
    INNER JOIN variants va         ON va.id = vs.variant_id
    INNER JOIN customer_account ca ON ca.id = cu.customer_account_id
    INNER JOIN users u             ON u.id = ca.user_id
    WHERE cu.customer_name IS NOT NULL AND cu.customer_name <> ''
";
$paramsCust = [];
if ($fullname !== "") { $sqlCustomers .= " AND u.fullname LIKE :dl_fullname"; $paramsCust[':dl_fullname'] = "%{$fullname}%"; }
if ($item !== "")     { $sqlCustomers .= " AND va.item_short_description LIKE :dl_item"; $paramsCust[':dl_item'] = "%{$item}%"; }
appendDateRangeFilters($sqlCustomers, $paramsCust, $date_from, $date_to, $validDate);
$sqlCustomers .= " ORDER BY cu.customer_name ASC LIMIT 200";
$stmt = $pdo->prepare($sqlCustomers); $stmt->execute($paramsCust); $customers = $stmt->fetchAll();

// 3) ITEM SHORT DESCRIPTION (phụ thuộc fullname, customer, from/to)  **ĐÃ SỬA INNER JOIN**
$sqlItems = "
    SELECT DISTINCT va.item_short_description
    FROM variants va
    INNER JOIN variants_sales vs   ON vs.variant_id = va.id
    INNER JOIN sales sa            ON sa.id = vs.sale_id
    INNER JOIN customers cu        ON cu.id = sa.customer_id
    INNER JOIN customer_account ca ON ca.id = cu.customer_account_id
    INNER JOIN users u             ON u.id = ca.user_id
    WHERE va.item_short_description IS NOT NULL AND va.item_short_description <> ''
";
$paramsItems = [];
if ($fullname !== "") { $sqlItems .= " AND u.fullname LIKE :dl_fullname"; $paramsItems[':dl_fullname'] = "%{$fullname}%"; }
if ($customer !== "") { $sqlItems .= " AND cu.customer_name LIKE :dl_customer_name"; $paramsItems[':dl_customer_name'] = "%{$customer}%"; }
appendDateRangeFilters($sqlItems, $paramsItems, $date_from, $date_to, $validDate);
$sqlItems .= " ORDER BY va.item_short_description ASC LIMIT 200";
$stmt = $pdo->prepare($sqlItems); $stmt->execute($paramsItems); $items = $stmt->fetchAll();

/* ==================== TRUY VẤN CHÍNH ==================== */
$sql = "
SELECT
    u.fullname,
    cu.customer_code,
    cu.customer_name,
    va.item_short_description,   -- Item ở giữa Customer Name và Order Number
    sa.order_number,
    sa.commercial_quantity,
    sa.invoice_confirmed_date,
    sa.expiry_date
FROM users u
INNER JOIN customer_account ca ON ca.user_id = u.id
INNER JOIN customers cu        ON cu.customer_account_id = ca.id
INNER JOIN sales sa            ON sa.customer_id = cu.id
INNER JOIN variants_sales vs   ON vs.sale_id = sa.id
INNER JOIN variants va         ON va.id = vs.variant_id
WHERE 1=1
";
$params = [];
if ($fullname !== "") { $sql .= " AND u.fullname LIKE :fullname"; $params[':fullname'] = "%{$fullname}%"; }
if ($customer !== "") { $sql .= " AND cu.customer_name LIKE :customer_name"; $params[':customer_name'] = "%{$customer}%"; }
if ($item !== "")     { $sql .= " AND va.item_short_description LIKE :item_short_description"; $params[':item_short_description'] = "%{$item}%"; }
appendDateRangeFilters($sql, $params, $date_from, $date_to, $validDate);
$sql .= " ORDER BY sa.invoice_confirmed_date IS NULL, sa.invoice_confirmed_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Tổng quantity sau filter
$totalQty = 0;
foreach ($rows as $r) { $totalQty += (float)($r['commercial_quantity'] ?? 0); }

// Dropdown Năm: 2023 -> năm hiện tại
$startYear = 2023;
$currentYear = (int)date('Y');
$years = range($startYear, $currentYear);
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
    tfoot td{font-weight:600;background:#f8f9fa}
  </style>
</head>
<body>
<div class="container">
  <h3 class="mb-3">Sales Report</h3>

  <form id="filterForm" method="get" class="row g-3 mb-3" autocomplete="off">
    <div class="col-md-3">
      <label class="form-label">Fullname</label>
      <input list="fullnames" id="fullname" name="fullname"
             value="<?= h($fullname) ?>" class="form-control"
             placeholder="Nhập/chọn fullname...">
      <datalist id="fullnames">
        <?php foreach ($fullnames as $n): ?>
          <option value="<?= h($n['fullname']) ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>

    <div class="col-md-3">
      <label class="form-label">Customer name</label>
      <input list="customers" id="customer_name" name="customer_name"
             value="<?= h($customer) ?>" class="form-control"
             placeholder="Nhập/chọn customer...">
      <datalist id="customers">
        <?php foreach ($customers as $c): ?>
          <option value="<?= h($c['customer_name']) ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>

    <div class="col-md-3">
      <label class="form-label">Item short description</label>
      <input list="items" id="item_short_description" name="item_short_description"
             value="<?= h($item) ?>" class="form-control"
             placeholder="Nhập/chọn item...">
      <datalist id="items">
        <?php foreach ($items as $it): ?>
          <option value="<?= h($it['item_short_description']) ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>

    <div class="col-md-4">
      <label class="form-label">Invoice date (from → to)</label>
      <div class="d-flex gap-2">
        <input type="date" id="date_from" name="date_from" value="<?= h($date_from) ?>" class="form-control">
        <input type="date" id="date_to"   name="date_to"   value="<?= h($date_to) ?>"   class="form-control">
      </div>
      <div class="small-muted mt-1">Chọn Năm/Quý để tự điền nhanh khoảng ngày bên trên.</div>
    </div>

    <div class="col-md-2">
      <label class="form-label">Năm</label>
      <select id="year" name="year" class="form-select">
        <option value="">--Tất cả--</option>
        <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= ($year!=="" && (int)$year===$y)?"selected":"" ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Quý</label>
      <select id="quarter" name="quarter" class="form-select">
        <option value="">--Tất cả--</option>
        <option value="1" <?= $quarter==="1"?"selected":"" ?>>Quý 1 (01–03)</option>
        <option value="2" <?= $quarter==="2"?"selected":"" ?>>Quý 2 (04–06)</option>
        <option value="3" <?= $quarter==="3"?"selected":"" ?>>Quý 3 (07–09)</option>
        <option value="4" <?= $quarter==="4"?"selected":"" ?>>Quý 4 (10–12)</option>
      </select>
    </div>

    <div class="col-12 d-flex gap-2">
      <button type="button" id="btnReset" class="btn btn-outline-secondary">Reset</button>
      <button type="submit" class="btn btn-outline-primary">Submit (tùy chọn)</button>
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
          <th>Item</th>               <!-- Item ở giữa -->
          <th>Order Number</th>
          <th class="text-end">Quantity</th>
          <th>Invoice Date</th>
          <th>Expiry Date</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted">Không có dữ liệu</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r['fullname']) ?></td>
          <td><?= h($r['customer_code']) ?></td>
          <td><?= h($r['customer_name']) ?></td>
          <td><?= h($r['item_short_description']) ?></td>
          <td><?= h($r['order_number']) ?></td>
          <td class="text-end"><?= h($r['commercial_quantity']) ?></td>
          <td><?= h($r['invoice_confirmed_date']) ?></td>
          <td><?= h($r['expiry_date']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="5" class="text-end">Tổng Quantity sau filter:</td>
          <td class="text-end"><?= number_format($totalQty, 0, '.', ',') ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<script>
// Debounce để tránh submit quá dày khi gõ nhanh
function debounce(fn, delay){ let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn(...args), delay); }; }

const form      = document.getElementById('filterForm');
const inpName   = document.getElementById('fullname');
const inpCust   = document.getElementById('customer_name');
const inpItem   = document.getElementById('item_short_description');
const inpFrom   = document.getElementById('date_from');
const inpTo     = document.getElementById('date_to');
const inpYear   = document.getElementById('year');
const inpQuarter= document.getElementById('quarter');
const btnReset  = document.getElementById('btnReset');

// Auto submit khi nhập/chọn (datalist -> 'input')
[inpName, inpCust, inpItem].forEach(el => el.addEventListener('input', debounce(()=>form.submit(), 300)));
[inpFrom, inpTo].forEach(el => el.addEventListener('change', ()=>form.submit()));

// Map Quý -> khoảng ngày
const quarterRanges = {
  "1": {from:"01-01", to:"03-31"},
  "2": {from:"04-01", to:"06-30"},
  "3": {from:"07-01", to:"09-30"},
  "4": {from:"10-01", to:"12-31"}
};

// Khi đổi Năm/Quý: tự động điền date_from/date_to rồi submit
function applyYearQuarterToDates(){
  const y = inpYear.value.trim();
  const q = inpQuarter.value.trim();

  if (y !== "" && q !== "" && quarterRanges[q]) {
    inpFrom.value = `${y}-${quarterRanges[q].from}`;
    inpTo.value   = `${y}-${quarterRanges[q].to}`;
    form.submit();
    return;
  }
  if (y !== "" && q === "") {
    // Cả năm
    inpFrom.value = `${y}-01-01`;
    inpTo.value   = `${y}-12-31`;
    form.submit();
    return;
  }
  // Nếu xóa Năm/Quý, không tự đổi from/to (giữ nguyên để người dùng tự chỉnh)
}

inpYear.addEventListener('change', applyYearQuarterToDates);
inpQuarter.addEventListener('change', applyYearQuarterToDates);

// Reset tất cả
btnReset.addEventListener('click', ()=>{
  inpName.value = ''; inpCust.value = ''; inpItem.value = '';
  inpFrom.value = ''; inpTo.value = ''; inpYear.value = ''; inpQuarter.value = '';
  form.submit();
});
</script>
</body>
</html>
