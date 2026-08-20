<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobBoard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1fa97d;
            --on-primary: #ffffff;
            --primary-hover: #106647;
            
            --secondary: #e8724a;
            --on-secondary: #ffffff;
            --secondary-hover: #7a2e10;
            
            --background: #faf9f2;
            --on-background: #1e2a22;
            
            --surface: #eeeadb;
            --surface-hover: #e4dfcc;
            --on-surface: #1e2a22;
            --on-surface-variant: #52584a;
            
            --outline: #cac5b0;
            --shadow-color: rgba(30, 42, 34, 0.06);
            --shadow-strong: rgba(30, 42, 34, 0.12);
        }
        body {
            margin: 0;
            padding: 0;
            background-color: var(--background);
            color: var(--on-background);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-size: 16px; /* body-lg */
            line-height: 26px;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--on-background);
            margin-top: 0;
        }
        header {
            background-color: var(--background);
            padding: 1rem 64px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h1 {
            margin: 0;
            font-size: 24px; /* headline-md */
            color: var(--primary);
            line-height: 32px;
        }
        main {
            flex: 1;
            padding: 0 64px;
            max-width: 1280px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }
        .container {
            display: flex;
            flex-direction: column;
            gap: 96px; /* section-gap */
            padding-bottom: 96px;
        }
        footer {
            text-align: center;
            padding: 2rem;
            color: var(--on-surface-variant);
            font-size: 14px;
            background-color: var(--background);
        }
        @media (max-width: 768px) {
            header, main {
                padding-left: 20px;
                padding-right: 20px;
            }
            .container {
                gap: 48px;
                padding-bottom: 48px;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>JobBoard</h1>
        <nav>
            <!-- Navigation items can go here -->
        </nav>
    </header>

    <main>
        <div class="container">
            {{ $slot }}
        </div>
    </main>

    <footer>
        &copy; {{ date('Y') }} JobBoard. All rights reserved.
    </footer>
</body>
</html>
