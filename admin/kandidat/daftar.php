<?php
session_start();
require '../../db/db.php';

if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$query = mysqli_query($db, "SELECT * FROM tb_kandidat ORDER BY nomor_kandidat ASC");
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
            display: flex;
            min-height: 100vh;
            background: #f4f6f9;
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
            padding: 40px;
        }

        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }

        /* Kandidat List */
        .kandidat-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .kandidat-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
            padding: 18px;
            text-align: center;
            transition: 0.3s;
        }

        .kandidat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.15);
        }

        .foto-wrapper {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 12px;
        }

        .foto-wrapper img {
            width: 130px;
            height: 130px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #ddd;
        }

        .kandidat-card h3 {
            color: #2c3e50;
            font-size: 16px;
            margin: 10px 0;
        }

        .kandidat-card p {
            font-size: 13px;
            color: #777;
        }

        .actions {
            margin-top: 12px;
        }

        .actions a {
            display: inline-block;
            padding: 6px 14px;
            margin: 3px;
            font-size: 13px;
            border-radius: 6px;
            text-decoration: none;
            transition: 0.3s;
        }

        .actions a.edit {
            background: #3498db;
            color: #fff;
        }

        .actions a.edit:hover {
            background: #2980b9;
        }

        .actions a.delete {
            background: #e74c3c;
            color: #fff;
        }

        .actions a.delete:hover {
            background: #c0392b;
        }

        /* Form Tambah Kandidat */
        .form-container {
            background: #fff;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
        }

        .form-container h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        form label {
            display: block;
            font-weight: bold;
            color: #2c3e50;
            margin-top: 10px;
        }

        form input[type="text"],
        form input[type="number"],
        form input[type="file"] {
            width: 100%;
            padding: 9px;
            margin-top: 5px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        form input:focus {
            border-color: #3498db;
            outline: none;
        }

        form button {
            width: 100%;
            padding: 12px;
            background: #2c3e50;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            margin-top: 20px;
            transition: background 0.3s;
        }

        form button:hover {
            background: #34495e;
        }

        .no-data {
            text-align: center;
            color: #888;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div>
            <h2>Admin Panel</h2>
            <ul>
                <li><a href="../index.php">Dashboard</a></li>
                <li><a href="../hasil-vote/result.php">Hasil</a></li>
                <li><a href="../kandidat/daftar.php" class="active">Daftar Kandidat</a></li>
                <li><a href="../kandidat/voter.php">Daftar Voter</a></li>
                <li><a href="../kandidat/token.php">Kelas dan Token</a></li>
                <li><a href="../kandidat/kode-guru.php">Buat Kode Guru</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </div>
        
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h1>Daftar Kandidat OSIS</h1>

        <div class="kandidat-container">
            <?php if (mysqli_num_rows($query) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($query)): ?>
                    <div class="kandidat-card">
                        <div class="foto-wrapper">
                            <img src="../uploads/<?= $row['foto_ketua']; ?>" alt="Foto Ketua">
                            <img src="../uploads/<?= $row['foto_wakil']; ?>" alt="Foto Wakil">
                        </div>
                        <h3><?= htmlspecialchars($row['nama_ketua']); ?> & <?= htmlspecialchars($row['nama_wakil']); ?></h3>
                        <p>Pasangan Nomor: <b><?= $row['nomor_kandidat']; ?></b></p>
                        <div class="actions">
                            <a href="edit.php?id=<?= $row['id']; ?>" class="edit">✏ Edit</a>
                            <a href="hapus.php?id=<?= $row['id']; ?>" class="delete" onclick="return confirm('Yakin ingin menghapus kandidat ini?')">🗑 Hapus</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-data">Belum ada kandidat ditambahkan.</p>
            <?php endif; ?>
        </div>

        <div class="form-container">
            <h2>Tambah Kandidat Baru</h2>
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

                <button type="submit">💾 Simpan Kandidat</button>
            </form>
        </div>
    </div>
</body>

</html>
