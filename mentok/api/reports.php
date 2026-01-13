<?php
include "./db.php";

$action = $_GET["action"] ?? "";

/* ================= KPI ================= */
if ($action == "kpi") {

  $q = $conn->query("
    SELECT
      SUM(CASE WHEN trx_type='IN' THEN qty ELSE 0 END) AS total_in,
      SUM(CASE WHEN trx_type='OUT' THEN qty ELSE 0 END) AS total_out,
      COUNT(*) AS total_tx
    FROM stock_transactions
    WHERE trx_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
  ")->fetch_assoc();

  $low = $conn->query("
    SELECT COUNT(*) AS low
    FROM items
    WHERE stock <= min_stock
  ")->fetch_assoc();

  echo json_encode([
    "ok" => true,
    "data" => [
      "in7"  => (int)($q["total_in"] ?? 0),
      "out7" => (int)($q["total_out"] ?? 0),
      "tx7"  => (int)($q["total_tx"] ?? 0),
      "low"  => (int)($low["low"] ?? 0)
    ]
  ]);
}

/* ================= DAILY ================= */
else if ($action == "daily") {

  $res = $conn->query("
    SELECT 
      DATE(trx_time) AS tanggal,
      SUM(CASE WHEN trx_type='IN' THEN qty ELSE 0 END) AS in_qty,
      SUM(CASE WHEN trx_type='OUT' THEN qty ELSE 0 END) AS out_qty,
      SUM(CASE WHEN trx_type='ADJ' THEN qty ELSE 0 END) AS adj_qty,
      COUNT(*) AS total_tx
    FROM stock_transactions
    WHERE trx_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(trx_time)
    ORDER BY tanggal DESC
  ");

  $data=[];
  while($r=$res->fetch_assoc()){
    $data[]=$r;
  }

  echo json_encode([
    "ok" => true,
    "data" => $data
  ]);
}

/* ================= WEEKLY ================= */
else if ($action == "weekly") {

  $res = $conn->query("
    SELECT
      YEARWEEK(trx_time) AS minggu,
      SUM(CASE WHEN trx_type='IN' THEN qty ELSE 0 END) AS in_qty,
      SUM(CASE WHEN trx_type='OUT' THEN qty ELSE 0 END) AS out_qty,
      SUM(CASE WHEN trx_type='ADJ' THEN qty ELSE 0 END) AS adj_qty,
      COUNT(*) AS total_tx
    FROM stock_transactions
    GROUP BY YEARWEEK(trx_time)
    ORDER BY minggu DESC
    LIMIT 8
  ");

  $data=[];
  while($r=$res->fetch_assoc()){
    $data[]=$r;
  }

  echo json_encode([
    "ok" => true,
    "data" => $data
  ]);
}

/* ================= EXPORT CSV ================= */
else if ($action == "export") {

  header("Content-Type: text/csv");
  header("Content-Disposition: attachment; filename=laporan-7hari.csv");

  echo "Tanggal,IN,OUT,ADJ,Total\n";

  $res = $conn->query("
    SELECT 
      DATE(trx_time),
      SUM(CASE WHEN trx_type='IN' THEN qty ELSE 0 END),
      SUM(CASE WHEN trx_type='OUT' THEN qty ELSE 0 END),
      SUM(CASE WHEN trx_type='ADJ' THEN qty ELSE 0 END),
      COUNT(*)
    FROM stock_transactions
    WHERE trx_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(trx_time)
  ");

  while($r=$res->fetch_row()){
    echo implode(",",$r)."\n";
  }
}

else if ($action == "summary") {
  $q = $conn->query("
    SELECT 
      COUNT(*) as total_items,
      SUM(stock) as total_stock,
      SUM(stock <= min_stock) as low_items
    FROM items
  ")->fetch_assoc();

  $today = $conn->query("
    SELECT COUNT(*) as tx_today
    FROM stock_transactions
    WHERE DATE(trx_time) = CURDATE()
  ")->fetch_assoc();

  echo json_encode([
    "ok" => true,
    "data" => [
      "total_items" => (int)$q["total_items"],
      "total_stock" => (int)$q["total_stock"],
      "low_items"   => (int)$q["low_items"],
      "tx_today"    => (int)$today["tx_today"]
    ]
  ]);
}

/* ================= LOW STOCK ================= */
else if ($action == "lowstock") {

  $res = $conn->query("
    SELECT id, code, name, category, stock, min_stock, unit
    FROM items
    WHERE stock <= min_stock
    ORDER BY stock ASC
    LIMIT 10
  ");

  $data = [];
  while($r = $res->fetch_assoc()){
    $data[] = $r;
  }

  echo json_encode([
    "ok" => true,
    "data" => $data
  ]);
}



else {
  echo json_encode([
    "ok" => false,
    "message" => "Invalid action"
  ]);
}
?>