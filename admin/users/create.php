<?php
session_start();
include('../../includes/config.php');
include('../../includes/adminCheck.php');
include('../../includes/header.php');
?>
<div class="container mt-4">
    <div class="col-md-6 mx-auto">
        <h2>Add User</h2>
        <?php include('../../includes/alert.php'); ?>
        <form action="store.php" method="POST" enctype="multipart/form-data" novalidate>
            <div class="mb-3">
                <label for="name" class="form-label">name</label>
                <input type="text" class="form-control" id="name" name="name"
                    value="<?php if (isset($_SESSION['regName'])) echo $_SESSION['regName']; ?>">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">email</label>
                <input type="text" class="form-control" id="email" name="email"
                    value="<?php if (isset($_SESSION['regEmail'])) echo $_SESSION['regEmail']; ?>">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">password</label>
                <input type="password" class="form-control" id="password" name="password">
                <small class="text-muted">at least 6 characters</small>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">role</label>
                <select name="role" id="role" class="form-control">
                    <option value="customer">customer</option>
                    <option value="admin">admin</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="photo" class="form-label">photo (optional, JPG or PNG)</label>
                <input type="file" class="form-control" id="photo" name="photo">
            </div>
            <button type="submit" class="btn btn-primary" name="submit">Submit</button>
            <a href="index.php" role="button" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
<?php
unset($_SESSION['regName'], $_SESSION['regEmail']);
include('../../includes/footer.php');
?>
