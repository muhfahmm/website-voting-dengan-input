<?php
session_start();
require '../../db/db.php';

// cek login
if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$admin = $_SESSION['username'];

$dataKelas = [
    "X-1" => 21,
    "X-2" => 18,
    "XI-1" => 29,
    "XI-2" => 17,
    "XI-TJA" => 11,
    "XII" => 29
];
$total_siswa = array_sum($dataKelas);

// Ambil data kandidat beserta jumlah suara
$query = mysqli_query($db, "
    SELECT k.nomor_kandidat, k.nama_ketua, k.nama_wakil, COUNT(v.id) AS total_suara
    FROM tb_kandidat k
    LEFT JOIN tb_vote_log v ON k.nomor_kandidat = v.nomor_kandidat
    GROUP BY k.nomor_kandidat, k.nama_ketua, k.nama_wakil
    ORDER BY k.nomor_kandidat ASC
");

// Ambil data untuk Chart.js
$labels = [];
$dataVotes = [];
while ($row = mysqli_fetch_assoc($query)) {
    $labels[] = $row['nama_ketua'] . " & " . $row['nama_wakil'];
    $dataVotes[] = $row['total_suara'];
}

// Reset pointer untuk diagram bar custom
mysqli_data_seek($query, 0);

// Hitung total siswa & guru yang sudah voting
$totalVotesSiswaQuery = mysqli_query($db, "
    SELECT COUNT(DISTINCT v.id) AS total 
    FROM tb_voter v
    JOIN tb_vote_log l ON v.id = l.voter_id
    WHERE v.role = 'siswa'
");
$totalVotesSiswaRow = mysqli_fetch_assoc($totalVotesSiswaQuery);
$totalVotesSiswa = isset($totalVotesSiswaRow['total']) ? (int)$totalVotesSiswaRow['total'] : 0;

$totalVotesGuruQuery = mysqli_query($db, "
    SELECT COUNT(DISTINCT v.id) AS total 
    FROM tb_voter v
    JOIN tb_vote_log l ON v.id = l.voter_id
    WHERE v.role = 'guru'
");
$totalVotesGuruRow = mysqli_fetch_assoc($totalVotesGuruQuery);
$totalVotesGuru = isset($totalVotesGuruRow['total']) ? (int)$totalVotesGuruRow['total'] : 0;
// Hitung total semua suara
$totalQuery = mysqli_query($db, "SELECT COUNT(*) AS total FROM tb_vote_log");
$totalRow = mysqli_fetch_assoc($totalQuery);
$totalVotes = $totalRow['total'];


$totalSiswaTarget = array_sum($dataKelas); // total dari semua kelas siswa
$totalGuruTarget = 25; // total guru tetap


?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin - Voting OSIS</title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .sidebar ul li a.active {
            background: #34495e;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .bar-chart {
            margin-top: 20px;
            margin-bottom: 100px;
        }

        .bar {
            margin: 15px 0;
            background: #eee;
            border-radius: 5px;
            overflow: hidden;
            position: relative;
            padding: 5px;
            width: 100%;
        }

        .bar-fill {
            height: 40px;
            border-radius: 4px;
            background: #3498db;
            position: relative;
            overflow: hidden;
            transition: width 0.5s ease;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .bar-percent {
            font-size: 13px;
            font-weight: bold;
            color: #fff;
        }

        .bar-name {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            margin-right: 10px;
        }

        .result-container {
            display: flex;
            justify-content: space-between;
        }

        .chart-container {
            width: 35%;
            margin: 20px;
            display: inline-block;
            vertical-align: top;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="../index.php">Dashboard</a></li>
            <li><a href="result.php" class="active">Hasil</a></li>
            <li><a href="../kandidat/tambah.php">Tambah Kandidat</a></li>
            <li><a href="../kandidat/daftar.php">Daftar Kandidat</a></li>
            <li><a href="../kandidat/voter.php">Daftar Voter</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h2 style="margin-bottom: 20px; text-align:center;">Hasil Sementara</h2>
        <div class="summary-box" style="text-align: center; font-weight:600; font-size:18px; margin-bottom:10px;">
            Total Siswa: <?= $totalSiswaTarget; ?> | Sudah Voting: <?= $totalVotesSiswa; ?> | Belum Voting: <?= $totalSiswaTarget - $totalVotesSiswa; ?>
        </div>

        <div class="summary-box" style="text-align: center; font-weight:600; font-size:18px;">
            Total Guru: <?= $totalGuruTarget; ?> | Sudah Voting: <?= $totalVotesGuru; ?> | Belum Voting: <?= $totalGuruTarget - $totalVotesGuru; ?>
        </div>


        <div class="chart-container">
            <canvas id="pieChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="barChart"></canvas>
        </div>
        <div class="bar-chart">
            <?php
            while ($row = mysqli_fetch_assoc($query)) {
                $persentase = $totalVotes > 0 ? round(($row['total_suara'] / $totalVotes) * 100, 2) : 0;
            ?>
                <div class="bar">
                    <span class="bar-name"><?= $row['nama_ketua']; ?> & <?= $row['nama_wakil']; ?></span>
                    <div class="bar-fill" style="width: <?= $persentase; ?>%;">
                        <span class="bar-percent"><?= $persentase; ?>%</span>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <script>
        const labels = <?php echo json_encode($labels); ?>;
        const dataVotes = <?php echo json_encode($dataVotes); ?>;

        // Pie Chart
        new Chart(document.getElementById('pieChart'), {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: dataVotes,
                    backgroundColor: ['#3498db', '#e74c3c', '#2ecc71', '#f1c40f', '#9b59b6']
                }]
            }
        });


        // Bar Chart Vertikal
        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Suara',
                    data: dataVotes,
                    backgroundColor: ['#3498db', '#e74c3c', '#2ecc71', '#f1c40f', '#9b59b6']
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>