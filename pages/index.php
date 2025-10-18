<?php
session_start();
require '../db/db.php';

// proses vote
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['kirim'])) {
    $token_pemilih      = trim($_POST['token_pemilih'] ?? '');
    $role              = trim($_POST['role'] ?? 'siswa');
    $kelas_pemilih     = trim($_POST['kelas'] ?? '');
    $kandidat_terpilih = (int) ($_POST['kandidat_terpilih'] ?? 0);

    $errorMessage = "";
    $successMessage = "";

    if ($token_pemilih === '' || $kandidat_terpilih <= 0) {
        $errorMessage = "Input nama dan kandidat wajib diisi!";
    } elseif (!in_array($role, ['siswa', 'guru'])) {
        $errorMessage = "Role tidak valid.";
    } elseif ($role === 'siswa' && $kelas_pemilih === '') {
        $errorMessage = "Untuk siswa, kelas wajib diisi.";
    } else {
        $kelas_db = ($role === 'siswa') ? $kelas_pemilih : '';
        mysqli_begin_transaction($db);
        try {
            $voter = mysqli_prepare($db, "INSERT INTO tb_voter (nama_voter, kelas, role, created_at) VALUES (?, ?, ?, NOW())");
            mysqli_stmt_bind_param($voter, "sss", $token_pemilih, $kelas_db, $role);
            mysqli_stmt_execute($voter);
            $voter_id = mysqli_insert_id($db);
            mysqli_stmt_close($voter);

            $vote_log = mysqli_prepare($db, "INSERT INTO tb_vote_log (voter_id, nomor_kandidat, created_at) VALUES (?, ?, NOW())");
            mysqli_stmt_bind_param($vote_log, "ii", $voter_id, $kandidat_terpilih);
            mysqli_stmt_execute($vote_log);
            mysqli_stmt_close($vote_log);

            $update = mysqli_prepare($db, "UPDATE tb_vote_result SET jumlah_vote = jumlah_vote + 1 WHERE nomor_kandidat = ?");
            mysqli_stmt_bind_param($update, "i", $kandidat_terpilih);
            mysqli_stmt_execute($update);
            mysqli_stmt_close($update);

            mysqli_commit($db);

            $successMessage = "Vote berhasil! Terima kasih sudah memilih.";
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($db);
            $errorMessage = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
// ambil kandidat
$query = mysqli_query($db, "SELECT * FROM tb_kandidat ORDER BY nomor_kandidat ASC");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Voting Kandidat OSIS</title>
    <link rel="icon" href="assets/img/logo osis.png">
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
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
            margin-bottom: 20px;
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

<body style="background-color: grey;">
    <div class="container">
        <div class="title">
            <div class="logo">
                <img src="assets/img/logo osis.png">
                <img src="assets/img/logo sekolah.png">
                <style>
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
                                <img src="../admin/uploads/<?= $row['foto_ketua'] ?>" alt="Ketua">
                                <h3 style="margin: 0; margin-top:15px;"><?= $row['nama_ketua']; ?></h3>
                                <small>Calon Ketua OSIS</small>
                            </div>
                            <div class="card">
                                <img src="../admin/uploads/<?= $row['foto_wakil'] ?>" alt="Wakil">
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
            <div style="display: flex; align-items:center; gap: 10px;">
                <h3>Form Pemilih</h3>
                <p style="color: red; text-align:center; margin: 0;">( Wajib Diisi )</p>
            </div>
            <form action="" method="post" id="formVote" novalidate>
                <label for="pemilih" style="font-weight: bold;">Token Pemilih</label>
                <input type="text" id="pemilih" name="token_pemilih" placeholder="Masukkan Token" autocomplete="off">
                <label for="role" style="font-weight: bold;">Role</label>
                <select id="role" name="role">
                    <option value="siswa" selected>Siswa</option>
                    <option value="guru">Guru</option>
                </select>
                <div id="kelasWrap" style="margin: 10px 0;">
                    <label for="kelas" style="font-weight: bold;">Kelas Pemilih</label>
                    <br>
                    <select id="kelas" name="kelas" class="pilih-kelas">
                        <option value="">Pilih Kelas</option>
                        <option value="X-1">X-1</option>
                        <option value="X-2">X-2</option>
                        <option value="XI-1">XI-1 TKJ</option>
                        <option value="XI-2">XI-2 TKJ</option>
                        <option value="XI-TJA">XI TJA</option>
                        <option value="XII">XII TKJ</option>
                    </select>
                </div>
                <input type="hidden" name="kandidat_terpilih" id="kandidat_terpilih">
                <button type="submit" name="kirim">Kirim Vote</button>
            </form>
        </div>
    </div>

    <!-- Modal Success -->
    <div id="modalSuccess" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Vote Berhasil!</h2>
            <p class="text-terimakasih">Terima kasih sudah memilih. Semoga pilihanmu bisa menang yakk.</p>
            <button id="okBtn" class="button-ok" style="cursor: pointer;">OK</button>
        </div>
    </div>

    <!-- Modal Error -->
    <div id="modalError" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="icon"></div>
            <h2>Terjadi Kesalahan</h2>
            <p id="errorText"></p>
            <button id="errorBtn" class="button-ok" style="cursor: pointer;">OK</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const kandidatCards = document.querySelectorAll('.kandidat-card');
            const inputKandidat = document.getElementById('kandidat_terpilih');
            const roleSelect = document.getElementById('role');
            const kelasWrap = document.getElementById('kelasWrap');

            roleSelect.addEventListener('change', () => {
                kelasWrap.style.display = (roleSelect.value === 'siswa') ? 'block' : 'none';
            });
            kelasWrap.style.display = (roleSelect.value === 'siswa') ? 'block' : 'none';

            kandidatCards.forEach(card => {
                const btn = card.querySelector('button');
                btn.addEventListener('click', () => {
                    if (card.classList.contains('active')) {
                        card.classList.remove('active');
                        btn.textContent = "Pilih Kandidat";
                        inputKandidat.value = "";
                    } else {
                        kandidatCards.forEach(c => {
                            c.classList.remove('active');
                            c.querySelector('button').textContent = "Pilih Kandidat";
                        });
                        card.classList.add('active');
                        btn.textContent = "Dipilih";
                        inputKandidat.value = card.getAttribute('data-id');
                    }
                });
            });

            const modalSuccess = document.getElementById('modalSuccess');
            const modalError = document.getElementById('modalError');
            const errorText = document.getElementById('errorText');

            const closeBtns = document.querySelectorAll('.modal .close, #okBtn, #errorBtn');
            closeBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    modalSuccess.style.display = 'none';
                    modalError.style.display = 'none';
                    window.location.href = 'index.php';
                });
            });

            window.onclick = (e) => {
                if (e.target === modalSuccess || e.target === modalError) {
                    modalSuccess.style.display = 'none';
                    modalError.style.display = 'none';
                    window.location.href = 'index.php';
                }
            };

            <?php if (!empty($successMessage)) : ?>
                modalSuccess.style.display = 'flex';
            <?php elseif (!empty($errorMessage)) : ?>
                errorText.innerText = "<?= $errorMessage ?>";
                modalError.style.display = 'flex';
            <?php endif; ?>
        });
    </script>
</body>

</html>
<?php mysqli_close($db); ?>