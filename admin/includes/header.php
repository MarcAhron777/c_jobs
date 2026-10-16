<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER["PHP_SELF"]);

?>

<style>
.admin-navbar {
    background: #111827;
    padding: 18px 50px;
}

.admin-navbar-container {
    max-width: 1250px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.admin-logo {
    color: #ffffff;
    text-decoration: none;
    font-size: 22px;
    font-weight: bold;
    white-space: nowrap;
}

.admin-nav-links {
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-nav-links a {
    padding: 9px 12px;
    color: #ffffff;
    text-decoration: none;
    font-size: 14px;
    border-radius: 5px;
    transition: 0.2s;
}

.admin-nav-links a:hover {
    background: #1f2937;
}

.admin-nav-links a.active {
    background: #2563eb;
}

.admin-nav-links a.logout {
    background: #dc2626;
}

.admin-nav-links a.logout:hover {
    background: #b91c1c;
}

.admin-menu-button {
    display: none;
    padding: 9px 12px;
    background: #2563eb;
    color: #ffffff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

@media (max-width: 900px) {
    .admin-navbar {
        padding: 15px 20px;
    }

    .admin-navbar-container {
        flex-wrap: wrap;
    }

    .admin-menu-button {
        display: block;
    }

    .admin-nav-links {
        display: none;
        width: 100%;
    }

    .admin-nav-links.show {
        display: flex;
        flex-direction: column;
        align-items: stretch;
    }

    .admin-nav-links a {
        width: 100%;
    }
}
</style>

<nav class="admin-navbar">

    <div class="admin-navbar-container">

        <a href="dashboard.php" class="admin-logo">
            Job Portal Admin
        </a>

        <button
            type="button"
            class="admin-menu-button"
            id="adminMenuButton"
        >
            Menu
        </button>

        <div
            class="admin-nav-links"
            id="adminNavLinks"
        >

            <a
                href="dashboard.php"
                class="<?= $currentPage === "dashboard.php"
                    ? "active"
                    : ""
                ?>"
            >
                Dashboard
            </a>

            <a
                href="jobs.php"
                class="<?= in_array(
                    $currentPage,
                    ["jobs.php", "view-job.php"],
                    true
                )
                    ? "active"
                    : ""
                ?>"
            >
                Manage Jobs
            </a>

            <a
                href="employers.php"
                class="<?= in_array(
                    $currentPage,
                    ["employers.php", "view-employer.php"],
                    true
                )
                    ? "active"
                    : ""
                ?>"
            >
                Manage Employers
            </a>

            <a
                href="applicants.php"
                class="<?= $currentPage === "applicants.php"
                    ? "active"
                    : ""
                ?>"
            >
                Manage Applicants
            </a>

            <a
                href="reports.php"
                class="<?= $currentPage === "reports.php"
                    ? "active"
                    : ""
                ?>"
            >
                Manage Reports
            </a>

            <a href="../logout.php" class="logout">
                Logout
            </a>

        </div>

    </div>

</nav>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const button = document.getElementById("adminMenuButton");
    const menu = document.getElementById("adminNavLinks");

    if (!button || !menu) {
        return;
    }

    button.addEventListener("click", function () {
        menu.classList.toggle("show");
    });
});
</script>