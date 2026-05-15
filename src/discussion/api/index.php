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
        } elseif ($id !== null) {
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
    sendResponse(['success' => false, 'message' => 'Database error occurred'], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error occurred'], 500);
}

function getAllTopics(PDO $db): void {
    $search = $_GET['search'] ?? null;
    $sort = $_GET['sort'] ?? 'created_at';
    $order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

    $allowedSort = ['subject', 'author', 'created_at'];
    if (!in_array($sort, $allowedSort)) {
        $sort = 'created_at';
    }

    $sql = "SELECT id, subject, message, author, created_at FROM topics";
    $params = [];

    if ($search !== null && trim($search) !== '') {
        $sql .= " WHERE subject LIKE :search OR message LIKE :search OR author LIKE :search";
        $params['search'] = "%" . trim($search) . "%";
    }

    $sql .= " ORDER BY $sort $order";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $topics]);
}

function getTopicById(PDO $db, $id): void {
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Missing or invalid topic ID'], 400);
    }

    $stmt = $db->prepare("SELECT id, subject, message, author, created_at FROM topics WHERE id = ?");
    $stmt->execute([$id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($topic) {
        sendResponse(['success' => true, 'data' => $topic]);
    } else {
        sendResponse(['success' => false, 'message' => 'Topic not found'], 404);
    }
}

function createTopic(PDO $db, array $data): void {
    $subject = isset($data['subject']) ? trim($data['subject']) : '';
    $message = isset($data['message']) ? trim($data['message']) : '';
    $author  = isset($data['author']) ? trim($data['author']) : '';

    if ($subject === '' || $message === '' || $author === '') {
        sendResponse(['success' => false, 'message' => 'Missing required fields'], 400);
    }

    $stmt = $db->prepare("INSERT INTO topics (subject, message, author) VALUES (?, ?, ?)");
    if ($stmt->execute([$subject, $message, $author])) {
        sendResponse([
            'success' => true,
            'message' => 'Topic created successfully',
            'id' => (int)$db->lastInsertId()
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create topic'], 500);
    }
}

function updateTopic(PDO $db, array $data): void {
    $id = $data['id'] ?? $_GET['id'] ?? null;
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Missing or invalid topic ID'], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM topics WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found'], 404);
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
        sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }

    $params[] = $id;
    $sql = "UPDATE topics SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute($params)) {
        sendResponse(['success' => true, 'message' => 'Topic updated successfully']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update topic'], 500);
    }
}

function deleteTopic(PDO $db, $id): void {
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Missing or invalid topic ID'], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM topics WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM topics WHERE id = ?");
    if ($stmt->execute([$id])) {
        sendResponse(['success' => true, 'message' => 'Topic deleted successfully']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete topic'], 500);
    }
}

function getRepliesByTopicId(PDO $db, $topicId): void {
    if ($topicId === null || !is_numeric($topicId)) {
        sendResponse(['success' => false, 'message' => 'Missing or invalid topic ID'], 400);
    }

    $stmt = $db->prepare("SELECT id, topic_id, text, author, created_at FROM replies WHERE topic_id = ? ORDER BY created_at ASC");
    $stmt->execute([$topicId]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $replies]);
}

function createReply(PDO $db, array $data): void {
    $topicId = $data['topic_id'] ?? null;
    $text    = isset($data['text']) ? trim($data['text']) : '';
    $author  = isset($data['author']) ? trim($data['author']) : '';

    if ($topicId === null || !is_numeric($topicId) || $text === '' || $author === '') {
        sendResponse(['success' => false, 'message' => 'Missing required fields'], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM topics WHERE id = ?");
    $checkStmt->execute([$topicId]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found'], 404);
    }

    $stmt = $db->prepare("INSERT INTO replies (topic_id, text, author) VALUES (?, ?, ?)");
    if ($stmt->execute([$topicId, $text, $author])) {
        $newId = $db->lastInsertId();
        
        $fetchStmt = $db->prepare("SELECT id, topic_id, text, author, created_at FROM replies WHERE id = ?");
        $fetchStmt->execute([$newId]);
        $replyObject = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        sendResponse([
            'success' => true,
            'message' => 'Reply created successfully',
            'id' => (int)$newId,
            'data' => $replyObject
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create reply'], 500);
    }
}

function deleteReply(PDO $db, $id): void {
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Missing or invalid reply ID'], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM replies WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Reply not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM replies WHERE id = ?");
    if ($stmt->execute([$id])) {
        sendResponse(['success' => true, 'message' => 'Reply deleted successfully']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete reply'], 500);
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
