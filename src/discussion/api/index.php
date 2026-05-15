<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

try {
    $db = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true) ?? [];

    $action  = $_GET['action']  ?? null;
    $id      = $_GET['id']      ?? null;
    $topicId = $_GET['topic_id'] ?? null;

    if ($method === 'GET') {
        if ($action === 'replies') {
            getRepliesByTopicId($db, $topicId);
        } elseif ($id) {
            getTopicById($db, $id);
        } else {
            getAllTopics($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'reply') {
            createReply($db, $data);
        } else {
            createTopic($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateTopic($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_reply') {
            deleteReply($db, $id);
        } else {
            deleteTopic($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method Not Allowed'], 405);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal Server Error'], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal Server Error'], 500);
}

function getAllTopics(PDO $db): void {
    $search = $_GET['search'] ?? null;
    $sort = $_GET['sort'] ?? 'created_at';
    $order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

    $allowedSort = ['subject', 'author', 'created_at'];
    if (!in_array($sort, $allowedSort)) { $sort = 'created_at'; }

    $sql = "SELECT id, subject, message, author, created_at FROM topics";
    $params = [];

    if ($search) {
        $sql .= " WHERE subject LIKE :search OR message LIKE :search OR author LIKE :search";
        $params['search'] = "%$search%";
    }

    $sql .= " ORDER BY $sort $order";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getTopicById(PDO $db, $id): void {
    if (!is_numeric($id)) sendResponse(['success' => false], 400);

    $stmt = $db->prepare("SELECT * FROM topics WHERE id = ?");
    $stmt->execute([$id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($topic) {
        sendResponse(['success' => true, 'data' => $topic]);
    } else {
        sendResponse(['success' => false], 404);
    }
}

function createTopic(PDO $db, array $data): void {
    $subject = sanitizeInput($data['subject'] ?? '');
    $message = sanitizeInput($data['message'] ?? '');
    $author = sanitizeInput($data['author'] ?? '');

    if (empty($subject) || empty($message) || empty($author)) {
        sendResponse(['success' => false], 400);
    }

    $stmt = $db->prepare("INSERT INTO topics (subject, message, author) VALUES (?, ?, ?)");
    if ($stmt->execute([$subject, $message, $author])) {
        sendResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
    } else {
        sendResponse(['success' => false], 500);
    }
}

function updateTopic(PDO $db, array $data): void {
    $id = $data['id'] ?? null;
    if (!$id) sendResponse(['success' => false], 400);

    $fields = [];
    $params = [];

    if (isset($data['subject'])) {
        $fields[] = "subject = ?";
        $params[] = sanitizeInput($data['subject']);
    }
    if (isset($data['message'])) {
        $fields[] = "message = ?";
        $params[] = sanitizeInput($data['message']);
    }

    if (empty($fields)) sendResponse(['success' => false], 400);

    $params[] = $id;
    $sql = "UPDATE topics SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute($params)) {
        sendResponse(['success' => true]);
    } else {
        sendResponse(['success' => false], 500);
    }
}

function deleteTopic(PDO $db, $id): void {
    if (!is_numeric($id)) sendResponse(['success' => false], 400);

    $stmt = $db->prepare("DELETE FROM topics WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true]);
    } else {
        sendResponse(['success' => false], 404);
    }
}

function getRepliesByTopicId(PDO $db, $topicId): void {
    if (!is_numeric($topicId)) sendResponse(['success' => false], 400);

    $stmt = $db->prepare("SELECT * FROM replies WHERE topic_id = ? ORDER BY created_at ASC");
    $stmt->execute([$topicId]);
    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createReply(PDO $db, array $data): void {
    $topicId = $data['topic_id'] ?? null;
    $text = sanitizeInput($data['text'] ?? '');
    $author = sanitizeInput($data['author'] ?? '');

    if (!$topicId || empty($text) || empty($author)) {
        sendResponse(['success' => false], 400);
    }

    $stmt = $db->prepare("INSERT INTO replies (topic_id, text, author) VALUES (?, ?, ?)");
    if ($stmt->execute([$topicId, $text, $author])) {
        sendResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
    } else {
        sendResponse(['success' => false], 500);
    }
}

function deleteReply(PDO $db, $id): void {
    if (!is_numeric($id)) sendResponse(['success' => false], 400);

    $stmt = $db->prepare("DELETE FROM replies WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true]);
    } else {
        sendResponse(['success' => false], 404);
    }
}

function sendResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sanitizeInput(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
