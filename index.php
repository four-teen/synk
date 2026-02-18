<?php
  session_start();
  ob_start();
  include 'backend/db.php';

if (isset($_POST['login'])) {

    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Query account + college name
    $stmt = $conn->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.email,
            u.password,
            u.role,
            u.status,
            u.college_id,
            c.college_name
        FROM tbl_useraccount u
        LEFT JOIN tbl_college c ON c.college_id = u.college_id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "invalid";
        exit;
    }

    $row = $result->fetch_assoc();

    if ($row['status'] !== "active") {
        echo "inactive";
        exit;
    }

    if (!password_verify($password, $row['password'])) {
        echo "invalid";
        exit;
    }

    // Save session info
    $_SESSION['user_id']      = $row['user_id'];
    $_SESSION['username']     = $row['username'];
    $_SESSION['email']        = $row['email'];
    $_SESSION['role']         = $row['role'];
    $_SESSION['college_id']   = $row['college_id'];
    $_SESSION['college_name'] = $row['college_name']; // FIXED

    echo $row['role'];
    exit;
}


  
?>


<!DOCTYPE html>

<!-- =========================================================
* Sneat - Bootstrap 5 HTML Admin Template - Pro | v1.0.0
==============================================================

* Product Page: https://themeselection.com/products/sneat-bootstrap-html-admin-template/
* Created by: ThemeSelection
* License: You must have a valid license purchased in order to legally use the theme for your project.
* Copyright ThemeSelection (https://themeselection.com)

=========================================================
 -->
<!-- beautify ignore:start -->
<html
  lang="en"
  class="light-style customizer-hide"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="assets/"
  data-template="vertical-menu-template-free"
>
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>SKSU Synk</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Page CSS -->
    <!-- Page -->
    <link rel="stylesheet" href="assets/vendor/css/pages/page-auth.css" />
    <!-- Helpers -->
    <script src="assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="assets/js/config.js"></script>
  </head>

  <body>
    <!-- Content -->

    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner">
          <!-- Register -->
          <div class="card">
            <div class="card-body">
              <!-- Logo -->
              <div class="app-brand justify-content-center">
                <a href="index.html" class="app-brand-link gap-2">
                  <span class="app-brand-logo demo">

                  </span>
                  <span class="app-brand-text text-body fw-bolder">Synk</span>
                </a>
              </div>
              <!-- /Logo -->
              <h4 class="mb-2">Welcome to SKSU!</h4>
              <p class="mb-4">Please sign-in to your account</p>

              <form id="loginForm" class="mb-3">
                <div class="mb-3">
                  <label for="email" class="form-label">Email</label>
                  <input
                    type="text"
                    class="form-control"
                    id="email"
                    name="email-username"
                    placeholder="Enter your email"
                    autofocus
                  />
                </div>
                <div class="mb-3 form-password-toggle">
                  <div class="d-flex justify-content-between">
                    <label class="form-label" for="password">Password</label>
                  </div>
                  <div class="input-group input-group-merge">
                    <input
                      type="password"
                      id="password"
                      class="form-control"
                      name="password"
                      placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                      aria-describedby="password"
                    />
                    <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                  </div>
                </div>
                <div class="mb-3">
                  <button type="button" id="btn_login" class="btn btn-primary d-grid w-100">Sign in</button>
                </div>
            <footer class="content-footer footer bg-footer-theme">
              <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                <div class="mb-2 mb-md-0">
                  DevDate 12 8 
                  <script>
                    document.write(new Date().getFullYear());
                  </script>
                  <br>
                  <a href="#" target="_blank" class="footer-link fw-bolder">SAM Project</a>
                  Secured Administrative Management
                </div>
                <div>
                  <a href="#" class="footer-link me-4" target="_blank">eOa</a>
                </div>
              </div>
            </footer>
              </form>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- / Content -->


    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/main.js"></script>

<script>
$(document).ready(function(){

// 🔹 Trigger login when pressing ENTER inside email or password
$('#email, #password').on('keypress', function(e) {
    if (e.which === 13) {  // 13 = ENTER key
        $("#btn_login").click(); // simulate button click
    }
});


  $("#btn_login").click(function(){

      let email    = $("#email").val();
      let password = $("#password").val();

      // 🔸 Missing fields
      if (email === "" || password === "") {
          Swal.fire({
            icon: "warning",
            title: "Incomplete Details",
            text: "Please enter both your email and password.",
            confirmButtonColor: "#3085d6",
            width: "360px",
            padding: "1.5rem",
            allowOutsideClick: false
          });
          return;
      }

      $.ajax({
        url: "index.php",
        type: "POST",
        data: {
          login: 1,
          email: email,
          password: password
        },
success: function(response) {

  response = response.trim();

  // INVALID LOGIN
  if (response === "invalid") {
      Swal.fire({
        icon: "error",
        title: "Login Failed",
        text: "Incorrect email or password.",
        width: "360px",
        padding: "1.5rem",
      });
      return;
  }

  // INACTIVE ACCOUNT
  if (response === "inactive") {
      Swal.fire({
        icon: "warning",
        title: "Account Inactive",
        text: "Please contact the administrator.",
        width: "360px",
        padding: "1.5rem",
      });
      return;
  }

  // ADMIN LOGIN
  if (response === "admin") {
      Swal.fire({
        icon: "success",
        title: "Welcome Admin!",
        text: "Redirecting...",
        timer: 1500,
        showConfirmButton: false,
        width: "360px",
        padding: "1.5rem",
      });

      setTimeout(() => {
        window.location = "administrator/";
      }, 1500);
      return;
  }

  // SCHEDULER LOGIN
  if (response === "scheduler") {
      Swal.fire({
        icon: "success",
        title: "Welcome Scheduler!",
        text: "Redirecting...",
        timer: 1500,
        showConfirmButton: false,
        width: "360px",
        padding: "1.5rem",
      });

      setTimeout(() => {
        window.location = "scheduler/";
      }, 1500);
      return;
  }

  // VIEWER LOGIN
  if (response === "viewer") {
      Swal.fire({
        icon: "success",
        title: "Welcome!",
        text: "Redirecting...",
        timer: 1500,
        showConfirmButton: false,
        width: "360px",
        padding: "1.5rem",
      });

      setTimeout(() => {
        window.location = "viewer/";
      }, 1500);
      return;
  }

  // UNEXPECTED RESPONSE
  Swal.fire({
    icon: "error",
    title: "Unexpected Error",
    text: "Please try again later.",
    width: "360px",
    padding: "1.5rem",
  });
}

      });

  });

});

</script>

  </body>
</html>
