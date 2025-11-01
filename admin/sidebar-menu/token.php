<?php
session_start();
require '../../db/db.php';

if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$admin = $_SESSION['username'];

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
// jumlah data per halaman
$limit = 10;

// halaman untuk kelas
$pageKelas = isset($_GET['page_kelas']) ? (int)$_GET['page_kelas'] : 1;
if ($pageKelas < 1) $pageKelas = 1;
$offsetKelas = ($pageKelas - 1) * $limit;

// halaman untuk token
$pageToken = isset($_GET['page_token']) ? (int)$_GET['page_token'] : 1;
if ($pageToken < 1) $pageToken = 1;
$offsetToken = ($pageToken - 1) * $limit;

$totalKelasResult = mysqli_query($db, "SELECT COUNT(*) AS total FROM tb_kelas");
$totalKelas = mysqli_fetch_assoc($totalKelasResult)['total'] ?? 0;
$totalPagesKelas = max(1, ceil($totalKelas / $limit));

$kelasQuery = mysqli_query($db, "SELECT * FROM tb_kelas ORDER BY id ASC LIMIT $limit OFFSET $offsetKelas");

$kelasList = [];
while ($r = mysqli_fetch_assoc($kelasQuery)) {
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

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $q = mysqli_query($db, "SELECT nama_kelas FROM tb_kelas WHERE id=$id");
    $r = mysqli_fetch_assoc($q);
    $kelas = $r ? $r['nama_kelas'] : null;

    if ($kelas) {
        $prefix = kelasToPrefix($kelas);
        mysqli_query($db, "DELETE FROM tb_kelas WHERE id=$id");

        $like = mysqli_real_escape_string($db, $prefix . '%');
        mysqli_query($db, "DELETE FROM tb_buat_token WHERE token LIKE '{$like}'");

        $message = "🗑️ Kelas '$kelas' dan token terkait berhasil dihapus.";
        header("Location: " . preg_replace('/(\?.*)?$/', '', $_SERVER['REQUEST_URI']));
        exit;
    }
}

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

if (isset($_POST['generate'])) {
    $kelas_id = (int)$_POST['kelas_id'];
    $q = mysqli_query($db, "SELECT nama_kelas FROM tb_kelas WHERE id = $kelas_id LIMIT 1");
    $r = mysqli_fetch_assoc($q);
    if ($r) {
        $kelas_nama = $r['nama_kelas'];
        $prefix = kelasToPrefix($kelas_nama);
        $classNum = $classNumberMap[$kelas_id] ?? 0;
        $token = generateTokenByPrefixAndNumber($prefix, $classNum, $db);
        $token_esc = mysqli_real_escape_string($db, $token);
        mysqli_query($db, "INSERT INTO tb_buat_token (token, kelas_id, created_by) VALUES ('$token_esc', $kelas_id, '$admin')");
        $message = "✅ Token dibuat untuk <b>$kelas_nama</b>: <b>$token</b>";
        header("Location: " . preg_replace('/(\?.*)?$/', '', $_SERVER['REQUEST_URI']));
        exit;
    } else {
        $message = "⚠️ Kelas tidak ditemukan.";
    }
}

// Ambil kelas yang sedang dipilih
$kelasTerpilih = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;

// Jika tidak ada kelas terpilih, ambil kelas pertama di daftar
if ($kelasTerpilih === 0 && !empty($kelasList)) {
    $kelasTerpilih = (int)$kelasList[0]['id'];
}

// Ambil data kelas terpilih
$kelasData = mysqli_query($db, "SELECT * FROM tb_kelas WHERE id = $kelasTerpilih");
$kelasRow = mysqli_fetch_assoc($kelasData);

// Jumlah siswa di kelas tersebut digunakan sebagai limit
$limitToken = isset($kelasRow['jumlah_siswa']) ? (int)$kelasRow['jumlah_siswa'] : 10;

// Pagination token khusus kelas terpilih
$pageToken = isset($_GET['page_token']) ? (int)$_GET['page_token'] : 1;
if ($pageToken < 1) $pageToken = 1;
$offsetToken = ($pageToken - 1) * $limitToken;

// Hitung total token per kelas
$totalTokenResult = mysqli_query($db, "SELECT COUNT(*) AS total FROM tb_buat_token WHERE kelas_id = $kelasTerpilih");
$totalToken = mysqli_fetch_assoc($totalTokenResult)['total'] ?? 0;
$totalPagesToken = max(1, ceil($totalToken / $limitToken));

