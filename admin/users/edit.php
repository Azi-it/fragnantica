<?php
session_start();
include('../../includes/config.php');
include('../../includes/adminCheck.php');

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$sql = "SELECT user_id, name, email, img_path, role, status FROM users WHERE user_id = ? LIMIT 1";
$user = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$user_id]));
if (!$user) {
    $_SESSION['message'] = 'user not found';
    header("Location: index.php");
    exit();
}

include('../../includes/header.php');
?>
<div class="container mt-4">
    <div class="col-md-6 mx-auto">
        <h2>Edit User</h2>
        <?php include('../../includes/alert.php'); ?>
        <form action="update.php" method="POST" enctype="multipart/form-data" novalidate>
            <div class="mb-3">
                <?php
                if (!empty($user['img_path'])) {
                    echo "<img src='../../user/{$user['img_path']}' class='profile-photo'>";
                }
                ?>
            </div>
            <div class="mb-3">
                <label for="name" class="form-label">name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo $user['name']; ?>">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">email</label>
                <input type="text" class="form-control" id="email" name="email" value="<?php echo $user['email']; ?>">
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">role</label>
                <?php
                // only an active user's role can be changed
                if ($user['status'] === 'active') {
                    echo "<select name='role' id='role' class='form-control'>";
                } else {
                    echo "<select name='role' id='role' class='form-control' disabled>";
                }
                if ($user['role'] === 'admin') {
                    echo "<option value='customer'>customer</option><option value='admin' selected>admin</option>";
                } else {
                    echo "<option value='customer' selected>customer</option><option value='admin'>admin</option>";
                }
                echo "</select>";
                if ($user['status'] !== 'active') {
                    echo "<small class='text-muted'>this user is deactivated, activate the user first to change the role</small>";
                }
                ?>
            </div>
            <div class="mb-3">
                <label for="photo" class="form-label">new photo (optional, JPG or PNG)</label>
                <input type="file" class="form-control" id="photo" name="photo">
            </div>
            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>" />
            <button type="submit" class="btn btn-primary" name="submit">Submit</button>
            <a href="index.php" role="button" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
<?php
include('../../includes/footer.php');
?>
