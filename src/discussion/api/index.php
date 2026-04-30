<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';
$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

$data = json_decode(file_get_contents("php://input"), true) ?? [];

$action  = $_GET['action']   ?? null;
$id      = $_GET['id']       ?? null;
$topicId = $_GET['topic_id'] ?? null;

/* ================= TOPICS ================= */

function getAllTopics($db) {
    $stmt = $db->query("SELECT * FROM topics ORDER BY created_at DESC");
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(["success" => true, "data" => $topics]);
}

function getTopicById($db, $id) {
    $stmt = $db->prepare("SELECT * FROM topics WHERE id=?");
    $stmt->execute([$id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($topic) {
        sendResponse(["success" => true, "data" => $topic]);
    } else {
        sendResponse(["success" => false], 404);
    }
}

function createTopic($db, $data) {
    if (empty($data["subject"]) || empty($data["message"]) || empty($data["author"])) {
        sendResponse(["success" => false], 400);
    }

    $stmt = $db->prepare("INSERT INTO topics (subject, message, author) VALUES (?, ?, ?)");
    $stmt->execute([
        $data["subject"],
        $data["message"],
        $data["author"]
    ]);

    sendResponse([
        "success" => true,
        "id" => $db->lastInsertId()
    ], 201);
}

function updateTopic($db, $data) {
    if (empty($data["id"])) {
        sendResponse(["success" => false], 400);
    }

    $stmt = $db->prepare("UPDATE topics SET subject=?, message=? WHERE id=?");
    $stmt->execute([
        $data["subject"],
        $data["message"],
        $data["id"]
    ]);

    sendResponse(["success" => true]);
}

function deleteTopic($db, $id) {
    $stmt = $db->prepare("DELETE FROM topics WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(["success" => true]);
}

/* ================= REPLIES ================= */

function getRepliesByTopicId($db, $topicId) {
    $stmt = $db->prepare("SELECT * FROM replies WHERE topic_id=? ORDER BY created_at ASC");
    $stmt->execute([$topicId]);

    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(["success" => true, "data" => $replies]);
}

function createReply($db, $data) {
    if (empty($data["topic_id"]) || empty($data["text"]) || empty($data["author"])) {
        sendResponse(["success" => false], 400);
    }

    $stmt = $db->prepare("INSERT INTO replies (topic_id, text, author) VALUES (?, ?, ?)");
    $stmt->execute([
        $data["topic_id"],
        $data["text"],
        $data["author"]
    ]);

    sendResponse([
        "success" => true,
        "data" => [
            "id" => $db->lastInsertId(),
            "topic_id" => $data["topic_id"],
            "text" => $data["text"],
            "author" => $data["author"],
            "created_at" => date("Y-m-d H:i:s")
        ]
    ], 201);
}

function deleteReply($db, $id) {
    $stmt = $db->prepare("DELETE FROM replies WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(["success" => true]);
}

/* ================= ROUTER ================= */

try {

    if ($method === "GET") {

        if ($action === "replies") {
            getRepliesByTopicId($db, $topicId);
        } elseif ($id) {
            getTopicById($db, $id);
        } else {
            getAllTopics($db);
        }

    } elseif ($method === "POST") {

        if ($action === "reply") {
            createReply($db, $data);
        } else {
            createTopic($db, $data);
        }

    } elseif ($method === "PUT") {

        updateTopic($db, $data);

    } elseif ($method === "DELETE") {

        if ($action === "delete_reply") {
            deleteReply($db, $id);
        } else {
            deleteTopic($db, $id);
        }

    } else {
        sendResponse(["success" => false], 405);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(["success" => false], 500);
}

/* ================= HELPERS ================= */

function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}
?>
