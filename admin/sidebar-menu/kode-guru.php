<?php
session_start();
require '../../db/db.php'; // Koneksi database

if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$admin = $_SESSION['username'];
$message = '';

/* ======================
   TAMBAH KODE GURU MANUAL
   ====================== */
if (isset($_POST['tambah_kode'])) {
    $kode_manual = trim($_POST['kode_manual'] ?? '');

    if (empty($kode_manual)) {
        $message = "⚠️ Kode Guru tidak boleh kosong.";
    } else {
        $kode_esc = mysqli_real_escape_string($db, $kode_manual);

        // Cek apakah kode sudah ada
        $check = mysqli_query($db, "SELECT id FROM tb_kode_guru WHERE kode = '$kode_esc'");
        if (mysqli_num_rows($check) == 0) {
            // Validasi hanya huruf dan angka
            if (!preg_match('/^[a-zA-Z0-9]+$/', $kode_manual)) {
                $message = "⚠️ Kode Guru hanya boleh berisi huruf dan angka (tanpa spasi/simbol).";
            } else {
                // Tambahkan kode ke database
                $insert_stmt = mysqli_prepare($db, "INSERT INTO tb_kode_guru (kode, status_kode) VALUES (?, 'belum')");
                mysqli_stmt_bind_param($insert_stmt, "s", $kode_esc);
                $insert = mysqli_stmt_execute($insert_stmt);
                mysqli_stmt_close($insert_stmt);

                if ($insert) {
                    $message = "✅ Kode Guru <b>$kode_manual</b> berhasil ditambahkan.";
                    header("Location: " . preg_replace('/(\?.*)?$/', '', $_SERVER['REQUEST_URI']) . "?msg=" . urlencode($message));
                    exit;
                } else {
                    $message = "❌ Gagal menambahkan Kode Guru.";
                }
            }
        } else {
            $message = "⚠️ Kode Guru <b>$kode_manual</b> sudah ada. Silakan gunakan kode lain.";
        }
    }
}

/* ======================
   HAPUS KODE GURU
   ====================== */
if (isset($_GET['hapus_kode'])) {
    $id_kode = (int)$_GET['hapus_kode'];
    $check = mysqli_query($db, "SELECT kode FROM tb_kode_guru WHERE id = $id_kode");

    if (mysqli_num_rows($check) > 0) {
        $r = mysqli_fetch_assoc($check);
        $kode = $r['kode'];

        // Hitung berapa voter guru yang akan ikut terhapus otomatis
        $count_voter = mysqli_query($db, "SELECT COUNT(*) AS total FROM tb_voter WHERE kode_guru_id = $id_kode");
        $count_row = mysqli_fetch_assoc($count_voter);
        $total_voter = (int)$count_row['total'];

        // Hapus Kode Guru (otomatis menghapus voter terkait via ON DELETE CASCADE)
        $delete_stmt = mysqli_prepare($db, "DELETE FROM tb_kode_guru WHERE id = ?");
        mysqli_stmt_bind_param($delete_stmt, "i", $id_kode);
        $hapus = mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);

        if ($hapus) {
            $message = "🗑️ Kode Guru '$kode' berhasil dihapus. <b>$total_voter</b> voter terkait juga dihapus otomatis.";
            header("Location: " . preg_replace('/(\?.*)?$/', '', $_SERVER['REQUEST_URI']));
            exit;
        } else {
            $message = "❌ Gagal menghapus Kode Guru.";
        }
    } else {
        $message = "⚠️ Kode Guru tidak ditemukan.";
    }
}

// Ambil pesan dari redirect (jika ada)
if (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
}

/* ======================
   STATISTIK KODE GURU
   ====================== */
$total_kode = 0;
$sudah_digunakan = 0;
$belum_digunakan = 0;

// Hitung total
$q_total = mysqli_query($db, "SELECT COUNT(id) AS total FROM tb_kode_guru");
$r_total = mysqli_fetch_assoc($q_total);
$total_kode = (int)$r_total['total'];

