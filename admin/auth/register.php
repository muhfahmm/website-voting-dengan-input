<?php
session_start();
require '../../db/db.php';

if (isset($_SESSION['login'])) {
    header("Location: ../index.php");
    exit;
}

if (isset($_POST['register'])) {
    $username = mysqli_real_escape_string($db, $_POST['username']);
    $password1 = $_POST['password1'];
    $password2 = $_POST['password2'];

    // cek username sudah ada
    $result = mysqli_query($db, "SELECT username FROM tb_admin WHERE username = '$username'");
    if (mysqli_fetch_assoc($result)) {
        echo "<script>alert('Username sudah terdaftar');</script>";
    } elseif ($password1 !== $password2) {
        echo "<script>alert('Password tidak sama');</script>";
    } else {
        // enkripsi password
        $password = password_hash($password1, PASSWORD_DEFAULT);

        // simpan user baru
        mysqli_query($db, "INSERT INTO tb_admin VALUES('', '$username', '$password')");

        echo "<script>alert('Registrasi berhasil! Silakan login.'); document.location.href='login.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Register Admin</title>
</head>

<body>
    <h2>Register Admin</h2>
    <form action="" method="post">
        <input type="text" name="username" placeholder="username" required><br>
        <input type="password" name="password1" placeholder="password" required><br>
        <input type="password" name="password2" placeholder="konfirmasi password" required><br>
        <button type="submit" name="register">Daftar</button>
    </form>
    <p>Sudah punya akun? <a href="login.php">Login</a></p>
</body>

</html>