// Ambil token berdasarkan kelas terpilih
$tokens = mysqli_query($db, "
    SELECT t.*, k.nama_kelas 
    FROM tb_buat_token t
    LEFT JOIN tb_kelas k ON t.kelas_id = k.id
    WHERE t.kelas_id = $kelasTerpilih
    ORDER BY t.created_at DESC
    LIMIT $limitToken OFFSET $offsetToken
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
            padding: 10px;
            text-align: center;
        }

        th {
            background: #2c3e50;
            color: #fff;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 42px;
            min-width: 120px;
            padding: 0 14px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: all .25s ease;
        }

        .btn-blue {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: #fff;
            border: none;
        }

        .btn-blue:hover {
            background: linear-gradient(135deg, #2980b9, #1f5f8a);
        }

        .btn-border-red {
            background: #fff;
            color: #e74c3c;
            border: 2px solid #e74c3c;
        }

        .btn-border-red:hover {
            background: #e74c3c;
            color: #fff;
        }

        .pagination {
            text-align: center;
            margin: 15px 0;
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
            <li><a href="../sidebar-menu/token.php" class="active">Kelas & Token Siswa</a></li>
            <li><a href="../sidebar-menu/kode-guru.php">Token Guru</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>Manajemen Token</h1>

        <?php if (!empty($message)): ?>
            <div style="text-align:center;margin-bottom:15px;color:#2c3e50;"><?= $message; ?></div>
        <?php endif; ?>

        <form method="POST" class="form-modern">
            <input type="text" name="kelas" placeholder="Masukkan nama kelas" required>
            <input type="number" name="jumlah_siswa" placeholder="Jumlah siswa" min="1" required>
            <button type="submit" name="add_class" class="btn btn-blue">Tambah</button>
            <style>
                .form-modern {
                    margin: 40px auto;
                    padding: 24px 28px;
                    background: #ffffff;
                    border-radius: 10px;
                    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
                    display: flex;
                    flex-direction: column;
                    gap: 16px;
                    font-family: "Poppins", sans-serif;
                    transition: 0.3s ease;
                }

                .form-modern:hover {
                    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
                }

                .form-modern input[type="text"],
                .form-modern input[type="number"] {
                    width: 100%;
                    padding: 12px 14px;
                    font-size: 15px;
                    border: 1.5px solid #ccc;
                    border-radius: 8px;
                    outline: none;
                    transition: all 0.2s ease;
                }

                .form-modern input[type="text"]:focus,
                .form-modern input[type="number"]:focus {
                    border-color: #3498db;
                    box-shadow: 0 0 4px rgba(52, 152, 219, 0.4);
                }

                .form-modern input::placeholder {
                    color: #999;
                    font-style: italic;
                }

                .btn.btn-blue {
                    background-color: #3498db;
                    color: #fff;
                    border: none;
                    border-radius: 8px;
                    padding: 12px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background-color 0.3s ease, transform 0.2s;
                }

                .btn.btn-blue:hover {
                    background-color: #2980b9;
                    transform: scale(1.03);
                }
            </style>
        </form>

        <h3>Daftar Kelas</h3>
        <table>
            <tr>
                <th>No</th>
                <th>Nama Kelas</th>
                <th>Jumlah Siswa</th>
                <th>Aksi</th>
            </tr>
            <?php if ($totalKelas > 0): $no = $offsetKelas + 1;
                foreach ($kelasList as $k): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($k['nama_kelas']); ?></td>
                        <td><?= htmlspecialchars($k['jumlah_siswa']); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="kelas_id" value="<?= $k['id']; ?>">
                                <button type="submit" name="generate" class="btn btn-blue">Buat Token</button>
                            </form>
                            <a href="?hapus=<?= $k['id']; ?>" class="btn btn-border-red"
                                onclick="return confirm('Yakin ingin menghapus kelas ini?')">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach;
            else: ?>
                <tr>
                    <td colspan="4">Belum ada kelas.</td>
                </tr>
            <?php endif; ?>
        </table>

        <div class="pagination">
            <?php for ($p = 1; $p <= $totalPagesKelas; $p++): ?>
                <a href="?page_kelas=<?= $p ?>&page_token=<?= $pageToken ?>" class="<?= $p == $pageKelas ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <form method="GET" class="kelas-filter-form">
            <label for="kelas_id"><b>Pilih Kelas:</b></label>
            <select name="kelas_id" id="kelas_id" onchange="this.form.submit()">
                <?php foreach ($kelasList as $k): ?>
                    <option value="<?= $k['id']; ?>" <?= $k['id'] == $kelasTerpilih ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($k['nama_kelas']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
                    <style>
            .kelas-filter-form {
                display: flex;
                align-items: center;
                justify-content: flex-start;
                gap: 12px;
                margin: 25px 0;
                padding: 14px 18px;
                border-radius: 8px;
                font-family: "Poppins", sans-serif;
                transition: box-shadow 0.3s ease;
            }

            .kelas-filter-form label {
                color: #2c3e50;
                font-size: 15px;
                font-weight: 600;
                margin-right: 6px;
            }

            .kelas-filter-form select {
                padding: 10px 14px;
                font-size: 15px;
                border: 1.5px solid #ccc;
                border-radius: 8px;
                outline: none;
                background: #f9f9f9;
                transition: all 0.2s ease;
                cursor: pointer;
            }

            .kelas-filter-form select:focus {
                border-color: #3498db;
                background: #fff;
                box-shadow: 0 0 5px rgba(52, 152, 219, 0.4);
            }

            .kelas-filter-form option {
                padding: 10px;
            }
        </style>
        </form>

        <h3>Daftar Token</h3>
        <table>
            <tr>
                <th>No</th>
                <th>Kelas</th>
                <th>Token</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
            <?php if ($totalToken > 0): $no = $offsetToken + 1;
                while ($row = mysqli_fetch_assoc($tokens)): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($row['nama_kelas'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($row['token']); ?></td>
                        <td><?= $row['status_token'] === 'sudah' ? '<span style="color:green">Sudah</span>' : '<span style="color:red">Belum Dipakai</span>'; ?></td>
                        <td><a href="?hapus_token=<?= $row['id']; ?>" class="btn btn-border-red" onclick="return confirm('Hapus token ini?')">Hapus</a></td>
                    </tr>
                <?php endwhile;
            else: ?>
                <tr>
                    <td colspan="5">Belum ada token.</td>
                </tr>
            <?php endif; ?>
        </table>
        <div class="pagination">
            <?php for ($p = 1; $p <= $totalPagesToken; $p++): ?>
                <a href="?page_token=<?= $p ?>&page_kelas=<?= $pageKelas ?>" class="<?= $p == $pageToken ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
</body>

</html>