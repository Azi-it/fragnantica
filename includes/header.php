<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
        integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="/Fragnantica/includes/style/style.css" rel="stylesheet" type="text/css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous">
    </script>
    <title>Fragrance &amp; Scent Store</title>
</head>

<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/Fragnantica/index.php">Fragrance &amp; Scent Store</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="/Fragnantica/index.php">Home</a>
                    </li>
                    <?php
                    // admin-only links
                    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                        echo '<li class="nav-item"><a class="nav-link" href="/Fragnantica/product/index.php">Products</a></li>';
                        echo '<li class="nav-item"><a class="nav-link" href="/Fragnantica/product/create.php">Add Product</a></li>';
                    }

                    // links for any logged in user
                    if (isset($_SESSION['user_id'])) {
                        echo '<li class="nav-item"><a class="nav-link" href="/Fragnantica/user/profile.php">My Profile</a></li>';
                    }

                    if (isset($_SESSION['role']) && $_SESSION['role'] === 'customer') {
                        echo '<li class="nav-item"><a class="nav-link" href="/Fragnantica/cart/index.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a></li>';
                        echo '<li class="nav-item"><a class="nav-link" href="/Fragnantica/cart/orders.php"><i class="fa-solid fa-box"></i> My Orders</a></li>';
                    }
                    ?>
                </ul>
                <form action="/Fragnantica/index.php" method="GET" class="d-flex" role="search">
                    <input class="form-control me-2" type="search" placeholder="Search products" aria-label="Search"
                        name="search" value="<?php if (isset($_GET['search'])) echo htmlentities($_GET['search']); ?>">
                    <button class="btn btn-outline-success" type="submit">Search</button>
                </form>
                <?php
                if (!isset($_SESSION['user_id'])) {
                    echo "<div class='navbar-nav ms-auto'>
                        <a href='/Fragnantica/user/login.php' class='nav-item nav-link'>Login</a>
                        <a href='/Fragnantica/user/register.php' class='nav-item nav-link'>Register</a></div>";
                } else {
                    echo "<div class='navbar-nav ms-auto'>
                        <span class='nav-item nav-link'><i class='fa-solid fa-user'></i> {$_SESSION['name']}</span>
                        <a href='/Fragnantica/user/logout.php' class='nav-item nav-link'>Logout</a></div>";
                }
                ?>
            </div>
        </div>
    </nav>
