<?php
/**
 * JTDIS ASSET - PASSWORD HASH GENERATOR
 * Gunakan skrip ini untuk menghasilkan bcrypt hash untuk password
 */

header('Content-Type: text/html; charset=utf-8');

$hash = '';
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator - JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 500px; padding-top: 50px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white p-3">
                <h5 class="mb-0">🔐 Penjanaan Hash Kata Laluan</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Kata Laluan</label>
                        <input type="text" name="password" class="form-control form-control-lg" 
                               value="<?php echo htmlspecialchars($password); ?>" 
                               placeholder="Contoh: 12345678">
                        <small class="text-muted">Masukkan kata laluan yang ingin di-hash</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Janakan Hash</button>
                </form>
                
                <?php if (!empty($hash)): ?>
                    <hr class="my-4">
                    <div class="bg-light p-3 rounded">
                        <label class="form-label">Hash Bcrypt:</label>
                        <input type="text" class="form-control font-monospace" value="<?php echo $hash; ?>" readonly>
                        <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyHash()">Salin Hash</button>
                    </div>
                    
                    <div class="alert alert-info mt-3 small">
                        <strong>Untuk INSERT ke database:</strong><br>
                        <code>
                            INSERT INTO pengguna (..., kata_laluan_hash, ...) VALUES (..., '<?php echo addslashes($hash); ?>', ...);
                        </code>
                    </div>
                    
                    <div class="alert alert-success small">
                        <strong>✓ Sahkan:</strong> Gunakan password <code><?php echo htmlspecialchars($password); ?></code> untuk log masuk dengan hash ini
                    </div>
                <?php endif; ?>
                
                <hr class="my-3">
                <p class="text-muted small">
                    <strong>Password Demo:</strong><br>
                    Emel: admin@jtdis.gov.my<br>
                    Kata Laluan: 12345678
                </p>
            </div>
        </div>
    </div>
    
    <script>
        function copyHash() {
            const hashInput = document.querySelector('input[type="text"].font-monospace');
            hashInput.select();
            document.execCommand('copy');
            alert('Hash disalin ke clipboard!');
        }
    </script>
</body>
</html>
