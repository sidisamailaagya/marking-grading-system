<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduGrade Pro - Advanced Marking & Grading System</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- AOS for animations -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #48bb78;
            --dark-color: #1a202c;
            --light-color: #f8fafc;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-alt: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            --gradient-premium: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #48bb78 100%);
            --shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 30px 60px rgba(0, 0, 0, 0.2);
            --shadow-premium: 0 40px 80px rgba(102, 126, 234, 0.3);
            --border-radius: 20px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            overflow-x: hidden;
            background: #ffffff;
        }

        /* Premium Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
            padding: 1.2rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.15);
            padding: 0.8rem 0;
        }

        .navbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.8rem;
            background: var(--gradient-premium);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: var(--transition);
        }

        .navbar-nav .nav-link {
            font-weight: 500;
            color: var(--dark-color);
            margin: 0 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: var(--transition);
            position: relative;
        }

        .navbar-nav .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient);
            border-radius: 25px;
            opacity: 0;
            transform: scale(0.8);
            transition: var(--transition);
            z-index: -1;
        }

        .navbar-nav .nav-link:hover::before {
            opacity: 0.1;
            transform: scale(1);
        }

        .navbar-nav .nav-link:hover {
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        .btn-login {
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: var(--transition);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
        }

        .btn-login:hover::before {
            left: 100%;
        }
/* Enhanced carousel image loading states */
.carousel-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0;
    transform: scale(1.1);
    transition: all 1.5s cubic-bezier(0.4, 0, 0.2, 1);
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.carousel-image.loaded {
    opacity: 0.9;
    transform: scale(1);
}

/* Loading placeholder */
.carousel-image:not(.loaded) {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
}

.carousel-image:not(.loaded)::before {
    content: '🖼️';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 4rem;
    color: white;
    opacity: 0.7;
}

/* Error state styling */
.carousel-image[src*="unsplash"] {
    border: 2px solid rgba(255, 255, 255, 0.3);
}

