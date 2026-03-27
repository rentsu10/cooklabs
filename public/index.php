<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CookLabs · LMS · IETI</title>
    <link rel="icon" type="image/png" href="../uploads/images/ieti-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            /* background removed — image will be set via inline style on body */
            color: #13294b;
            height: 100vh;          /* exactly viewport height */
            display: flex;
            flex-direction: column;
            overflow: hidden;        /* prevent scrolling */
        }

        /* geometric overlay removed — you can keep or delete this block entirely */
        /* body::before removed as requested */

        .wrapper {
            position: relative;
            z-index: 5;
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;      /* reduced padding to fit */
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100%;             /* fill parent */
        }

        /* ---------- SHARP HEADER — no rounded corners, no clutter ---------- */
        .sharp-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;        /* prevent wrapping */
            gap: 1.5rem;
            background: #ffffff;
            border: 2px solid #1e4f7a;
            box-shadow: 8px 8px 0 #1e3f60;
            padding: 0.5rem 2rem;     /* slightly tighter */
            border-radius: 0px;
            margin-top: 1.5rem;
        }

        .logo-block {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* dedicated logo container for header */
        .header-logo-container {
            width: 48px;              /* slightly smaller */
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0px;
        }

        .header-logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .logo-name {
            font-size: 1.8rem;        /* adjusted */
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #0a2d4b;
            border-right: 3px solid #2367a3;
            padding-right: 1rem;
            line-height: 1.2;
        }

        .logo-name span {
            font-weight: 400;
            color: #3677b5;
            margin-left: 6px;
        }

        /* auth buttons */
        .auth-row {
            display: flex;
            gap: 0.8rem;
        }

        .btn {
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 1rem;          /* slightly smaller */
            padding: 0.5rem 1.8rem;
            border: 2px solid #0f3d5e;
            background: white;
            color: #0a314b;
            cursor: pointer;
            transition: all 0.1s ease;
            box-shadow: 5px 5px 0 #123a57;
            border-radius: 0px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn i {
            color: #1e5f96;
        }

        .btn-register {
            background: #1661a3;
            color: white;
            border-color: #0c314d;
            box-shadow: 5px 5px 0 #0b263b;
        }

        .btn-register i {
            color: #cde1ff;
        }

        .btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 8px 8px 0 #123450;
        }

        .btn:active {
            transform: translate(2px, 2px);
            box-shadow: 2px 2px 0 #123450;
        }

        /* ---------- HERO: ultra clean, sharp, fits without scroll ---------- */
        .hero-geometric {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
            margin: 1.5rem 0 1rem;   /* reduced vertical margins */
            flex: 1;                  /* take remaining space */
            min-height: 0;            /* important for flex children */
        }

        .hero-left {
            flex: 1 1 450px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* logo inside hero-left (restored) */
        .hero-logo-container {
            width: 600px;              /* appropriate size */
            height: 300px;
            margin-bottom: 1rem;
            display: flex;
            justify-content: flex-start;
        }

        .hero-logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .hero-left h1 {
            font-size: 3.2rem;         /* slightly reduced */
            font-weight: 700;
            line-height: 1.1;
            color: #07223b;
            margin-bottom: 0.75rem;
            border-left: 10px solid #1d6fb0;
            padding-left: 1.2rem;
        }

        .hero-left h1 i {
            color: #2680cf;
            font-size: 2.8rem;
            margin-right: 10px;
        }

        .sharp-badge {
            background: white;
            border: 2px solid #104a77;
            box-shadow: 5px 5px 0 #184369;
            padding: 0.5rem 1.8rem;
            display: inline-block;
            margin: 0.3rem 0 1rem;
            margin-top: 1rem;
            font-weight: 600;
            font-size: 1rem;
            color: #0a3458;
            border-radius: 0px;
            width: fit-content;
        }

        .sharp-badge i {
            color: #1c6fb0;
            margin-right: 8px;
        }

        .hero-left p {
            font-size: 1rem;
            color: #1e4465;
            max-width: 500px;
            margin-bottom: 0.8rem;
        }

        .simple-tagline {
            font-size: 1rem;
            font-weight: 500;
            color: #10487a;
            margin-top: 0.8rem;
            border-left: 3px solid #307fc7;
            padding-left: 1rem;
        }

        /* ===== REDESIGNED HERO-RIGHT ===== */
        /* hero-right divided into two horizontal divisions */
        .hero-right {
            flex: 1 1 500px;
            background: #d7e9ff;
            border: 3px solid #15415e;
            box-shadow: 12px 12px 0 #1b3b58;
            border-radius: 0px;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            gap: 1.5rem;
            width: 650px;        /* Fixed width */
            height: 600px;       /* Fixed height */
            max-width: 650px;    /* Prevents growing beyond this */
            max-height: 600px;   /* Prevents growing beyond this */
        }

        /* each horizontal division takes equal space */
        .hero-right-top,
        .hero-right-bottom {
            flex: 1;
            background: rgba(255, 255, 255, 0.3);
            border: 2px solid #1b5790;
            box-shadow: 5px 5px 0 #1a4270;
            padding: 1.2rem;
            display: flex;
            flex-direction: column;
            position: relative;
            min-height: 0; /* Important for flex children */
        }

        /* logo placeholder in upper right corner */
        .logo-placeholder {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 80px;   /* Default size, can be adjusted via inline style or class */
            height: 80px;  /* Default size */
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
        }

        .logo-placeholder img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* container for title and description */
        .text-content {
            margin-top: auto;
            margin-bottom: auto;
            padding-right: 90px; /* Make room for logo */
            width: 100%;
        }

        .title-container {
            background: white;
            border: 2px solid #14568a;
            box-shadow: 4px 4px 0 #1d4670;
            padding: 0.5rem 1rem;
            margin-bottom: 0.8rem;
            border-radius: 0px;
            font-weight: 700;
            font-size: 1.2rem;
            color: #073457;
            display: inline-block;
        }

        .description-container {
            background: white;
            border: 2px solid #14568a;
            box-shadow: 4px 4px 0 #1d4670;
            padding: 0.8rem 1rem;
            border-radius: 0px;
            font-size: 0.9rem;
            color: #0f3960;
            line-height: 1.4;
        }

        /* optional adjuster classes for logo sizes */
        .logo-small {
            width: 60px;
            height: 60px;
        }
        .logo-medium {
            width: 80px;
            height: 80px;
        }
        .logo-large {
            width: 100px;
            height: 100px;
        }

        /* ---------- FOOTER sharp, minimal ---------- */
        .sharp-footer {
            display: flex;
            justify-content: right;
            align-items: center;
            padding: 0.8rem 0 0.3rem;
            border-top: 4px solid #245e91;
            color: #113b5e;
            flex-wrap: nowrap;
            gap: 1rem;
            margin-top: auto;          /* push to bottom */
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-links a {
            text-decoration: none;
            color: #0e3f64;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .footer-links a:hover {
            border-bottom-color: #0e3f64;
        }

        .sharp-stamp {
            font-size: 0.9rem;
            text-align: right;
        }

        .sharp-stamp i {
            color: #296ea8;
            margin: 0 4px;
        }

        @media (max-width: 1100px) {
            .hero-geometric {
                flex-direction: column;
                overflow-y: auto;
            }
            body { overflow: hidden; }
            
            .hero-right {
                width: 100%;
                max-width: 100%;
                height: auto;
                max-height: none;
            }
            
            .footer-links {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<!-- background image applied here — replace 'YOUR_IMAGE_URL.jpg' with actual path -->
<body style="background: url('../uploads/images/cooklabs-bg.png') no-repeat center center fixed; background-size: cover;">
    <div class="wrapper">
        <header class="sharp-header">
            <div class="logo-block">
                <!-- optional header logo container (empty but present) -->
                <!-- <div class="header-logo-container"><img src="../uploads/images/cooklabs-logo.png" alt="CookLabs Logo"></div> -->
                <div class="logo-name">
                    IETI - COLLEGE OF SCIENCE AND TECHNOLOGY · MARIKINA
                </div>
                <i class="fas fa-cube" style="color:#2170b3; font-size: 1.5rem; margin-left: -0.2rem;"></i>
            </div>
            <div class="auth-row">
                <a href="../public/login.php" class="btn"><i class="fas fa-gear"></i> Login</a>
                <a href="../public/register.php" class="btn btn-register"><i class="fas fa-cube"></i> Register</a>
            </div>
        </header>

        <div class="hero-geometric">
            <div class="hero-left">
                <div class="hero-logo-container">
                    <img src="../uploads/images/cooklabs-logo.png" alt="CookLabs Logo">
                </div>
                <div class="sharp-badge">
                    <i class="fas fa-cubes"></i> A Learning Platform for Cookery NCII Students of IETI·Marikina 
                </div>
                <p>
                    "A modern learning management system crafted for precision and excellence. 
                    Where students can sharpen skills and build the future."
                </p>
                <div class="simple-tagline">
                    <i class="fas fa-knife"></i> Cook up your potential  ·  One course at a time
                </div>
            </div>

            <!-- ===== hero right added back ===== -->
            <div class="hero-right">
                <!-- top horizontal division -->
                <div class="hero-right-top">
                    <div class="logo-placeholder logo-medium">
                        <img src="../uploads/images/ieti-logo.png" alt="logo">
                    </div>
                    <div class="text-content">
                        <div class="title-container">
                            <i class="fas fa-kitchen-set"></i> IETI College of Science and Technology
                        </div>
                        <div class="description-container">
                            To produce world-class multi-skilled graduates adaptive to new and emerging technologies.
                        </div>
                    </div>
                </div>
                <!-- bottom horizontal division -->
                <div class="hero-right-bottom">
                    <div class="logo-placeholder logo-medium">
                        <img src="../uploads/images/tesda-logo.png" alt="logo">
                    </div>
                    <div class="text-content">
                        <div class="title-container">
                             TESDA · Technical Education and Skills Development Authority
                        </div>
                        <div class="description-container">
                             “Sa TESDA, Lingap ay Maaasahan”
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="sharp-footer">
            <div class="sharp-stamp">
                <i class="fas fa-copyright"></i> 2026 CookLabs LMS · IETI College of Science and Technology·Marikina
            </div>
        </footer>
    </div>

    <div style="position: fixed; bottom: 10px; left: 10px; opacity: 0.1; pointer-events: none; z-index: 1; font-size: 3rem; color: #2b6ca3;">
        <i class="fas fa-cube"></i>
    </div>
</body>
</html>