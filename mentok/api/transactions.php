<?php
require_once __DIR__ . "/db.php";

$u = require_login();
$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
  $type = $_GET["type"] ?? "";
  $stmtSql = "
    SELECT st.id, st.trx_time, st.trx_type, st.qty, st.note,
           u.username, i.code, i.name, i.category, i.unit
    FROM stock_transactions st
    JOIN users u ON u.id = st.user_id
    JOIN items i ON i.id = st.item_id
  ";
  if ($type === "IN" || $type === "OUT" || $type === "ADJ") {
    $stmtSql .= " WHERE st.trx_type=? ";
    $stmtSql .= " ORDER BY st.trx_time DESC";
    $stmt = $conn->prepare($stmtSql);
    $stmt->bind_param("s", $type);
  } else {
    $stmtSql .= " ORDER BY st.trx_time DESC";
    $stmt = $conn->prepare($stmtSql);
  }

  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  ok($rows);
}

if ($method === "POST") {
  // REVISI: Hanya KARYAWAN yang boleh membuat transaksi stok
  if ($u["role"] !== "KARYAWAN") fail("Owner tidak boleh membuat transaksi stok.", 403);

  $b = json_body();
  
  $trx_type = $b["trx_type"] ?? "";
  $item_id = (int)($b["item_id"] ?? 0);
  $qty = (int)($b["qty"] ?? 0);
  $note = trim($b["note"] ?? "");

  if (!in_array($trx_type, ["IN","OUT","ADJ"])) fail("Tipe transaksi tidak valid.");
  if ($item_id <= 0) fail("Item tidak valid.");
  if ($qty <= 0) fail("Qty harus > 0.");

  $conn->begin_transaction();

  // ambil stok sekarang
  $stmt = $conn->prepare("SELECT stock FROM items WHERE id=? FOR UPDATE");
  $stmt->bind_param("i", $item_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) { $conn->rollback(); fail("Barang tidak ditemukan."); }

  $stock = (int)$row["stock"];
  if ($trx_type === "IN") $newStock = $stock + $qty;
  else if ($trx_type === "OUT") $newStock = $stock - $qty;
  else $newStock = $qty; // ADJ set stok jadi nilai qty

  if ($newStock < 0) { $conn->rollback(); fail("Stok Kosong."); }

  // update stok item
  $stmt2 = $conn->prepare("UPDATE items SET stock=? WHERE id=?");
  $stmt2->bind_param("ii", $newStock, $item_id);
  $stmt2->execute();

  // insert transaksi
  $now = date("Y-m-d H:i:s");
  $stmt3 = $conn->prepare("INSERT INTO stock_transactions(trx_time,trx_type,item_id,qty,note,user_id) VALUES (?,?,?,?,?,?)");
  $stmt3->bind_param("ssissi", $now, $trx_type, $item_id, $qty, $note, $u["id"]);
  $stmt3->execute();

  $conn->commit();
  ok(["new_stock" => $newStock]);
}

fail("Method tidak didukung.", 405);