/* Debug info overlay (remove in production) */
.debug-overlay {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 12px;
    z-index: 10;
}



        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-premium);
            color: white;
        }

        /* Premium Hero Section with Carousel */
        .hero {
            background: var(--gradient-premium);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 60%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
        }

        .hero-content {
            position: relative;
            z-index: 3;
            color: white;
        }

        .hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 4.5rem;
            font-weight: 900;
            margin-bottom: 2rem;
            line-height: 1.1;
            background: linear-gradient(135deg, #ffffff 0%, rgba(255, 255, 255, 0.8) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .hero .subtitle {
            font-size: 1.4rem;
            margin-bottom: 3rem;
            opacity: 0.95;
            font-weight: 400;
            line-height: 1.6;
            max-width: 600px;
        }

        .hero-stats {
            display: flex;
            gap: 3rem;
            margin-top: 4rem;
        }

        .hero-stat {
            text-align: center;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .hero-stat:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }

        .hero-stat h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #ffffff 0%, rgba(255, 255, 255, 0.8) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-stat p {
            font-size: 0.95rem;
            opacity: 0.9;
            font-weight: 500;
        }

        .btn-hero {
            background: white;
            color: var(--primary-color);
            border: none;
            border-radius: 35px;
            padding: 1.2rem 2.5rem;
            font-weight: 700;
            font-size: 1.1rem;
            transition: var(--transition);
            margin-right: 1.5rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }

        .btn-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--gradient);
            transition: left 0.6s;
            z-index: -1;
        }

        .btn-hero:hover::before {
            left: 0;
        }

        .btn-hero:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            color: white;
        }

        .btn-outline-hero {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.8);
            border-radius: 35px;
            padding: 1.2rem 2.5rem;
            font-weight: 700;
            font-size: 1.1rem;
            transition: var(--transition);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .btn-outline-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.6s;
            z-index: -1;
        }

        .btn-outline-hero:hover::before {
            transform: scaleX(1);
        }

        .btn-outline-hero:hover {
            color: var(--primary-color);
            border-color: white;
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(255, 255, 255, 0.3);
        }

        /* Premium Image Carousel */
        .hero-carousel {
            position: relative;
            z-index: 2;
            height: 600px;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3);
        }

        .swiper {
            width: 100%;
            height: 100%;
        }

        .swiper-slide {
            position: relative;
            overflow: hidden;
        }

        .carousel-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            transform: scale(1.1);
            transition: all 1.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .carousel-image.loaded {
            opacity: 0.9;
            transform: scale(1);
        }

        .swiper-slide::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.3) 0%, rgba(118, 75, 162, 0.3) 100%);
            z-index: 1;
        }

        .carousel-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.4) 0%, rgba(0, 0, 0, 0.2) 100%);
            z-index: 2;
        }

        .carousel-content {
            position: absolute;
            bottom: 30px;
            left: 30px;
            right: 30px;
            z-index: 3;
            color: white;
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .swiper-slide-active .carousel-content {
            opacity: 1;
            transform: translateY(0);
        }

        .carousel-content h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .carousel-content p {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        /* Custom Swiper Navigation */
        .swiper-button-next,
        .swiper-button-prev {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: var(--transition);
        }

        .swiper-button-next:hover,
        .swiper-button-prev:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .swiper-button-next::after,
        .swiper-button-prev::after {
            font-size: 16px;
            color: white;
            font-weight: 700;
        }

        .swiper-pagination-bullet {
            width: 12px;
            height: 12px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 1;
            transition: var(--transition);
        }

        .swiper-pagination-bullet-active {
            background: white;
            transform: scale(1.3);
        }

        /* Enhanced Features Section */
        .features {
            padding: 120px 0;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            position: relative;
        }

        .features::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 200px;
            background: linear-gradient(180deg, rgba(102, 126, 234, 0.05) 0%, transparent 100%);
        }

        .section-title {
            text-align: center;
            margin-bottom: 5rem;
            position: relative;
        }

        .section-title::before {
            content: '';
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--gradient);
            border-radius: 2px;
        }

        .section-title h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            color: var(--dark-color);
            background: linear-gradient(135deg, var(--dark-color) 0%, #4a5568 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-title p {
            font-size: 1.2rem;
            color: #6b7280;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.7;
        }

        .feature-card {
            background: white;
            border-radius: 25px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            transition: var(--transition);
            height: 100%;
            border: 1px solid rgba(102, 126, 234, 0.1);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient);
            transform: scaleX(0);
            transition: transform 0.6s;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 40px 80px rgba(102, 126, 234, 0.2);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .feature-icon {
            width: 90px;
            height: 90px;
            border-radius: 25px;
            background: var(--gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 2.2rem;
            color: white;
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.3);
            transition: var(--transition);
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
        }

        .feature-card h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            color: var(--dark-color);
        }

        .feature-card p {
            color: #6b7280;
            line-height: 1.7;
            font-size: 1rem;
        }

        /* Enhanced How It Works */
        .how-it-works {
            padding: 120px 0;
            background: white;
            position: relative;
        }

        .step-card {
            text-align: center;
            padding: 2.5rem;
            position: relative;
        }

        .step-card::before {
            content: '';
            position: absolute;
            top: 30px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-color), transparent);
            z-index: 1;
        }

        .step-card:last-child::before {
            display: none;
        }

        .step-number {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0 auto 2rem;
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.3);
            transition: var(--transition);
            position: relative;
            z-index: 2;
        }

        .step-card:hover .step-number {
            transform: scale(1.1);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
        }

        .step-card h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            color: var(--dark-color);
        }

        .step-card p {
            color: #6b7280;
            line-height: 1.7;
        }

        /* Enhanced Benefits Section */
        .benefits {
            padding: 120px 0;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }

        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 2.5rem;
            padding: 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
            transition: var(--transition);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .benefit-item:hover {
            transform: translateX(15px);
            box-shadow: 0 25px 50px rgba(102, 126, 234, 0.15);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .benefit-icon {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            background: var(--gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 2rem;
            font-size: 1.6rem;
            color: white;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            transition: var(--transition);
        }

        .benefit-item:hover .benefit-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .benefit-content h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 0.8rem;
            color: var(--dark-color);
        }

        .benefit-content p {
            color: #6b7280;
            margin: 0;
            line-height: 1.6;
        }

        /* Enhanced CTA Section */
        .cta {
            padding: 120px 0;
            background: var(--gradient-premium);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 70% 70%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            animation: float 15s ease-in-out infinite reverse;
        }

        .cta h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #ffffff 0%, rgba(255, 255, 255, 0.8) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .cta p {
            font-size: 1.2rem;
            margin-bottom: 3rem;
            opacity: 0.95;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Enhanced Footer */
        .footer {
            background: linear-gradient(135deg, var(--dark-color) 0%, #2d3748 100%);
            color: white;
            padding: 80px 0 40px;
            position: relative;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--gradient);
        }

        .footer h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            margin-bottom: 2rem;
            color: white;
            font-size: 1.2rem;
        }

        .footer ul li a {
            color: #cbd5e0;
            text-decoration: none;
            transition: var(--transition);
            padding: 0.3rem 0;
            display: inline-block;
        }

        .footer ul li a:hover {
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .footer-bottom {
            border-top: 1px solid #4a5568;
            margin-top: 3rem;
            padding-top: 2rem;
            text-align: center;
            color: #cbd5e0;
        }

        /* Responsive Enhancements */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 3rem;
            }

            .hero-stats {
                flex-direction: column;
                gap: 1.5rem;
            }

            .btn-hero,
            .btn-outline-hero {
                display: block;
                margin: 0.8rem 0;
                width: 100%;
            }

            .section-title h2 {
                font-size: 2.2rem;
            }

            .hero-carousel {
                height: 400px;
                margin-top: 3rem;
            }

            .benefit-item {
                flex-direction: column;
                text-align: center;
            }

            .benefit-icon {
                margin-right: 0;
                margin-bottom: 1.5rem;
            }
        }

        /* Premium Animations */
        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            33% {
                transform: translateY(-20px) rotate(1deg);
            }

            66% {
                transform: translateY(-10px) rotate(-1deg);
            }
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(100%);
            }
        }

        .floating {
            animation: float 8s ease-in-out infinite;
        }

        .pulsing {
            animation: pulse 3s ease-in-out infinite;
        }

        /* Loading Animation */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient-premium);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.8s, visibility 0.8s;
        }

        .page-loader.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .loader-content {
            text-align: center;
            color: white;
        }

        .loader-spinner {
            width: 60px;
            height: 60px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 2rem;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Scroll Progress Bar */
        .scroll-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: var(--gradient);
            z-index: 9998;
            transition: width 0.3s;
        }
    </style>
