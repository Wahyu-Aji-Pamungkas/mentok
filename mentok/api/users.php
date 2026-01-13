<?php
require_once __DIR__ . "/db.php";

$u = require_role("OWNER");
$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
  $rows = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
  ok($rows);
}

if ($method === "POST") {
  $b = json_body();
  $username = trim($b["username"] ?? "");
  $password = trim($b["password"] ?? "");
  $role = $b["role"] ?? "KARYAWAN";

  if ($username === "") fail("Username wajib.");
  if (strlen($password) < 6) fail("Password minimal 6 karakter.");
  if (!in_array($role, ["OWNER","KARYAWAN"])) fail("Role tidak valid.");

  $hash = password_hash($password, PASSWORD_BCRYPT);

  $stmt = $conn->prepare("INSERT INTO users(username,password_hash,role) VALUES (?,?,?)");
  $stmt->bind_param("sss", $username, $hash, $role);
  $stmt->execute();
  ok(["id" => $conn->insert_id]);
}

if ($method === "DELETE") {
  $id = (int)($_GET["id"] ?? 0);
  if ($id <= 0) fail("ID tidak valid.");
  if ($id === (int)$u["id"]) fail("Tidak boleh menghapus akun sendiri.");

  $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  ok(["deleted" => true]);
}

fail("Method tidak didukung.", 405);
