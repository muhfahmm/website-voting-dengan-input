<?php
session_start();
require '../db/db.php';

// cek login
if (!isset($_SESSION['login'])) {
    header("Location: auth/login.php");
    exit;
}

$admin = $_SESSION['username'];

// Ambil data kandidat beserta jumlah suara
$query = mysqli_query($db, "
    SELECT k.nomor_kandidat, k.nama_ketua, k.nama_wakil, COUNT(v.id) AS total_suara
    FROM tb_kandidat k
    LEFT JOIN tb_vote_log v ON k.nomor_kandidat = v.nomor_kandidat
    GROUP BY k.nomor_kandidat, k.nama_ketua, k.nama_wakil
    ORDER BY k.nomor_kandidat ASC
");

// Hitung total suara
$totalQuery = mysqli_query($db, "SELECT COUNT(*) AS total FROM tb_vote_log");
$totalRow = mysqli_fetch_assoc($totalQuery);
$totalVotes = $totalRow['total'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Voting OSIS</title>
    <link rel="stylesheet" href="./assets/css/index.css">
    <link rel="stylesheet" href="./assets/css/global.css">
    <style>
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

        .sidebar ul li a:hover {
            background: #34495e;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="hasil-vote/result.php">Hasil</a></li>
            <li><a href="kandidat/daftar.php">Daftar Kandidat</a></li>
            <li><a href="kandidat/voter.php">Daftar Voter</a></li>
            <li><a href="kandidat/token.php">Kelas dan  Token</a></li>
            <li><a href="kandidat/kode-guru.php">Buat Kode Guru</a></li>
            <li><a href="./auth/logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h1>Dashboard Admin</h1>
            <p>Selamat datang, <b><?php echo htmlspecialchars($admin); ?></b></p>
        </header>

        <section class="card">
            <h2>Daftar Calon</h2>
            <table border="1" cellspacing="0" cellpadding="8">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Pasangan Kandidat</th>
                        <th>Nomor Urut</th>
                        <th>Jumlah Suara</th>
                        <th>Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    if (mysqli_num_rows($query) > 0) {
                        while ($row = mysqli_fetch_assoc($query)) {
                            $persentase = $totalVotes > 0 ? round(($row['total_suara'] / $totalVotes) * 100, 2) : 0;
                    ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= $row['nama_ketua']; ?> & <?= $row['nama_wakil']; ?></td>
                                <td><?= $row['nomor_kandidat']; ?></td>
                                <td><?= $row['total_suara']; ?></td>
                                <td><?= $persentase; ?>%</td>
                            </tr>
                    <?php
                        }
                    } else {
                        echo '<tr><td colspan="4" style="text-align:center; color: #888;">Belum ada kandidat</td></tr>';
                    }
                    ?>
                </tbody>
            </table>

            <div class="bar-chart">
                <?php
                mysqli_data_seek($query, 0);
                while ($row = mysqli_fetch_assoc($query)) {
                    $persentase = $totalVotes > 0 ? round(($row['total_suara'] / $totalVotes) * 100, 2) : 0;
                ?>
                    <div class="bar">
                        <div class="bar-label"><?= $row['nama_ketua']; ?> & <?= $row['nama_wakil']; ?></div>
                        <div class="bar-fill" style="width: <?= $persentase; ?>%;"><?= $persentase; ?>%</div>
                    </div>
                <?php } ?>
            </div>
        </section>
    </div>
</body>

</html>