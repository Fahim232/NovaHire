<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id']) && !isset($_SESSION['company_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$room_name = isset($_GET['room']) ? htmlspecialchars($_GET['room']) : '';
if (empty($room_name)) {
    die("Invalid meeting link.");
}

$display_name = isset($_SESSION['username']) ? $_SESSION['username'] : (isset($_SESSION['company_name']) ? $_SESSION['company_name'] : 'Guest');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Interview - <?php echo $room_name; ?></title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #1a1a1a;
            font-family: 'Inter', sans-serif;
        }
        #meet {
            width: 100%;
            height: 100%;
        }
        .header {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
            box-sizing: border-box;
        }
        .header h3 {
            margin: 0;
            font-size: 16px;
        }
        .btn-leave {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
        }
        .btn-leave:hover {
            background-color: #c82333;
        }
    </style>
    <!-- Include Jitsi Meet External API -->
    <script src="https://meet.jit.si/external_api.js"></script>
</head>
<body>

    <div class="header">
        <h3>Interview Session: <?php echo $room_name; ?></h3>
        <a href="javascript:history.back()" class="btn-leave">Leave Interview</a>
    </div>

    <div id="meet"></div>

    <script>
        window.onload = () => {
            const domain = "meet.jit.si";
            const options = {
                roomName: "<?php echo $room_name; ?>",
                width: '100%',
                height: '100%',
                parentNode: document.querySelector('#meet'),
                userInfo: {
                    displayName: '<?php echo htmlspecialchars($display_name); ?>'
                },
                configOverwrite: { 
                    startWithAudioMuted: false,
                    startWithVideoMuted: false
                },
                interfaceConfigOverwrite: {
                    DISABLE_DOMINANT_SPEAKER_INDICATOR: true
                }
            };
            const api = new JitsiMeetExternalAPI(domain, options);
        }
    </script>
</body>
</html>
