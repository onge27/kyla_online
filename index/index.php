<?php
session_start();
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'teacher':
            header('Location: teacher/dashboard.php');
            break;
        case 'student':
            header('Location: student/dashboard.php');
            break;
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Exam & Grading System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="card" style="max-width: 600px; margin: 5rem auto; text-align: center;">
            <h1 style="color: #667eea; margin-bottom: 2rem;">Online Exam & Grading System</h1>
            <p style="margin-bottom: 2rem; color: #666;">Welcome to the Online Exam & Grading System</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="login.php" class="btn">Login</a>
                <a href="register.php" class="btn btn-success">Register</a>
            </div>

            <div style="margin-top: 2rem;">
                <p style="color: #666; font-size: 0.9rem;"></p>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>
