<?php
session_start();
require '../../db/db.php';

if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Tambah Kandidat - Voting OSIS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f6f9;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 220px;
            background: #2c3e50;
            color: #fff;
            padding: 20px;
        }

        .sidebar h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .sidebar ul {
            list-style: none;
        }

        .sidebar ul li {
            margin: 15px 0;
        }

        .sidebar ul li a {
            color: #fff;
            text-decoration: none;
            display: block;
            padding: 8px 10px;
            border-radius: 5px;
            transition: 0.3s;
        }

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background: #34495e;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .card {
            background: #fff;
            padding: 20px;
            width: 100%;
            max-width: 800px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            margin-bottom: 20px;
            color: #2c3e50;
            text-align: center;
        }

        form label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
            color: #2c3e50;
        }

        form input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        form button {
            width: 100%;
            padding: 10px;
            background: #2c3e50;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
            transition: 0.3s;
        }

        form button:hover {
            background: #34495e;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="../index.php">Dashboard</a></li>
            <li><a href="../hasil-vote/result.php">Hasil</a></li>
            <li><a href="../kandidat/tambah.php" class="active">Tambah Kandidat</a></li>
            <li><a href="../kandidat/daftar.php">Daftar Kandidat</a></li>
            <li><a href="../kandidat/voter.php">Daftar Voter</a></li>
            <li><a href="../kandidat/token.php">Kelas dan Token</a></li>
            <li><a href="../kandidat/kode-guru.php">Buat Kode Guru</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>

    <section class="main-content">
        <div class="card">
            <h2>Tambah Kandidat</h2>
            <form action="../api/proses-tambah.php" method="post" enctype="multipart/form-data">
                <label for="nomor_kandidat">Nomor Kandidat</label>
                <input type="number" id="nomor_kandidat" name="nomor_kandidat" placeholder="Masukkan nomor kandidat..." required>

                <label for="nama_ketua">Nama Ketua</label>
                <input type="text" id="nama_ketua" name="nama_ketua" placeholder="Masukkan nama ketua..." required>

                <label for="kelas_ketua">Kelas Ketua</label>
                <input type="text" id="kelas_ketua" name="kelas_ketua" placeholder="Masukkan kelas ketua..." required>

                <label for="foto_ketua">Foto Ketua</label>
                <input type="file" id="foto_ketua" name="foto_ketua" required>

                <label for="nama_wakil">Nama Wakil</label>
                <input type="text" id="nama_wakil" name="nama_wakil" placeholder="Masukkan nama wakil..." required>

                <label for="kelas_wakil">Kelas Wakil</label>
                <input type="text" id="kelas_wakil" name="kelas_wakil" placeholder="Masukkan kelas wakil..." required>

                <label for="foto_wakil">Foto Wakil</label>
                <input type="file" id="foto_wakil" name="foto_wakil" required>

                <button type="submit">Simpan</button>
            </form>
        </div>
    </section>
</body>

</html>