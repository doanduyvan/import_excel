<?php
/************ DB CONFIG (chỉnh lại cho phù hợp) ************/
$DB_HOST = '127.0.0.1';
$DB_NAME = 'odin';
$DB_USER = 'odin';
$DB_PASS = 'Odin123456@';
$DB_CHARSET = 'utf8mb4';
/***********************************************************/
// (Tùy chọn) Bật lỗi tạm thời để debug 500 trong môi trường dev
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try { $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) { http_response_code(500); echo "DB connection failed."; exit; }

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$validDate = fn($d) => $d !== "" && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;

// --------- Lấy tham số filter ---------
$fullname   = $_GET['fullname']             ?? "";
$customer   = $_GET['customer_name']        ?? "";
$item       = $_GET['item_short_description'] ?? "";
$date_from  = $_GET['date_from']            ?? "";
$date_to    = $_GET['date_to']              ?? "";
$year       = $_GET['year']                 ?? "";
$quarter    = $_GET['quarter']              ?? "";
$fullname   = trim($fullname);
$customer   = trim($customer);
$item       = trim($item);
$date_from  = trim($date_from);
$date_to    = trim($date_to);
$year       = trim($year);
$quarter    = trim($quarter);

// --------- Server-side fallback cho Năm/Quý -> from/to nếu JS không điền ---------
if ($year !== "") {
    $qMap = ["1"=>["01-01","03-31"], "2"=>["04-01","06-30"], "3"=>["07-01","09-30"], "4"=>["10-01","12-31"]];
    if ($quarter !== "" && isset($qMap[$quarter])) {
        if (!$validDate($date_from)) $date_from = "$year-{$qMap[$quarter][0]}";
        if (!$validDate($date_to))   $date_to   = "$year-{$qMap[$quarter][1]}";
    } else {
        if (!$validDate($date_from)) $date_from = "$year-01-01";
        if (!$validDate($date_to))   $date_to   = "$year-12-31";
    }
}

// --------- Helper: áp dụng from/to vào SQL ---------
function appendDateRangeFilters(&$sql, &$params, $date_from, $date_to, $validDate) {
    if ($validDate($date_from)) { $sql .= " AND sa.invoice_confirmed_date >= :date_from"; $params[':date_from'] = $date_from; }
    if ($validDate($date_to))   { $sql .= " AND sa.invoice_confirmed_date <= :date_to";   $params[':date_to']   = $date_to; }
}

/* ==================== DATALIST PHỤ ==================== */
// 1) FULLNAME
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

// 2) CUSTOMER NAME
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

// 3) ITEM SHORT DESCRIPTION
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
    va.item_short_description,
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

