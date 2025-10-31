<?php
session_start();
require '../../db/db.php'; // Sesuaikan dengan path file koneksi database Anda

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
    // Mengambil kode dari input form
    $kode_manual = trim($_POST['kode_manual'] ?? '');
    
    if (empty($kode_manual)) {
        $message = "⚠️ Kode Guru tidak boleh kosong.";
    } else {
        $kode_esc = mysqli_real_escape_string($db, $kode_manual);

        // Memeriksa duplikasi kode
        $check = mysqli_query($db, "SELECT id FROM tb_kode_guru WHERE kode = '$kode_esc'");
        if (mysqli_num_rows($check) == 0) {
            // Memastikan format kode yang dimasukkan hanya huruf dan angka, dan tidak mengandung spasi
            if (!preg_match('/^[a-zA-Z0-9]+$/', $kode_manual)) {
                 $message = "⚠️ Kode Guru hanya boleh mengandung huruf dan angka (tanpa spasi/simbol).";
            } else {
                // Menggunakan Prepared Statement untuk INSERT (praktik terbaik)
                $insert_stmt = mysqli_prepare($db, "INSERT INTO tb_kode_guru (kode, status_kode) VALUES (?, 'belum')");
                mysqli_stmt_bind_param($insert_stmt, "s", $kode_esc);
                $insert = mysqli_stmt_execute($insert_stmt);
                mysqli_stmt_close($insert_stmt);

                if ($insert) {
                    $message = "✅ Kode Guru berhasil dibuat: <b>$kode_manual</b>";
                    // Redirect untuk mencegah resubmission form
                    header("Location: " . preg_replace('/(\?.*)?$/', '', $_SERVER['REQUEST_URI']) . "?msg=" . urlencode($message));
                    exit;
                } else {
                    $message = "❌ Gagal membuat Kode Guru.";
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
        
        // Query DELETE ini akan memicu Foreign Key ON DELETE CASCADE,
        // yang secara otomatis menghapus record di tb_voter yang berelasi
        $delete_stmt = mysqli_prepare($db, "DELETE FROM tb_kode_guru WHERE id = ?");
        mysqli_stmt_bind_param($delete_stmt, "i", $id_kode);
        $hapus = mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);

        if ($hapus) {
            $message = "🗑️ Kode Guru '$kode' berhasil dihapus. Voter terkait (jika ada) juga dihapus.";
            // Redirect ke halaman tanpa parameter GET hapus_kode
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
    PENGHITUNGAN STATISTIK KODE GURU (MENGGUNAKAN RELASI BARU)
    ====================== */
$total_kode = 0;
$sudah_digunakan = 0;
$belum_digunakan = 0;

// 1. Hitung TOTAL KODE
$q_total = mysqli_query($db, "SELECT COUNT(id) AS total FROM tb_kode_guru");
$r_total = mysqli_fetch_assoc($q_total);
$total_kode = (int)$r_total['total'];

// 2. Hitung SUDAH DIGUNAKAN (Kode yang ID-nya ada di tb_voter)
$q_sudah = mysqli_query($db, "
    SELECT COUNT(DISTINCT g.id) AS sudah 
    FROM tb_kode_guru g 
    JOIN tb_voter v ON g.id = v.kode_guru_id
");
$r_sudah = mysqli_fetch_assoc($q_sudah);
$sudah_digunakan = (int)$r_sudah['sudah'];

// 3. Hitung BELUM DIGUNAKAN (Kode yang ID-nya TIDAK ada di tb_voter)
// Catatan: Jika Anda tidak lagi memperbarui kolom status_kode di tb_kode_guru,
// maka cara terbaik adalah menghitung selisihnya.
$belum_digunakan = $total_kode - $sudah_digunakan;

// Query untuk menampilkan semua kode guru, termasuk status Voted (untuk tabel)
$kodeGuruQuery = mysqli_query($db, "
    SELECT 
        g.*, 
        v.id AS voter_id
    FROM tb_kode_guru g
    LEFT JOIN tb_voter v ON g.id = v.kode_guru_id
    ORDER BY g.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Manajemen Kode Guru</title>
    <style>
        /* Gaya dasar (tidak diubah dari versi sebelumnya) */
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

        /* Gaya untuk form input manual */
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
        
        /* Gaya untuk statistik */
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
            <li><a href="../sidebar-menu/token.php">Kelas dan Token</a></li>
            <li><a href="../sidebar-menu/kode-guru.php" class="active">Buat Kode Guru</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>Manajemen Kode Guru</h1>

        <?php if (!empty($message)): ?>
            <div style="text-align:center;margin-bottom:15px;color:#2c3e50;padding:10px;border:1px solid #ccc;border-radius:5px;background:#fff;"><?= $message; ?></div>
        <?php endif; ?>

        <div class="statistik-cards">
            <div class="card" style="border-left: 5px solid #3498db;">
                <h4>Total Kode Guru</h4>
                <p style="color: #3498db;"><?= $total_kode; ?></p>
            </div>
            <div class="card" style="border-left: 5px solid #e67e22;">
                <h4>Belum Digunakan</h4>
                <p style="color: #e67e22;"><?= $belum_digunakan; ?></p>
            </div>
            <div class="card" style="border-left: 5px solid #2ecc71;">
                <h4>Sudah Digunakan</h4>
                <p style="color: #2ecc71;"><?= $sudah_digunakan; ?></p>
            </div>
        </div>

        <form method="POST" class="input-form">
            <input type="text" name="kode_manual" placeholder="Masukkan Kode Guru (e.g., GURU1, STAFFBP)" required>
            <button type="submit" name="tambah_kode">
                Tambah Kode Guru
            </button>
        </form>

        ---

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
                    // Cek status berdasarkan relasi (voter_id tidak null berarti sudah digunakan)
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
                        <td><a href="?hapus_kode=<?= $row['id']; ?>" class="btn-delete"
                                onclick="return confirm('Yakin ingin menghapus kode guru ini? Voter yang menggunakan kode ini akan dihapus.')">Hapus</a></td>
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