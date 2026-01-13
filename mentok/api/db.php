<?php
// api/db.php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

session_start();

$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = ""; // default XAMPP
$DB_NAME = "mentok_db";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$conn->set_charset("utf8mb4");

function json_body() {
  $raw = file_get_contents("php://input");
  return $raw ? json_decode($raw, true) : [];
}

function ok($data = []) {
  echo json_encode(["ok" => true, "data" => $data], JSON_UNESCAPED_UNICODE);
  exit;
}
function fail($msg, $code = 400) {
  http_response_code($code);
  echo json_encode(["ok" => false, "message" => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

function require_login() {
  if (!isset($_SESSION["user"])) fail("Belum login.", 401);
  return $_SESSION["user"];
}

function require_role($role) {
  $u = require_login();
  if ($u["role"] !== $role) fail("Tidak punya akses.", 403);
  return $u;
}
