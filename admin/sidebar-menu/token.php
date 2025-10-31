<?php
session_start();
require '../../db/db.php';

if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$admin = $_SESSION['username'];

/**
 * Bersihkan kelas jadi prefix token
 * Contoh: "X-1" -> "x1" (prefix part is letters only, we keep letter part as prefix)
 * But we will use prefix only for grouping, numeric part akan di-handle terpisah.
 */
function kelasToPrefix($kelas)
{
    // ambil bagian huruf dari kelas (misal X-1 -> x, XI-2 -> xi, XII-RPL -> xii)
    $letters = preg_replace('/[^a-zA-Z]/', '', $kelas);
    $prefix = strtolower($letters);
    if ($prefix === '') {
        // fallback jika tidak ada huruf
        $prefix = 'k';
    }
    return $prefix;
}

/**
 * Generate token berdasarkan prefix + class number
 * token format: <prefix><classNum><3-digit-urut>
 * contoh: x1001, x1002, xi3001, xii4001
 */
function generateTokenByPrefixAndNumber($prefix, $classNum, $db)
{
    // hitung jumlah token yang sudah ada untuk kombinasi prefix+classNum
    $like = mysqli_real_escape_string($db, $prefix . $classNum . '%');
    $q = mysqli_query($db, "SELECT COUNT(*) AS jumlah FROM tb_buat_token WHERE token LIKE '{$like}'");
    $row = mysqli_fetch_assoc($q);
    $jumlah = (int)$row['jumlah'] + 1;

    // urutan 3 digit (001,002,...)
    $token = $prefix . $classNum . str_pad($jumlah, 3, '0', STR_PAD_LEFT);
    return $token;
}

/**
 * Ambil semua kelas dari DB dan bangun mapping:
 * $kelasList = [ ['id'=>..,'nama_kelas'=>..], ... ]
 * $classNumberMap[id] = classNum (angka yang dipakai di token, bukan id DB)
 * aturan:
 * - jika nama_kelas mengandung angka -> ambil angka pertama yang ditemukan
 * - jika tidak ada angka -> berikan nomor urut per prefix (1,2,3...) berdasarkan urutan pengambilan dari DB
 */
$kelasList = [];
$kelasResult = mysqli_query($db, "SELECT * FROM tb_kelas ORDER BY id ASC");
while ($r = mysqli_fetch_assoc($kelasResult)) {
    $kelasList[] = $r;
}

// building map
$classNumberMap = [];
$prefixCounters = []; // untuk kelas tanpa angka, jumlah per prefix
foreach ($kelasList as $k) {
    $id = (int)$k['id'];
    $nama = $k['nama_kelas'];

    // cari angka di nama kelas
    if (preg_match('/(\d+)/', $nama, $m)) {
        // ambil angka pertama sebagai nomor kelas
        $classNum = (int)$m[1];
    } else {
        // jika tidak ada angka, berikan nomor urut per prefix (start 1)
        $prefix = kelasToPrefix($nama);
        if (!isset($prefixCounters[$prefix])) $prefixCounters[$prefix] = 0;
        $prefixCounters[$prefix]++;
        $classNum = $prefixCounters[$prefix];
    }

    $classNumberMap[$id] = $classNum;
}

/* =========================
   HANDLE FORM ACTIONS
   ========================= */

// pesan untuk UI
$message = '';

// Tambah kelas baru
if (isset($_POST['add_class'])) {
    $kelas_input = trim($_POST['kelas']);
    if ($kelas_input !== '') {
        $kelas_esc = mysqli_real_escape_string($db, $kelas_input);
        $exists = mysqli_query($db, "SELECT * FROM tb_kelas WHERE nama_kelas='$kelas_esc'");
        if (mysqli_num_rows($exists) == 0) {
            mysqli_query($db, "INSERT INTO tb_kelas (nama_kelas, created_by) VALUES ('$kelas_esc', '" . mysqli_real_escape_string($db, $admin) . "')");
            $message = "✅ Kelas '$kelas_input' berhasil ditambahkan.";
            // reload page vars (simple redirect supaya map ter-update)
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $message = "⚠️ Kelas '$kelas_input' sudah ada.";
        }
    } else {
        $message = "⚠️ Nama kelas tidak boleh kosong!";
    }
}

