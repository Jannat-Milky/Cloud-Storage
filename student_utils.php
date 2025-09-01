<?php
// student_utils.php
// Helpers used by student join flow.

function getStudentUniversityId(mysqli $conn, int $user_id): ?string
{
    // Try users.university_id first
    $stmt = $conn->prepare("SELECT email, university_id FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) return null;

    $email = trim((string)$res['email']);
    $uid   = trim((string)$res['university_id']);

    if ($uid !== '') return $uid;

    // Derive from email local part
    $local = '';
    if ($email !== '' && strpos($email, '@') !== false) {
        $local = substr($email, 0, strpos($email, '@'));
        $local = trim($local);
    }

    if ($local === '') return null;

    // Persist to users.university_id for future queries (optional but handy)
    $upd = $conn->prepare("UPDATE users SET university_id = ? WHERE id = ?");
    $upd->bind_param('si', $local, $user_id);
    $upd->execute();
    $upd->close();

    return $local;
}
