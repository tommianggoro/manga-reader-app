<?php
/**
 * Unified Tags API - handles all tag operations for logged-in users.
 * Actions: list, create, delete, assign, remove, manga_tags
 */
require_once "config.php";
requireAuth();

header("Content-Type: application/json; charset=utf-8");

$action = $_GET["action"] ?? $_POST["action"] ?? "";
$userId = currentUserId();

try {
    switch ($action) {
        case "list":
            // Get all tags for current user with manga count
            $stmt = $pdo->prepare("
                SELECT ut.id, ut.name, ut.color, ut.created_at,
                       COUNT(mut.manga_id) AS manga_count
                FROM user_tags ut
                LEFT JOIN manga_user_tags mut ON mut.tag_id = ut.id AND mut.user_id = ut.user_id
                WHERE ut.user_id = :uid
                GROUP BY ut.id
                ORDER BY ut.name ASC
            ");
            $stmt->execute([":uid" => $userId]);
            $tags = $stmt->fetchAll();
            echo json_encode(["success" => true, "tags" => $tags]);
            break;

        case "create":
            $name = trim($_POST["name"] ?? "");
            $color = $_POST["color"] ?? "#6c757d";

            if ($name === "") {
                echo json_encode(["success" => false, "error" => "Nama tag tidak boleh kosong"]);
                exit;
            }
            if (mb_strlen($name) > 64) {
                echo json_encode(["success" => false, "error" => "Nama tag maksimal 64 karakter"]);
                exit;
            }
            // Validate hex color
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = "#6c757d";
            }

            $stmt = $pdo->prepare("INSERT INTO user_tags (user_id, name, color) VALUES (:uid, :name, :color)");
            try {
                $stmt->execute([":uid" => $userId, ":name" => $name, ":color" => $color]);
                $tagId = $pdo->lastInsertId();
                echo json_encode(["success" => true, "tag" => ["id" => $tagId, "name" => $name, "color" => $color, "manga_count" => 0]]);
            } catch (PDOException $e) {
                if ($e->getCode() === "23000") { // Duplicate entry
                    echo json_encode(["success" => false, "error" => "Tag dengan nama ini sudah ada"]);
                } else {
                    throw $e;
                }
            }
            break;

        case "delete":
            $tagId = (int) ($_POST["tag_id"] ?? 0);
            if (!$tagId) {
                echo json_encode(["success" => false, "error" => "tag_id wajib diisi"]);
                exit;
            }
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM user_tags WHERE id = :tid AND user_id = :uid");
            $stmt->execute([":tid" => $tagId, ":uid" => $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(["success" => false, "error" => "Tag tidak ditemukan"]);
                exit;
            }
            // Delete (cascade will remove manga_user_tags entries)
            $stmt = $pdo->prepare("DELETE FROM user_tags WHERE id = :tid AND user_id = :uid");
            $stmt->execute([":tid" => $tagId, ":uid" => $userId]);
            echo json_encode(["success" => true]);
            break;

        case "assign":
            $mangaId = $_POST["manga_id"] ?? "";
            $tagId = (int) ($_POST["tag_id"] ?? 0);
            if (!$mangaId || !$tagId) {
                echo json_encode(["success" => false, "error" => "manga_id dan tag_id wajib diisi"]);
                exit;
            }
            // Verify manga exists
            $stmt = $pdo->prepare("SELECT manga_id FROM mangas WHERE manga_id = :mid");
            $stmt->execute([":mid" => $mangaId]);
            if (!$stmt->fetch()) {
                echo json_encode(["success" => false, "error" => "Manga tidak ditemukan"]);
                exit;
            }
            // Verify tag ownership
            $stmt = $pdo->prepare("SELECT id FROM user_tags WHERE id = :tid AND user_id = :uid");
            $stmt->execute([":tid" => $tagId, ":uid" => $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(["success" => false, "error" => "Tag tidak ditemukan"]);
                exit;
            }
            // Assign tag
            $stmt = $pdo->prepare("
                INSERT INTO manga_user_tags (user_id, manga_id, tag_id)
                VALUES (:uid, :mid, :tid)
                ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)
            ");
            $stmt->execute([":uid" => $userId, ":mid" => $mangaId, ":tid" => $tagId]);
            echo json_encode(["success" => true]);
            break;

        case "remove":
            $mangaId = $_POST["manga_id"] ?? "";
            $tagId = (int) ($_POST["tag_id"] ?? 0);
            if (!$mangaId || !$tagId) {
                echo json_encode(["success" => false, "error" => "manga_id dan tag_id wajib diisi"]);
                exit;
            }
            $stmt = $pdo->prepare("
                DELETE FROM manga_user_tags
                WHERE user_id = :uid AND manga_id = :mid AND tag_id = :tid
            ");
            $stmt->execute([":uid" => $userId, ":mid" => $mangaId, ":tid" => $tagId]);
            echo json_encode(["success" => true]);
            break;

        case "manga_tags":
            // Get tags assigned to a specific manga for current user
            $mangaId = $_GET["manga_id"] ?? "";
            if (!$mangaId) {
                echo json_encode(["success" => false, "error" => "manga_id wajib diisi"]);
                exit;
            }
            $stmt = $pdo->prepare("
                SELECT ut.id, ut.name, ut.color
                FROM user_tags ut
                JOIN manga_user_tags mut ON mut.tag_id = ut.id
                WHERE mut.user_id = :uid AND mut.manga_id = :mid
                ORDER BY ut.name ASC
            ");
            $stmt->execute([":uid" => $userId, ":mid" => $mangaId]);
            $tags = $stmt->fetchAll();
            echo json_encode(["success" => true, "tags" => $tags]);
            break;

        default:
            echo json_encode(["success" => false, "error" => "Action tidak valid: " . $action]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Server error: " . $e->getMessage()]);
}