// Hitung sudah digunakan
$q_sudah = mysqli_query($db, "
    SELECT COUNT(DISTINCT g.id) AS sudah 
    FROM tb_kode_guru g 
    JOIN tb_voter v ON g.id = v.kode_guru_id
");
$r_sudah = mysqli_fetch_assoc($q_sudah);
$sudah_digunakan = (int)$r_sudah['sudah'];

// Hitung belum digunakan
$belum_digunakan = $total_kode - $sudah_digunakan;

// Ambil semua data kode guru
$kodeGuruQuery = mysqli_query($db, "
    SELECT g.*, v.id AS voter_id
    FROM tb_kode_guru g
    LEFT JOIN tb_voter v ON g.id = v.kode_guru_id
    ORDER BY g.created_at ASC
");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Manajemen Kode Guru</title>
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

        h3 {
            margin-top: 20px;
            margin-bottom: 15px;
            color: #34495e;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }

        th {
            background: #2c3e50;
            color: #fff;
        }

        a.btn-delete {
            color: #e74c3c;
            text-decoration: none;
            font-weight: bold;
            padding: 5px 10px;
            border: 1px solid #e74c3c;
            border-radius: 4px;
            transition: background 0.3s;
        }

        a.btn-delete:hover {
            background: #e74c3c;
            color: #fff;
            text-decoration: none;
        }

        .input-form {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            align-items: center;
        }

        .input-form input[type="text"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            flex-grow: 1;
            max-width: 300px;
            font-size: 16px;
        }

        .input-form button {
            padding: 10px 20px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        }

        .input-form button:hover {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(46, 204, 113, 0.4);
        }

        .statistik-cards {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            flex: 1;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .card h4 {
            margin: 0 0 10px 0;
            color: #7f8c8d;
            font-size: 14px;
        }

        .card p {
            font-size: 32px;
            font-weight: bold;
            margin: 0;
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
            <li><a href="../sidebar-menu/voter.php">Daftar Voter</a></li>
            <li><a href="../sidebar-menu/token.php">Kelas & Token Siswa</a></li>
            <li><a href="../sidebar-menu/kode-guru.php" class="active">Token Guru</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>Manajemen Kode Guru</h1>

        <?php if (!empty($message)): ?>
            <div style="text-align:center;margin-bottom:15px;color:#2c3e50;padding:10px;border:1px solid #ccc;border-radius:5px;background:#fff;">
                <?= $message; ?>
            </div>
        <?php endif; ?>

        <div class="statistik-cards">
            <div class="card" style="border-left:5px solid #3498db;">
                <h4>Total Kode Guru</h4>
                <p style="color:#3498db;"><?= $total_kode; ?></p>
            </div>
            <div class="card" style="border-left:5px solid #e67e22;">
                <h4>Belum Digunakan</h4>
                <p style="color:#e67e22;"><?= $belum_digunakan; ?></p>
            </div>
            <div class="card" style="border-left:5px solid #2ecc71;">
                <h4>Sudah Digunakan</h4>
                <p style="color:#2ecc71;"><?= $sudah_digunakan; ?></p>
            </div>
        </div>

        <form method="POST" class="input-form">
            <input type="text" name="kode_manual" placeholder="Masukkan Kode Guru (contoh: GURU1, STAFFBP)" required>
            <button type="submit" name="tambah_kode">Tambah Kode Guru</button>
        </form>

        <h3>Daftar Kode Guru yang Sudah Dibuat</h3>
        <table>
            <tr>
                <th>No</th>
                <th>Kode Guru</th>
                <th>Status</th>
                <th>Waktu Dibuat</th>
                <th>Aksi</th>
            </tr>
            <?php
            $no = 1;
            if (mysqli_num_rows($kodeGuruQuery) > 0):
                while ($row = mysqli_fetch_assoc($kodeGuruQuery)):
                    $is_used = !empty($row['voter_id']);
                    $status = $is_used
                        ? '<span style="color:green;font-weight:bold;">Sudah Digunakan</span>'
                        : '<span style="color:red;font-weight:bold;">Belum Digunakan</span>';
            ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><b><?= htmlspecialchars($row['kode']); ?></b></td>
                        <td><?= $status; ?></td>
                        <td><?= date('d-m-Y H:i:s', strtotime($row['created_at'])); ?></td>
                        <td>
                            <a href="?hapus_kode=<?= $row['id']; ?>" class="btn-delete"
                                onclick="return confirm('Yakin ingin menghapus kode guru ini? Semua voter terkait akan dihapus otomatis.')">
                                Hapus
                            </a>
                        </td>
                    </tr>
                <?php endwhile;
            else: ?>
                <tr>
                    <td colspan="5">Belum ada Kode Guru dibuat.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</body>

</html>
<?php mysqli_close($db); ?>