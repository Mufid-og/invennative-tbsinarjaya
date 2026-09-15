<?php
// actions/user.php
$action = $_POST['action'] ?? '';

// ===== SIMPAN (CREATE / UPDATE) =====
if ($action === 'simpan') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $errors = [];

    if ($nama === '') {
        $errors['nama'] = 'Nama wajib diisi.';
    }
    if ($username === '') {
        $errors['username'] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
        $errors['username'] = 'Username hanya boleh huruf, angka, garis bawah, dan strip.';
    }

    if ($id === 0) {
        if (strlen($password) < 6) {
            $errors['password'] = 'Password minimal 6 karakter.';
        }
    } elseif ($password !== '' && strlen($password) < 6) {
        $errors['password'] = 'Password minimal 6 karakter.';
    }

    if (empty($errors)) {
        // Username unik (kecuali dirinya sendiri saat edit)
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? AND deleted_at IS NULL AND id != ?');
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            $errors['username'] = 'Username sudah digunakan.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['user_errors'] = $errors;
        $_SESSION['old_nama'] = $nama;
        $_SESSION['old_username'] = $username;
        redirect('/manajemen-user?modal=1' . ($id ? '&edit_id=' . $id : ''));
    }

    $db = db();

    if ($id === 0) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $ts = now();
        $stmt = $db->prepare('INSERT INTO users (username, nama, password, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$username, $nama, $hashed, $ts, $ts]);
        $newId = (int)$db->lastInsertId();

        log_aktivitas('CREATE', 'user', $newId, "User \"{$username}\" ditambahkan", [
            'sesudah' => [
                'nama' => $nama,
                'username' => $username,
            ],
        ]);

        set_flash('success', "User \"{$username}\" berhasil ditambahkan.");
    } else {
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            set_flash('error', 'User tidak ditemukan.');
            redirect('/manajemen-user');
        }

        $before = [
            'nama' => $user['nama'],
            'username' => $user['username'],
        ];

        if ($password !== '') {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE users SET username = ?, nama = ?, password = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$username, $nama, $hashed, now(), $id]);
        } else {
            $stmt = $db->prepare('UPDATE users SET username = ?, nama = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$username, $nama, now(), $id]);
        }

        log_aktivitas('UPDATE', 'user', $id, "User \"{$username}\" diperbarui", [
            'sebelum' => $before,
            'sesudah' => [
                'nama' => $nama,
                'username' => $username,
            ],
        ]);

        set_flash('success', "User \"{$username}\" berhasil diperbarui.");
    }

    redirect('/manajemen-user');
}

// ===== HAPUS =====
if ($action === 'hapus') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id === auth_id()) {
        set_flash('error', 'Tidak dapat menghapus akun yang sedang Anda gunakan.');
        redirect('/manajemen-user');
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        set_flash('error', 'User tidak ditemukan.');
        redirect('/manajemen-user');
    }

    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);

    log_aktivitas('DELETE', 'user', $id, "User \"{$user['username']}\" dihapus", [
        'sebelum' => [
            'nama' => $user['nama'],
            'username' => $user['username'],
        ],
    ]);

    set_flash('success', "User \"{$user['username']}\" berhasil dihapus.");
    redirect('/manajemen-user');
}

set_flash('error', 'Aksi tidak diketahui.');
redirect('/manajemen-user');