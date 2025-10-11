<?php
session_start();
require '../../db/db.php';

if (isset($_POST['edit'])) {
    $id = $_POST['id'];
    $nama_ketua = $_POST['nama_ketua'];
    $nama_wakil = $_POST['nama_wakil'];

    // ambil data lama untuk hapus foto kalau diganti
    $queryOld = mysqli_query($db, "SELECT * FROM tb_kandidat WHERE id='$id'");
    $old = mysqli_fetch_assoc($queryOld);

    $foto_ketua = $old['foto_ketua'];
    $foto_wakil = $old['foto_wakil'];

    // jika upload foto ketua baru
    if (!empty($_FILES['foto_ketua']['name'])) {
        $fileName = time() . "_ketua_" . $_FILES['foto_ketua']['name'];
        $target = "../uploads/" . $fileName;
        move_uploaded_file($_FILES['foto_ketua']['tmp_name'], $target);
        // hapus foto lama
        if (file_exists("../uploads/" . $foto_ketua)) {
            unlink("../uploads/" . $foto_ketua);
        }
        $foto_ketua = $fileName;
    }

    // jika upload foto wakil baru
    if (!empty($_FILES['foto_wakil']['name'])) {
        $fileName2 = time() . "_wakil_" . $_FILES['foto_wakil']['name'];
        $target2 = "../uploads/" . $fileName2;
        move_uploaded_file($_FILES['foto_wakil']['tmp_name'], $target2);
        // hapus foto lama
        if (file_exists("../uploads/" . $foto_wakil)) {
            unlink("../uploads/" . $foto_wakil);
        }
        $foto_wakil = $fileName2;
    }

    // update ke database
    $update = mysqli_query($db, "UPDATE tb_kandidat SET 
        nama_ketua='$nama_ketua',
        nama_wakil='$nama_wakil',
        foto_ketua='$foto_ketua',
        foto_wakil='$foto_wakil'
        WHERE id='$id'");

    if ($update) {
        header("Location: ../pages/../kandidat/daftar.php");
        exit;
    } else {
        echo "Gagal update kandidat: " . mysqli_error($db);
    }
}
