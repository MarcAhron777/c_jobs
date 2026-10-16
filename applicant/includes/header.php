<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../utilities/NotificationService.php";

$currentPage = basename($_SERVER["PHP_SELF"]);

$unreadNotificationCount = 0;

if (
    isset($_SESSION["user_id"]) &&
    ($_SESSION["role"] ?? "") === "applicant"
) {
    try {
        $unreadNotificationCount =
            NotificationService::getUnreadCount(
                $pdo,
                (int) $_SESSION["user_id"]
            );
    } catch (Throwable $e) {
        $unreadNotificationCount = 0;
    }
}

?>

<style>
.navbar {
    background: #ffffff;
    border-bottom: 1px solid #ddd;
    padding: 16px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    font-size: 24px;
    font-weight: bold;
    color: #2563eb;
    text-decoration: none;
}

.nav-links {
    display: flex;
    gap: 20px;
    align-items: center;
}

.nav-links a {
    position: relative;
    text-decoration: none;
    color: #333;
    font-size: 14px;
}

.nav-links a:hover {
    color: #2563eb;
}

.nav-links a.active {
    color: #2563eb;
    font-weight: bold;
}

.nav-links a.logout {
    color: #dc2626 !important;
}

.nav-links a.logout:hover {
    color: #b91c1c !important;
}

.notification-count {
    position: absolute;
    top: -10px;
    right: -13px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    background: #dc2626;
    color: #ffffff;
    border-radius: 20px;
    font-size: 10px;
    font-weight: bold;
}

@media (max-width: 768px) {
    .navbar {
        padding: 15px 20px;
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .nav-links {
        width: 100%;
        gap: 12px;
        flex-wrap: wrap;
    }
}
</style>

<nav class="navbar">

    <a href="../index.php" class="logo">
        JobPortal
    </a>

    <div class="nav-links">

        <a href="../index.php">
            Home
        </a>

        <a
            href="dashboard.php"
            class="<?= $currentPage === "dashboard.php"
                ? "active"
                : ""
            ?>"
        >
            Dashboard
        </a>

        <a href="../jobs/index.php">
            Find Jobs
        </a>

        <a
            href="profile.php"
            class="<?= $currentPage === "profile.php"
                ? "active"
                : ""
            ?>"
        >
            Profile
        </a>

        <a
            href="qualifications.php"
            class="<?= $currentPage === "qualifications.php"
                ? "active"
                : ""
            ?>"
        >
            Qualifications
        </a>

        <a
            href="resume.php"
            class="<?= $currentPage === "resume.php"
                ? "active"
                : ""
            ?>"
        >
            Resume
        </a>

        <a
            href="applications.php"
            class="<?= in_array(
                $currentPage,
                [
                    "applications.php",
                    "application.php"
                ],
                true
            )
                ? "active"
                : ""
            ?>"
        >
            Applications
        </a>

        <a
            href="notifications.php"
            class="<?= $currentPage === "notifications.php"
                ? "active"
                : ""
            ?>"
        >
            Notifications

            <?php if ($unreadNotificationCount > 0): ?>
                <span class="notification-count">
                    <?= $unreadNotificationCount > 99
                        ? "99+"
                        : $unreadNotificationCount
                    ?>
                </span>
            <?php endif; ?>
        </a>

        <a
            href="../logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</nav>