// Hapus kelas dan token terkait (menggunakan classNum, bukan id dalam token)
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $q = mysqli_query($db, "SELECT nama_kelas FROM tb_kelas WHERE id=$id");
    $r = mysqli_fetch_assoc($q);
    $kelas = $r ? $r['nama_kelas'] : null;

    if ($kelas) {
        $prefix = kelasToPrefix($kelas);

        // tentukan classNum untuk kelas ini (menggunakan logic yang sama seperti map)
        // coba ambil angka dari nama kelas
        if (preg_match('/(\d+)/', $kelas, $m)) {
            $classNum = (int)$m[1];
        } else {
            // jika tidak ada angka, kita harus menghitung posisi kelas di DB yang memiliki prefix sama
            // ambil daftar nama_kelas untuk prefix yang sama, urut by id asc
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

        // hapus kelas dari tb_kelas
        mysqli_query($db, "DELETE FROM tb_kelas WHERE id=$id");

        // hapus token yang berawalan prefix + classNum
        if ($classNum > 0) {
            $like = mysqli_real_escape_string($db, $prefix . $classNum . '%');
            mysqli_query($db, "DELETE FROM tb_buat_token WHERE token LIKE '{$like}'");
        } else {
            // sebagai fallback, hapus token yang mengandung prefix saja (lebih luas)
            $like = mysqli_real_escape_string($db, $prefix . '%');
            mysqli_query($db, "DELETE FROM tb_buat_token WHERE token LIKE '{$like}'");
        }

        $message = "🗑️ Kelas '$kelas' dan token terkait berhasil dihapus.";
        // redirect supaya tampilan & map refresh
        header("Location: " . preg_replace('/(\?.*)?$/', '', $_SERVER['REQUEST_URI']));
        exit;
    }
}

// Hapus token individu + voter terkait (otomatis lewat ON DELETE CASCADE)
if (isset($_GET['hapus_token'])) {
    $id_token = (int)$_GET['hapus_token'];

    // Cek apakah token ada dulu
    $check = mysqli_query($db, "SELECT token FROM tb_buat_token WHERE id = $id_token");
    if (mysqli_num_rows($check) > 0) {
        $hapus = mysqli_query($db, "DELETE FROM tb_buat_token WHERE id = $id_token");

        if ($hapus) {
            $message = "🗑️ Token dan voter terkait berhasil dihapus secara otomatis.";
        } else {
            $message = "❌ Gagal menghapus token: " . mysqli_error($db);
        }
    } else {
        $message = "⚠️ Token tidak ditemukan.";
    }
}