// Dropdown Năm
$startYear = 2023; $currentYear = (int)date('Y'); $years = range($startYear, $currentYear);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Sales Report</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=yes, maximum-scale=5">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    html{ -webkit-text-size-adjust:100%; text-size-adjust:100%; }
    :root{
      /* cỡ chữ & padding nền tảng */
      --cell-font-base: 14;         /* px */
      --cell-font-scale: 1;         /* sẽ giảm để fit */
      --cell-pad-y: 0.55rem;
      --cell-pad-x: 0.55rem;
      --radius: 12px;
    }
    /* khi bật Compact */
    html.compact{
      --cell-font-base: 13;
      --cell-pad-y: 0.45rem;
      --cell-pad-x: 0.45rem;
    }

    body{padding:1rem;background:#fafafa;}
    .toolbar{
      position: sticky; top:0; z-index:30; background:#fff; padding:.5rem; margin:-.5rem -1rem 0; border-bottom:1px solid #eee;
      display:flex; gap:.5rem; align-items:center; justify-content:space-between;
    }
    .filters-panel{ margin-top:.75rem; }
    @media (max-width: 576px){
      .filters-panel{ display:none; }
      .filters-panel.show{ display:block; }
    }

    .table-wrap{
      position:relative; border-radius: var(--radius); background:#fff;
      box-shadow: 0 1px 8px rgba(0,0,0,.05); overflow:hidden;
    }
    .table-scroll{
      -webkit-overflow-scrolling: touch; overflow:auto; scroll-behavior: smooth;
      touch-action: pan-x pan-y; /* cho phép pinch-zoom */
    }
    .table thead th{ position: sticky; top:0; z-index:2; background:#f8f9fa; }

    /* bảng luôn wrap chữ an toàn, KHÔNG dùng line-clamp để tránh chồng */
    table{ width:100%; border-collapse: separate; border-spacing:0; table-layout:auto; }
    th, td{
      font-size: calc(var(--cell-font-base) * 1px * var(--cell-font-scale));
      line-height: 1.35;
      padding: var(--cell-pad-y) var(--cell-pad-x);
      vertical-align: top;
      overflow-wrap: anywhere;
      word-break: break-word;
      white-space: normal;
    }
    .nowrap{ white-space: nowrap; }   /* chỉ dùng cho số/ngày ngắn */
    .text-end.nowrap{ white-space: nowrap; }

    tfoot td{ font-weight:600; background:#f8f9fa; }
    .total-bar{
      position: sticky; bottom:0; background:#fff; padding:.5rem .75rem; border-top:1px solid #eee;
      display:flex; justify-content:flex-end; z-index:20;
    }
  </style>
</head>
<body>
<div class="container">
  <div class="toolbar">
    <div class="d-flex align-items-center gap-2">
      <button id="btnToggleFilters" class="btn btn-outline-secondary btn-sm">Filters</button>
      <button id="btnCompact" class="btn btn-outline-primary btn-sm" aria-pressed="false">Compact</button>
      <button id="btnFit" class="btn btn-outline-success btn-sm" aria-pressed="false">Fit width</button>
    </div>
    <div class="text-muted small">Tổng: <strong><?= count($rows) ?></strong> bản ghi</div>
  </div>

  <div id="filtersPanel" class="filters-panel">
    <form id="filterForm" method="get" class="row g-3 mb-3" autocomplete="off">
      <div class="col-12 col-md-3">
        <label class="form-label">Fullname</label>
        <input list="fullnames" id="fullname" name="fullname" value="<?= h($fullname) ?>" class="form-control" placeholder="Nhập/chọn fullname...">
        <datalist id="fullnames">
          <?php foreach ($fullnames as $n): ?><option value="<?= h($n['fullname']) ?>"></option><?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Customer name</label>
        <input list="customers" id="customer_name" name="customer_name" value="<?= h($customer) ?>" class="form-control" placeholder="Nhập/chọn customer...">
        <datalist id="customers">
          <?php foreach ($customers as $c): ?><option value="<?= h($c['customer_name']) ?>"></option><?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Item short description</label>
        <input list="items" id="item_short_description" name="item_short_description" value="<?= h($item) ?>" class="form-control" placeholder="Nhập/chọn item...">
        <datalist id="items">
          <?php foreach ($items as $it): ?><option value="<?= h($it['item_short_description']) ?>"></option><?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Invoice date (from → to)</label>
        <div class="d-flex gap-2">
          <input type="date" id="date_from" name="date_from" value="<?= h($date_from) ?>" class="form-control">
          <input type="date" id="date_to"   name="date_to"   value="<?= h($date_to) ?>"   class="form-control">
        </div>
        <div class="text-muted mt-1" style="font-size:0.95rem">Chọn Năm/Quý để tự điền nhanh khoảng ngày bên trên.</div>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Năm</label>
        <select id="year" name="year" class="form-select">
          <option value="">--Tất cả--</option>
          <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= ($year!=="" && (int)$year===$y)?"selected":"" ?>><?= $y ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Quý</label>
        <select id="quarter" name="quarter" class="form-select">
          <option value="">--Tất cả--</option>
          <option value="1" <?= $quarter==="1"?"selected":"" ?>>Q1 (01–03)</option>
          <option value="2" <?= $quarter==="2"?"selected":"" ?>>Q2 (04–06)</option>
          <option value="3" <?= $quarter==="3"?"selected":"" ?>>Q3 (07–09)</option>
          <option value="4" <?= $quarter==="4"?"selected":"" ?>>Q4 (10–12)</option>
        </select>
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="button" id="btnReset" class="btn btn-outline-secondary">Reset</button>
        <button type="submit" class="btn btn-outline-primary">Submit (tùy chọn)</button>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    <div class="table-scroll" id="tableScroll">
      <table class="table table-bordered table-striped align-top" id="dataTable">
        <thead class="table-light">
          <tr>
            <th>Fullname</th>
            <th>Customer Code</th>
            <th>Customer Name</th>
            <th>Item</th>
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
            <td class="text-end nowrap"><?= h($r['commercial_quantity']) ?></td>
            <td class="nowrap"><?= h($r['invoice_confirmed_date']) ?></td>
            <td class="nowrap"><?= h($r['expiry_date']) ?></td>
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
    <div class="total-bar">
      <div class="ms-auto">Total Qty: <strong><?= number_format($totalQty, 0, '.', ',') ?></strong></div>
    </div>
  </div>
</div>

<script>
// Debounce
function debounce(fn, d){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), d); }; }
const form = document.getElementById('filterForm');
['fullname','customer_name','item_short_description'].forEach(id=>{
  const el = document.getElementById(id);
  if(el) el.addEventListener('input', debounce(()=>form.submit(), 300));
});
['date_from','date_to'].forEach(id=>{
  const el = document.getElementById(id);
  if(el) el.addEventListener('change', ()=>form.submit());
});
const inpYear = document.getElementById('year');
const inpQuarter = document.getElementById('quarter');
const quarterRanges = {"1":{from:"01-01",to:"03-31"},"2":{from:"04-01",to:"06-30"},"3":{from:"07-01",to:"09-30"},"4":{from:"10-01",to:"12-31"}};
function applyYearQuarterToDates(){
  const y = inpYear.value.trim(), q = inpQuarter.value.trim();
  if (y && q && quarterRanges[q]) { document.getElementById('date_from').value = `${y}-${quarterRanges[q].from}`; document.getElementById('date_to').value = `${y}-${quarterRanges[q].to}`; form.submit(); return; }
  if (y && !q) { document.getElementById('date_from').value = `${y}-01-01`; document.getElementById('date_to').value = `${y}-12-31`; form.submit(); }
}
inpYear.addEventListener('change', applyYearQuarterToDates);
inpQuarter.addEventListener('change', applyYearQuarterToDates);
document.getElementById('btnReset').addEventListener('click', ()=>{ ['fullname','customer_name','item_short_description','date_from','date_to','year','quarter'].forEach(id=>{const el=document.getElementById(id); if(el) el.value='';}); form.submit(); });

