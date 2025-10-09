<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Misuki</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .welcome-container {
            text-align: center;
            color: white;
        }

        .welcome-text {
            font-size: 3rem;
            font-weight: 300;
            opacity: 0;
            animation: fadeIn 1s ease-in forwards;
        }

        .name-text {
            font-size: 4rem;
            font-weight: 600;
            opacity: 0;
            animation: fadeIn 1s ease-in 1.5s forwards;
        }

        .heart {
            position: fixed;
            bottom: -50px;
            font-size: 2rem;
            color: white;
            opacity: 0.8;
            animation: floatUp 3s ease-in forwards;
            pointer-events: none;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes floatUp {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.8;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
            }
        }

        .fade-out {
            animation: fadeOut 0.5s ease-out forwards;
        }
    </style>
</head>
<body>
    <div class="welcome-container" id="welcomeContainer">
        <div class="welcome-text" id="welcomeText">Welcome back,</div>
        <div class="name-text" id="nameText">Dan!</div>
    </div>

    <script>
        // Get username from URL parameter or localStorage
        const urlParams = new URLSearchParams(window.location.search);
        const username = urlParams.get('name') || localStorage.getItem('username') || 'Dan';
        
        document.getElementById('nameText').textContent = username + '!';

        // Create heart animation
        function createHeart() {
            const heart = document.createElement('div');
            heart.className = 'heart';
            heart.innerHTML = '♥';
            heart.style.left = Math.random() * 100 + '%';
            heart.style.animationDelay = Math.random() * 0.5 + 's';
            heart.style.animationDuration = (Math.random() * 2 + 2) + 's';
            document.body.appendChild(heart);
            
            setTimeout(() => heart.remove(), 3000);
        }

        // Start heart flurry after name appears
        setTimeout(() => {
            const heartInterval = setInterval(createHeart, 100);
            
            // Stop creating hearts and fade out
            setTimeout(() => {
                clearInterval(heartInterval);
                document.getElementById('welcomeContainer').classList.add('fade-out');
                
                // Redirect to chat page
                setTimeout(() => {
                    window.location.href = 'chat.php';
                }, 500);
            }, 2000);
        }, 2500);
    </script>
</body>
</html>