// Generate token berdasarkan kelas yang dipilih
if (isset($_POST['generate'])) {
    $kelas_id = (int)$_POST['kelas_id'];
    // ambil nama kelas dari DB untuk prefix
    $q = mysqli_query($db, "SELECT nama_kelas FROM tb_kelas WHERE id = $kelas_id LIMIT 1");
    $r = mysqli_fetch_assoc($q);
    if (!$r) {
        $message = "⚠️ Kelas tidak ditemukan.";
    } else {
        $kelas_nama = $r['nama_kelas'];
        $prefix = kelasToPrefix($kelas_nama);

        // dapatkan classNum dari map; jika tidak ada, coba hitung seperti sebelumnya
        if (isset($classNumberMap[$kelas_id])) {
            $classNum = $classNumberMap[$kelas_id];
        } else {
            // fallback: ambil angka di nama atau hitung urut per prefix
            if (preg_match('/(\d+)/', $kelas_nama, $m)) {
                $classNum = (int)$m[1];
            } else {
                // hitung urut per prefix
                $listQ = mysqli_query($db, "SELECT id, nama_kelas FROM tb_kelas ORDER BY id ASC");
                $counter = 0;
                $found = null;
                while ($row = mysqli_fetch_assoc($listQ)) {
                    $p = kelasToPrefix($row['nama_kelas']);
                    if ($p === $prefix) {
                        $counter++;
                        if ((int)$row['id'] === $kelas_id) {
                            $found = $counter;
                            break;
                        }
                    }
                }
                $classNum = $found ? $found : 0;
            }
        }

        if ($classNum <= 0) {
            $message = "❌ Gagal menentukan nomor kelas untuk token.";
        } else {
            $token = generateTokenByPrefixAndNumber($prefix, $classNum, $db);
            $created_by = mysqli_real_escape_string($db, $admin);
            $token_esc = mysqli_real_escape_string($db, $token);

            $insert = mysqli_query($db, "INSERT INTO tb_buat_token (token, kelas_id, created_by) 
VALUES ('$token_esc', $kelas_id, '$created_by')");

            if ($insert) {
                $message = "✅ Token berhasil dibuat untuk kelas <b>" . htmlspecialchars($kelas_nama) . "</b>: <b>$token</b>";
                header("Location: " . preg_replace('/(\?.*)?$/', '', $_SERVER['REQUEST_URI']));
                exit;
            } else {
                $message = "❌ Gagal membuat token: " . mysqli_error($db);
            }
        }
    }
}

// Reload kelas dan tokens untuk tampilan (ambil ulang dari DB)
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Voting OSIS</title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <!-- css sidebar -->
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

    <!-- css konten tabel -->
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 90%;
            max-width: 1000px;
            margin: 30px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 12px;
        }

        form {
            margin-bottom: 18px;
            text-align: center;
        }

        input[type=text],
        select,
        button {
            padding: 8px 12px;
            font-size: 14px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        button {
            background: #3498db;
            color: #fff;
            border: none;
            cursor: pointer;
            margin-left: 8px;
            padding: 8px 12px;
        }

        button:hover {
            background: #2980b9;
        }

        .message {
            text-align: center;
            margin: 10px 0;
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

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        a.btn-delete {
            color: #e74c3c;
            text-decoration: none;
            font-weight: bold;
        }

        a.btn-delete:hover {
            text-decoration: underline;
        }

        .small {
            font-size: 13px;
            color: #666;
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
        <h1>Manajemen Token Voting</h1>
        <?php if (!empty($message)): ?>
            <div class="message"><?= $message; ?></div>
        <?php endif; ?>

        <!-- Form tambah kelas -->
        <form method="POST" style="margin-bottom: 12px;">
            <input type="text" name="kelas" placeholder="Masukkan nama kelas baru (mis: X-1, X-2, XI-RPL)" required>
            <button type="submit" name="add_class">Tambah Kelas</button>
        </form>

        <!-- Daftar kelas -->
        <h3>Daftar Kelas</h3>
        <table style="margin-bottom:16px;">
            <tr>
                <th>No</th>
                <th>Kelas</th>
                <th>Aksi</th>
            </tr>
            <?php
            $no = 1;
            // tampilkan berdasarkan query yang diambil ulang
            if (mysqli_num_rows($kelasQuery) > 0):
                mysqli_data_seek($kelasQuery, 0);
                while ($k = mysqli_fetch_assoc($kelasQuery)):
                    $classNumDisplay = isset($classNumberMap[$k['id']]) ? $classNumberMap[$k['id']] : '-';
            ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($k['nama_kelas']); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="kelas_id" value="<?= $k['id']; ?>">
                                <button type="submit" name="generate">Buat Token</button>
                            </form>
                            <a href="?hapus=<?= $k['id']; ?>" class="btn-delete" onclick="return confirm('Yakin ingin menghapus kelas ini dan semua token terkait?')">Hapus</a>
                        </td>
                    </tr>
                <?php endwhile;
            else: ?>
                <tr>
                    <td colspan="4" style="text-align:center;">Belum ada kelas ditambahkan.</td>
                </tr>
            <?php endif; ?>
        </table>

        <!-- Daftar token -->
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
                        <td>
                            <a href="?hapus_token=<?= $row['id']; ?>" class="btn-delete"
                                onclick="return confirm('Hapus token ini?')">Hapus</a>
                        </td>
                    </tr>
                <?php endwhile;
            else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;">Belum ada token yang dibuat.</td>
                </tr>
            <?php endif; ?>
        </table>


    </div>
</body>

</html>