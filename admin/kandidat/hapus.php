<?php
require '../../db/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $query = mysqli_query($db, "DELETE FROM tb_kandidat WHERE id = '$id'");

    if ($query) {
        echo "<script>alert('Kandidat berhasil dihapus!'); window.location.href='daftar.php';</script>";
    } else {
        echo "Gagal menghapus data!";
    }
}
