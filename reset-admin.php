<?php
require_once __DIR__ . '/config/database.php';

// Passwords cusub la samaynayo
$passwords = [
    'admin@queuepro.so' => 'Admin@123',
    'staff@queuepro.so' => 'Staff@123',
];

foreach ($passwords as $email => $pass) {
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = db()->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->execute([$hash, $email]);
    echo "✅ Updated: <b>$email</b> → password: <b>$pass</b><br>";
}

echo "<br><a href='http://localhost/queuepro/login.php'>→ Go to Login</a>";
echo "<br><br><b style='color:red'>⚠️ DELETE this file after use!</b>";
?>