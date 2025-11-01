<?php
session_start();
require '../../db/db.php';

if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$admin = $_SESSION['username'];

// jumlah data per halaman
$limit = 15;

// halaman untuk siswa & guru
$pageSiswa = isset($_GET['page_siswa']) ? (int)$_GET['page_siswa'] : 1;
if ($pageSiswa < 1) $pageSiswa = 1;
$offsetSiswa = ($pageSiswa - 1) * $limit;

$pageGuru = isset($_GET['page_guru']) ? (int)$_GET['page_guru'] : 1;
if ($pageGuru < 1) $pageGuru = 1;
$offsetGuru = ($pageGuru - 1) * $limit;

// variabel hasil
$votersSiswa = [];
$votersGuru = [];

// hitung total siswa yang sudah vote
$totalSiswaQuery = mysqli_query($db, "
    SELECT COUNT(*) as total
    FROM tb_voter v
    JOIN tb_vote_log l ON v.id = l.voter_id
    WHERE v.role = 'siswa'
");
$totalSiswaRow = mysqli_fetch_assoc($totalSiswaQuery);
$totalSiswa = isset($totalSiswaRow['total']) ? (int)$totalSiswaRow['total'] : 0;
$totalPagesSiswa = $totalSiswa > 0 ? ceil($totalSiswa / $limit) : 1;

// ambil data vote untuk siswa
$votedSiswaQuery = mysqli_query($db, "
    SELECT v.id, v.nama_voter, v.kelas, v.role, l.nomor_kandidat
    FROM tb_voter v
    JOIN tb_vote_log l ON v.id = l.voter_id
    WHERE v.role = 'siswa'
    ORDER BY v.kelas, v.nama_voter
    LIMIT $limit OFFSET $offsetSiswa
");
while ($row = mysqli_fetch_assoc($votedSiswaQuery)) {
    $votersSiswa[] = $row;
}

$totalGuruTargetQuery = mysqli_query($db, "SELECT COUNT(*) as total FROM tb_kode_guru");
$totalGuruTargetRow = mysqli_fetch_assoc($totalGuruTargetQuery);
$totalGuruTarget = isset($totalGuruTargetRow['total']) ? (int)$totalGuruTargetRow['total'] : 0;

$totalGuruVotedQuery = mysqli_query($db, "
    SELECT COUNT(DISTINCT v.id) as total
    FROM tb_voter v
    JOIN tb_vote_log l ON v.id = l.voter_id
    JOIN tb_kode_guru g ON v.nama_voter = g.kode /* RELASI UTAMA */
    WHERE v.role = 'guru'
");
$totalGuruRow = mysqli_fetch_assoc($totalGuruVotedQuery);
$totalGuru = isset($totalGuruRow['total']) ? (int)$totalGuruRow['total'] : 0;
$totalPagesGuru = $totalGuru > 0 ? ceil($totalGuru / $limit) : 1;

$votedGuru = $totalGuru;

$votedGuruQuery = mysqli_query($db, "
    SELECT v.id, v.nama_voter, v.kelas, v.role, l.nomor_kandidat
    FROM tb_voter v
    JOIN tb_vote_log l ON v.id = l.voter_id
    JOIN tb_kode_guru g ON v.nama_voter = g.kode /* RELASI UTAMA */
    WHERE v.role = 'guru'
    ORDER BY v.nama_voter
    LIMIT $limit OFFSET $offsetGuru
");
while ($row = mysqli_fetch_assoc($votedGuruQuery)) {
    $votersGuru[] = $row;
}

// jumlah siswa setiap kelas
$dataKelas = [];
$qKelas = mysqli_query($db, "SELECT nama_kelas, jumlah_siswa FROM tb_kelas ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($qKelas)) {
    $dataKelas[$row['nama_kelas']] = (int)$row['jumlah_siswa'];
}


$kelasSummary = [];
foreach ($dataKelas as $kelas => $target) {
    $q = mysqli_query($db, "
        SELECT COUNT(*) as jumlah 
        FROM tb_voter v 
        JOIN tb_vote_log l ON v.id=l.voter_id 
        WHERE v.kelas='" . mysqli_real_escape_string($db, $kelas) . "'
          AND v.role='siswa'
    ");
    $row = mysqli_fetch_assoc($q);
    $kelasSummary[$kelas] = [
        "voted" => isset($row['jumlah']) ? (int)$row['jumlah'] : 0,
        "target" => (int)$target
    ];
}

$hasilKandidat = [];
$q = mysqli_query($db, "
    SELECT v.kelas, l.nomor_kandidat, COUNT(*) as total_suara
    FROM tb_vote_log l
    JOIN tb_voter v ON l.voter_id = v.id
    WHERE v.role = 'siswa'
    GROUP BY v.kelas, l.nomor_kandidat
");

while ($row = mysqli_fetch_assoc($q)) {
    $kelas = $row['kelas'];
    $nomor = $row['nomor_kandidat'];
    $jumlah = $row['total_suara'];

    if (!isset($hasilKandidat[$kelas])) {
        $hasilKandidat[$kelas] = [
            "total" => 0,
            "kandidat" => []
        ];
    }

    $hasilKandidat[$kelas]["kandidat"][$nomor] = $jumlah;
    $hasilKandidat[$kelas]["total"] += $jumlah;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Daftar Voter - Voting OSIS</title>
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
            font-family: Arial, sans-serif;
        }

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
        }

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background: #34495e;
        }

        .main-content {
            flex: 1;
            padding: 30px;
        }

        h1 {
            margin-bottom: 20px;
            color: #2c3e50;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        table th {
            background: #2c3e50;
            color: #fff;
        }

        .pagination {
            text-align: center;
            margin-bottom: 30px;
        }

        .pagination a {
            display: inline-block;
            padding: 6px 12px;
            margin: 0 3px;
            background: #ecf0f1;
            color: #2c3e50;
            text-decoration: none;
            border-radius: 4px;
        }

        .pagination a.active {
            background: #3498db;
            color: #fff;
            font-weight: bold;
        }

        .pagination a:hover {
            background: #2980b9;
            color: #fff;
        }

        .kelas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .kelas-card {
            background: #fff;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
        }

        .progress {
            height: 8px;
            background: #ecf0f1;
            border-radius: 5px;
            overflow: hidden;
            margin: 6px 0 12px;
        }

        .progress-fill {
            height: 100%;
            background: #3498db;
            border-radius: 5px;
            width: 0;
            transition: width 0.8s ease-in-out;
        }

        .sub-fill {
            background: #2ecc71;
            height: 100%;
            border-radius: 5px;
            transition: width 0.8s ease-in-out;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="../index.php">Dashboard</a></li>
            <li><a href="../hasil-vote/result.php">Hasil</a></li>
            <li><a href="../kandidat/daftar.php">Daftar Kandidat</a></li>
            <li><a href="../sidebar-menu/voter.php" class="active">Daftar Voter</a></li>
            <li><a href="../sidebar-menu/token.php">Kelas & Token Siswa</a></li>
            <li><a href="../sidebar-menu/kode-guru.php">Token Guru</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <h1>Daftar Voter Khusus Siswa</h1>

        <table>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Kelas</th>
                <th>Pilihan Kandidat</th>
                <th>Role</th>
            </tr>
            <?php if (count($votersSiswa) > 0): ?>
                <?php foreach ($votersSiswa as $i => $v): ?>
                    <tr>
                        <td><?= $offsetSiswa + $i + 1 ?></td>
                        <td><?= htmlspecialchars($v['nama_voter']) ?></td>
                        <td><?= htmlspecialchars($v['kelas']) ?></td>
                        <td>Kandidat <?= htmlspecialchars($v['nomor_kandidat']) ?></td>
                        <td><?= ucfirst(htmlspecialchars($v['role'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;">Belum ada siswa yang memilih.</td>
                </tr>
            <?php endif; ?>
        </table>

        <?php if ($totalPagesSiswa > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $totalPagesSiswa; $p++): ?>
                    <a href="?page_siswa=<?= $p ?>&page_guru=<?= $pageGuru ?>" class="<?= $p == $pageSiswa ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <h3>Ringkasan Voting per Kelas</h3>
        <div class="kelas-grid">
            <?php foreach ($dataKelas as $kelas => $target): ?>
                <?php
                $voted = $kelasSummary[$kelas]['voted'];
                $percent = $target > 0 ? round(($voted / $target) * 100, 2) : 0;
                ?>
                <div class="kelas-card">
                    <h4><?= $kelas ?></h4>
                    <p><?= $voted ?> dari <?= $target ?> siswa (<?= $percent ?>%)</p>
                    <div class="progress">
                        <div class="progress-fill" style="width: <?= $percent ?>%;"></div>
                    </div>
                    <div class="kandidat-list">
                        <?php if (isset($hasilKandidat[$kelas])): ?>
                            <?php foreach ($hasilKandidat[$kelas]["kandidat"] as $nomor => $jumlah): ?>
                                <?php
                                $persen = $hasilKandidat[$kelas]["total"] > 0
                                    ? round(($jumlah / $hasilKandidat[$kelas]["total"]) * 100, 2)
                                    : 0;
                                ?>
                                <div class="kandidat-item">
                                    <span>Kandidat <?= $nomor ?> - <?= $jumlah ?> suara (<?= $persen ?>%)</span>
                                    <div class="progress sub-progress">
                                        <div class="sub-fill" style="width: <?= $persen ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <i>Belum ada suara di kelas ini.</i>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <h1>Daftar Voter Khusus Guru</h1>
        <table>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Pilihan Kandidat</th>
                <th>Role</th>
            </tr>
            <?php if (count($votersGuru) > 0): ?>
                <?php foreach ($votersGuru as $i => $v): ?>
                    <tr>
                        <td><?= $offsetGuru + $i + 1 ?></td>
                        <td><?= htmlspecialchars($v['nama_voter']) ?></td>
                        <td>Kandidat <?= htmlspecialchars($v['nomor_kandidat']) ?></td>
                        <td><?= ucfirst(htmlspecialchars($v['role'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align:center;">Belum ada guru yang memilih, atau data guru sudah dihapus dari manajemen.</td>
                </tr>
            <?php endif; ?>
        </table>

        <?php if ($totalPagesGuru > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $totalPagesGuru; $p++): ?>
                    <a href="?page_guru=<?= $p ?>&page_siswa=<?= $pageSiswa ?>" class="<?= $p == $pageGuru ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <?php

        $guruSummary = [
            "total" => 0,
            "kandidat" => []
        ];

        $qGuru = mysqli_query($db, "
            SELECT l.nomor_kandidat, COUNT(*) as total_suara
            FROM tb_vote_log l
            JOIN tb_voter v ON l.voter_id = v.id
            JOIN tb_kode_guru g ON v.nama_voter = g.kode /* RELASI UTAMA */
            WHERE v.role = 'guru'
            GROUP BY l.nomor_kandidat
        ");

        while ($row = mysqli_fetch_assoc($qGuru)) {
            $nomor = $row['nomor_kandidat'];
            $jumlah = $row['total_suara'];

            $guruSummary["kandidat"][$nomor] = $jumlah;
            $guruSummary["total"] += $jumlah;
        }

        $percentGuru = $totalGuruTarget > 0 ? round(($votedGuru / $totalGuruTarget) * 100, 2) : 0;
        ?>

        <h3>Ringkasan Voting Guru</h3>
        <div class="kelas-grid">
            <div class="kelas-card">
                <h4>Guru</h4>
                <p><?= $votedGuru ?> dari <?= $totalGuruTarget ?> guru (<?= $percentGuru ?>%)</p>
                <div class="progress">
                    <div class="progress-fill" style="width: <?= $percentGuru ?>%;"></div>
                </div>


                <div class="kandidat-list">
                    <?php if (!empty($guruSummary["kandidat"])): ?>
                        <?php foreach ($guruSummary["kandidat"] as $nomor => $jumlah): ?>
                            <?php
                            $persen = $guruSummary["total"] > 0
                                ? round(($jumlah / $guruSummary["total"]) * 100, 2)
                                : 0;
                            ?>
                            <div class="kandidat-item">
                                <span>Kandidat <?= $nomor ?> - <?= $jumlah ?> suara (<?= $persen ?>%)</span>
                                <div class="progress sub-progress">
                                    <div class="sub-fill" style="width: <?= $persen ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <i>Belum ada suara dari guru.</i>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>