<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| AUTH CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| ACTIVATE / DEACTIVATE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $userId =
        $_POST["user_id"]
        ?? null;

    $action =
        $_POST["action"]
        ?? null;


    if (
        $userId &&
        in_array(
            $action,
            [
                "activate",
                "deactivate"
            ],
            true
        )
    ) {

        $isActive =
            $action === "activate"
            ? 1
            : 0;


        $stmt = $pdo->prepare("
            UPDATE users
            SET is_active = ?
            WHERE id = ?
            AND role = 'employer'
        ");


        $stmt->execute([
            $isActive,
            $userId
        ]);
    }


    header(
        "Location: employers.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| GET EMPLOYERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT

        e.id,

        e.user_id,

        e.company_name,

        e.company_description,

        e.contact_person,

        e.contact_number,

        e.address,

        e.barangay,

        e.caloocan_area,

        e.verification_status,

        e.created_at,

        u.email,

        u.is_active,

        (
            SELECT COUNT(*)
            FROM jobs j
            WHERE j.employer_id = e.id
        ) AS total_jobs

    FROM employers e

    INNER JOIN users u
        ON e.user_id = u.id

    ORDER BY e.created_at DESC
");


$employers =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

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
    Employers - Admin
</title>


<style>

* {
    box-sizing:
        border-box;

    margin:
        0;

    padding:
        0;
}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        #f5f7fb;
}


/*
|--------------------------------------------------------------------------
| NAVBAR
|--------------------------------------------------------------------------
*/

.navbar {

    background:
        #111827;

    padding:
        18px 50px;
}


.navbar-container {

    max-width:
        1200px;

    margin:
        auto;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;
}


.logo {

    color:
        white;

    text-decoration:
        none;

    font-size:
        22px;

    font-weight:
        bold;
}


.nav-links {

    display:
        flex;

    gap:
        20px;

    align-items:
        center;
}


.nav-links a {

    color:
        white;

    text-decoration:
        none;

    font-size:
        14px;
}


.logout {

    background:
        #dc2626;

    padding:
        8px 14px;

    border-radius:
        5px;
}


/*
|--------------------------------------------------------------------------
| CONTAINER
|--------------------------------------------------------------------------
*/

.container {

    max-width:
        1250px;

    margin:
        40px auto;

    padding:
        0 20px;
}


h1 {

    margin-bottom:
        10px;
}


.description {

    color:
        #666;

    margin-bottom:
        30px;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-container {

    background:
        white;

    border-radius:
        8px;

    border:
        1px solid #e5e7eb;

    overflow-x:
        auto;
}


table {

    width:
        100%;

    border-collapse:
        collapse;
}


th,
td {

    padding:
        15px;

    text-align:
        left;

    border-bottom:
        1px solid #eee;

    vertical-align:
        middle;
}


th {

    background:
        #f9fafb;

    font-size:
        13px;
}


td {

    font-size:
        14px;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.status {

    display:
        inline-block;

    padding:
        5px 10px;

    border-radius:
        15px;

    font-size:
        12px;

    font-weight:
        bold;
}


.active {

    background:
        #dcfce7;

    color:
        #166534;
}


.inactive {

    background:
        #fee2e2;

    color:
        #991b1b;
}


.pending {

    background:
        #fef3c7;

    color:
        #92400e;
}


.approved {

    background:
        #dcfce7;

    color:
        #166534;
}


.rejected {

    background:
        #fee2e2;

    color:
        #991b1b;
}


.unverified {

    background:
        #e5e7eb;

    color:
        #374151;
}


/*
|--------------------------------------------------------------------------
| AREA
|--------------------------------------------------------------------------
*/

.area {

    display:
        inline-block;

    padding:
        5px 10px;

    border-radius:
        15px;

    font-size:
        12px;

    font-weight:
        bold;
}


.north {

    background:
        #dbeafe;

    color:
        #1e40af;
}


.south {

    background:
        #dcfce7;

    color:
        #166534;
}


/*
|--------------------------------------------------------------------------
| BUTTONS
|--------------------------------------------------------------------------
*/

.actions {

    display:
        flex;

    gap:
        7px;

    align-items:
        center;

    flex-wrap:
        wrap;
}


.actions form {

    margin:
        0;
}


.btn {

    display:
        inline-block;

    border:
        none;

    padding:
        7px 12px;

    border-radius:
        4px;

    cursor:
        pointer;

    color:
        white;

    font-size:
        12px;

    text-decoration:
        none;
}


.view {

    background:
        #2563eb;
}


.view:hover {

    background:
        #1d4ed8;
}


.activate {

    background:
        #16a34a;
}


.deactivate {

    background:
        #dc2626;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 800px) {

    .navbar {

        padding:
            15px 20px;
    }


    .navbar-container {

        flex-direction:
            column;

        align-items:
            flex-start;

        gap:
            15px;
    }


    .nav-links {

        flex-wrap:
            wrap;

        gap:
            12px;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">

    <div class="navbar-container">


        <a
            href="dashboard.php"
            class="logo"
        >
            Job Portal Admin
        </a>


        <div class="nav-links">


            <a href="dashboard.php">
                Dashboard
            </a>


            <a href="jobs.php">
                Manage Jobs
            </a>


            <a href="employers.php">
                Manage Employers
            </a>


            <a href="applicants.php">
                Manage Applicants
            </a>


            <a
                href="../logout.php"
                class="logout"
            >
                Logout
            </a>


        </div>

    </div>

</nav>



<!-- =========================================================
     MAIN
========================================================= -->

<div class="container">


    <h1>
        Employers
    </h1>


    <p class="description">
        View and manage registered employers.
    </p>



    <div class="table-container">

        <table>


            <thead>

                <tr>

                    <th>
                        Company
                    </th>

                    <th>
                        Email
                    </th>

                    <th>
                        Contact
                    </th>

                    <th>
                        Barangay
                    </th>

                    <th>
                        Area
                    </th>

                    <th>
                        Jobs
                    </th>

                    <th>
                        Verification
                    </th>

                    <th>
                        Account
                    </th>

                    <th>
                        Registered
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>



            <tbody>


            <?php if (
                empty($employers)
            ): ?>


                <tr>

                    <td colspan="10">

                        No employers found.

                    </td>

                </tr>


            <?php else: ?>


                <?php foreach (
                    $employers
                    as $employer
                ): ?>


                    <tr>


                        <!-- COMPANY -->

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $employer[
                                        "company_name"
                                    ]
                                ) ?>

                            </strong>

                        </td>



                        <!-- EMAIL -->

                        <td>

                            <?= htmlspecialchars(
                                $employer[
                                    "email"
                                ]
                            ) ?>

                        </td>



                        <!-- CONTACT -->

                        <td>

                            <?= htmlspecialchars(
                                $employer[
                                    "contact_person"
                                ]
                                ?: "N/A"
                            ) ?>

                            <br>

                            <small>

                                <?= htmlspecialchars(
                                    $employer[
                                        "contact_number"
                                    ]
                                    ?: ""
                                ) ?>

                            </small>

                        </td>



                        <!-- BARANGAY -->

                        <td>

                            <?= htmlspecialchars(
                                $employer[
                                    "barangay"
                                ]
                                ?: "N/A"
                            ) ?>

                        </td>



                        <!-- AREA -->

                        <td>


                            <?php if (
                                !empty(
                                    $employer[
                                        "caloocan_area"
                                    ]
                                )
                            ): ?>


                                <span
                                    class="
                                        area
                                        <?= htmlspecialchars(
                                            $employer[
                                                "caloocan_area"
                                            ]
                                        ) ?>
                                    "
                                >

                                    <?= strtoupper(
                                        htmlspecialchars(
                                            $employer[
                                                "caloocan_area"
                                            ]
                                        )
                                    ) ?>

                                </span>


                            <?php else: ?>


                                N/A


                            <?php endif; ?>


                        </td>



                        <!-- JOBS -->

                        <td>

                            <?= (int)
                                $employer[
                                    "total_jobs"
                                ]
                            ?>

                        </td>



                        <!-- VERIFICATION -->

                        <td>


                            <?php

                            $verificationStatus =
                                $employer[
                                    "verification_status"
                                ]
                                ?: "unverified";

                            ?>


                            <span
                                class="
                                    status
                                    <?= htmlspecialchars(
                                        $verificationStatus
                                    ) ?>
                                "
                            >

                                <?= ucfirst(
                                    htmlspecialchars(
                                        $verificationStatus
                                    )
                                ) ?>

                            </span>


                        </td>



                        <!-- ACCOUNT -->

                        <td>


                            <?php if (
                                $employer[
                                    "is_active"
                                ]
                            ): ?>


                                <span
                                    class="
                                        status
                                        active
                                    "
                                >
                                    Active
                                </span>


                            <?php else: ?>


                                <span
                                    class="
                                        status
                                        inactive
                                    "
                                >
                                    Inactive
                                </span>


                            <?php endif; ?>


                        </td>



                        <!-- REGISTERED -->

                        <td>

                            <?= date(
                                "M d, Y",
                                strtotime(
                                    $employer[
                                        "created_at"
                                    ]
                                )
                            ) ?>

                        </td>



                        <!-- ACTION -->

                        <td>


                            <div class="actions">


                                <!-- VIEW -->

                                <a
                                    href="view-employer.php?id=<?= (int)
                                        $employer[
                                            "id"
                                        ]
                                    ?>"
                                    class="
                                        btn
                                        view
                                    "
                                >
                                    View
                                </a>



                                <!-- ACTIVATE / DEACTIVATE -->

                                <form method="POST">


                                    <input
                                        type="hidden"
                                        name="user_id"

                                        value="<?= (int)
                                            $employer[
                                                "user_id"
                                            ]
                                        ?>"
                                    >


                                    <?php if (
                                        $employer[
                                            "is_active"
                                        ]
                                    ): ?>


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="deactivate"
                                        >


                                        <button
                                            type="submit"
                                            class="
                                                btn
                                                deactivate
                                            "
                                        >
                                            Deactivate
                                        </button>


                                    <?php else: ?>


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="activate"
                                        >


                                        <button
                                            type="submit"
                                            class="
                                                btn
                                                activate
                                            "
                                        >
                                            Activate
                                        </button>


                                    <?php endif; ?>


                                </form>


                            </div>


                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php endif; ?>


            </tbody>

        </table>

    </div>

</div>


</body>

</html>