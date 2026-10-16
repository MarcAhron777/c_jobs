<?php

class NotificationService
{
    public static function create(
        PDO $pdo,
        int $userId,
        string $title,
        string $message,
        string $type = "general",
        ?int $relatedId = null
    ): int {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                title,
                message,
                type,
                related_id,
                is_read,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");

        $stmt->execute([
            $userId,
            $title,
            $message,
            $type,
            $relatedId
        ]);

        return (int) $pdo->lastInsertId();
    }


    public static function getUnreadCount(
        PDO $pdo,
        int $userId
    ): int {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = ?
            AND is_read = 0
        ");

        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }


    public static function getUserNotifications(
        PDO $pdo,
        int $userId,
        int $limit = 50
    ): array {
        $limit = max(1, min(100, $limit));

        $stmt = $pdo->prepare("
            SELECT
                id,
                user_id,
                title,
                message,
                type,
                related_id,
                is_read,
                created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT $limit
        ");

        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function markAsRead(
        PDO $pdo,
        int $notificationId,
        int $userId
    ): void {
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = ?
            AND user_id = ?
        ");

        $stmt->execute([
            $notificationId,
            $userId
        ]);
    }


    public static function markAllAsRead(
        PDO $pdo,
        int $userId
    ): void {
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ?
            AND is_read = 0
        ");

        $stmt->execute([$userId]);
    }


    public static function delete(
        PDO $pdo,
        int $notificationId,
        int $userId
    ): void {
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE id = ?
            AND user_id = ?
        ");

        $stmt->execute([
            $notificationId,
            $userId
        ]);
    }


    public static function exists(
        PDO $pdo,
        int $userId,
        string $type,
        ?int $relatedId
    ): bool {
        $stmt = $pdo->prepare("
            SELECT id
            FROM notifications
            WHERE user_id = ?
            AND type = ?
            AND related_id <=> ?
            LIMIT 1
        ");

        $stmt->execute([
            $userId,
            $type,
            $relatedId
        ]);

        return (bool) $stmt->fetchColumn();
    }
}