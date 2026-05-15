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

    if (!empty($search)) {
        $sql .= " WHERE subject LIKE :search OR message LIKE :search OR author LIKE :search";
        $params['search'] = "%$search%";
    }

    $sql .= " ORDER BY $sort $order";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getTopicById(PDO $db, $id): void {
    if (empty($id) || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Bad Request'], 400);
    }

    $stmt = $db->prepare("SELECT id, subject, message, author, created_at FROM topics WHERE id = ?");
    $stmt->execute([$id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($topic) {
        sendResponse(['success' => true, 'data' => $topic]);
    } else {
        sendResponse(['success' => false, 'message' => 'Not Found'], 404);
    }
}

function createTopic(PDO $db, array $data): void {
    $subject = isset($data['subject']) ? trim($data['subject']) : '';
    $message = isset($data['message']) ? trim($data['message']) : '';
    $author  = isset($data['author']) ? trim($data['author']) : '';

    if (empty($subject) || empty($message) || empty($author)) {
        sendResponse(['success' => false, 'message' => 'Bad Request'], 400);
    }

    $stmt = $db->prepare("INSERT INTO topics (subject, message, author) VALUES (?, ?, ?)");
    if ($stmt->execute([$subject, $message, $author])) {
        sendResponse([
            'success' => true,
            'message' => 'Topic created successfully',
            'id' => (int)$db->lastInsertId()
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Internal Server Error'], 500);
    }
}

function updateTopic(PDO $db, array $data): void {
    $id = $data['id'] ?? $_GET['id'] ?? null;
    if (empty($id) || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Bad Request'], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM topics WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Not Found'], 404);
    }

    $fields = [];
    $params = [];

    if (isset($data['subject'])) {
        $fields[] = "subject = ?";
        $params[] = trim($data['subject']);
    }
    if (isset($data['message'])) {
        $fields[] = "message = ?";
        $params[] = trim($data['message']);
    }

    if (empty($fields)) {
