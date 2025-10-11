<?php
session_start();
require '../../db/db.php';

if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$query = mysqli_query($db, "SELECT * FROM tb_kandidat");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Daftar Kandidat - Voting OSIS</title>
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
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }

        /* Card kandidat */
        .kandidat-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .kandidat-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
            transition: 0.3s;
        }

        .kandidat-card:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .foto-wrapper {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .foto-wrapper img {
            width: 150px;
            height: 150px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #ddd;
            background: #fff;
        }

        .kandidat-card h3 {
            margin: 12px 0;
            color: #2c3e50;
            font-size: 16px;
        }

        .kandidat-card .actions {
            margin-top: 10px;
        }

        .kandidat-card a {
            display: inline-block;
            padding: 6px 12px;
            margin: 3px;
            font-size: 13px;
            border-radius: 5px;
            text-decoration: none;
            transition: 0.3s;
        }

        .kandidat-card a.edit {
            background: #3498db;
            color: #fff;
        }

        .kandidat-card a.edit:hover {
            background: #2980b9;
        }

        .kandidat-card a.delete {
            background: #e74c3c;
            color: #fff;
        }

        .kandidat-card a.delete:hover {
            background: #c0392b;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="../index.php">Dashboard</a></li>
            <li><a href="../hasil-vote/result.php">Hasil</a></li>
            <li><a href="../kandidat/tambah.php">Tambah Kandidat</a></li>
            <li><a href="../kandidat/daftar.php" class="active">Daftar Kandidat</a></li>
            <li><a href="../kandidat/voter.php">Daftar Voter</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>Daftar Kandidat</h1>
        <div class="kandidat-container">
            <?php
            if (mysqli_num_rows($query) > 0) {
                while ($row = mysqli_fetch_assoc($query)) : ?>
                    <div class="kandidat-card">
                        <div class="foto-wrapper">
                            <img src="../uploads/<?= $row['foto_ketua'] ?>" alt="Foto Ketua">
                            <img src="../uploads/<?= $row['foto_wakil'] ?>" alt="Foto Wakil">
                        </div>
                        <h3><?= $row['nama_ketua']; ?> & <?= $row['nama_wakil']; ?></h3>
                        <div class="actions">
                            <a href="edit.php?id=<?= $row['id']; ?>" class="edit">✏ Edit</a>
                            <a href="hapus.php?id=<?= $row['id']; ?>" class="delete" onclick="return confirm('Yakin ingin menghapus kandidat ini?')">🗑 Hapus</a>
                        </div>
                    </div>
                <?php endwhile;
            } else { ?>
                <p style="text-align:center;">Belum ada kandidat ditambahkan.</p>
            <?php } ?>
        </div>
    </div>
</body>

</html>