<?php

session_start();

include "koneksi.php";

$pesan = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = mysqli_query($conn, "
        SELECT *
        FROM users
        WHERE username = '$username'
        AND password = '$password'
        LIMIT 1
    ");

    if (mysqli_num_rows($query) > 0) {

        $user = mysqli_fetch_assoc($query);

        if ($user['role'] == 'admin' || $user['role'] == 'guru') {

            $_SESSION['login'] = true;
            $_SESSION['username'] = $user['username'];

            header("Location: index.php");
            exit;
        }

    } else {

        $pesan = "Username atau password salah.";

    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Sistem Absensi Digital</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            min-height: 100vh;

            display: flex;
            justify-content: center;
            align-items: center;

            background:
                radial-gradient(circle at 10% 10%, #bfdbfe 0, transparent 28%),
                radial-gradient(circle at 90% 90%, #93c5fd 0, transparent 30%),
                linear-gradient(135deg, #eff6ff, #dbeafe);

            padding: 25px;

            position: relative;
            overflow: hidden;
        }

        /* Dekorasi background */

        body::before {
            content: "";
            position: absolute;

            width: 350px;
            height: 350px;

            background: #bfdbfe;

            border-radius: 50%;

            top: -180px;
            left: -120px;

            opacity: 0.7;
        }

        body::after {
            content: "";
            position: absolute;

            width: 400px;
            height: 400px;

            background: #93c5fd;

            border-radius: 50%;

            bottom: -220px;
            right: -150px;

            opacity: 0.45;
        }

        .login-container {
            width: 100%;
            max-width: 500px;

            background: rgba(255, 255, 255, 0.96);

            border-radius: 25px;

            padding: 45px 45px 35px;

            box-shadow:
                0 25px 60px rgba(30, 64, 175, 0.18);

            position: relative;
            z-index: 2;
        }

        /* Logo */

        .logo {
            width: 85px;
            height: 85px;

            margin: 0 auto 20px;

            border-radius: 25px;

            background: linear-gradient(
                135deg,
                #2563eb,
                #1d4ed8
            );

            display: flex;
            justify-content: center;
            align-items: center;

            color: white;

            font-size: 42px;

            box-shadow:
                0 12px 25px rgba(37, 99, 235, 0.3);
        }

        .title {
            text-align: center;
        }

        .title h1 {
            font-size: 31px;

            color: #1e3a8a;

            margin-bottom: 8px;

            font-weight: 700;
        }

        .title h2 {
            font-size: 22px;

            color: #3b5998;

            font-weight: 600;

            margin-bottom: 14px;
        }

        .title-line {
            width: 65px;
            height: 5px;

            background: #2563eb;

            border-radius: 20px;

            margin: 0 auto 18px;
        }

        .description {
            text-align: center;

            color: #64748b;

            font-size: 15px;

            margin-bottom: 30px;
        }

        /* Pesan error */

        .error {
            background: #fee2e2;

            color: #b91c1c;

            padding: 12px 15px;

            border-radius: 10px;

            text-align: center;

            margin-bottom: 20px;

            font-size: 14px;
        }

        /* Form */

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;

            color: #334155;

            font-weight: 600;

            margin-bottom: 8px;

            font-size: 14px;
        }

        .input-box {
            position: relative;
        }

        .input-icon {
            position: absolute;

            left: 17px;
            top: 50%;

            transform: translateY(-50%);

            font-size: 20px;

            color: #64748b;
        }

        .input-box input {
            width: 100%;

            padding: 15px 18px 15px 50px;

            border: 2px solid #dbeafe;

            border-radius: 13px;

            outline: none;

            font-size: 16px;

            color: #1e293b;

            background: #f8fbff;

            transition: 0.25s;
        }

        .input-box input:focus {
            border-color: #3b82f6;

            background: white;

            box-shadow:
                0 0 0 4px rgba(59, 130, 246, 0.10);
        }

        /* Tombol */

        .login-button {
            width: 100%;

            border: none;

            border-radius: 13px;

            padding: 16px;

            margin-top: 5px;

            background: linear-gradient(
                135deg,
                #2563eb,
                #1d4ed8
            );

            color: white;

            font-size: 17px;

            font-weight: 700;

            cursor: pointer;

            transition: 0.25s;

            box-shadow:
                0 10px 20px rgba(37, 99, 235, 0.25);
        }

        .login-button:hover {
            transform: translateY(-2px);

            box-shadow:
                0 14px 25px rgba(37, 99, 235, 0.32);
        }

        .login-button:active {
            transform: translateY(0);
        }

        /* Footer */

        .footer {
            margin-top: 30px;

            padding-top: 20px;

            border-top: 1px solid #e2e8f0;

            text-align: center;
        }

        .footer-school {
            color: #1e40af;

            font-size: 17px;

            font-weight: 700;

            margin-bottom: 7px;
        }

        .footer-text {
            color: #64748b;

            font-size: 13px;

            letter-spacing: 0.5px;
        }

        .footer-text span {
            margin: 0 5px;

            color: #2563eb;
        }

        .copyright {
            margin-top: 15px;

            color: #94a3b8;

            font-size: 11px;
        }

        /* Mobile */

        @media (max-width: 600px) {

            body {
                padding: 15px;
            }

            .login-container {
                padding: 35px 25px 25px;

                border-radius: 20px;
            }

            .title h1 {
                font-size: 26px;
            }

            .title h2 {
                font-size: 19px;
            }

            .logo {
                width: 75px;
                height: 75px;

                font-size: 36px;
            }
        }

    </style>

</head>

<body>

    <div class="login-container">

        <!-- Logo -->

        <div class="logo">
            🔐
        </div>

        <!-- Judul -->

        <div class="title">

            <h1>
                Sistem Absensi Digital
            </h1>

            <h2>
                MTs Matholiul Huda
            </h2>

            <div class="title-line"></div>

        </div>

        <p class="description">
            Silakan login untuk mengakses halaman absensi
        </p>


        <!-- Pesan Error -->

        <?php if ($pesan != "") { ?>

            <div class="error">
                <?php echo $pesan; ?>
            </div>

        <?php } ?>


        <!-- Form Login -->

        <form method="POST">

            <div class="form-group">

                <label>
                    Username
                </label>

                <div class="input-box">

                    <span class="input-icon">
                        👤
                    </span>

                    <input
                        type="text"
                        name="username"
                        placeholder="Masukkan username"
                        required
                    >

                </div>

            </div>


            <div class="form-group">

                <label>
                    Password
                </label>

                <div class="input-box">

                    <span class="input-icon">
                        🔒
                    </span>

                    <input
                        type="password"
                        name="password"
                        placeholder="Masukkan password"
                        required
                    >

                </div>

            </div>


            <button
                type="submit"
                class="login-button"
            >

                ➜ &nbsp; Login

            </button>

        </form>


        <!-- Footer -->

        <div class="footer">

            <div class="footer-school">
                🎓 MTs Matholiul Huda
            </div>

            <div class="footer-text">

                Disiplin
                <span>•</span>
                Jujur
                <span>•</span>
                Berprestasi

            </div>

            <div class="copyright">
                Sistem Absensi Digital RFID
            </div>

        </div>

    </div>

</body>

</html>