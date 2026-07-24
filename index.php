<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['department'] == 'lmat') {
        header("Location: lmat/index.php");
    } else {
        header("Location: nibret/index.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- rest of the HTML remains the same as before -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Church Finance Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #DAA520 0%, #FFD700 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .header {
            background: linear-gradient(135deg, #B8860B 0%, #FFD700 100%);
            padding: 40px;
            text-align: center;
            color: white;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            font-weight: bold;
            color: #DAA520;
        }
        
        .header h1 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .login-form {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #DAA520;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #B8860B 0%, #FFD700 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #c33;
        }
        
        .demo-users {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .demo-users h4 {
            text-align: center;
            margin-bottom: 15px;
            color: #333;
        }
        
        .demo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .demo-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .demo-card:hover {
            border-color: #DAA520;
            background: #fff;
            transform: translateY(-2px);
        }
        
        .demo-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .demo-role {
            font-size: 11px;
            color: #DAA520;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .demo-dept {
            font-size: 10px;
            color: #666;
        }
        
        .demo-password {
            font-size: 10px;
            color: #999;
            margin-top: 5px;
        }
        
        .btn-demo {
            width: 100%;
            padding: 8px;
            background: #e9ecef;
            border: none;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            margin-top: 8px;
            color: #333;
        }
        
        .btn-demo:hover {
            background: #DAA520;
            color: white;
        }
        
        @media (max-width: 480px) {
            .header {
                padding: 30px;
            }
            .login-form {
                padding: 30px;
            }
            .demo-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">⛪</div>
            <h1>የቤተክርስቲያን ፋይናንስ አስተዳደር</h1>
            <p>Church Finance Management System</p>
        </div>
        <div class="login-form">
            <?php if (isset($_SESSION['login_error'])): ?>
                <div class="error"><?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?></div>
            <?php endif; ?>
            
            <form action="login.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label>ሙሉ ስም / Full Name</label>
                    <input type="text" name="name" id="username" required placeholder="Enter your name">
                </div>
                <div class="form-group">
                    <label>የይለፍ ቃል / Password</label>
                    <input type="password" name="password" id="password" required placeholder="Enter your password">
                </div>
                <button type="submit" class="btn-login">Login</button>
            </form>
            
            <div class="demo-users">
                <h4>📋 Demo Accounts (Click to auto-fill)</h4>
                <div class="demo-grid">
                    <!-- LMAT Admin -->
                    <div class="demo-card" onclick="fillCredentials('አለማየሁ ተስፋዬ', 'Admin@123')">
                        <div class="demo-name">አለማየሁ ተስፋዬ</div>
                        <div class="demo-role">👑 Lmat Admin (ልማት አስተዳዳሪ)</div>
                        <div class="demo-dept">🏪 ልማት ክፍል - Can add products, view reports</div>
                        <div class="demo-password">🔑 Password: Admin@123</div>
                        <button class="btn-demo" onclick="event.stopPropagation(); fillCredentials('አለማየሁ ተስፋዬ', 'Admin@123')">✍️ Auto Fill</button>
                    </div>
                    
                    <!-- Seller -->
                    <div class="demo-card" onclick="fillCredentials('ሄለን አበበ', 'Admin@123')">
                        <div class="demo-name">ሄለን አበበ</div>
                        <div class="demo-role">🛒 Seller (ሻጭ)</div>
                        <div class="demo-dept">🏪 ልማት ክፍል - Can only record sales</div>
                        <div class="demo-password">🔑 Password: Admin@123</div>
                        <button class="btn-demo" onclick="event.stopPropagation(); fillCredentials('ሄለን አበበ', 'Admin@123')">✍️ Auto Fill</button>
                    </div>
                    
                    <!-- Collector -->
                    <div class="demo-card" onclick="fillCredentials('መስፍን በቀለ', 'Admin@123')">
                        <div class="demo-name">መስፍን በቀለ</div>
                        <div class="demo-role">✅ Collector (ሰብሳቢ)</div>
                        <div class="demo-dept">💰 ንብረት ክፍል - First expense approver</div>
                        <div class="demo-password">🔑 Password: Admin@123</div>
                        <button class="btn-demo" onclick="event.stopPropagation(); fillCredentials('መስፍን በቀለ', 'Admin@123')">✍️ Auto Fill</button>
                    </div>
                    
                    <!-- Deputy -->
                    <div class="demo-card" onclick="fillCredentials('ስለሺ  ከበደ', 'Admin@123')">
                        <div class="demo-name">ስለሺ  ከበደ</div>
                        <div class="demo-role">👔 Deputy (ምክትል ሰብሳቢ)</div>
                        <div class="demo-dept">💰 ንብረት ክፍል - Second expense approver</div>
                        <div class="demo-password">🔑 Password: Admin@123</div>
                        <button class="btn-demo" onclick="event.stopPropagation(); fillCredentials('አብዱ ከበደ', 'Admin@123')">✍️ Auto Fill</button>
                    </div>
                    
                    <!-- Secretary -->
                    <div class="demo-card" onclick="fillCredentials('ማሞ ወንድሙ', 'Admin@123')">
                        <div class="demo-name">ማሞ ወንድሙ</div>
                        <div class="demo-role">📝 Secretary (ፀሀፊ)</div>
                        <div class="demo-dept">💰 ንብረት ክፍል - Third expense approver</div>
                        <div class="demo-password">🔑 Password: Admin@123</div>
                        <button class="btn-demo" onclick="event.stopPropagation(); fillCredentials('ማሞ ወንድሙ', 'Admin@123')">✍️ Auto Fill</button>
                    </div>
                    
                    <!-- Nibret Admin -->
                    <div class="demo-card" onclick="fillCredentials('ተስፋዬ አለሙ', 'Admin@123')">
                        <div class="demo-name">ተስፋዬ አለሙ</div>
                        <div class="demo-role">💎 Nibret Admin (ንብረት አስተዳዳሪ)</div>
                        <div class="demo-dept">💰 ንብረት ክፍል - Full finance access</div>
                        <div class="demo-password">🔑 Password: Admin@123</div>
                        <button class="btn-demo" onclick="event.stopPropagation(); fillCredentials('ተስፋዬ አለሙ', 'Admin@123')">✍️ Auto Fill</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function fillCredentials(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Optional: Auto submit after fill
            // document.getElementById('loginForm').submit();
            
            // Highlight the filled fields
            document.getElementById('username').style.borderColor = '#DAA520';
            document.getElementById('password').style.borderColor = '#DAA520';
            
            setTimeout(() => {
                document.getElementById('username').style.borderColor = '#e0e0e0';
                document.getElementById('password').style.borderColor = '#e0e0e0';
            }, 1000);
        }
    </script>
</body>
</html>