// Toggle filters (mobile)
document.getElementById('btnToggleFilters').addEventListener('click', ()=>{ document.getElementById('filtersPanel').classList.toggle('show'); });

// Compact toggle
const btnCompact = document.getElementById('btnCompact');
btnCompact.addEventListener('click', ()=>{
  const pressed = btnCompact.getAttribute('aria-pressed') === 'true';
  btnCompact.setAttribute('aria-pressed', String(!pressed));
  document.documentElement.classList.toggle('compact', !pressed);
  // nếu đang fit, tính lại
  if (btnFit.getAttribute('aria-pressed') === 'true') fitWidth();
});

// ====== Fit width (giảm cỡ chữ để vừa khít khung, KHÔNG dùng transform) ======
const btnFit = document.getElementById('btnFit');
const scroller = document.getElementById('tableScroll');
const table = document.getElementById('dataTable');

function setFontScale(v){
  document.documentElement.style.setProperty('--cell-font-scale', v.toString());
}

function fitWidth(){
  // reset về 1 trước khi đo
  setFontScale(1);
  // nếu bảng đã <= khung, xong
  if (table.scrollWidth <= scroller.clientWidth) return;
  // giảm dần scale đến min
  const minScale = 0.72;        // đừng thấp quá để khỏi chồng chữ
  let scale = 1.0;
  // Ở màn nhỏ, thử giảm từng bước 0.03
  while (table.scrollWidth > scroller.clientWidth && scale > minScale){
    scale = +(scale - 0.03).toFixed(2);
    setFontScale(scale);
  }
}

btnFit.addEventListener('click', ()=>{
  const pressed = btnFit.getAttribute('aria-pressed') === 'true';
  if (!pressed){
    btnFit.setAttribute('aria-pressed','true');
    fitWidth();
    // khi fit xong, nếu người dùng xoay máy/đổi viewport -> tính lại
    window.addEventListener('resize', fitWidth, { passive:true });
  }else{
    btnFit.setAttribute('aria-pressed','false');
    setFontScale(1);
    window.removeEventListener('resize', fitWidth);
  }
});

// Tự bật Compact + Fit width trên màn hình <=576px
function autoMobile(){
  const isMobile = matchMedia('(max-width: 576px)').matches;
  if (isMobile){
    if (btnCompact.getAttribute('aria-pressed') !== 'true'){
      btnCompact.setAttribute('aria-pressed','true');
      document.documentElement.classList.add('compact');
    }
    if (btnFit.getAttribute('aria-pressed') !== 'true'){
      btnFit.setAttribute('aria-pressed','true');
      fitWidth();
      window.addEventListener('resize', fitWidth, { passive:true });
    }
  }
}
window.addEventListener('load', autoMobile);
</script>
</body>
</html>
