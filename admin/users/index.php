<?php
session_start();
include('../../includes/config.php');
include('../../includes/adminCheck.php');
include('../../includes/header.php');

$sql = "SELECT user_id, name, email, img_path, role, status, created_at FROM users ORDER BY user_id DESC";
$result = mysqli_query($conn, $sql);
$userCount = mysqli_num_rows($result);
?>
<div class="container mt-4">
    <?php include('../../includes/alert.php'); ?>
    <a href="create.php" class="btn btn-primary btn-lg" role="button">Add user</a>
    <h2 class="mt-3">number of users <?= $userCount ?></h2>
    <table class="table table-striped table-bordered align-middle">
        <thead>
            <tr>
                <th>Photo</th>
                <th>Id</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Date joined</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            while ($row = mysqli_fetch_assoc($result)) {
                $photo = "<i class='fa-solid fa-user fa-2x text-muted'></i>";
                if (!empty($row['img_path'])) {
                    $photo = "<img src='../../user/{$row['img_path']}' class='profile-photo' style='width: 50px; height: 50px;'>";
                }

                // a deactivated user cannot login
                if ($row['status'] === 'active') {
                    $status = "<span class='badge bg-success'>active</span>";
                    $statusLink = "<a href='status.php?id={$row['user_id']}&status=inactive' class='btn btn-sm btn-outline-danger'>Deactivate</a>";
                } else {
                    $status = "<span class='badge bg-secondary'>inactive</span>";
                    $statusLink = "<a href='status.php?id={$row['user_id']}&status=active' class='btn btn-sm btn-outline-success'>Activate</a>";
                }

                echo "<tr>";
                echo "<td>{$photo}</td>";
                echo "<td>{$row['user_id']}</td>";
                echo "<td>{$row['name']}</td>";
                echo "<td>{$row['email']}</td>";
                echo "<td>{$row['role']}</td>";
                echo "<td>{$status}</td>";
                echo "<td>{$row['created_at']}</td>";
                echo "<td><a href='edit.php?id={$row['user_id']}'><i class='fa-regular fa-pen-to-square' style='color: blue'></i></a>
                    <a href='delete.php?id={$row['user_id']}'><i class='fa-solid fa-trash' style='color: red'></i></a>
                    {$statusLink}</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>
<?php
include('../../includes/footer.php');
?>
