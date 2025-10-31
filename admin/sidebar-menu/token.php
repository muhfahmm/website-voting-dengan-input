<?php
session_start();
require '../../db/db.php';

if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$admin = $_SESSION['username'];

// Fungsi bantu
function kelasToPrefix($kelas)
{
    $letters = preg_replace('/[^a-zA-Z]/', '', $kelas);
    $prefix = strtolower($letters);
    return $prefix === '' ? 'k' : $prefix;
}

function generateTokenByPrefixAndNumber($prefix, $classNum, $db)
{
    $like = mysqli_real_escape_string($db, $prefix . $classNum . '%');
    $q = mysqli_query($db, "SELECT COUNT(*) AS jumlah FROM tb_buat_token WHERE token LIKE '{$like}'");
    $row = mysqli_fetch_assoc($q);
    $jumlah = (int)$row['jumlah'] + 1;

    return $prefix . $classNum . str_pad($jumlah, 3, '0', STR_PAD_LEFT);
}

// Ambil semua kelas
$kelasList = [];
$kelasResult = mysqli_query($db, "SELECT * FROM tb_kelas ORDER BY id ASC");
while ($r = mysqli_fetch_assoc($kelasResult)) {
    $kelasList[] = $r;
}

$classNumberMap = [];
$prefixCounters = [];
foreach ($kelasList as $k) {
    $id = (int)$k['id'];
    $nama = $k['nama_kelas'];
    if (preg_match('/(\d+)/', $nama, $m)) {
        $classNum = (int)$m[1];
    } else {
        $prefix = kelasToPrefix($nama);
        if (!isset($prefixCounters[$prefix])) $prefixCounters[$prefix] = 0;
        $prefixCounters[$prefix]++;
        $classNum = $prefixCounters[$prefix];
    }
    $classNumberMap[$id] = $classNum;
}

$message = '';

/* ======================
   TAMBAH KELAS BARU
   ====================== */
if (isset($_POST['add_class'])) {
    $kelas_input = trim($_POST['kelas']);
    $jumlah_siswa = (int)$_POST['jumlah_siswa'];

    if ($kelas_input !== '' && $jumlah_siswa > 0) {
        $kelas_esc = mysqli_real_escape_string($db, $kelas_input);
        $exists = mysqli_query($db, "SELECT * FROM tb_kelas WHERE nama_kelas='$kelas_esc'");
        if (mysqli_num_rows($exists) == 0) {
            mysqli_query($db, "INSERT INTO tb_kelas (nama_kelas, jumlah_siswa) VALUES ('$kelas_esc', $jumlah_siswa)");
            $message = "✅ Kelas '$kelas_input' berhasil ditambahkan.";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $message = "⚠️ Kelas '$kelas_input' sudah ada.";
        }
    } else {
        $message = "⚠️ Nama kelas atau jumlah siswa tidak boleh kosong!";
    }
}

/* ======================
   HAPUS KELAS + TOKEN
   ====================== */
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $q = mysqli_query($db, "SELECT nama_kelas FROM tb_kelas WHERE id=$id");
    $r = mysqli_fetch_assoc($q);
    $kelas = $r ? $r['nama_kelas'] : null;

    if ($kelas) {
        $prefix = kelasToPrefix($kelas);

        if (preg_match('/(\d+)/', $kelas, $m)) {
            $classNum = (int)$m[1];
        } else {
            $pref = mysqli_real_escape_string($db, $prefix);
            $listQ = mysqli_query($db, "SELECT id, nama_kelas FROM tb_kelas ORDER BY id ASC");
            $counter = 0;
            $found = null;
            while ($row = mysqli_fetch_assoc($listQ)) {
                $p = kelasToPrefix($row['nama_kelas']);
                if ($p === $prefix) {
                    $counter++;
                    if ((int)$row['id'] === $id) {
                        $found = $counter;
                        break;
                    }
                }
            }
            $classNum = $found ? $found : 0;
        }

        mysqli_query($db, "DELETE FROM tb_kelas WHERE id=$id");

        if ($classNum > 0) {
            $like = mysqli_real_escape_string($db, $prefix . $classNum . '%');
            mysqli_query($db, "DELETE FROM tb_buat_token WHERE token LIKE '{$like}'");
        } else {
            $like = mysqli_real_escape_string($db, $prefix . '%');
            mysqli_query($db, "DELETE FROM tb_buat_token WHERE token LIKE '{$like}'");
        }

        $message = "🗑️ Kelas '$kelas' dan token terkait berhasil dihapus.";
        header("Location: " . preg_replace('/(\?.*)?$/', '', $_SERVER['REQUEST_URI']));
        exit;
    }
}

/* ======================
   HAPUS TOKEN
   ====================== */
if (isset($_GET['hapus_token'])) {
    $id_token = (int)$_GET['hapus_token'];
    $check = mysqli_query($db, "SELECT token FROM tb_buat_token WHERE id = $id_token");
    if (mysqli_num_rows($check) > 0) {
        $hapus = mysqli_query($db, "DELETE FROM tb_buat_token WHERE id = $id_token");
        $message = $hapus ? "🗑️ Token dan voter terkait berhasil dihapus." : "❌ Gagal menghapus token.";
    } else {
        $message = "⚠️ Token tidak ditemukan.";
    }
}

/* ======================
   GENERATE TOKEN
   ====================== */