</head>

<body>
    <!-- Page Loader -->
    <div class="page-loader" id="pageLoader">
        <div class="loader-content">
            <div class="loader-spinner"></div>
            <h3>Loading EduGrade Pro...</h3>
        </div>
    </div>

    <!-- Scroll Progress Bar -->
    <div class="scroll-progress" id="scrollProgress"></div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top" id="navbar">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap me-2"></i>EduGrade Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto me-4">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#benefits">Benefits</a>
                    </li>
                </ul>
                <a href="login.php" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </a>
            </div>
        </div>
    </nav>

   <!-- Replace the carousel section in your index.php (around lines 700-750) -->
<div class="hero-carousel" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
    <div class="swiper heroSwiper">
        <div class="swiper-wrapper">
            <div class="swiper-slide">
                <img src="images/ChatGPT Image Sep 26, 2025, 01_23_58 AM.png" 
                     alt="Students collaborating" 
                     class="carousel-image"
                     onerror="this.src='https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2071&q=80'; console.log('Image 1 failed to load');">
                <div class="carousel-overlay"></div>
                <div class="carousel-content">
                    <h3>Collaborative Learning</h3>
                    <p>Foster teamwork and peer-to-peer learning with our advanced collaboration tools</p>
                </div>
            </div>
            <div class="swiper-slide">
                <img src="images/ChatGPT Image Sep 26, 2025, 01_27_32 AM.png" 
                     alt="Digital learning" 
                     class="carousel-image"
                     onerror="this.src='https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80'; console.log('Image 2 failed to load');">
                <div class="carousel-overlay"></div>
                <div class="carousel-content">
                    <h3>Digital Innovation</h3>
                    <p>Embrace the future of education with cutting-edge technology and AI-powered insights</p>
                </div>
            </div>
            <div class="swiper-slide">
                <img src="images/ChatGPT Image Sep 26, 2025, 01_24_10 AM.png" 
                     alt="Academic excellence" 
                     class="carousel-image"
                     onerror="this.src='https://images.unsplash.com/photo-1434030216411-0b793f4b4173?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80'; console.log('Image 3 failed to load');">
                <div class="carousel-overlay"></div>
                <div class="carousel-content">
                    <h3>Academic Excellence</h3>
                    <p>Achieve outstanding results with personalized feedback and comprehensive analytics</p>
                </div>
            </div>
            <div class="swiper-slide">
                <img src="images/ims.png" 
                     alt="Modern classroom" 
                     class="carousel-image"
                     onerror="this.src='https://images.unsplash.com/photo-1509062522246-3755977927d7?ixlib=rb-4.0.3&auto=format&fit=crop&w=2032&q=80'; console.log('Image 4 failed to load');">
                <div class="carousel-overlay"></div>
                <div class="carousel-content">
                    <h3>Modern Classroom</h3>
                    <p>Transform traditional teaching methods with interactive and engaging assessment tools</p>
                </div>
            </div>
        </div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-pagination"></div>
    </div>
