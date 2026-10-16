<?php

session_start();

require_once "../config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
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
    $userId = isset($_POST["user_id"])
        ? (int) $_POST["user_id"]
        : 0;

    $action = $_POST["action"] ?? "";

    if (
        $userId > 0 &&
        in_array(
            $action,
            ["activate", "deactivate"],
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

    header("Location: employers.php");
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
        e.employer_type,
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
    $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #222;
}

.container {
    max-width: 1350px;
    margin: 40px auto;
    padding: 0 20px 50px;
}

h1 {
    margin-bottom: 10px;
}

.description {
    color: #666;
    margin-bottom: 30px;
}

.table-container {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

th {
    background: #f9fafb;
    font-size: 13px;
}

td {
    font-size: 14px;
}

.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
}

.type-private {
    background: #e0e7ff;
    color: #3730a3;
}

.type-lgu {
    background: #dbeafe;
    color: #1d4ed8;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

.approved {
    background: #dcfce7;
    color: #166534;
}

.rejected {
    background: #fee2e2;
    color: #991b1b;
}

.unverified {
    background: #e5e7eb;
    color: #374151;
}

.active-status {
    background: #dcfce7;
    color: #166534;
}

.inactive-status {
    background: #fee2e2;
    color: #991b1b;
}

.north {
    background: #dbeafe;
    color: #1e40af;
}

.south {
    background: #dcfce7;
    color: #166534;
}

.actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    align-items: center;
}

.actions form {
    margin: 0;
}

.btn {
    display: inline-block;
    padding: 7px 12px;
    border: none;
    border-radius: 4px;
    color: #ffffff;
    font-size: 12px;
    text-decoration: none;
    cursor: pointer;
}

.view {
    background: #2563eb;
}

.activate {
    background: #16a34a;
}

.deactivate {
    background: #dc2626;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #64748b;
}
</style>

</head>

<body>

<?php require __DIR__ . "/includes/header.php"; ?>

<div class="container">

    <h1>
        Employers
    </h1>

    <p class="description">
        View and verify registered Private and LGU employers.
    </p>

    <div class="table-container">

        <table>

            <thead>

                <tr>
                    <th>Company / Organization</th>
                    <th>Employer Type</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>Barangay</th>
                    <th>Area</th>
                    <th>Jobs</th>
                    <th>Verification</th>
                    <th>Account</th>
                    <th>Registered</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            <?php if (empty($employers)): ?>

                <tr>
                    <td colspan="11" class="empty">
                        No employers found.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($employers as $employer): ?>

                    <?php
                    $type =
                        $employer["employer_type"]
                        ?? "private";

                    $verificationStatus =
                        $employer["verification_status"]
                        ?: "unverified";
                    ?>

                    <tr>

                        <td>
                            <strong>
                                <?= htmlspecialchars(
                                    $employer["company_name"]
                                ) ?>
                            </strong>
                        </td>

                        <td>

                            <?php if ($type === "lgu"): ?>

                                <span class="badge type-lgu">
                                    LGU
                                </span>

                            <?php else: ?>

                                <span class="badge type-private">
                                    Private Employer
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $employer["email"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $employer["contact_number"]
                                ?: "N/A"
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $employer["barangay"]
                                ?: "N/A"
                            ) ?>
                        </td>

                        <td>

                            <?php if (
                                $employer["caloocan_area"] === "north"
                            ): ?>

                                <span class="badge north">
                                    North
                                </span>

                            <?php elseif (
                                $employer["caloocan_area"] === "south"
                            ): ?>

                                <span class="badge south">
                                    South
                                </span>

                            <?php else: ?>

                                N/A

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= (int)
                                $employer["total_jobs"]
                            ?>
                        </td>

                        <td>
                            <span
                                class="badge <?= htmlspecialchars(
                                    $verificationStatus
                                ) ?>"
                            >
                                <?= htmlspecialchars(
                                    ucfirst(
                                        $verificationStatus
                                    )
                                ) ?>
                            </span>
                        </td>

                        <td>

                            <?php if ($employer["is_active"]): ?>

                                <span class="badge active-status">
                                    Active
                                </span>

                            <?php else: ?>

                                <span class="badge inactive-status">
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= date(
                                "M d, Y",
                                strtotime(
                                    $employer["created_at"]
                                )
                            ) ?>
                        </td>

                        <td>

                            <div class="actions">

                                <a
                                    href="view-employer.php?id=<?= (int)
                                        $employer["id"]
                                    ?>"
                                    class="btn view"
                                >
                                    View
                                </a>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int)
                                            $employer["user_id"]
                                        ?>"
                                    >

                                    <?php if ($employer["is_active"]): ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="deactivate"
                                        >

                                        <button
                                            type="submit"
                                            class="btn deactivate"
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
                                            class="btn activate"
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