if (isset($_POST['generate'])) {
    $kelas_id = (int)$_POST['kelas_id'];
    $q = mysqli_query($db, "SELECT nama_kelas FROM tb_kelas WHERE id = $kelas_id LIMIT 1");
    $r = mysqli_fetch_assoc($q);
    if (!$r) {
        $message = "⚠️ Kelas tidak ditemukan.";
    } else {
        $kelas_nama = $r['nama_kelas'];
        $prefix = kelasToPrefix($kelas_nama);
        $classNum = $classNumberMap[$kelas_id] ?? 0;
        if ($classNum <= 0) {
            $message = "❌ Gagal menentukan nomor kelas.";
        } else {
            $token = generateTokenByPrefixAndNumber($prefix, $classNum, $db);
            $token_esc = mysqli_real_escape_string($db, $token);
            $insert = mysqli_query($db, "INSERT INTO tb_buat_token (token, kelas_id, created_by) VALUES ('$token_esc', $kelas_id, '$admin')");
            $message = $insert ? "✅ Token dibuat untuk <b>$kelas_nama</b>: <b>$token</b>" : "❌ Gagal membuat token.";
            header("Location: " . preg_replace('/(\?.*)?$/', '', $_SERVER['REQUEST_URI']));
            exit;
        }
    }
}

$kelasQuery = mysqli_query($db, "SELECT * FROM tb_kelas ORDER BY id ASC");
$tokens = mysqli_query($db, "
    SELECT t.*, k.nama_kelas 
    FROM tb_buat_token t
    LEFT JOIN tb_kelas k ON t.kelas_id = k.id
    ORDER BY t.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Manajemen Token Voting</title>
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
            margin-bottom: 18px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
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
        }

        a.btn-delete:hover {
            text-decoration: underline;
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
            <li><a href="../sidebar-menu/token.php" class="active">Kelas dan Token</a></li>
            <li><a href="../sidebar-menu/kode-guru.php">Buat Kode Guru</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>Manajemen Token</h1>

        <?php if (!empty($message)): ?>
            <div style="text-align:center;margin-bottom:15px;color:#2c3e50;"><?= $message; ?></div>
        <?php endif; ?>

        <!-- Tambah Kelas -->
        <form method="POST" class="form-modern">
    <div class="form-group">
        <input type="text" name="kelas" placeholder="Masukkan nama kelas (misal: X-1, XI-RPL)" required>
        <input type="number" name="jumlah_siswa" placeholder="Jumlah siswa" min="1" required>
        <button type="submit" name="add_class">
            <span>Tambah</span>
        </button>
    </div>

    <style>
        .form-modern {
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .form-modern input[type=text],
        .form-modern input[type=number] {
            padding: 10px 14px;
            font-size: 15px;
            border: 1.5px solid #ccc;
            border-radius: 6px;
            transition: all 0.3s ease;
            min-width: 220px;
            outline: none;
        }

        .form-modern input[type=text]:focus,
        .form-modern input[type=number]:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        .form-modern button {
            gap: 5px;
            padding: 10px 16px;
            font-size: 15px;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        }

        .form-modern button:hover {
            background: linear-gradient(135deg, #2980b9, #1f5f8a);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.4);
        }

        .form-modern button:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(52, 152, 219, 0.3);
        }
    </style>
</form>


        <!-- Daftar Kelas -->
        <h3>Daftar Kelas</h3>
        <table>
            <tr>
                <th>No</th>
                <th>Nama Kelas</th>
                <th>Jumlah Siswa</th>
                <th>Aksi</th>
            </tr>
            <?php
            $no = 1;
            if (mysqli_num_rows($kelasQuery) > 0):
                while ($k = mysqli_fetch_assoc($kelasQuery)): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($k['nama_kelas']); ?></td>
                        <td><?= htmlspecialchars($k['jumlah_siswa']); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="kelas_id" value="<?= $k['id']; ?>">
                                <button type="submit" name="generate">Buat Token</button>
                            </form>
                            <a href="?hapus=<?= $k['id']; ?>" class="btn-delete"
                                onclick="return confirm('Yakin ingin menghapus kelas ini dan semua token terkait?')">Hapus</a>
                        </td>
                    </tr>
                <?php endwhile;
            else: ?>
                <tr>
                    <td colspan="4">Belum ada kelas.</td>
                </tr>
            <?php endif; ?>
        </table>

        <!-- Daftar Token -->
        <h3>Daftar Token yang Sudah Dibuat</h3>
        <table>
            <tr>
                <th>No</th>
                <th>Kelas</th>
                <th>Token</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
            <?php
            $no = 1;
            if (mysqli_num_rows($tokens) > 0):
                while ($row = mysqli_fetch_assoc($tokens)):
                    $status = $row['status_token'] === 'sudah'
                        ? '<span style="color:green;font-weight:bold;">Sudah Digunakan</span>'
                        : '<span style="color:red;font-weight:bold;">Belum Digunakan</span>';
                    $kelasNama = $row['nama_kelas'] ?? '<i>Tidak Diketahui</i>';
            ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($kelasNama); ?></td>
                        <td><?= htmlspecialchars($row['token']); ?></td>
                        <td><?= $status; ?></td>
                        <td><a href="?hapus_token=<?= $row['id']; ?>" class="btn-delete"
                                onclick="return confirm('Hapus token ini?')">Hapus</a></td>
                    </tr>
                <?php endwhile;
            else: ?>
                <tr>
                    <td colspan="5">Belum ada token dibuat.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</body>

</html>