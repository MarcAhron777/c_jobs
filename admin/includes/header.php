<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER["PHP_SELF"]);

$reportPages = [
    "report-overview.php",
    "report-employers.php",
    "report-applicants.php",
    "report-jobs.php",
    "report-applications.php"
];

$isReportPage = in_array(
    $currentPage,
    $reportPages,
    true
);

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

.admin-nav-links > a,
.admin-dropdown-button {
    padding: 9px 12px;
    color: #ffffff;
    text-decoration: none;
    font-size: 14px;
    border-radius: 5px;
    transition: 0.2s;
}

.admin-nav-links > a:hover,
.admin-dropdown-button:hover {
    background: #1f2937;
}

.admin-nav-links > a.active,
.admin-dropdown-button.active {
    background: #2563eb;
}

.admin-nav-links > a.logout {
    background: #dc2626;
}

.admin-nav-links > a.logout:hover {
    background: #b91c1c;
}


/*
|--------------------------------------------------------------------------
| REPORTS DROPDOWN
|--------------------------------------------------------------------------
*/

.admin-dropdown {
    position: relative;
}

.admin-dropdown-button {
    display: flex;
    align-items: center;
    gap: 6px;
    background: transparent;
    border: none;
    cursor: pointer;
    font-family: inherit;
}

.admin-dropdown-arrow {
    font-size: 10px;
}

.admin-dropdown-menu {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    min-width: 240px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 7px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    z-index: 9999;
}

.admin-dropdown-menu.show {
    display: block;
}

.admin-dropdown-menu a {
    display: block;
    padding: 11px 15px;
    color: #333333;
    text-decoration: none;
    font-size: 13px;
    border-bottom: 1px solid #f1f5f9;
}

.admin-dropdown-menu a:last-child {
    border-bottom: none;
}

.admin-dropdown-menu a:hover {
    background: #f8fafc;
    color: #2563eb;
}

.admin-dropdown-menu a.active {
    background: #eff6ff;
    color: #2563eb;
    font-weight: bold;
}


/*
|--------------------------------------------------------------------------
| MOBILE MENU
|--------------------------------------------------------------------------
*/

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

    .admin-nav-links > a,
    .admin-dropdown-button {
        width: 100%;
        text-align: left;
    }

    .admin-dropdown {
        width: 100%;
    }

    .admin-dropdown-button {
        justify-content: space-between;
    }

    .admin-dropdown-menu {
        position: static;
        width: 100%;
        min-width: 0;
        margin-top: 5px;
        box-shadow: none;
        border-color: #374151;
    }
}
</style>


<nav class="admin-navbar">

    <div class="admin-navbar-container">

        <a
            href="dashboard.php"
            class="admin-logo"
        >
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
                    [
                        "jobs.php",
                        "view-job.php"
                    ],
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
                    [
                        "employers.php",
                        "view-employer.php"
                    ],
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


            <!-- REPORTS DROPDOWN -->

            <div class="admin-dropdown">

                <button
                    type="button"
                    class="admin-dropdown-button <?= $isReportPage
                        ? "active"
                        : ""
                    ?>"
                    id="reportsDropdownButton"
                >
                    <span>Reports</span>

                    <span class="admin-dropdown-arrow">
                        ▼
                    </span>
                </button>


                <div
                    class="admin-dropdown-menu"
                    id="reportsDropdownMenu"
                >

                    <a
                        href="report-employers.php"
                        class="<?= $currentPage === "report-employers.php"
                            ? "active"
                            : ""
                        ?>"
                    >
                        Employer Report
                    </a>


                    <a
                        href="report-applicants.php"
                        class="<?= $currentPage === "report-applicants.php"
                            ? "active"
                            : ""
                        ?>"
                    >
                        Applicant Report
                    </a>


                    <a
                        href="report-jobs.php"
                        class="<?= $currentPage === "report-jobs.php"
                            ? "active"
                            : ""
                        ?>"
                    >
                        Job Posting Report
                    </a>


                    <a
                        href="report-applications.php"
                        class="<?= $currentPage === "report-applications.php"
                            ? "active"
                            : ""
                        ?>"
                    >
                        Application & Matching Report
                    </a>

                </div>

            </div>


            <a
                href="../logout.php"
                class="logout"
            >
                Logout
            </a>

        </div>

    </div>

</nav>


<script>
document.addEventListener("DOMContentLoaded", function () {

    const menuButton =
        document.getElementById(
            "adminMenuButton"
        );

    const navMenu =
        document.getElementById(
            "adminNavLinks"
        );

    const reportsButton =
        document.getElementById(
            "reportsDropdownButton"
        );

    const reportsMenu =
        document.getElementById(
            "reportsDropdownMenu"
        );


    /*
    |--------------------------------------------------------------------------
    | MOBILE MENU
    |--------------------------------------------------------------------------
    */

    if (menuButton && navMenu) {

        menuButton.addEventListener(
            "click",
            function () {
                navMenu.classList.toggle(
                    "show"
                );
            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | REPORTS DROPDOWN
    |--------------------------------------------------------------------------
    */

    if (
        reportsButton &&
        reportsMenu
    ) {

        reportsButton.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();

                reportsMenu.classList.toggle(
                    "show"
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | CLOSE WHEN CLICKING OUTSIDE
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            "click",
            function (event) {

                if (
                    !reportsMenu.contains(
                        event.target
                    ) &&
                    !reportsButton.contains(
                        event.target
                    )
                ) {
                    reportsMenu.classList.remove(
                        "show"
                    );
                }

            }
        );

    }

});
</script>