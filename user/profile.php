<?php
session_start();
include("../includes/config.php");
include("../includes/loginCheck.php");

$user_id = $_SESSION['user_id'];

// upload a new profile photo
if (isset($_POST['upload_photo'])) {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
        $_SESSION['message'] = 'Please select an image';
    } else if ($_FILES['photo']['type'] == "image/jpeg" || $_FILES['photo']['type'] == "image/jpg" || $_FILES['photo']['type'] == "image/png") {
        $source = $_FILES['photo']['tmp_name'];
        $target = 'images/' . time() . '_' . $_FILES['photo']['name'];
        move_uploaded_file($source, $target) or die("Couldn't copy");

        $sql = "UPDATE users SET img_path = ? WHERE user_id = ?";
        mysqli_execute_query($conn, $sql, [$target, $user_id]);
        $_SESSION['photo'] = $target;
        $_SESSION['success'] = 'profile photo updated';
    } else {
        $_SESSION['message'] = 'wrong file type, please use JPG or PNG';
    }
    header("Location: profile.php");
    exit();
}

// save the name and delivery details
if (isset($_POST['submit'])) {
    $name = trim(strip_tags($_POST['name']));
    $address = trim(strip_tags($_POST['address']));
    $town = trim(strip_tags($_POST['town']));
    $zipcode = trim($_POST['zipcode']);
    $phone = trim($_POST['phone']);

    //validation
    if ($name === '' || $address === '' || $town === '' || $zipcode === '' || $phone === '') {
        $_SESSION['message'] = 'please fill in all fields';
    } else if (!preg_match("/^[0-9]{4}$/", $zipcode)) {
        $_SESSION['message'] = 'zip code should be 4 digits';
    } else if (!preg_match("/^09[0-9]{9}$/", $phone)) {
        $_SESSION['message'] = 'phone number should be 11 digits and start with 09';
    } else {
        $sql = "UPDATE users SET name = ? WHERE user_id = ?";
        mysqli_execute_query($conn, $sql, [$name, $user_id]);

        // first time = INSERT, after that = UPDATE
        $result = mysqli_execute_query($conn, "SELECT customer_id FROM customer WHERE user_id = ? LIMIT 1", [$user_id]);
        if (mysqli_num_rows($result) === 1) {
            $sql = "UPDATE customer SET addressline = ?, town = ?, zipcode = ?, phone = ? WHERE user_id = ?";
        } else {
            $sql = "INSERT INTO customer (addressline, town, zipcode, phone, user_id) VALUES (?, ?, ?, ?, ?)";
        }
        mysqli_execute_query($conn, $sql, [$address, $town, $zipcode, $phone, $user_id]);
        $_SESSION['success'] = 'profile saved';
    }
    header("Location: profile.php");
    exit();
}

$sql = "SELECT u.name, u.email, u.img_path, c.addressline, c.town, c.zipcode, c.phone FROM users u LEFT JOIN customer c USING (user_id) WHERE u.user_id = ? LIMIT 1";
$profile = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$user_id]));

include("../includes/header.php");
?>
<div class="container-xl px-4 mt-4">
    <?php include("../includes/alert.php"); ?>
    <div class="row">
        <div class="col-xl-4">
            <!-- Profile picture card-->
            <div class="card mb-4 mb-xl-0">
                <div class="card-header">Profile Picture</div>
                <div class="card-body text-center">
                    <?php
                    if (!empty($profile['img_path'])) {
                        echo "<img class='profile-photo mb-2' src='{$profile['img_path']}'>";
                    } else {
                        echo "<p><i class='fa-solid fa-user fa-5x text-muted'></i></p>";
                    }
                    ?>
                    <div class="small font-italic text-muted mb-3">JPG or PNG</div>
                    <form action="profile.php" method="POST" enctype="multipart/form-data">
                        <input class="form-control mb-2" type="file" name="photo">
                        <button class="btn btn-primary" type="submit" name="upload_photo">Upload new image</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <!-- Account details card-->
            <div class="card mb-4">
                <div class="card-header">Account Details</div>
                <div class="card-body">
                    <p class="small text-muted">These details are used as the delivery information of your orders.</p>
                    <form action="profile.php" method="POST" novalidate>
                        <div class="row gx-3 mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1" for="name">Name</label>
                                <input class="form-control" id="name" type="text" name="name" value="<?php echo $profile['name']; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1" for="email">Email</label>
                                <input class="form-control" id="email" type="text" value="<?php echo $profile['email']; ?>" disabled>
                            </div>
                        </div>
                        <div class="row gx-3 mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1" for="address">Address</label>
                                <input class="form-control" id="address" type="text" placeholder="House no., street, barangay"
                                    name="address" value="<?php echo $profile['addressline']; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1" for="town">Town / City</label>
                                <input class="form-control" id="town" type="text" name="town" value="<?php echo $profile['town']; ?>">
                            </div>
                        </div>
                        <div class="row gx-3 mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1" for="zip">Zip code</label>
                                <input class="form-control" id="zip" type="text" placeholder="e.g. 1600" name="zipcode" value="<?php echo $profile['zipcode']; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1" for="phone">Phone number</label>
                                <input class="form-control" id="phone" type="text" placeholder="e.g. 09171234567" name="phone" value="<?php echo $profile['phone']; ?>">
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit" name="submit">Save changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
include("../includes/footer.php");
?>
