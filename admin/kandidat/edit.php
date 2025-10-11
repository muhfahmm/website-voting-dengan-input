<?php
session_start();
require '../../db/db.php';

if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id = $_GET['id'];
$query = mysqli_query($db, "SELECT * FROM tb_kandidat WHERE id='$id'");
$data = mysqli_fetch_assoc($query);
if (!$data) {
    echo "Data kandidat tidak ditemukan.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Edit Kandidat</title>
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

        /* Main content */
        .main-content {
            flex: 1;
            padding: 40px 20px;
        }

        .card {
            background: #fff;
            padding: 20px;
            max-width: 500px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        h2 {
            margin-bottom: 20px;
            text-align: center;
        }

        form label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: #333;
        }

        form input[type="text"],
        form input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-top: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .preview {
            margin-top: 10px;
            text-align: center;
        }

        .preview img {
            width: 300px;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            margin-top: 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-primary {
            background: #3498db;
            color: #fff;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .back-btn {
            background: #7f8c8d;
            color: #fff;
            margin-left: 10px;
        }

        .back-btn:hover {
            background: #606e73;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="../index.php">🏠 Dashboard</a></li>
            <li><a href="result.php">📋 Hasil</a></li>
            <li><a href="tambah.php">➕ Tambah Kandidat</a></li>
            <li><a href="daftar.php" class="active">📋 Daftar Kandidat</a></li>
            <li><a href="../kandidat/voter.php">📋 voter</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="card">
            <h2>Edit Kandidat</h2>
            <form action="../api/proses-edit.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $data['id']; ?>">

                <label for="nama_ketua">Nama Ketua</label>
                <input type="text" id="nama_ketua" name="nama_ketua" value="<?= $data['nama_ketua']; ?>" required>

                <label for="nama_wakil">Nama Wakil</label>
                <input type="text" id="nama_wakil" name="nama_wakil" value="<?= $data['nama_wakil']; ?>" required>

                <label>Foto Ketua</label>
                <div class="preview">
                    <img id="preview_ketua" src="../uploads/<?= $data['foto_ketua']; ?>" alt="Foto Ketua">
                </div>
                <input type="file" name="foto_ketua" accept="image/*" onchange="previewImage(this, 'preview_ketua')">

                <label>Foto Wakil</label>
                <div class="preview">
                    <img id="preview_wakil" src="../uploads/<?= $data['foto_wakil']; ?>" alt="Foto Wakil">
                </div>
                <input type="file" name="foto_wakil" accept="image/*" onchange="previewImage(this, 'preview_wakil')">

                <button type="submit" name="edit" class="btn btn-primary">💾 Simpan Perubahan</button>
                <a href="daftar.php" class="btn back-btn">⬅ Kembali</a>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input, previewId) {
            const file = input.files[0];
            const preview = document.getElementById(previewId);

            if (file) {
                const reader = new FileReader();
                reader.onload = e => {
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>

</html>