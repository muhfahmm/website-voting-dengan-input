<?php
require '../../db/db.php';

// Cek apakah tombol submit ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor = $_POST['nomor_kandidat'];
    $nama_ketua = $_POST['nama_ketua'];
    $kelas_ketua = $_POST['kelas_ketua'];
    $nama_wakil = $_POST['nama_wakil'];
    $kelas_wakil = $_POST['kelas_wakil'];

    // Upload foto ketua
    $foto_ketua = $_FILES['foto_ketua']['name'];
    $tmp_ketua = $_FILES['foto_ketua']['tmp_name'];
    $path_ketua = "../uploads/" . time() . "_ketua_" . basename($foto_ketua);

    // Upload foto wakil
    $foto_wakil = $_FILES['foto_wakil']['name'];
    $tmp_wakil = $_FILES['foto_wakil']['tmp_name'];
    $path_wakil = "../uploads/" . time() . "_wakil_" . basename($foto_wakil);

    // Pindahkan file
    if (move_uploaded_file($tmp_ketua, $path_ketua) && move_uploaded_file($tmp_wakil, $path_wakil)) {
        // Simpan ke database
        $sql = "INSERT INTO tb_kandidat (nomor_kandidat, nama_ketua, kelas_ketua, foto_ketua, nama_wakil, kelas_wakil, foto_wakil)
                VALUES ('$nomor', '$nama_ketua', '$kelas_ketua', '$path_ketua', '$nama_wakil', '$kelas_wakil', '$path_wakil')";

        if (mysqli_query($db, $sql)) {
            echo "<script>alert('Kandidat berhasil ditambahkan!'); window.location.href='../index.php';</script>";
        } else {
            echo "Error: " . mysqli_error($db);
        }
    } else {
        echo "Gagal upload foto!";
    }
}
