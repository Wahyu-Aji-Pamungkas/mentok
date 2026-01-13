<?php
require_once __DIR__ . "/db.php";

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

if ($method === "POST" && $action === "login") {
  $b = json_body();
  $username = trim($b["username"] ?? "");
  $password = trim($b["password"] ?? "");

  if ($username === "" || $password === "") fail("Username & password wajib diisi.");

  $stmt = $conn->prepare("SELECT id, username, password_hash, role FROM users WHERE username=?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();

  if (!$res) fail("Username / password salah.", 401);

  // demo: kalau kamu pakai seed placeholder, ini tetap bisa
  if (!password_verify($password, $res["password_hash"])) {
    // fallback demo: kalau hash seed placeholder bukan hash password kamu
    // kamu bisa hapus blok fallback ini kalau sudah pakai hash bener semua
    if (!($username === "owner" && $password === "owner123") && !($username === "karyawan" && $password === "karyawan123")) {
      fail("Username / password salah.", 401);
    }
  }

  $_SESSION["user"] = [
    "id" => (int)$res["id"],
    "username" => $res["username"],
    "role" => $res["role"]
  ];
  ok($_SESSION["user"]);
}

if ($method === "POST" && $action === "logout") {
  session_destroy();
  ok(["message" => "logout"]);
}

if ($method === "GET" && $action === "me") {
  if (!isset($_SESSION["user"])) fail("Belum login.", 401);
  ok($_SESSION["user"]);
}

fail("Endpoint tidak ditemukan.", 404);
