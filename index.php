<?php
session_start();
require 'db/db.php'; // Pastikan path ke file koneksi database sudah benar

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['kirim'])) {
    $token_pemilih      = trim($_POST['token_pemilih'] ?? '');
    $role               = trim($_POST['role'] ?? 'siswa');
    $kelas_pemilih      = trim($_POST['kelas'] ?? '');
    $kandidat_terpilih  = (int) ($_POST['kandidat_terpilih'] ?? 0);

    $errorMessage = "";
    $successMessage = "";
    $tokenUsedMessage = "";
    
    // ID yang akan dimasukkan ke tb_voter.token_id (Untuk Siswa)
    $voter_token_id = null; 
    // MODIFIKASI: ID yang akan dimasukkan ke tb_voter.kode_guru_id (Untuk Guru)
    $voter_kode_guru_id = null; 

    if ($token_pemilih === '' || $kandidat_terpilih <= 0) {
        $errorMessage = "Token dan kandidat wajib diisi!";
    } elseif (!in_array($role, ['siswa', 'guru'])) {
        $errorMessage = "Role tidak valid.";
    } elseif ($role === 'siswa' && $kelas_pemilih === '') {
        $errorMessage = "Untuk siswa, kelas wajib diisi.";
    } else {
        $token_db_id = null; // ID asli dari tb_buat_token/tb_kode_guru
        $nama_token = '';
        $token_table_name = '';
        $status_check = null;

        // --- 1. CEK VALIDITAS TOKEN / KODE BERDASARKAN ROLE ---
        if ($role === 'siswa') {
            // Cek di tabel tb_buat_token (untuk siswa)
            $token_check = mysqli_prepare($db, "
                SELECT t.id, t.kelas_id, t.status_token, k.nama_kelas 
                FROM tb_buat_token t
                LEFT JOIN tb_kelas k ON t.kelas_id = k.id
                WHERE t.token = ?
            ");
            mysqli_stmt_bind_param($token_check, "s", $token_pemilih);
            mysqli_stmt_execute($token_check);
            mysqli_stmt_bind_result($token_check, $token_db_id, $kelas_id_token, $status_token, $nama_kelas_token);
            mysqli_stmt_fetch($token_check);
            mysqli_stmt_close($token_check);

            if ($token_db_id) {
                $voter_token_id = $token_db_id; 
                $voter_kode_guru_id = null; // Pastikan ini NULL untuk siswa
                $nama_token = $nama_kelas_token;
                $token_table_name = 'tb_buat_token';
                $status_check = $status_token;

                // Token Siswa ditemukan → cek apakah kelas cocok
                if (strcasecmp($kelas_pemilih, $nama_kelas_token) !== 0) {
                    $errorMessage = "Token tidak cocok dengan kelas yang dipilih!";
                }
            }

        } elseif ($role === 'guru') {
            // Cek di tabel tb_kode_guru (untuk guru)
            $kode_guru_check = mysqli_prepare($db, "
                SELECT id, status_kode 
                FROM tb_kode_guru 
                WHERE kode = ?
            ");
            mysqli_stmt_bind_param($kode_guru_check, "s", $token_pemilih);
            mysqli_stmt_execute($kode_guru_check);
            mysqli_stmt_bind_result($kode_guru_check, $token_db_id_guru, $status_kode);
            mysqli_stmt_fetch($kode_guru_check);
            mysqli_stmt_close($kode_guru_check);
            
            if ($token_db_id_guru) {
                // MODIFIKASI: ID asli dari tb_kode_guru
                $token_db_id = $token_db_id_guru;
                // MODIFIKASI: ID yang akan dimasukkan ke tb_voter.kode_guru_id
                $voter_kode_guru_id = $token_db_id_guru; 
                $voter_token_id = null; // Pastikan ini NULL untuk guru
                $nama_token = 'Guru/Staf'; 
                $token_table_name = 'tb_kode_guru';
                $status_check = $status_kode;
            }
        }

        if (!$token_db_id) {
            $errorMessage = "Kode/Token tidak terdaftar.";
        } 
        
        // Cek status setelah validasi kode/token
        if (empty($errorMessage) && $token_db_id) {
            
            $is_already_used = false;

            if ($status_check === 'sudah') {
                $is_already_used = true;
            }

            if ($is_already_used) {
                $tokenUsedMessage = "Kode/Token sudah digunakan.";
            } else {
                // Token/Kode valid dan belum digunakan → proses voting
                mysqli_begin_transaction($db);
                try {
                    // Masukkan ke tb_voter
                    $kelas_voter = ($role === 'siswa') ? $kelas_pemilih : $nama_token;
                    
                    // MODIFIKASI: Query INSERT diperbarui untuk menyertakan kolom kode_guru_id
                    $sql_voter = "
                        INSERT INTO tb_voter 
                        (nama_voter, kelas, role, token_id, kode_guru_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ";
                    $voter = mysqli_prepare($db, $sql_voter);

                    // Menentukan tipe binding parameter
                    if ($role === 'siswa') {
                        // Siswa: token_id (int), kode_guru_id (NULL)
                        // Karena kita tidak bisa bind "NULL", kita harus menggunakan prepared statement yang berbeda
                        // ATAU menggunakan bind_param dengan 'i' dan menyetel kolom ke NULL secara manual
                        // Cara yang lebih aman dan terstruktur adalah:
                        $voter_token_id_val = $voter_token_id;
                        $sql_voter_siswa = "
                            INSERT INTO tb_voter 
                            (nama_voter, kelas, role, token_id, created_at) 
                            VALUES (?, ?, ?, ?, NOW())
                        ";
                        $voter_siswa = mysqli_prepare($db, $sql_voter_siswa);
                        mysqli_stmt_bind_param($voter_siswa, "sssi", $token_pemilih, $kelas_voter, $role, $voter_token_id_val);
                        mysqli_stmt_execute($voter_siswa);
                        $voter_id = mysqli_insert_id($db);
                        mysqli_stmt_close($voter_siswa);

                    } elseif ($role === 'guru') {
                        // Guru: token_id (NULL), kode_guru_id (int)
                        $voter_kode_guru_id_val = $voter_kode_guru_id;
                        $sql_voter_guru = "
                            INSERT INTO tb_voter 
                            (nama_voter, kelas, role, kode_guru_id, created_at) 
                            VALUES (?, ?, ?, ?, NOW())
                        ";
                        $voter_guru = mysqli_prepare($db, $sql_voter_guru);
                        mysqli_stmt_bind_param($voter_guru, "sssi", $token_pemilih, $kelas_voter, $role, $voter_kode_guru_id_val);
                        mysqli_stmt_execute($voter_guru);
                        $voter_id = mysqli_insert_id($db);
                        mysqli_stmt_close($voter_guru);
                    } else {
                        throw new Exception("Role tidak terdefinisi.");
                    }

                    // Masukkan ke tb_vote_log
                    $vote_log = mysqli_prepare($db, "INSERT INTO tb_vote_log (voter_id, nomor_kandidat, created_at) VALUES (?, ?, NOW())");
                    mysqli_stmt_bind_param($vote_log, "ii", $voter_id, $kandidat_terpilih);
                    mysqli_stmt_execute($vote_log);
                    mysqli_stmt_close($vote_log);

                    // Update hasil vote
                    $update = mysqli_prepare($db, "UPDATE tb_vote_result SET jumlah_vote = jumlah_vote + 1 WHERE nomor_kandidat = ?");
                    mysqli_stmt_bind_param($update, "i", $kandidat_terpilih);
                    mysqli_stmt_execute($update);
                    mysqli_stmt_close($update);

                    // Update status token/kode (Menggunakan ID asli: $token_db_id)
                    if ($token_table_name === 'tb_buat_token') {
                        // Update status token siswa
                        mysqli_query($db, "UPDATE tb_buat_token SET status_token = 'sudah' WHERE id = $token_db_id");
                    } elseif ($token_table_name === 'tb_kode_guru') {
                        // Update status kode guru
                        mysqli_query($db, "UPDATE tb_kode_guru SET status_kode = 'sudah' WHERE id = $token_db_id");
                    }

                    mysqli_commit($db);
                    $successMessage = "Vote berhasil! Terima kasih sudah memilih.";
                } catch (Exception $e) {
                    mysqli_rollback($db);
                    $errorMessage = "Terjadi kesalahan pada database: " . $e->getMessage();
                }
            }
        }
    }
}

// Data untuk tampilan (Tidak diubah)
$query = mysqli_query($db, "SELECT * FROM tb_kandidat ORDER BY nomor_kandidat ASC");
$query_kelas = mysqli_query($db, "SELECT * FROM tb_kelas ORDER BY nama_kelas ASC");
$kelas_list = [];
while ($k = mysqli_fetch_assoc($query_kelas)) {
    $kelas_list[] = $k;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Voting Kandidat OSIS</title>
    <link rel="icon" href="admin/assets/img/logo osis.png">
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        /* CSS DARI KODE ASLI ANDA */
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 20px;
        }

        .container {
            margin: auto;
        }

        .title h1 {
            text-align: center;
            margin: 0;
            padding: 0 20%;
        }

        .kandidat-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 22px;
            margin-right: 5%;
            margin-left: 5%;
        }

        .kandidat-card {
            background: #fff;
            border-radius: 10px;
            padding: 16px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            transition: .2s;
        }

        .kandidat-card.active {
            border: 3px solid #2ecc71;
            transform: scale(1.02);
        }

        .card-wrapper {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .card {
            margin: 5px;
            flex: 1;
            min-width: 48%;
            background: #fafafa;
            border-radius: 8px;
            text-align: center;
        }

        .card-content {
            display: flex;
        }

        .card img {
            width: 100%;
            height: 350px;
            aspect-ratio: 1/1;
            object-fit: cover;
            border-radius: 8px;
            display: block;
        }

        .btn-vote {
            margin-top: 12px;
            text-align: center;
        }

        .btn-vote button {
            background: #3498db;
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            height: 40px;
        }

        .btn-vote button:hover {
            background: #2980b9;
        }

        .form-user {
            background: #fff;
            padding: 16px;
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            margin: 0 5%;
        }

        .form-user form {
            display: flex;
            flex-direction: column;
        }

        .form-user label {
            margin-top: 10px;
        }

        .form-user input,
        .form-user select,
        .form-user textarea {
            margin-top: 5px;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        .form-user button {
            margin-top: 15px;
            background: green;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .form-user button:hover {
            background: #126a36ff;
        }

        .form-user select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-size: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 10px 35px 10px 12px;
            font-size: 14px;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-user select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        .form-user select:hover {
            border-color: #2980b9;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 999;
        }

        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            width: 500px;
            text-align: center;
            position: relative;
            animation: fadeIn .3s ease-in-out;
        }

        .modal-content .close {
            position: absolute;
            top: 10px;
            right: 15px;
            cursor: pointer;
            font-size: 20px;
        }

        .modal-content .icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .text-terimakasih {
            font-size: 20px;
        }

        .button-ok {
            background-color: green;
            width: 100%;
            height: 50px;
            border: none;
            border-radius: 8px;
            font-size: 20px;
            color: white;
        }

        #errorText {
            font-size: 20px;
            font-weight: 300;
        }

        .pilih-kelas {
            width: 50%;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="title">
            <div class="header-wrap">
                <h1>Selamat Datang di Forum Pemilihan Osis Skalsa</h1>
                <div class="btn-login">
                    <button class="user-login" name="login" onclick="window.open('admin/auth/login.php', '_blank')">hanya admin</button>
                    <style>
                        /* CSS login admin */
                        .btn-login button {
                            background: none;
                            border: none;
                            border: 2px solid #2ecc71;
                            height: 40px;
                            width: 120px;
                            border-radius: 7px;
                            font-size: 18px;
                            font-weight: 400;
                            cursor: pointer;
                        }

                        .btn-login button:hover {
                            transition: .9s;
                        }

                        .header-wrap {
                            display: flex;
                        }
                    </style>
                </div>
            </div>
            <div class="logo">
                <img src="pages/assets/img/logo osis.png">
                <img src="pages/assets/img/logo sekolah.png">
                <style>
                    /* CSS logo */
                    .logo {
                        width: 100%;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                    }

                    .logo img {
                        height: 310px;
                    }
                </style>
            </div>
        </div>
        <div class="kandidat-list">
            <?php while ($row = mysqli_fetch_assoc($query)) : ?>
                <div class="kandidat-card" data-id="<?= $row['nomor_kandidat']; ?>">
                    <h3 style="text-align: center;">Pasangan Nomor <?= $row['nomor_kandidat']; ?></h3>
                    <div class="card-wrapper">
                        <div class="card-content">
                            <div class="card">
                                <img src="admin/uploads/<?= $row['foto_ketua'] ?>" alt="Ketua">
                                <h3 style="margin: 0; margin-top:15px;"><?= $row['nama_ketua']; ?></h3>
                                <small>Calon Ketua OSIS</small>
                            </div>
                            <div class="card">
                                <img src="admin/uploads/<?= $row['foto_wakil'] ?>" alt="Wakil">
                                <h3 style="margin: 0; margin-top:15px;"><?= $row['nama_wakil']; ?></h3>
                                <small>Calon Wakil OSIS</small>
                            </div>
                        </div>
                    </div>
                    <div class="btn-vote"><button type="button">Pilih Kandidat <?= $row['nomor_kandidat']; ?></button></div>
                </div>
            <?php endwhile; ?>
        </div>

        <div class="form-user">
            <form action="" method="post" id="formVote" novalidate>
                <label for="pemilih" style="font-weight: bold;">Token Pemilih</label>
                <input type="text" id="pemilih" name="token_pemilih" placeholder="Masukkan Token/Kode" autocomplete="off">
                
                <label for="role" style="font-weight: bold;">Role</label>
                <select id="role" name="role">
                    <option value="siswa" selected>Siswa</option>
                    <option value="guru" <?= (isset($_POST['role']) && $_POST['role'] === 'guru') ? 'selected' : '' ?>>Guru</option>
                </select>
                
                <div id="kelasWrap" style="margin: 10px 0;">
                    <label for="kelas" style="font-weight: bold;">Kelas Pemilih</label>
                    <br>
                    <select id="kelas" name="kelas" class="pilih-kelas">
                        <option value="">Pilih Kelas</option>
                        <?php foreach ($kelas_list as $kelas): ?>
                            <option value="<?= htmlspecialchars($kelas['nama_kelas']) ?>" 
                                <?= (isset($_POST['kelas']) && $_POST['kelas'] === $kelas['nama_kelas']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kelas['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <input type="hidden" name="kandidat_terpilih" id="kandidat_terpilih">
                <button type="submit" name="kirim">Kirim Vote</button>
            </form>
        </div>
    </div>

    <div id="modalSuccess" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Vote Berhasil!</h2>
            <p class="text-terimakasih">Terima kasih sudah memilih. Semoga pilihanmu menang.</p>
            <button id="okBtn" class="button-ok" style="cursor: pointer;">OK</button>
        </div>
    </div>

    <div id="modalError" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Terjadi Kesalahan</h2>
            <p id="errorText"></p>
            <button id="errorBtn" class="button-ok" style="cursor: pointer;">OK</button>
        </div>
    </div>

    <div id="modalTokenUsed" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Token Sudah Digunakan</h2>
            <p>Kode/Token ini sudah dipakai untuk memilih.</p>
            <button id="tokenUsedBtn" class="button-ok" style="cursor: pointer;">OK</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const kandidatCards = document.querySelectorAll('.kandidat-card');
            const inputKandidat = document.getElementById('kandidat_terpilih');
            const roleSelect = document.getElementById('role');
            const kelasWrap = document.getElementById('kelasWrap');

            // Logika untuk menampilkan/menyembunyikan pilihan kelas
            roleSelect.addEventListener('change', () => {
                // Jika role adalah 'siswa', tampilkan pilihan kelas
                kelasWrap.style.display = (roleSelect.value === 'siswa') ? 'block' : 'none';
                
                // Opsional: Atur ulang nilai kelas ketika role berubah
                if (roleSelect.value === 'guru') {
                    document.getElementById('kelas').value = ''; 
                }
            });
            
            // Set initial state
            kelasWrap.style.display = (roleSelect.value === 'siswa') ? 'block' : 'none';


            kandidatCards.forEach(card => {
                const btn = card.querySelector('button');
                btn.addEventListener('click', () => {
                    kandidatCards.forEach(c => {
                        c.classList.remove('active');
                        c.querySelector('button').textContent = `Pilih Kandidat ${c.getAttribute('data-id')}`;
                    });
                    card.classList.add('active');
                    btn.textContent = "Dipilih";
                    inputKandidat.value = card.getAttribute('data-id');
                });
            });

            const modalSuccess = document.getElementById('modalSuccess');
            const modalError = document.getElementById('modalError');
            const modalTokenUsed = document.getElementById('modalTokenUsed');
            const errorText = document.getElementById('errorText');

            const closeBtns = document.querySelectorAll('.modal .close, #okBtn, #errorBtn, #tokenUsedBtn');
            closeBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    modalSuccess.style.display = 'none';
                    modalError.style.display = 'none';
                    modalTokenUsed.style.display = 'none';
                    window.location.href = 'index.php';
                });
            });

            window.onclick = (e) => {
                if (e.target === modalSuccess || e.target === modalError || e.target === modalTokenUsed) {
                    modalSuccess.style.display = 'none';
                    modalError.style.display = 'none';
                    modalTokenUsed.style.display = 'none';
                    window.location.href = 'index.php';
                }
            };

            <?php if (!empty($successMessage)) : ?>
                modalSuccess.style.display = 'flex';
            <?php elseif (!empty($errorMessage)) : ?>
                errorText.innerText = "<?= $errorMessage ?>";
                modalError.style.display = 'flex';
            <?php elseif (!empty($tokenUsedMessage)) : ?>
                modalTokenUsed.style.display = 'flex';
            <?php endif; ?>
        });
    </script>
</body>

</html>
<?php mysqli_close($db); ?>