</div>

<!-- Add this enhanced JavaScript section to replace the existing image loading script -->
<script>
// Enhanced image loading with better error handling and debugging
document.addEventListener('DOMContentLoaded', function() {
    console.log('🖼️ Starting image loading process...');
    
    const images = document.querySelectorAll('.carousel-image');
    let loadedCount = 0;
    
    // Function to check if image exists
    function imageExists(url, callback) {
        const img = new Image();
        img.onload = function() { callback(true); };
        img.onerror = function() { callback(false); };
        img.src = url;
    }
    
    // Enhanced image loading with fallbacks
    images.forEach((img, index) => {
        const originalSrc = img.src;
        console.log(`🔍 Checking image ${index + 1}: ${originalSrc}`);
        
        // Check if original image exists
        imageExists(originalSrc, function(exists) {
            if (exists) {
                console.log(`✅ Image ${index + 1} loaded successfully`);
                setTimeout(() => {
                    img.classList.add('loaded');
                    loadedCount++;
                    
                    // If all images loaded, initialize carousel
                    if (loadedCount === images.length) {
                        console.log('🎉 All images loaded successfully!');
                        initializeCarousel();
                    }
                }, index * 200);
            } else {
                console.log(`❌ Image ${index + 1} failed to load, using fallback`);
                // Fallback images will be handled by onerror attribute
                setTimeout(() => {
                    img.classList.add('loaded');
                    loadedCount++;
                    
                    if (loadedCount === images.length) {
                        console.log('🎉 All images loaded (with fallbacks)!');
                        initializeCarousel();
                    }
                }, index * 200);
            }
        });
    });
    
    // Timeout fallback - if images don't load within 5 seconds, show anyway
    setTimeout(() => {
        if (loadedCount < images.length) {
            console.log('⏰ Timeout reached, showing images anyway');
            images.forEach(img => {
                if (!img.classList.contains('loaded')) {
                    img.classList.add('loaded');
                }
            });
            initializeCarousel();
        }
    }, 5000);
});

// Separate function to initialize carousel
function initializeCarousel() {
    console.log('🎠 Initializing carousel...');
    
    // Initialize Swiper (if not already initialized)
    if (typeof swiper === 'undefined' || !swiper) {
        window.swiper = new Swiper('.heroSwiper', {
            loop: true,
            autoplay: {
                delay: 4000,
                disableOnInteraction: false,
            },
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            on: {
                init: function() {
                    console.log('🎠 Carousel initialized successfully!');
                }
            }
        });
    }
}

