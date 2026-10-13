<?php
session_start();
include("../includes/config.php");
include("../includes/functions.php");

// only logged in users can open their profile
require_login();

$user_id = $_SESSION['user_id'];

// ---------- profile photo: upload a new one, or remove it ----------
if (isset($_POST['upload_photo']) || isset($_POST['remove_photo'])) {
    $sql = "SELECT img_path FROM users WHERE user_id = ? LIMIT 1";
    $oldPhoto = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$user_id]))['img_path'] ?? null;

    if (!csrf_valid()) {
        $_SESSION['message'] = 'Your session expired. Please try again.';
    } else if (isset($_POST['remove_photo'])) {
        mysqli_execute_query($conn, "UPDATE users SET img_path = NULL WHERE user_id = ?", [$user_id]);
        if ($oldPhoto) {
            delete_photo_file(__DIR__ . '/../' . $oldPhoto);
        }
        $_SESSION['success'] = 'profile photo removed';
    } else {
        $photo = isset($_FILES['photo']) ? $_FILES['photo'] : null;
        $error = photo_upload_error($photo, true);
        $fileName = $error === '' ? save_photo($photo, __DIR__ . '/images') : '';

        if ($error !== '') {
            $_SESSION['message'] = $error;
        } else if ($fileName === '') {
            $_SESSION['message'] = 'The photo could not be saved. Please try again.';
        } else {
            mysqli_execute_query($conn, "UPDATE users SET img_path = ? WHERE user_id = ?", ['user/images/' . $fileName, $user_id]);
            // the old photo file is not needed anymore
            if ($oldPhoto) {
                delete_photo_file(__DIR__ . '/../' . $oldPhoto);
            }
            $_SESSION['photo'] = 'user/images/' . $fileName;
            $_SESSION['success'] = 'profile photo updated';
        }
    }
    header("Location: profile.php");
    exit();
}

// ---------- change password ----------
if (isset($_POST['change_password'])) {
    $current = isset($_POST['current_password']) && is_string($_POST['current_password']) ? trim($_POST['current_password']) : '';
    $new = isset($_POST['new_password']) && is_string($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirm = isset($_POST['confirm_password']) && is_string($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    $sql = "SELECT name, email, password FROM users WHERE user_id = ? LIMIT 1";
    $account = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$user_id]));

    if (!csrf_valid()) {
        $_SESSION['message'] = 'Your session expired. Please try again.';
    } else if (!$account || !password_verify($current, $account['password'])) {
        $_SESSION['message'] = 'your current password is not correct';
    } else if (strlen($new) < 6) {
        $_SESSION['message'] = 'the new password should be at least 6 characters';
    } else if ($new !== $confirm) {
        $_SESSION['message'] = 'the new passwords do not match';
    } else if ($new === $current) {
        $_SESSION['message'] = 'the new password must be different from the current one';
    } else if (in_array($new, KNOWN_DEFAULT_PASSWORDS, true)) {
        $_SESSION['message'] = 'that password was published with this project; please choose another';
    } else {
        try {
            mysqli_execute_query($conn, "UPDATE users SET password = ? WHERE user_id = ?", [password_hash($new, PASSWORD_BCRYPT), $user_id]);
            // any reset links that were still waiting can no longer be used
            mysqli_execute_query($conn, "UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL", [$user_id]);
            session_regenerate_id(true);
            unset($_SESSION['default_password']);
            notify_password_changed($account['email'], $account['name']);
            $_SESSION['success'] = 'password changed';
        } catch (mysqli_sql_exception $e) {
            $_SESSION['message'] = 'could not change your password, please try again';
        }
    }
    header("Location: profile.php");
    exit();
}

