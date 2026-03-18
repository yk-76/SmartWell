<?php
require_once 'auth_helper.php';
start_secure_session();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Desktop QR Login Demo</title>
</head>
<body>
    <h2>Scan this QR code with your phone to log in:</h2>
    <img id="qr-img" src="qr_login_session.php?t=<?php echo time(); ?>" width="220" height="220" alt="Scan QR">
    <div id="status"></div>
    <script>
    let interval = setInterval(function() {
        fetch('check_qr_status.php').then(r=>r.json()).then(data => {
            if(data && data.success) {
                clearInterval(interval);
                document.getElementById('status').innerHTML = "✅ Login successful! Reloading...";
                setTimeout(()=>{window.location='Service.php';},1200);
            }
        });
    }, 2000);
    </script>
</body>
</html>