// Debug function to check image paths
function debugImagePaths() {
    console.log('🔧 Debugging image paths...');
    console.log('Current page URL:', window.location.href);
    console.log('Base URL:', window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1));
    
    const images = [
        'images/ChatGPT Image Sep 26, 2025, 01_23_58 AM.png',
        'images/ChatGPT Image Sep 26, 2025, 01_27_32 AM.png', 
        'images/ChatGPT Image Sep 26, 2025, 01_24_10 AM.png',
        'images/ims.png'
    ];
    
    images.forEach((imagePath, index) => {
        const fullPath = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1) + imagePath;
        console.log(`Image ${index + 1} full path:`, fullPath);
        
        // Try to fetch the image
        fetch(fullPath, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    console.log(`✅ Image ${index + 1} exists and is accessible`);
                } else {
                    console.log(`❌ Image ${index + 1} returned status:`, response.status);
                }
            })
            .catch(error => {
                console.log(`❌ Image ${index + 1} fetch error:`, error);
            });
    });
}

// Run debug function
debugImagePaths();
</script>

<!-- Add this CSS to improve image loading states -->
    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Powerful Features</h2>
                <p>Discover the comprehensive tools that make our grading system the perfect choice for modern educational institutions worldwide</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <h4>AI-Powered Grading</h4>
                        <p>Advanced machine learning algorithms ensure consistent, fair, and accurate grading across all assessments with minimal human intervention and maximum precision.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h4>Real-time Analytics</h4>
                        <p>Comprehensive dashboards provide instant insights into student performance, learning trends, and areas needing attention with beautiful visualizations.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h4>Instant Feedback</h4>
                        <p>Students receive immediate, personalized feedback on their performance, helping them improve continuously and stay motivated throughout their learning journey.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Secure & Reliable</h4>
                        <p>Bank-level security ensures all academic data is protected with encrypted storage, secure access controls, and compliance with educational privacy standards.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4>Mobile Responsive</h4>
                        <p>Access the system anywhere, anytime with our fully responsive design that works perfectly on all devices, from smartphones to tablets and desktops.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="600">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-export"></i>
                        </div>
                        <h4>Export & Reports</h4>
                        <p>Generate detailed reports in multiple formats including PDF, Excel, and CSV for easy sharing, archiving, and integration with other educational systems.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>How It Works</h2>
                <p>Simple, efficient, and effective - transform your grading process in just three easy steps</p>
            </div>
            <div class="row">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4>Setup Your Courses</h4>
                        <p>Create courses, add students, and configure grading criteria with our intuitive interface. Set up your academic structure in minutes, not hours.</p>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4>Input Assessments</h4>
                        <p>Enter assignment scores, test results, and behavioral assessments quickly and efficiently. Our smart forms make data entry a breeze.</p>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4>Generate Results</h4>
                        <p>Automatic calculation of final grades, GPA, and comprehensive performance reports. Get insights that drive better educational outcomes.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section id="benefits" class="benefits">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Why Choose EduGrade Pro?</h2>
                <p>Experience the advantages that make us the preferred choice for educational institutions worldwide</p>
            </div>
            <div class="row">
                <div class="col-lg-6">
                    <div class="benefit-item" data-aos="fade-right" data-aos-delay="100">
                        <div class="benefit-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="benefit-content">
                            <h5>Save Time</h5>
                            <p>Reduce grading time by up to 80% with automated calculations, smart workflows, and intelligent data processing that works around the clock.</p>
                        </div>
                    </div>
                    <div class="benefit-item" data-aos="fade-right" data-aos-delay="200">
                        <div class="benefit-icon">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <div class="benefit-content">
                            <h5>Improve Accuracy</h5>
                            <p>Eliminate human errors with precise calculations, consistent grading standards, and AI-powered quality assurance that ensures fairness.</p>
                        </div>
                    </div>
                    <div class="benefit-item" data-aos="fade-right" data-aos-delay="300">
                        <div class="benefit-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="benefit-content">
                            <h5>Enhance Collaboration</h5>
                            <p>Foster better communication between educators, students, and administrators with integrated messaging and feedback systems.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="benefit-item" data-aos="fade-left" data-aos-delay="100">
                        <div class="benefit-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="benefit-content">
                            <h5>Track Progress</h5>
                            <p>Monitor student performance trends and identify areas for improvement instantly with advanced analytics and predictive insights.</p>
                        </div>
                    </div>
                    <div class="benefit-item" data-aos="fade-left" data-aos-delay="200">
                        <div class="benefit-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="benefit-content">
                            <h5>Boost Engagement</h5>
                            <p>Motivate students with transparent grading, immediate feedback mechanisms, and gamification elements that make learning fun.</p>
                        </div>
                    </div>
                    <div class="benefit-item" data-aos="fade-left" data-aos-delay="300">
                        <div class="benefit-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="benefit-content">
                            <h5>Easy Integration</h5>
                            <p>Seamlessly integrate with existing school management systems, LMS platforms, and educational workflows without disruption.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center" data-aos="fade-up">
                    <h2>Ready to Transform Your Grading Process?</h2>
                    <p>Join thousands of educators who have already revolutionized their assessment methods with EduGrade Pro. Start your journey towards smarter, more efficient education today.</p>
                    <a href="login.php" class="btn btn-hero btn-lg">
                        <i class="fas fa-rocket me-2"></i>Start Your Journey Today
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5><i class="fas fa-graduation-cap me-2"></i>EduGrade Pro</h5>
                    <p>Empowering education through intelligent grading solutions. Making assessment fair, transparent, and efficient for everyone in the educational ecosystem.</p>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5>Quick Links</h5>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#benefits">Benefits</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5>System Access</h5>
                    <ul>
                        <li><a href="login.php">Login Portal</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5>Contact Information</h5>
                    <ul>
                        <li><i class="fas fa-envelope me-2"></i>support@iamstarkeys@gmail.com</li>
                        <li><i class="fas fa-phone me-2"></i>09033162442</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i></li>
                        <li><i class="fas fa-clock me-2"></i>24/7 Support Available</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 EduGrade Pro. All rights reserved.|<i class="fas fa-heart text-danger"></i> Developed by KeysDev.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Page Loader
        window.addEventListener('load', function() {
            setTimeout(() => {
                document.getElementById('pageLoader').classList.add('hidden');
            }, 1500);
        });

        // Initialize AOS
        AOS.init({
            duration: 1200,
            once: true,
            offset: 100,
            easing: 'ease-out-cubic'
        });

        // Initialize Swiper
        const swiper = new Swiper('.heroSwiper', {
            loop: true,
            autoplay: {
                delay: 4000,
                disableOnInteraction: false,
            },
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
        });

        // Load images with fade effect
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.carousel-image');
            images.forEach((img, index) => {
                img.addEventListener('load', function() {
                    setTimeout(() => {
                        this.classList.add('loaded');
                    }, index * 200);
                });
            });
        });

        // Enhanced navbar scroll effect
        let lastScrollTop = 0;
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            if (scrollTop > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }

            // Hide/show navbar on scroll
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                navbar.style.transform = 'translateY(-100%)';
            } else {
                navbar.style.transform = 'translateY(0)';
            }
            lastScrollTop = scrollTop;

            // Update scroll progress
            const scrollProgress = document.getElementById('scrollProgress');
            const scrollPercent = (scrollTop / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
            scrollProgress.style.width = scrollPercent + '%';
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Parallax effect for hero section
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero');
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });

        // Add intersection observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate');
                }
            });
        }, observerOptions);

        // Observe all feature cards
        document.querySelectorAll('.feature-card, .benefit-item, .step-card').forEach(card => {
            observer.observe(card);
        });

        // Add mouse move effect to hero
        document.querySelector('.hero').addEventListener('mousemove', function(e) {
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;

            this.style.background = `
        linear-gradient(135deg, 
          hsl(${240 + x * 20}, 70%, ${60 + y * 10}%) 0%, 
          hsl(${280 + x * 20}, 60%, ${50 + y * 10}%) 50%,
          hsl(${140 + x * 20}, 60%, ${55 + y * 10}%) 100%)
      `;
        });

        // Add typing effect to hero title
        function typeWriter(element, text, speed = 100) {
            let i = 0;
            element.innerHTML = '';

            function type() {
                if (i < text.length) {
                    element.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(type, speed);
                }
            }
            type();
        }

        // Initialize typing effect after page load
        setTimeout(() => {
            const heroTitle = document.querySelector('.hero h1');
            if (heroTitle) {
                const originalText = heroTitle.textContent;
                typeWriter(heroTitle, originalText, 80);
            }
        }, 2000);
    </script>
</body>

</html>