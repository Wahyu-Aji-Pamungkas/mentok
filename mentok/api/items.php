<?php
require_once __DIR__ . "/db.php";

$u = require_login();
$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
  $q = trim($_GET["q"] ?? "");
  if ($q !== "") {
    $like = "%$q%";
    $stmt = $conn->prepare("SELECT * FROM items WHERE code LIKE ? OR name LIKE ? OR category LIKE ? ORDER BY created_at DESC");
    $stmt->bind_param("sss", $like, $like, $like);
  } else {
    $stmt = $conn->prepare("SELECT * FROM items ORDER BY created_at DESC");
  }
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  ok($rows);
}

/*
  RULES:
  - OWNER: hanya lihat (tidak boleh tambah/hapus/edit)
  - KARYAWAN: boleh tambah & hapus, tidak boleh edit
*/
if ($method === "POST") {
  if ($u["role"] !== "KARYAWAN") fail("Owner tidak boleh menambah barang.", 403);

  $b = json_body();
  $code = strtoupper(trim($b["code"] ?? ""));
  $name = trim($b["name"] ?? "");
  $category = trim($b["category"] ?? "Umum");
  $unit = trim($b["unit"] ?? "pcs");
  $stock = (int)($b["stock"] ?? 0);
  $min_stock = (int)($b["min_stock"] ?? 5);
  $note = trim($b["note"] ?? "");

  if ($code === "" || $name === "") fail("Kode & nama wajib diisi.");

  $stmt = $conn->prepare("INSERT INTO items(code,name,category,unit,stock,min_stock,note) VALUES (?,?,?,?,?,?,?)");
  $stmt->bind_param("sssssis", $code,$name,$category,$unit,$stock,$min_stock,$note);
  $stmt->execute();
  ok(["id" => $conn->insert_id]);
}

if ($method === "DELETE") {
  if ($u["role"] !== "KARYAWAN") fail("Owner tidak boleh menghapus barang.", 403);

  $id = (int)($_GET["id"] ?? 0);
  if ($id <= 0) fail("ID tidak valid.");

  $stmt = $conn->prepare("DELETE FROM items WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  ok(["deleted" => true]);
}

fail("Method tidak didukung.", 405);