// ---------- delivery details ----------
if (isset($_POST['submit'])) {
    $name = trim(strip_tags($_POST['name'] ?? ''));
    $address = trim(strip_tags($_POST['address'] ?? ''));
    $town = trim(strip_tags($_POST['town'] ?? ''));
    $zipcode = trim($_POST['zipcode'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    //validation
    if (!csrf_valid()) {
        $_SESSION['message'] = 'Your session expired. Please try again.';
    } else if ($name === '' || $address === '' || $town === '' || $zipcode === '' || $phone === '') {
        $_SESSION['message'] = 'please fill in all fields';
    } else if (!preg_match("/^[0-9]{4}$/", $zipcode)) {
        $_SESSION['message'] = 'zip code should be 4 digits';
    } else if (!preg_match("/^09[0-9]{9}$/", $phone)) {
        $_SESSION['message'] = 'phone number should be 11 digits and start with 09';
    } else {
        try {
            // the name is saved in the users table
            $sql = "UPDATE users SET name=? WHERE user_id=?";
            mysqli_execute_query($conn, $sql, [$name, $user_id]);

            // the delivery details are saved in the customer table
            // first time = INSERT, after that = UPDATE
            $sql = "SELECT customer_id FROM customer WHERE user_id=? LIMIT 1";
            $result = mysqli_execute_query($conn, $sql, [$user_id]);

            if (mysqli_num_rows($result) === 1) {
                $sql = "UPDATE customer SET addressline=?, town=?, zipcode=?, phone=? WHERE user_id=?";
            } else {
                $sql = "INSERT INTO customer (addressline, town, zipcode, phone, user_id) VALUES (?, ?, ?, ?, ?)";
            }
            // both queries use the values in the same order
            mysqli_execute_query($conn, $sql, [$address, $town, $zipcode, $phone, $user_id]);

            $_SESSION['name'] = $name;
            $_SESSION['success'] = 'profile saved';
            header("Location: profile.php");
            exit();
        } catch (mysqli_sql_exception $e) {
            $_SESSION['message'] = 'could not save profile, please try again';
        }
    }
}

// get the saved details (customer columns are empty if the profile is not filled in yet)
$sql = "SELECT u.name, u.email, u.img_path, u.role, c.addressline, c.town, c.zipcode, c.phone FROM users u LEFT JOIN customer c USING (user_id) WHERE u.user_id=? LIMIT 1";
$result = mysqli_execute_query($conn, $sql, [$user_id]);
$profile = mysqli_fetch_assoc($result);

// if saving failed, show what the user typed instead
if (isset($_POST['submit'])) {
    $profile['name'] = $name;
    $profile['addressline'] = $address;
    $profile['town'] = $town;
    $profile['zipcode'] = $zipcode;
    $profile['phone'] = $phone;
}

include("../includes/header.php");
?>

<div class="container-xl px-4 mt-4">
    <?php include("../includes/alert.php"); ?>
    <div class="row">
        <div class="col-xl-8 mx-auto">
            <div class="card mb-4">
                <div class="card-header">Profile Photo</div>
                <div class="card-body d-flex flex-wrap align-items-center gap-4">
                    <?= avatar($profile['name'], $profile['img_path'], 120) ?>
                    <form action="profile.php" method="POST" enctype="multipart/form-data" class="flex-fill" novalidate>
                        <?= csrf_field() ?>
                        <label class="small mb-1" for="photo">Upload a new photo (JPG or PNG, up to <?= MAX_PHOTO_BYTES / 1024 / 1024 ?> MB)</label>
                        <input class="form-control mb-2" id="photo" type="file" name="photo" accept=".jpg,.jpeg,.png">
                        <button class="btn btn-primary" type="submit" name="upload_photo">Upload photo</button>
                        <?php if ($profile['img_path']): ?>
                            <button class="btn btn-outline-danger" type="submit" name="remove_photo"
                                onclick="return confirm('Remove your profile photo?');">Remove photo</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">My Profile</div>
                <div class="card-body">
                    <p class="small text-muted">These details will be used as the delivery information for your orders.</p>
                    <form action="profile.php" method="POST" novalidate>
                        <?= csrf_field() ?>
                        <div class="row gx-3 mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1" for="name">Name</label>
                                <input class="form-control" id="name" type="text" placeholder="Enter your name"
                                    name="name" value="<?= e($profile['name']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1" for="email">Email</label>
                                <input class="form-control" id="email" type="text" value="<?= e($profile['email']) ?>" disabled>
                            </div>
                        </div>

                        <div class="row gx-3 mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1" for="address">Address</label>
                                <input class="form-control" id="address" type="text" placeholder="House no., street, barangay"
                                    name="address" value="<?= e($profile['addressline']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1" for="town">Town / City</label>
                                <input class="form-control" id="town" type="text" placeholder="Enter your town or city"
                                    name="town" value="<?= e($profile['town']) ?>">
                            </div>
                        </div>

                        <div class="row gx-3 mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1" for="zip">Zip Code</label>
                                <input class="form-control" id="zip" type="text" placeholder="e.g. 1630"
                                    name="zipcode" value="<?= e($profile['zipcode']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1" for="phone">Phone Number</label>
                                <input class="form-control" id="phone" type="text" placeholder="e.g. 09171234567"
                                    name="phone" value="<?= e($profile['phone']) ?>">
                            </div>
                        </div>

                        <button class="btn btn-primary" type="submit" name="submit">Save changes</button>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Change Password</div>
                <div class="card-body">
                    <form action="profile.php" method="POST" autocomplete="off" novalidate>
                        <?= csrf_field() ?>
                        <div class="row gx-3 mb-3">
                            <div class="col-md-4">
                                <label class="small mb-1" for="current_password">Current password</label>
                                <input class="form-control" id="current_password" type="password" name="current_password">
                            </div>
                            <div class="col-md-4">
                                <label class="small mb-1" for="new_password">New password</label>
                                <input class="form-control" id="new_password" type="password" name="new_password">
                            </div>
                            <div class="col-md-4">
                                <label class="small mb-1" for="confirm_password">Confirm new password</label>
                                <input class="form-control" id="confirm_password" type="password" name="confirm_password">
                            </div>
                        </div>
                        <button class="btn btn-outline-primary" type="submit" name="change_password">Change password</button>
                        <span class="small text-muted ms-2">You will get an email confirming the change.</span>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include("../includes/footer.php");
?>
