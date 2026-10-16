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
            AND role = 'applicant'
        ");

        $stmt->execute([
            $isActive,
            $userId
        ]);
    }

    header("Location: applicants.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| GET APPLICANTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        a.id,
        a.user_id,
        a.first_name,
        a.last_name,
        a.phone,
        a.address,
        a.created_at,

        u.email,
        u.is_active,

        (
            SELECT COUNT(*)
            FROM applications app
            WHERE app.applicant_id = a.id
        ) AS total_applications,

        (
            SELECT COUNT(*)
            FROM resumes r
            WHERE r.applicant_id = a.id
        ) AS total_resumes

    FROM applicants a

    INNER JOIN users u
        ON a.user_id = u.id

    ORDER BY a.created_at DESC
");

$applicants =
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
    Applicants - Admin
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
    max-width: 1250px;
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
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 15px;
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

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
}

.active-status {
    background: #dcfce7;
    color: #166534;
}

.inactive-status {
    background: #fee2e2;
    color: #991b1b;
}

.btn {
    border: none;
    padding: 7px 12px;
    border-radius: 4px;
    cursor: pointer;
    color: #ffffff;
    font-size: 12px;
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
        Applicants
    </h1>

    <p class="description">
        View and manage registered applicants.
    </p>

    <div class="table-container">

        <table>

            <thead>

                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Applications</th>
                    <th>Resumes</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            <?php if (empty($applicants)): ?>

                <tr>
                    <td colspan="8" class="empty">
                        No applicants found.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($applicants as $applicant): ?>

                    <tr>

                        <td>
                            <strong>
                                <?= htmlspecialchars(
                                    $applicant["first_name"]
                                    . " "
                                    . $applicant["last_name"]
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $applicant["email"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $applicant["phone"]
                                ?: "N/A"
                            ) ?>
                        </td>

                        <td>
                            <?= (int)
                                $applicant["total_applications"]
                            ?>
                        </td>

                        <td>
                            <?= (int)
                                $applicant["total_resumes"]
                            ?>
                        </td>

                        <td>

                            <?php if ($applicant["is_active"]): ?>

                                <span class="status active-status">
                                    Active
                                </span>

                            <?php else: ?>

                                <span class="status inactive-status">
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= date(
                                "M d, Y",
                                strtotime(
                                    $applicant["created_at"]
                                )
                            ) ?>
                        </td>

                        <td>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="user_id"
                                    value="<?= (int)
                                        $applicant["user_id"]
                                    ?>"
                                >

                                <?php if ($applicant["is_active"]): ?>

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