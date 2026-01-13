<?php
require_once __DIR__ . "/db.php";

/*
  HANYA OWNER yang boleh akses
*/
$u = require_role("OWNER");
$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
  $rows = $conn->query(
    "SELECT id, nama_karyawan, kode FROM karyawan ORDER BY created_at DESC"
  )->fetch_all(MYSQLI_ASSOC);
  ok($rows);
}

if ($method === "POST") {
  $b = json_body();
  $nama = trim($b["nama_karyawan"] ?? "");
  $kode = trim($b["kode"] ?? "");

  if ($nama === "" || $kode === "") {
    fail("Nama karyawan dan kode wajib diisi.");
  }

  $stmt = $conn->prepare(
    "INSERT INTO karyawan (nama_karyawan, kode) VALUES (?, ?)"
  );
  $stmt->bind_param("ss", $nama, $kode);
  $stmt->execute();

  ok(["id" => $conn->insert_id]);
}

if ($method === "DELETE") {
  $id = (int)($_GET["id"] ?? 0);
  if ($id <= 0) fail("ID tidak valid.");

  $stmt = $conn->prepare("DELETE FROM karyawan WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();

  ok(["deleted" => true]);
}

fail("Method tidak didukung.", 405);
