<?php
require_once '../config/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$user = getCurrentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'poll':
        // Evaluasi pengingat berkala secara pasif (tanpa memperpanjang timeout sesi)
        evaluateLazyReminders($user['id']);

        $sinceId = (int)($_GET['since_id'] ?? $_POST['since_id'] ?? 0);

        // 1. Ambil ID maksimum notifikasi milik user
        $stmtMax = $conn->prepare("SELECT COALESCE(MAX(id), 0) AS max_id FROM notifikasi WHERE user_id = ?");
        $stmtMax->bind_param("i", $user['id']);
        $stmtMax->execute();
        $maxId = (int)($stmtMax->get_result()->fetch_assoc()['max_id'] ?? 0);

        // 2. Ambil jumlah notifikasi belum dibaca
        $stmtUnread = $conn->prepare("SELECT COUNT(*) AS n FROM notifikasi WHERE user_id = ? AND status_baca = 'belum'");
        $stmtUnread->bind_param("i", $user['id']);
        $stmtUnread->execute();
        $unread = (int)($stmtUnread->get_result()->fetch_assoc()['n'] ?? 0);

        // 3. Ambil item baru jika sinceId > 0 dan maxId > sinceId
        $newItems = [];
        if ($sinceId > 0 && $maxId > $sinceId) {
            $stmtNew = $conn->prepare("
                SELECT id, modul_asal, pesan, waktu, status_baca, kategori, prioritas, link_page, link_tab 
                FROM notifikasi 
                WHERE user_id = ? AND id > ? 
                ORDER BY id ASC 
                LIMIT 50
            ");
            $stmtNew->bind_param("ii", $user['id'], $sinceId);
            $stmtNew->execute();
            $newItems = $stmtNew->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        // 4. Preferensi notifikasi pengguna
        $stmtPref = $conn->prepare("SELECT sound_enabled, kategori_persetujuan, kategori_tugas, kategori_pengingat, kategori_info FROM notif_pref WHERE user_id = ? LIMIT 1");
        $stmtPref->bind_param("i", $user['id']);
        $stmtPref->execute();
        $prefRow = $stmtPref->get_result()->fetch_assoc();
        $pref = $prefRow ? [
            'sound_enabled' => (int)$prefRow['sound_enabled'],
            'kategori_persetujuan' => (int)$prefRow['kategori_persetujuan'],
            'kategori_tugas' => (int)$prefRow['kategori_tugas'],
            'kategori_pengingat' => (int)$prefRow['kategori_pengingat'],
            'kategori_info' => (int)$prefRow['kategori_info'],
        ] : [
            'sound_enabled' => 1,
            'kategori_persetujuan' => 1,
            'kategori_tugas' => 1,
            'kategori_pengingat' => 1,
            'kategori_info' => 1,
        ];

        echo json_encode([
            'ok' => true,
            'unread' => $unread,
            'max_id' => $maxId,
            'new_items' => $newItems,
            'pref' => $pref
        ]);
        exit();

    case 'list':
        evaluateLazyReminders($user['id']);
        $stmt = $conn->prepare("
            SELECT id, user_id, modul_asal, pesan, status_baca, waktu, kategori, prioritas, link_page, link_tab 
            FROM notifikasi 
            WHERE user_id = ? 
            ORDER BY id DESC 
            LIMIT 25
        ");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $unreadStmt = $conn->prepare("SELECT COUNT(*) AS n FROM notifikasi WHERE user_id = ? AND status_baca = 'belum'");
        $unreadStmt->bind_param("i", $user['id']);
        $unreadStmt->execute();
        $unread = (int)($unreadStmt->get_result()->fetch_assoc()['n'] ?? 0);

        echo json_encode(['ok' => true, 'data' => $data, 'unread' => $unread]);
        exit();

    case 'mark_read':
        $notifId = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($notifId > 0) {
            $stmt = $conn->prepare("UPDATE notifikasi SET status_baca = 'sudah' WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notifId, $user['id']);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("UPDATE notifikasi SET status_baca = 'sudah' WHERE user_id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
        }
        echo json_encode(['ok' => true]);
        exit();

    case 'get_pref':
        $stmt = $conn->prepare("SELECT sound_enabled, kategori_persetujuan, kategori_tugas, kategori_pengingat, kategori_info FROM notif_pref WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $prefRow = $stmt->get_result()->fetch_assoc();
        $pref = $prefRow ? [
            'sound_enabled' => (int)$prefRow['sound_enabled'],
            'kategori_persetujuan' => (int)$prefRow['kategori_persetujuan'],
            'kategori_tugas' => (int)$prefRow['kategori_tugas'],
            'kategori_pengingat' => (int)$prefRow['kategori_pengingat'],
            'kategori_info' => (int)$prefRow['kategori_info'],
        ] : [
            'sound_enabled' => 1,
            'kategori_persetujuan' => 1,
            'kategori_tugas' => 1,
            'kategori_pengingat' => 1,
            'kategori_info' => 1,
        ];
        echo json_encode(['ok' => true, 'data' => $pref]);
        exit();

    case 'save_pref':
        $sound = isset($_POST['sound_enabled']) ? ((int)$_POST['sound_enabled'] ? 1 : 0) : 1;
        $persetujuan = isset($_POST['kategori_persetujuan']) ? ((int)$_POST['kategori_persetujuan'] ? 1 : 0) : 1;
        $tugas = isset($_POST['kategori_tugas']) ? ((int)$_POST['kategori_tugas'] ? 1 : 0) : 1;
        $pengingat = isset($_POST['kategori_pengingat']) ? ((int)$_POST['kategori_pengingat'] ? 1 : 0) : 1;
        $info = isset($_POST['kategori_info']) ? ((int)$_POST['kategori_info'] ? 1 : 0) : 1;

        $stmt = $conn->prepare("
            INSERT INTO notif_pref (user_id, sound_enabled, kategori_persetujuan, kategori_tugas, kategori_pengingat, kategori_info) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                sound_enabled = VALUES(sound_enabled),
                kategori_persetujuan = VALUES(kategori_persetujuan),
                kategori_tugas = VALUES(kategori_tugas),
                kategori_pengingat = VALUES(kategori_pengingat),
                kategori_info = VALUES(kategori_info)
        ");
        $stmt->bind_param("iiiiii", $user['id'], $sound, $persetujuan, $tugas, $pengingat, $info);
        $stmt->execute();

        echo json_encode(['ok' => true]);
        exit();

    default:
        echo json_encode(['ok' => false, 'msg' => 'Aksi tidak dikenal.']);
        exit();
}
