<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Board Laravel</title>
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #3b82f6;
            --accent-hover: #60a5fa;
        }
        body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        header {
            background-color: var(--card-bg);
            padding: 1rem 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            border-bottom: 1px solid #334155;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
        }
        main {
            flex: 1;
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }
        .container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        footer {
            text-align: center;
            padding: 1.5rem;
            color: var(--text-muted);
            font-size: 0.875rem;
            background-color: var(--card-bg);
            border-top: 1px solid #334155;
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
        &copy; {{ date('Y') }} JobBoard Laravel. All rights reserved.
    </footer>
</body>
</html>
