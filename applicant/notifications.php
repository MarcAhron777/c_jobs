<?php

session_start();

require_once "../config/database.php";
require_once "../utilities/NotificationService.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "applicant"
) {
    header("Location: ../login.php");
    exit;
}

$userId =
    (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action =
        $_POST["action"]
        ?? "";

    $notificationId =
        isset($_POST["notification_id"])
            ? (int) $_POST["notification_id"]
            : 0;

    if (
        $action === "read" &&
        $notificationId > 0
    ) {
        NotificationService::markAsRead(
            $pdo,
            $notificationId,
            $userId
        );
    }

    if ($action === "read_all") {
        NotificationService::markAllAsRead(
            $pdo,
            $userId
        );
    }

    if (
        $action === "delete" &&
        $notificationId > 0
    ) {
        NotificationService::delete(
            $pdo,
            $notificationId,
            $userId
        );
    }

    header("Location: notifications.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| GET NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$notifications =
    NotificationService::getUserNotifications(
        $pdo,
        $userId,
        100
    );


function applicantNotificationUrl(
    array $notification
): string {
    $type =
        $notification["type"]
        ?? "general";

    $relatedId =
        (int) (
            $notification["related_id"]
            ?? 0
        );

    if (
        $type === "job_match" &&
        $relatedId > 0
    ) {
        return "../jobs/view.php?id="
            . $relatedId;
    }

    if (
        $type === "application_status" &&
        $relatedId > 0
    ) {
        return "application.php?id="
            . $relatedId;
    }

    return "#";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Notifications - Applicant
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #222;
}

.container {
    max-width: 950px;
    margin: 35px auto;
    padding: 0 20px 50px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.page-header p {
    margin-top: 7px;
    color: #64748b;
}

.notification {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 12px;
    padding: 18px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
}

.notification.unread {
    border-left: 4px solid #2563eb;
    background: #eff6ff;
}

.notification-content {
    flex: 1;
}

.notification-title {
    margin-bottom: 7px;
    font-weight: bold;
}

.notification-message {
    color: #475569;
    line-height: 1.6;
}

.notification-date {
    margin-top: 8px;
    color: #94a3b8;
    font-size: 12px;
}

.notification-link {
    display: inline-block;
    margin-top: 10px;
    color: #2563eb;
    font-size: 13px;
    font-weight: bold;
    text-decoration: none;
}

.actions {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 11px;
    border: none;
    border-radius: 5px;
    color: #ffffff;
    cursor: pointer;
    font-size: 12px;
}

.btn-blue {
    background: #2563eb;
}

.btn-red {
    background: #dc2626;
}

.empty {
    padding: 40px;
    background: #ffffff;
    border-radius: 9px;
    text-align: center;
    color: #64748b;
}

@media (max-width: 700px) {
    .page-header,
    .notification {
        flex-direction: column;
        align-items: flex-start;
    }
}

</style>

</head>

<body>

<?php require __DIR__ . "/includes/header.php"; ?>


<div class="container">

    <div class="page-header">

        <div>

            <h1>
                Notifications
            </h1>

            <p>
                Job matches and application updates.
            </p>

        </div>

        <?php if (
            NotificationService::getUnreadCount(
                $pdo,
                $userId
            ) > 0
        ): ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="read_all"
                >

                <button
                    type="submit"
                    class="btn btn-blue"
                >
                    Mark All as Read
                </button>

            </form>

        <?php endif; ?>

    </div>


    <?php if (empty($notifications)): ?>

        <div class="empty">
            You do not have any notifications yet.
        </div>

    <?php else: ?>

        <?php foreach (
            $notifications
            as
            $notification
        ): ?>

            <?php
            $targetUrl =
                applicantNotificationUrl(
                    $notification
                );
            ?>

            <div
                class="notification <?= !$notification["is_read"]
                    ? "unread"
                    : ""
                ?>"
            >

                <div class="notification-content">

                    <div class="notification-title">
                        <?= htmlspecialchars(
                            $notification["title"]
                        ) ?>
                    </div>

                    <div class="notification-message">
                        <?= htmlspecialchars(
                            $notification["message"]
                        ) ?>
                    </div>

                    <div class="notification-date">
                        <?= date(
                            "M d, Y h:i A",
                            strtotime(
                                $notification["created_at"]
                            )
                        ) ?>
                    </div>

                    <?php if ($targetUrl !== "#"): ?>

                        <a
                            href="<?= htmlspecialchars(
                                $targetUrl
                            ) ?>"
                            class="notification-link"
                        >
                            View Details
                        </a>

                    <?php endif; ?>

                </div>


                <div class="actions">

                    <?php if (
                        !$notification["is_read"]
                    ): ?>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="notification_id"
                                value="<?= (int)
                                    $notification["id"]
                                ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="read"
                            >

                            <button
                                type="submit"
                                class="btn btn-blue"
                            >
                                Mark Read
                            </button>

                        </form>

                    <?php endif; ?>


                    <form method="POST">

                        <input
                            type="hidden"
                            name="notification_id"
                            value="<?= (int)
                                $notification["id"]
                            ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="delete"
                        >

                        <button
                            type="submit"
                            class="btn btn-red"
                        >
                            Delete
                        </button>

                    </form>

                </div>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>